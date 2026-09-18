<?php
/**
 * Canonical Record WhatsApp Repository — SQL de aa_canonical_record_whatsapp (sin TX propia).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalRecordWhatsappRepository {

    /** @var object */
    private $wpdb;

    /**
     * @param object|null $wpdb
     */
    public function __construct($wpdb = null) {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;
            return;
        }

        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * @return object
     */
    public function connection() {
        return $this->wpdb;
    }

    /**
     * @return string|null E.164 or null if absent
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function find_whatsapp(int $record_id): ?string {
        $table = AA_Canonical_Schema::record_whatsapp_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT whatsapp FROM `{$table}` WHERE record_id = %d LIMIT 1",
                $record_id
            )
        );

        if ($value === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to SELECT record whatsapp.');
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param list<int> $record_ids
     * @return array<int, string|null>
     *
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function find_whatsapps_by_record_ids(array $record_ids): array {
        $table = AA_Canonical_Schema::record_whatsapp_table_name();
        $this->assert_table_exists($table);

        $ids = [];
        foreach ($record_ids as $id) {
            $id = (int) $id;
            if ($id >= 1) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = null;
        }

        if ($ids === []) {
            return $out;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $this->clear_error_state();
        $sql = $this->wpdb->prepare(
            "SELECT record_id, whatsapp FROM `{$table}` WHERE record_id IN ({$placeholders})",
            $ids
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if ($rows === false || $this->wpdb->last_error !== '' || !is_array($rows)) {
            throw new CanonicalCapabilityPersistenceFailed('Failed to SELECT record whatsapps batch.');
        }

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['record_id'])) {
                continue;
            }
            $rid = (int) $row['record_id'];
            if (!array_key_exists($rid, $out)) {
                continue;
            }
            if (!array_key_exists('whatsapp', $row) || $row['whatsapp'] === null || $row['whatsapp'] === '') {
                $out[$rid] = null;
                continue;
            }
            $out[$rid] = (string) $row['whatsapp'];
        }

        return $out;
    }

    /**
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function upsert(int $record_id, string $whatsapp, string $utc_now): void {
        $table = AA_Canonical_Schema::record_whatsapp_table_name();
        $this->assert_table_exists($table);

        $existing = $this->find_whatsapp($record_id);
        $this->clear_error_state();

        if ($existing === null) {
            $result = $this->wpdb->insert(
                $table,
                [
                    'record_id' => $record_id,
                    'whatsapp' => $whatsapp,
                    'created_at' => $utc_now,
                    'updated_at' => $utc_now,
                ],
                ['%d', '%s', '%s', '%s']
            );
            if ($result === false) {
                throw new CanonicalCapabilityPersistenceFailed('Failed to INSERT record whatsapp.');
            }
            return;
        }

        $result = $this->wpdb->update(
            $table,
            [
                'whatsapp' => $whatsapp,
                'updated_at' => $utc_now,
            ],
            ['record_id' => $record_id],
            ['%s', '%s'],
            ['%d']
        );
        if ($result === false) {
            throw new CanonicalCapabilityPersistenceFailed('Failed to UPDATE record whatsapp.');
        }
    }

    /**
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function delete(int $record_id): void {
        $table = AA_Canonical_Schema::record_whatsapp_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $result = $this->wpdb->delete($table, ['record_id' => $record_id], ['%d']);
        if ($result === false) {
            throw new CanonicalCapabilityPersistenceFailed('Failed to DELETE record whatsapp.');
        }
    }

    /**
     * @throws CanonicalCapabilitySchemaNotReady
     */
    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($this->wpdb->last_error !== '' || $found !== $table) {
            throw new CanonicalCapabilitySchemaNotReady('Required capability table missing: ' . $table);
        }
    }

    private function clear_error_state(): void {
        $this->wpdb->last_error = '';
    }
}
