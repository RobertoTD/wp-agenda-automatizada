<?php
/**
 * Canonical Contact Dossier Repository — SQL de aa_canonical_contact_dossier (sin TX propia).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalContactDossierRepository {

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
     * @return array{contact_record_id:int,archive_container_id:int,created_at:string,updated_at:string}|null
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function find_by_contact_record_id(int $contact_record_id): ?array {
        $table = AA_Canonical_Schema::contact_dossier_table_name();
        $this->assert_table_exists($table);

        if ($contact_record_id < 1) {
            return null;
        }

        $this->clear_error_state();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT contact_record_id, archive_container_id, created_at, updated_at
                 FROM `{$table}` WHERE contact_record_id = %d LIMIT 1",
                $contact_record_id
            ),
            ARRAY_A
        );

        if ($row === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to SELECT contact dossier.');
        }

        return $this->map_row(is_array($row) ? $row : null);
    }

    /**
     * @param list<int> $contact_record_ids
     * @return array<int, array{contact_record_id:int,archive_container_id:int,created_at:string,updated_at:string}|null>
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function find_by_contact_record_ids(array $contact_record_ids): array {
        $table = AA_Canonical_Schema::contact_dossier_table_name();
        $this->assert_table_exists($table);

        $ids = [];
        foreach ($contact_record_ids as $id) {
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
            "SELECT contact_record_id, archive_container_id, created_at, updated_at
             FROM `{$table}` WHERE contact_record_id IN ({$placeholders})",
            $ids
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if ($rows === false || $this->wpdb->last_error !== '' || !is_array($rows)) {
            throw new CanonicalCapabilityPersistenceFailed('Failed to SELECT contact dossiers batch.');
        }

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['contact_record_id'])) {
                continue;
            }
            $rid = (int) $row['contact_record_id'];
            if (!array_key_exists($rid, $out)) {
                continue;
            }
            $out[$rid] = $this->map_row($row);
        }

        return $out;
    }

    /**
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function insert_association(int $contact_record_id, int $archive_container_id, string $now_utc): void {
        $table = AA_Canonical_Schema::contact_dossier_table_name();
        $this->assert_table_exists($table);

        if ($contact_record_id < 1 || $archive_container_id < 1) {
            throw new CanonicalCapabilityPersistenceFailed('Invalid dossier association ids.');
        }

        $this->clear_error_state();
        $result = $this->wpdb->insert(
            $table,
            [
                'contact_record_id' => $contact_record_id,
                'archive_container_id' => $archive_container_id,
                'created_at' => $now_utc,
                'updated_at' => $now_utc,
            ],
            ['%d', '%d', '%s', '%s']
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to INSERT contact dossier association.');
        }
    }

    /**
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function delete_by_contact_record_id(int $contact_record_id): void {
        $table = AA_Canonical_Schema::contact_dossier_table_name();
        $this->assert_table_exists($table);

        if ($contact_record_id < 1) {
            return;
        }

        $this->clear_error_state();
        $result = $this->wpdb->delete(
            $table,
            ['contact_record_id' => $contact_record_id],
            ['%d']
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to DELETE contact dossier association.');
        }
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{contact_record_id:int,archive_container_id:int,created_at:string,updated_at:string}|null
     */
    private function map_row(?array $row): ?array {
        if ($row === null) {
            return null;
        }

        return [
            'contact_record_id' => (int) ($row['contact_record_id'] ?? 0),
            'archive_container_id' => (int) ($row['archive_container_id'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function clear_error_state(): void {
        $this->wpdb->last_error = '';
    }

    /**
     * @throws CanonicalCapabilitySchemaNotReady
     */
    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $like = $this->wpdb->esc_like($table);
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $like));
        if ($found !== $table) {
            throw new CanonicalCapabilitySchemaNotReady('Contact dossier table is not ready.');
        }
    }
}
