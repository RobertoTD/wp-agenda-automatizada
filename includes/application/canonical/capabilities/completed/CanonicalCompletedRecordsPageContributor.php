<?php
/** Contributor de lectura para `completed`. */
defined('ABSPATH') or die('No direct access');

final class CanonicalCompletedRecordsPageContributor implements CanonicalCapabilityRecordPageContributor {
    public const KEY = 'completed';
    private $config_repo;
    private $completion_repo;
    private $registry;
    public function __construct($config_repo, $completion_repo, AA_Canonical_Capability_Registry $registry) {
        $this->config_repo = $config_repo; $this->completion_repo = $completion_repo; $this->registry = $registry;
    }
    public function capability_key(): string { return self::KEY; }
    public function contribute_for_records_page(string $family_key, int $container_id, array $record_ids): CanonicalCapabilityRecordsPageContribution {
        if ($family_key !== 'action') { return new CanonicalCapabilityRecordsPageContribution(self::KEY, false, []); }
        try {
            if (!$this->registry->get(self::KEY)->is_ready()) { return new CanonicalCapabilityRecordsPageContribution(self::KEY, false, []); }
            $assignment = $this->config_repo->find_container_capability($container_id, self::KEY);
            if ($assignment === null || empty($assignment['is_active'])) { return new CanonicalCapabilityRecordsPageContribution(self::KEY, false, []); }
            $states = $this->completion_repo->states_for_record_ids($record_ids);
        } catch (\Throwable $e) { return new CanonicalCapabilityRecordsPageContribution(self::KEY, false, []); }
        $records = [];
        foreach ($record_ids as $id) {
            $id = (int) $id;
            if ($id > 0) { $records[$id] = CanonicalCapabilityRecordReadState::known_value(isset($states[$id]) ? '1' : '0'); }
        }
        return new CanonicalCapabilityRecordsPageContribution(self::KEY, true, $records);
    }
}
