<?php
/**
 * Canonical Record Email Repository — SQL de aa_canonical_record_email (sin TX propia).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalRecordEmailRepository {

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
     * @return string|null
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function find_email(int $record_id): ?string {
        $table = AA_Canonical_Schema::record_email_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT email FROM `{$table}` WHERE record_id = %d LIMIT 1",
                $record_id
            )
        );

        if ($value === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to SELECT record email.');
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
    public function find_emails_by_record_ids(array $record_ids): array {
        $table = AA_Canonical_Schema::record_email_table_name();
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
            "SELECT record_id, email FROM `{$table}` WHERE record_id IN ({$placeholders})",
            $ids
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if ($rows === false || $this->wpdb->last_error !== '' || !is_array($rows)) {
            throw new CanonicalCapabilityPersistenceFailed('Failed to SELECT record emails batch.');
        }

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['record_id'])) {
                continue;
            }
            $rid = (int) $row['record_id'];
            if (!array_key_exists($rid, $out)) {
                continue;
            }
            if (!array_key_exists('email', $row) || $row['email'] === null || $row['email'] === '') {
                $out[$rid] = null;
                continue;
            }
            $out[$rid] = (string) $row['email'];
        }

        return $out;
    }

    /**
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function upsert(int $record_id, string $email, string $utc_now): void {
        $table = AA_Canonical_Schema::record_email_table_name();
        $this->assert_table_exists($table);

        $existing = $this->find_email($record_id);
        $this->clear_error_state();

        if ($existing === null) {
            $result = $this->wpdb->insert(
                $table,
                [
                    'record_id' => $record_id,
                    'email' => $email,
                    'created_at' => $utc_now,
                    'updated_at' => $utc_now,
                ],
                ['%d', '%s', '%s', '%s']
            );
            if ($result === false) {
                throw new CanonicalCapabilityPersistenceFailed('Failed to INSERT record email.');
            }
            return;
        }

        $result = $this->wpdb->update(
            $table,
            [
                'email' => $email,
                'updated_at' => $utc_now,
            ],
            ['record_id' => $record_id],
            ['%s', '%s'],
            ['%d']
        );
        if ($result === false) {
            throw new CanonicalCapabilityPersistenceFailed('Failed to UPDATE record email.');
        }
    }

    /**
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function delete(int $record_id): void {
        $table = AA_Canonical_Schema::record_email_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $result = $this->wpdb->delete($table, ['record_id' => $record_id], ['%d']);
        if ($result === false) {
            throw new CanonicalCapabilityPersistenceFailed('Failed to DELETE record email.');
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
