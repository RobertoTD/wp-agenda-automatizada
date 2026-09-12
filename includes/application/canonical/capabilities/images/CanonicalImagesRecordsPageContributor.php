<?php
/**
 * Contributor images para página de registros del shell (IMG-4).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities\Images
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalRecordImagePublicDto')) {
    require_once dirname(__DIR__, 2) . '/images/CanonicalRecordImagePublicDto.php';
}

final class CanonicalImagesRecordsPageContributor implements CanonicalCapabilityRecordPageContributor {

    public const KEY = 'images';

    /** @var object */
    private $config_repo;

    /** @var object */
    private $images_repo;

    /** @var AA_Canonical_Capability_Registry */
    private $capability_registry;

    /**
     * @param object $config_repo find_container_capability(int,string): ?array
     * @param object $images_repo find_public_rows_by_record_ids_for_container(int,array): array
     */
    public function __construct(
        $config_repo,
        $images_repo,
        AA_Canonical_Capability_Registry $capability_registry
    ) {
        $this->config_repo = $config_repo;
        $this->images_repo = $images_repo;
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
            return $this->offered_with_failed_reads($record_ids);
        } catch (\Throwable $e) {
            return $this->offered_with_failed_reads($record_ids);
        }

        if ($assignment === null || empty($assignment['is_active'])) {
            return $this->not_offered();
        }

        $ids = [];
        foreach ($record_ids as $id) {
            $id = (int) $id;
            if ($id >= 1) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return new CanonicalCapabilityRecordsPageContribution(self::KEY, true, []);
        }

        try {
            $batch = $this->images_repo->find_public_rows_by_record_ids_for_container($container_id, $ids);
        } catch (\Throwable $e) {
            return $this->offered_with_failed_reads($ids);
        }

        $records = [];
        foreach ($ids as $id) {
            $rows = isset($batch[$id]) && is_array($batch[$id]) ? $batch[$id] : [];
            $items = CanonicalRecordImagePublicDto::list_from_rows($rows);
            if ($items === []) {
                $records[$id] = CanonicalCapabilityRecordReadState::known_absent();
            } else {
                $records[$id] = CanonicalCapabilityRecordReadState::known_collection($items);
            }
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
