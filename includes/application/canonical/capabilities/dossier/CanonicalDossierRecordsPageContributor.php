<?php
/**
 * Contributor dossier para página de registros del shell (Contactos).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities\Dossier
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalDossierRecordsPageContributor implements CanonicalCapabilityRecordPageContributor {

    public const KEY = 'contact_dossier';

    /** Valor known_value: botón habilitado (abrir o crear en el servidor). */
    public const VALUE_READY = 'ready';

    /** Valor known_value: Archivo desactivado. */
    public const VALUE_ARCHIVE_DISABLED = 'archive_disabled';

    /** Valor known_value: expediente destino en retiro. */
    public const VALUE_DOSSIER_RETIRING = 'dossier_retiring';

    /** @var CanonicalContactDossierRepository */
    private $dossier_repo;

    /** @var ReadContactDossierApplicationUseCase */
    private $application_reader;

    /** @var CanonicalPurgeRunsRepository|null */
    private $purge_runs;

    /** @var callable|null ():bool */
    private $archive_enabled_resolver;

    public function __construct(
        CanonicalContactDossierRepository $dossier_repo,
        ReadContactDossierApplicationUseCase $application_reader,
        ?CanonicalPurgeRunsRepository $purge_runs = null,
        ?callable $archive_enabled_resolver = null
    ) {
        $this->dossier_repo = $dossier_repo;
        $this->application_reader = $application_reader;
        $this->purge_runs = $purge_runs;
        $this->archive_enabled_resolver = $archive_enabled_resolver;
    }

    public function capability_key(): string {
        return self::KEY;
    }

    /**
     * @param list<int> $record_ids
     */
    public function contribute_for_records_page(
        string $family_key,
        int $container_id,
        array $record_ids
    ): CanonicalCapabilityRecordsPageContribution {
        if ($family_key !== 'contact') {
            return $this->not_offered();
        }

        try {
            $application = $this->application_reader->execute($container_id);
        } catch (\Throwable $e) {
            return $this->offered_with_failed_reads($record_ids);
        }
        if (!$application->is_active()) {
            return $this->not_offered();
        }

        $archive_enabled = true;
        if ($this->archive_enabled_resolver !== null) {
            try {
                $archive_enabled = (bool) call_user_func($this->archive_enabled_resolver);
            } catch (\Throwable $e) {
                return $this->offered_with_failed_reads($record_ids);
            }
        }

        if (!$archive_enabled || !$application->is_available()) {
            $records = [];
            foreach ($record_ids as $id) {
                $id = (int) $id;
                if ($id >= 1) {
                    $records[$id] = CanonicalCapabilityRecordReadState::known_value(self::VALUE_ARCHIVE_DISABLED);
                }
            }

            return new CanonicalCapabilityRecordsPageContribution(self::KEY, true, $records, null);
        }

        try {
            $batch = $this->dossier_repo->find_by_contact_record_ids($record_ids);
        } catch (\Throwable $e) {
            return $this->offered_with_failed_reads($record_ids);
        }

        $records = [];
        foreach ($record_ids as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $assoc = $batch[$id] ?? null;
            if (!is_array($assoc)) {
                $records[$id] = CanonicalCapabilityRecordReadState::known_value(self::VALUE_READY);
                continue;
            }
            $archive_id = (int) ($assoc['archive_container_id'] ?? 0);
            if ($archive_id < 1) {
                $records[$id] = CanonicalCapabilityRecordReadState::known_value(self::VALUE_READY);
                continue;
            }
            if ($this->purge_runs !== null) {
                try {
                    if ($this->purge_runs->has_blocking_purge_for_container($archive_id)) {
                        $records[$id] = CanonicalCapabilityRecordReadState::known_value(self::VALUE_DOSSIER_RETIRING);
                        continue;
                    }
                } catch (\Throwable $e) {
                    $records[$id] = CanonicalCapabilityRecordReadState::read_failed();
                    continue;
                }
            }
            $records[$id] = CanonicalCapabilityRecordReadState::known_value(self::VALUE_READY);
        }

        return new CanonicalCapabilityRecordsPageContribution(self::KEY, true, $records, null);
    }

    private function not_offered(): CanonicalCapabilityRecordsPageContribution {
        return new CanonicalCapabilityRecordsPageContribution(self::KEY, false, [], null);
    }

    /**
     * @param list<int> $record_ids
     */
    private function offered_with_failed_reads(array $record_ids): CanonicalCapabilityRecordsPageContribution {
        $records = [];
        foreach ($record_ids as $id) {
            $id = (int) $id;
            if ($id >= 1) {
                $records[$id] = CanonicalCapabilityRecordReadState::read_failed();
            }
        }

        return new CanonicalCapabilityRecordsPageContribution(self::KEY, true, $records, null);
    }
}
