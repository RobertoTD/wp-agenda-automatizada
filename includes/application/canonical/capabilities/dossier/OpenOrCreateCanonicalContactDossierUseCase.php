<?php
/**
 * Abrir o crear el expediente canónico (lista Archivo) vinculado a un contacto.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Solutions\ContactDossier
 */

defined('ABSPATH') or die('No direct access');

final class OpenOrCreateCanonicalContactDossierUseCase {

    public const CONTACT_FAMILY = 'contact';
    public const ARCHIVE_FAMILY = 'archive';
    public const TITLE_PREFIX = 'Exp — ';

    /** @var CanonicalRelationalRepository */
    private $relational;

    /** @var CanonicalContactDossierRepository */
    private $dossier;

    /** @var ReadContactDossierApplicationUseCase */
    private $application_reader;

    /** @var CanonicalWriteGateway */
    private $gateway;

    /** @var AA_Canonical_Capability_Defaults_Materializer */
    private $materializer;

    /** @var CanonicalPurgeRunsRepository */
    private $purge_runs;

    /** @var AA_Expediente_Aggregate_Lock */
    private $lock;

    /** @var AA_Canonical_Family_Definition */
    private $archive_family;

    public function __construct(
        CanonicalRelationalRepository $relational,
        CanonicalContactDossierRepository $dossier,
        ReadContactDossierApplicationUseCase $application_reader,
        CanonicalWriteGateway $gateway,
        AA_Canonical_Capability_Defaults_Materializer $materializer,
        CanonicalPurgeRunsRepository $purge_runs,
        AA_Expediente_Aggregate_Lock $lock,
        AA_Canonical_Family_Definition $archive_family
    ) {
        $this->relational = $relational;
        $this->dossier = $dossier;
        $this->application_reader = $application_reader;
        $this->gateway = $gateway;
        $this->materializer = $materializer;
        $this->purge_runs = $purge_runs;
        $this->lock = $lock;
        $this->archive_family = $archive_family;
    }

    public function execute(
        OpenOrCreateCanonicalContactDossierCommand $command
    ): OpenOrCreateCanonicalContactDossierResult {
        $lease = $this->lock->acquire(
            AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER,
            $command->contact_container_id(),
            AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
        );
        if (function_exists('is_wp_error') && is_wp_error($lease)) {
            $code = $lease->get_error_code();
            if ($code === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) {
                return OpenOrCreateCanonicalContactDossierResult::resource_busy();
            }

            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        }

        try {
            $held = $this->lock->assert_held($lease);
            if (function_exists('is_wp_error') && is_wp_error($held)) {
                return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
            }

            return $this->execute_under_lock($command);
        } catch (CanonicalRelationalQueryFailed $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        } catch (CanonicalCapabilityPersistenceFailed $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        } catch (CanonicalImageUploadSchemaNotReady $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        } catch (CanonicalMutationPersistenceFailed $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        } catch (\Throwable $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        } finally {
            if (!(function_exists('is_wp_error') && is_wp_error($lease))) {
                $this->lock->release($lease);
            }
        }
    }

    private function execute_under_lock(
        OpenOrCreateCanonicalContactDossierCommand $command
    ): OpenOrCreateCanonicalContactDossierResult {
        $contact_family_id = $this->relational->resolve_family_id(self::CONTACT_FAMILY);
        if ($contact_family_id === null) {
            return OpenOrCreateCanonicalContactDossierResult::container_not_found();
        }

        $container = $this->relational->find_container(
            $contact_family_id,
            $command->contact_container_id()
        );
        if ($container === null) {
            return OpenOrCreateCanonicalContactDossierResult::container_not_found();
        }

        $record = $this->relational->find_record(
            $command->contact_container_id(),
            $command->contact_record_id()
        );
        if ($record === null) {
            return OpenOrCreateCanonicalContactDossierResult::record_not_found();
        }

        try {
            $application = $this->application_reader->execute($command->contact_container_id());
        } catch (\Throwable $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        }
        if (!$application->is_active() || !$application->is_available()) {
            return OpenOrCreateCanonicalContactDossierResult::solution_inactive();
        }

        if ($this->purge_runs->has_blocking_purge(
            $command->contact_record_id(),
            $command->contact_container_id()
        )) {
            return OpenOrCreateCanonicalContactDossierResult::origin_retiring();
        }

        $association = $this->dossier->find_by_contact_record_id($command->contact_record_id());
        if ($association !== null) {
            $resolved = $this->resolve_existing_association($association, $command);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $this->create_and_associate($command, (string) ($record['title'] ?? ''));
    }

    /**
     * @param array{contact_record_id:int,archive_container_id:int,created_at:string,updated_at:string} $association
     */
    private function resolve_existing_association(
        array $association,
        OpenOrCreateCanonicalContactDossierCommand $command
    ): ?OpenOrCreateCanonicalContactDossierResult {
        $archive_id = (int) ($association['archive_container_id'] ?? 0);
        if ($archive_id < 1) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        }

        try {
            $target = $this->relational->find_container_by_id($archive_id);
        } catch (CanonicalRelationalQueryFailed $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        }

        if ($target === null) {
            $this->dossier->delete_by_contact_record_id((int) $association['contact_record_id']);
            return null;
        }

        $family_key = (string) ($target['family_key'] ?? '');
        if ($family_key !== self::ARCHIVE_FAMILY) {
            return OpenOrCreateCanonicalContactDossierResult::target_invalid();
        }

        if ($this->purge_runs->has_blocking_purge_for_container($archive_id)) {
            return OpenOrCreateCanonicalContactDossierResult::dossier_retiring();
        }

        return OpenOrCreateCanonicalContactDossierResult::opened(
            $this->build_redirect_url($archive_id, $command),
            $archive_id
        );
    }

    private function create_and_associate(
        OpenOrCreateCanonicalContactDossierCommand $command,
        string $contact_title
    ): OpenOrCreateCanonicalContactDossierResult {
        $identity = new CanonicalReadIdentity(self::ARCHIVE_FAMILY);
        $title = self::build_dossier_title($contact_title);

        try {
            $create_command = new CanonicalCreateContainerCommand($title, null);
        } catch (\InvalidArgumentException $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        }

        $effects = [
            $this->materializer->build_effect(),
            new AA_Canonical_Dossier_Associate_Effect(
                $this->dossier,
                $command->contact_record_id()
            ),
        ];

        try {
            $receipt = $this->gateway->create_container($identity, $create_command, $effects);
        } catch (CanonicalWriteBindingNotFound $e) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        } catch (CanonicalMutationPersistenceFailed $e) {
            $recovered = $this->recover_after_create_conflict($command);
            if ($recovered !== null) {
                return $recovered;
            }

            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            return OpenOrCreateCanonicalContactDossierResult::uncertain();
        }

        if ($receipt->outcome() === CanonicalMutationReceipt::OUTCOME_UNCERTAIN) {
            return OpenOrCreateCanonicalContactDossierResult::uncertain();
        }

        $archive_id = (int) $receipt->resource_id();
        if ($archive_id < 1) {
            return OpenOrCreateCanonicalContactDossierResult::persistence_failed();
        }

        return OpenOrCreateCanonicalContactDossierResult::created(
            $this->build_redirect_url($archive_id, $command),
            $archive_id
        );
    }

    private function recover_after_create_conflict(
        OpenOrCreateCanonicalContactDossierCommand $command
    ): ?OpenOrCreateCanonicalContactDossierResult {
        try {
            $association = $this->dossier->find_by_contact_record_id($command->contact_record_id());
        } catch (\Throwable $e) {
            return null;
        }
        if ($association === null) {
            return null;
        }

        return $this->resolve_existing_association($association, $command);
    }

    private function build_redirect_url(
        int $archive_container_id,
        OpenOrCreateCanonicalContactDossierCommand $command
    ): string {
        $page = $command->page();
        $containers_page = $command->containers_page();

        return AA_Canonical_Shell_Base_Url_Policy::build_records_url(
            self::ARCHIVE_FAMILY,
            $archive_container_id,
            ($page !== null && $page > 1) ? $page : null,
            ($containers_page !== null && $containers_page > 1) ? $containers_page : null,
            $command->lists_scope()
        );
    }

    public static function build_dossier_title(string $contact_title): string {
        $prefix = self::TITLE_PREFIX;
        $max = CanonicalCreateContainerCommand::MAX_TITLE_LENGTH;
        $trimmed = trim($contact_title);
        if ($trimmed === '') {
            $trimmed = 'Contacto';
        }

        $prefix_len = self::utf8_length($prefix);
        $available = $max - $prefix_len;
        if ($available < 1) {
            return self::utf8_substr($prefix, 0, $max);
        }

        if (self::utf8_length($trimmed) > $available) {
            $trimmed = self::utf8_substr($trimmed, 0, $available);
        }

        return $prefix . $trimmed;
    }

    private static function utf8_length(string $string): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($string, 'UTF-8');
        }
        $matched = preg_match_all('/./us', $string);

        return $matched === false ? strlen($string) : (int) $matched;
    }

    private static function utf8_substr(string $string, int $start, int $length): string {
        if (function_exists('mb_substr')) {
            return (string) mb_substr($string, $start, $length, 'UTF-8');
        }

        return (string) substr($string, $start, $length);
    }
}
