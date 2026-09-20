<?php
/**
 * Estado tipado de la capability `completed`.
 * La ausencia de fila representa pendiente; una fila representa completado.
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalRecordCompletionRepository {
    /** @var object */
    private $wpdb;

    public function __construct($wpdb = null) {
        if ($wpdb === null) { global $wpdb; }
        $this->wpdb = $wpdb;
    }

    /** @return array<int,bool> */
    public function states_for_record_ids(array $record_ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $record_ids), static function ($id): bool { return $id > 0; })));
        if ($ids === []) { return []; }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = $this->wpdb->prepare(
            'SELECT record_id FROM `' . AA_Canonical_Schema::record_completion_table_name() . '` WHERE record_id IN (' . $placeholders . ')',
            ...$ids
        );
        $rows = $this->wpdb->get_col($sql);
        if ($rows === false || $this->wpdb->last_error !== '') {
            throw new \RuntimeException('completion_states_failed');
        }
        return array_fill_keys(array_map('intval', (array) $rows), true);
    }

    /** @throws \RuntimeException */
    public function set_completed(int $record_id, bool $completed): void {
        $table = AA_Canonical_Schema::record_completion_table_name();
        if ($completed) {
            $result = $this->wpdb->query($this->wpdb->prepare(
                "INSERT INTO `{$table}` (record_id, completed_at) VALUES (%d, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE completed_at = VALUES(completed_at)",
                $record_id
            ));
        } else {
            $result = $this->wpdb->delete($table, ['record_id' => $record_id], ['%d']);
        }
        if ($result === false) { throw new \RuntimeException('completion_write_failed'); }
    }
}
