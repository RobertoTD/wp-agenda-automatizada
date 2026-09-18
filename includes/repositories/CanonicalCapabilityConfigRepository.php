<?php
/**
 * Canonical Capability Config Repository — SQL de repertorio familiar y asignación por lista.
 *
 * Configuración de capacidades; valores de amount viven en CanonicalRecordAmountRepository.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityConfigRepository {

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
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function assert_schema_ready(): void {
        foreach ([
            AA_Canonical_Schema::family_capabilities_table_name(),
            AA_Canonical_Schema::container_capabilities_table_name(),
            AA_Canonical_Schema::record_amount_table_name(),
            AA_Canonical_Schema::record_phone_table_name(),
            AA_Canonical_Schema::record_whatsapp_table_name(),
            AA_Canonical_Schema::record_email_table_name(),
        ] as $table) {
            $this->assert_table_exists($table);
        }
    }

    /**
     * @return int|null
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function resolve_family_id(string $family_key): ?int {
        $table = AA_Canonical_Schema::families_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE family_key = %s LIMIT 1",
                $family_key
            )
        );

        if ($value === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to resolve family_id.');
        }

        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;
        return $id >= 1 ? $id : null;
    }

    /**
     * @return array{id:int,family_id:int,capability_key:string,is_default:bool,created_at:string,updated_at:string}|null
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function find_family_capability(int $family_id, string $capability_key): ?array {
        $table = AA_Canonical_Schema::family_capabilities_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, family_id, capability_key, is_default, created_at, updated_at
                 FROM `{$table}`
                 WHERE family_id = %d AND capability_key = %s
                 LIMIT 1",
                $family_id,
                $capability_key
            ),
            ARRAY_A
        );

        if ($row === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to SELECT family capability.');
        }

        return $this->map_family_capability_row(is_array($row) ? $row : null);
    }

    /**
     * Inserta solo si falta la fila. Nunca actualiza is_default existente.
     *
     * @return bool true si insertó; false si ya existía
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function insert_family_capability_if_missing(
        int $family_id,
        string $capability_key,
        bool $is_default
    ): bool {
        $existing = $this->find_family_capability($family_id, $capability_key);
        if ($existing !== null) {
            return false;
        }

        $table = AA_Canonical_Schema::family_capabilities_table_name();
        $now = gmdate('Y-m-d H:i:s');
        $this->clear_error_state();
        $result = $this->wpdb->insert(
            $table,
            [
                'family_id' => $family_id,
                'capability_key' => $capability_key,
                'is_default' => $is_default ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%d', '%s', '%s']
        );

        if ($result !== false) {
            return true;
        }

        // Carrera UNIQUE: si ya existe, éxito concurrente sin sobrescribir.
        if ($this->find_family_capability($family_id, $capability_key) !== null) {
            return false;
        }

        throw new CanonicalCapabilityPersistenceFailed('Failed to INSERT family capability.');
    }

    /**
     * Upsert explícito: inserta o actualiza is_default (modificación deliberada).
     *
     * @return array{id:int,family_id:int,capability_key:string,is_default:bool,created_at:string,updated_at:string}
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function upsert_family_capability(
        int $family_id,
        string $capability_key,
        bool $is_default
    ): array {
        $existing = $this->find_family_capability($family_id, $capability_key);
        $table = AA_Canonical_Schema::family_capabilities_table_name();
        $now = gmdate('Y-m-d H:i:s');

        if ($existing === null) {
            $this->clear_error_state();
            $result = $this->wpdb->insert(
                $table,
                [
                    'family_id' => $family_id,
                    'capability_key' => $capability_key,
                    'is_default' => $is_default ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%d', '%s', '%s']
            );
            if ($result === false) {
                throw new CanonicalCapabilityPersistenceFailed('Failed to INSERT family capability.');
            }
        } else {
            $this->clear_error_state();
            $result = $this->wpdb->update(
                $table,
                [
                    'is_default' => $is_default ? 1 : 0,
                    'updated_at' => $now,
                ],
                [
                    'family_id' => $family_id,
                    'capability_key' => $capability_key,
                ],
                ['%d', '%s'],
                ['%d', '%s']
            );
            if ($result === false) {
                throw new CanonicalCapabilityPersistenceFailed('Failed to UPDATE family capability.');
            }
        }

        $row = $this->find_family_capability($family_id, $capability_key);
        if ($row === null) {
            throw new CanonicalCapabilityPersistenceFailed('Family capability missing after upsert.');
        }

        return $row;
    }

    /**
     * @return bool true si el contenedor pertenece a la familia
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function container_belongs_to_family(int $family_id, int $container_id): bool {
        $table = AA_Canonical_Schema::containers_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE family_id = %d AND id = %d LIMIT 1",
                $family_id,
                $container_id
            )
        );

        if ($value === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to verify container family membership.');
        }

        return $value !== null && $value !== '' && (int) $value >= 1;
    }

    /**
     * @return array{id:int,container_id:int,capability_key:string,is_active:bool,created_at:string,updated_at:string}|null
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function find_container_capability(int $container_id, string $capability_key): ?array {
        $table = AA_Canonical_Schema::container_capabilities_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, container_id, capability_key, is_active, created_at, updated_at
                 FROM `{$table}`
                 WHERE container_id = %d AND capability_key = %s
                 LIMIT 1",
                $container_id,
                $capability_key
            ),
            ARRAY_A
        );

        if ($row === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to SELECT container capability.');
        }

        return $this->map_container_capability_row(is_array($row) ? $row : null);
    }

    /**
     * @return list<array{id:int,family_id:int,capability_key:string,is_default:bool,created_at:string,updated_at:string}>
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function list_family_capabilities(int $family_id): array {
        $table = AA_Canonical_Schema::family_capabilities_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, family_id, capability_key, is_default, created_at, updated_at
                 FROM `{$table}`
                 WHERE family_id = %d
                 ORDER BY capability_key ASC",
                $family_id
            ),
            ARRAY_A
        );

        if ($rows === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to LIST family capabilities.');
        }

        $out = [];
        foreach ((array) $rows as $row) {
            $mapped = $this->map_family_capability_row(is_array($row) ? $row : null);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @return list<array{id:int,container_id:int,capability_key:string,is_active:bool,created_at:string,updated_at:string}>
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function list_container_capabilities(int $container_id): array {
        $table = AA_Canonical_Schema::container_capabilities_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, container_id, capability_key, is_active, created_at, updated_at
                 FROM `{$table}`
                 WHERE container_id = %d
                 ORDER BY capability_key ASC",
                $container_id
            ),
            ARRAY_A
        );

        if ($rows === false || $this->wpdb->last_error !== '') {
            throw new CanonicalCapabilityPersistenceFailed('Failed to LIST container capabilities.');
        }

        $out = [];
        foreach ((array) $rows as $row) {
            $mapped = $this->map_container_capability_row(is_array($row) ? $row : null);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @return array{id:int,container_id:int,capability_key:string,is_active:bool,created_at:string,updated_at:string}
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalCapabilitySchemaNotReady
     */
    public function upsert_container_capability(
        int $container_id,
        string $capability_key,
        bool $is_active
    ): array {
        $existing = $this->find_container_capability($container_id, $capability_key);
        $table = AA_Canonical_Schema::container_capabilities_table_name();
        $now = gmdate('Y-m-d H:i:s');

        if ($existing === null) {
            $this->clear_error_state();
            $result = $this->wpdb->insert(
                $table,
                [
                    'container_id' => $container_id,
                    'capability_key' => $capability_key,
                    'is_active' => $is_active ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%d', '%s', '%s']
            );
            if ($result === false) {
                throw new CanonicalCapabilityPersistenceFailed('Failed to INSERT container capability.');
            }
        } else {
            $this->clear_error_state();
            $result = $this->wpdb->update(
                $table,
                [
                    'is_active' => $is_active ? 1 : 0,
                    'updated_at' => $now,
                ],
                [
                    'container_id' => $container_id,
                    'capability_key' => $capability_key,
                ],
                ['%d', '%s'],
                ['%d', '%s']
            );
            if ($result === false) {
                throw new CanonicalCapabilityPersistenceFailed('Failed to UPDATE container capability.');
            }
        }

        $row = $this->find_container_capability($container_id, $capability_key);
        if ($row === null) {
            throw new CanonicalCapabilityPersistenceFailed('Container capability missing after upsert.');
        }

        return $row;
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

    /**
     * @param array<string,mixed>|null $row
     * @return array{id:int,family_id:int,capability_key:string,is_default:bool,created_at:string,updated_at:string}|null
     */
    private function map_family_capability_row(?array $row): ?array {
        if (!is_array($row) || !isset($row['id']) || (int) $row['id'] < 1) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'family_id' => (int) ($row['family_id'] ?? 0),
            'capability_key' => (string) ($row['capability_key'] ?? ''),
            'is_default' => !empty($row['is_default']),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{id:int,container_id:int,capability_key:string,is_active:bool,created_at:string,updated_at:string}|null
     */
    private function map_container_capability_row(?array $row): ?array {
        if (!is_array($row) || !isset($row['id']) || (int) $row['id'] < 1) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'container_id' => (int) ($row['container_id'] ?? 0),
            'capability_key' => (string) ($row['capability_key'] ?? ''),
            'is_active' => !empty($row['is_active']),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
