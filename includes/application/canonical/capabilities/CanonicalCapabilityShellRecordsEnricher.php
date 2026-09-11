<?php
/**
 * Coordina contributors y enriquece items_view del shell.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityShellRecordsEnricher {

    /** @var CanonicalCapabilityRecordPageContributorRegistry */
    private $registry;

    public function __construct(CanonicalCapabilityRecordPageContributorRegistry $registry) {
        $this->registry = $registry;
    }

    /**
     * @param list<array<string,mixed>> $items_view
     * @return array{
     *   items_view: list<array<string,mixed>>,
     *   capability_contributions: array<string, array{offered:bool}>
     * }
     */
    public function enrich(string $family_key, int $container_id, array $items_view): array {
        $ids = [];
        foreach ($items_view as $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($id >= 1) {
                $ids[] = $id;
            }
        }

        $page_contributions = [];
        $per_record = [];

        foreach ($this->registry->all() as $contributor) {
            $contribution = $contributor->contribute_for_records_page($family_key, $container_id, $ids);
            $key = $contribution->capability_key();
            $page_contributions[$key] = $contribution->list_summary();
            if (!$contribution->offered()) {
                continue;
            }
            foreach ($contribution->records() as $record_id => $state) {
                if (!isset($per_record[$record_id])) {
                    $per_record[$record_id] = [];
                }
                $per_record[$record_id][$key] = $state->to_array();
            }
        }

        $enriched = [];
        foreach ($items_view as $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($id >= 1 && isset($per_record[$id]) && $per_record[$id] !== []) {
                $item['capabilities'] = $per_record[$id];
            }
            $enriched[] = $item;
        }

        return [
            'items_view' => $enriched,
            'capability_contributions' => $page_contributions,
        ];
    }
}
