<?php
/**
 * Canonical Record Amount Repository — SQL de aa_canonical_record_amount (sin TX propia).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalRecordAmountRepository {

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
     * @return string|null canonical decimal or null if absent
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function find_amount(int $record_id): ?string {
        $table = AA_Canonical_Schema::record_amount_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT amount FROM `{$table}` WHERE record_id = %d LIMIT 1",
                $record_id
            )
        );

        if ($value === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to SELECT record amount.');
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function upsert(int $record_id, string $amount, string $utc_now): void {
        $table = AA_Canonical_Schema::record_amount_table_name();
        $this->assert_table_exists($table);

        $existing = $this->find_amount($record_id);
        $this->clear_error_state();

        if ($existing === null) {
            $result = $this->wpdb->insert(
                $table,
                [
                    'record_id' => $record_id,
                    'amount' => $amount,
                    'created_at' => $utc_now,
                    'updated_at' => $utc_now,
                ],
                ['%d', '%s', '%s', '%s']
            );
            if ($result === false) {
                throw new CanonicalCapabilityPersistenceFailed('Failed to INSERT record amount.');
            }
            return;
        }

        $result = $this->wpdb->update(
            $table,
            [
                'amount' => $amount,
                'updated_at' => $utc_now,
            ],
            ['record_id' => $record_id],
            ['%s', '%s'],
            ['%d']
        );
        if ($result === false) {
            throw new CanonicalCapabilityPersistenceFailed('Failed to UPDATE record amount.');
        }
    }

    /**
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function delete(int $record_id): void {
        $table = AA_Canonical_Schema::record_amount_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $result = $this->wpdb->delete($table, ['record_id' => $record_id], ['%d']);
        if ($result === false) {
            throw new CanonicalCapabilityPersistenceFailed('Failed to DELETE record amount.');
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
