<?php
/**
 * Canonical Family Provisioner — Inserta filas faltantes en aa_canonical_families.
 *
 * Idempotente: no actualiza ni borra filas existentes. No toca containers/records.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalFamilyProvisioningFailed')) {
    require_once __DIR__ . '/CanonicalFamilyProvisioningFailed.php';
}
if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__) . '/wp/CanonicalSchema.php';
}

final class AA_Canonical_Family_Provisioner {

    public const INITIAL_IS_ENABLED = 0;
    public const INITIAL_SEED_VERSION = 0;

    /** @var object */
    private $wpdb;

    /**
     * @param object|null $wpdb Instancia wpdb; global si null.
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
     * Asegura una fila por cada familia declarada en el registry congelado.
     *
     * @throws CanonicalFamilyProvisioningFailed
     */
    public function ensure_declared_families(AA_Canonical_Registry $registry): void {
        if (!$registry->is_frozen()) {
            throw new CanonicalFamilyProvisioningFailed('Registry must be frozen before provisioning.');
        }

        $families = $registry->families();
        if ($families === []) {
            throw new CanonicalFamilyProvisioningFailed('Registry has no declared families.');
        }

        $table = $this->families_table();
        $this->assert_table_exists($table);

        $declared_keys = [];
        foreach ($families as $family) {
            $declared_keys[] = $family->key();
        }

        $existing = $this->load_existing_keys($table);
        $missing = [];
        foreach ($declared_keys as $key) {
            if (!isset($existing[$key])) {
                $missing[] = $key;
            }
        }

        foreach ($missing as $family_key) {
            $this->insert_family_row($table, $family_key);
        }

        $this->assert_all_declared_present($table, $declared_keys);
    }

    private function families_table(): string {
        return $this->wpdb->prefix . AA_Canonical_Schema::TABLE_FAMILIES;
    }

    /**
     * @throws CanonicalFamilyProvisioningFailed
     */
    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($this->wpdb->last_error !== '' || $found !== $table) {
            throw new CanonicalFamilyProvisioningFailed('Table aa_canonical_families is missing.');
        }
    }

    /**
     * @return array<string, true>
     * @throws CanonicalFamilyProvisioningFailed
     */
    private function load_existing_keys(string $table): array {
        $this->clear_error_state();
        $rows = $this->wpdb->get_results(
            "SELECT family_key FROM `{$table}`",
            ARRAY_A
        );

        if ($rows === false || $this->wpdb->last_error !== '') {
            throw new CanonicalFamilyProvisioningFailed('Failed to SELECT existing family rows.');
        }

        $keys = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row) || !isset($row['family_key'])) {
                continue;
            }
            $keys[(string) $row['family_key']] = true;
        }

        return $keys;
    }

    /**
     * @throws CanonicalFamilyProvisioningFailed
     */
    private function insert_family_row(string $table, string $family_key): void {
        $now = gmdate('Y-m-d H:i:s');
        $this->clear_error_state();
        $result = $this->wpdb->insert(
            $table,
            [
                'family_key' => $family_key,
                'is_enabled' => self::INITIAL_IS_ENABLED,
                'seed_version' => self::INITIAL_SEED_VERSION,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%d', '%d', '%s', '%s']
        );

        if ($result !== false) {
            return;
        }

        // Posible carrera UNIQUE: si la fila ya existe, éxito concurrente.
        if ($this->family_key_exists($table, $family_key)) {
            return;
        }

        throw new CanonicalFamilyProvisioningFailed(
            'INSERT failed for family_key "' . $family_key . '".'
        );
    }

    /**
     * @throws CanonicalFamilyProvisioningFailed
     */
    private function family_key_exists(string $table, string $family_key): bool {
        $this->clear_error_state();
        $value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT family_key FROM `{$table}` WHERE family_key = %s LIMIT 1",
                $family_key
            )
        );

        if ($value === false || $this->wpdb->last_error !== '') {
            throw new CanonicalFamilyProvisioningFailed(
                'Failed to re-read family_key after INSERT error.'
            );
        }

        return $value === $family_key;
    }

    /**
     * @param list<string> $declared_keys
     * @throws CanonicalFamilyProvisioningFailed
     */
    private function assert_all_declared_present(string $table, array $declared_keys): void {
        $existing = $this->load_existing_keys($table);
        foreach ($declared_keys as $key) {
            if (!isset($existing[$key])) {
                throw new CanonicalFamilyProvisioningFailed(
                    'Verification failed: family_key "' . $key . '" is still missing.'
                );
            }
        }
    }

    private function clear_error_state(): void {
        $this->wpdb->last_error = '';
    }
}
