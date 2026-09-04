<?php
/**
 * Canonical Family Enablement Store — SQL acotado a aa_canonical_families.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__) . '/wp/CanonicalSchema.php';
}

final class AA_Canonical_Family_Enablement_Store implements CanonicalFamilyEnablementPort {

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
     * {@inheritdoc}
     */
    public function read_for_declared_families(array $family_keys): CanonicalFamilyEnablementSnapshot {
        $keys = [];
        foreach ($family_keys as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $keys[] = $key;
        }

        if ($keys === []) {
            return new CanonicalFamilyEnablementSnapshot([]);
        }

        $table = $this->families_table();
        $this->assert_table_exists($table);

        $placeholders = implode(',', array_fill(0, count($keys), '%s'));
        $sql = "SELECT family_key, is_enabled FROM `{$table}` WHERE family_key IN ({$placeholders})";

        $this->clear_error_state();
        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $keys), ARRAY_A);

        if ($rows === false || $this->wpdb->last_error !== '') {
            throw new CanonicalFamilyEnablementPersistenceFailed('Failed to SELECT family enablement rows.');
        }

        $found = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row) || !isset($row['family_key'])) {
                continue;
            }
            $fk = (string) $row['family_key'];
            $found[$fk] = ((int) ($row['is_enabled'] ?? 0)) === 1;
        }

        $by_key = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $found)) {
                $by_key[$key] = new CanonicalFamilyEnablementStatus($key, true, $found[$key]);
            } else {
                $by_key[$key] = new CanonicalFamilyEnablementStatus($key, false, false);
            }
        }

        return new CanonicalFamilyEnablementSnapshot($by_key);
    }

    /**
     * {@inheritdoc}
     */
    public function set_enabled(string $family_key, bool $enabled): CanonicalFamilyEnablementResult {
        $table = $this->families_table();
        $this->assert_table_exists($table);

        $current = $this->read_row($table, $family_key);
        if ($current === null) {
            throw new CanonicalFamilyNotProvisioned($family_key);
        }

        $desired = $enabled ? 1 : 0;
        if ((int) $current['is_enabled'] === $desired) {
            return new CanonicalFamilyEnablementResult($family_key, $enabled, false);
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->clear_error_state();
        $result = $this->wpdb->update(
            $table,
            [
                'is_enabled' => $desired,
                'updated_at' => $now,
            ],
            ['family_key' => $family_key],
            ['%d', '%s'],
            ['%s']
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalFamilyEnablementPersistenceFailed('UPDATE is_enabled failed.');
        }

        if ((int) $result === 0) {
            $reloaded = $this->read_row($table, $family_key);
            if ($reloaded === null) {
                throw new CanonicalFamilyNotProvisioned($family_key);
            }
            if ((int) $reloaded['is_enabled'] !== $desired) {
                throw new CanonicalFamilyEnablementPersistenceFailed(
                    'UPDATE reported zero rows and persisted state does not match desired.'
                );
            }

            return new CanonicalFamilyEnablementResult($family_key, $enabled, true);
        }

        return new CanonicalFamilyEnablementResult($family_key, $enabled, true);
    }

    private function families_table(): string {
        return $this->wpdb->prefix . AA_Canonical_Schema::TABLE_FAMILIES;
    }

    /**
     * @throws CanonicalFamilyEnablementSchemaNotReady
     */
    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($this->wpdb->last_error !== '') {
            throw new CanonicalFamilyEnablementPersistenceFailed('SHOW TABLES failed.');
        }
        if ($found !== $table) {
            throw new CanonicalFamilyEnablementSchemaNotReady('Table aa_canonical_families is missing.');
        }
    }

    /**
     * @return array{family_key:string,is_enabled:int,updated_at?:string}|null
     * @throws CanonicalFamilyEnablementPersistenceFailed
     */
    private function read_row(string $table, string $family_key): ?array {
        $this->clear_error_state();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT family_key, is_enabled, updated_at FROM `{$table}` WHERE family_key = %s LIMIT 1",
                $family_key
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalFamilyEnablementPersistenceFailed('Failed to SELECT family row.');
        }

        if (!is_array($row) || !isset($row['family_key'])) {
            return null;
        }

        return $row;
    }

    private function clear_error_state(): void {
        if (isset($this->wpdb->last_error)) {
            $this->wpdb->last_error = '';
        }
    }
}
