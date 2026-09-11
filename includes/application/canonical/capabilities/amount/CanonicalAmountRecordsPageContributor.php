<?php
/**
 * Contributor amount para página de registros del shell (A1b).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities\Amount
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalAmountRecordsPageContributor implements CanonicalCapabilityRecordPageContributor {

    public const KEY = 'amount';

    /** @var CanonicalCapabilityConfigRepository */
    private $config_repo;

    /** @var CanonicalRecordAmountRepository */
    private $amount_repo;

    /** @var AA_Canonical_Capability_Registry */
    private $capability_registry;

    public function __construct(
        CanonicalCapabilityConfigRepository $config_repo,
        CanonicalRecordAmountRepository $amount_repo,
        AA_Canonical_Capability_Registry $capability_registry
    ) {
        $this->config_repo = $config_repo;
        $this->amount_repo = $amount_repo;
        $this->capability_registry = $capability_registry;
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
        try {
            $def = $this->capability_registry->get(self::KEY);
        } catch (\Throwable $e) {
            return $this->not_offered();
        }

        if (!$def->is_ready() || $def->scope() !== AA_Canonical_Capability_Definition::SCOPE_RECORD) {
            return $this->not_offered();
        }

        try {
            $assignment = $this->config_repo->find_container_capability($container_id, self::KEY);
        } catch (CanonicalCapabilitySchemaNotReady | CanonicalCapabilityPersistenceFailed $e) {
            // Config indeterminable: no afirmar desactivada; marcar read_failed por registro.
            return $this->offered_with_failed_reads($record_ids);
        } catch (\Throwable $e) {
            return $this->offered_with_failed_reads($record_ids);
        }

        if ($assignment === null || empty($assignment['is_active'])) {
            return $this->not_offered();
        }

        $records = [];
        try {
            $batch = $this->amount_repo->find_amounts_by_record_ids($record_ids);
            foreach ($record_ids as $id) {
                $id = (int) $id;
                if ($id < 1) {
                    continue;
                }
                if (!array_key_exists($id, $batch) || $batch[$id] === null) {
                    $records[$id] = CanonicalCapabilityRecordReadState::known_absent();
                } else {
                    $records[$id] = CanonicalCapabilityRecordReadState::known_value((string) $batch[$id]);
                }
            }
        } catch (\Throwable $e) {
            return $this->offered_with_failed_reads($record_ids);
        }

        return new CanonicalCapabilityRecordsPageContribution(self::KEY, true, $records);
    }

    private function not_offered(): CanonicalCapabilityRecordsPageContribution {
        return new CanonicalCapabilityRecordsPageContribution(self::KEY, false, []);
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

        return new CanonicalCapabilityRecordsPageContribution(self::KEY, true, $records);
    }
}
