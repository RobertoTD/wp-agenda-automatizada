<?php
/**
 * Sync Appointment Actions List Use Case — siembra la lista del sistema de citas.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once dirname(__DIR__, 2) . '/repositories/SeededTaskRepository.php';

final class SyncAppointmentActionsListUseCase {

    /**
     * @return array{lists_created:int,lists_updated:int,list_id:int}
     */
    public function execute(): array {
        $counts = [
            'lists_created' => 0,
            'lists_updated' => 0,
            'list_id' => 0,
        ];

        $definition = AA_Appointment_Actions_Catalog::list_definition();
        $source = (string) ($definition['source_category'] ?? '');
        $origin = (string) ($definition['origin_key'] ?? '');
        $existing = SeededTaskRepository::find_list_by_origin($source, $origin);
        $list = SeededTaskRepository::upsert_seeded_list($definition);

        if ($list === null) {
            return $counts;
        }

        $counts[$existing === null ? 'lists_created' : 'lists_updated']++;
        $counts['list_id'] = (int) ($list['id'] ?? 0);

        return $counts;
    }
}
