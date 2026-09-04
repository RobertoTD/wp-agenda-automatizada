<?php
/**
 * Canonical Relational Repository — SQL puro sobre las tres tablas universales.
 *
 * Único dueño de prepare, transacciones, UUID, UTC y touch de contenedor.
 * Sin conocimiento de familias de producto concretas ni tablas legacy.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CanonicalRelationalQueryFailed')) {
    require_once __DIR__ . '/CanonicalRelationalQueryFailed.php';
}
if (!class_exists('CanonicalRelationalAmbiguousOutcome')) {
    require_once __DIR__ . '/CanonicalRelationalAmbiguousOutcome.php';
}
if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__) . '/infrastructure/wp/CanonicalSchema.php';
}

final class CanonicalRelationalRepository {

    public const PAGE_SIZE = 15;

    /** @var object */
    private $wpdb;

    /**
     * @param object|null $wpdb Instancia wpdb; global si null (tests pueden inyectar).
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
     * @return int|null family_id o null si la familia no está provisionada.
     * @throws CanonicalRelationalQueryFailed
     */
    public function resolve_family_id(string $family_key): ?int {
        $table = $this->families_table();
        $this->clear_error_state();
        $value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE family_key = %s LIMIT 1",
                $family_key
            )
        );

        if ($value === false || $this->wpdb->last_error !== '') {
            throw new CanonicalRelationalQueryFailed(
                'resolve_family_id query failed.',
                CanonicalRelationalQueryFailed::CODE_SQL
            );
        }

        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;
        return $id >= 1 ? $id : null;
    }

    /**
     * @throws CanonicalRelationalQueryFailed
     */
    public function count_containers(int $family_id, string $variant_key): int {
        $table = $this->containers_table();
        $this->clear_error_state();
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE family_id = %d AND variant_key = %s",
                $family_id,
                $variant_key
            )
        );

        if ($count === false || $count === null || $this->wpdb->last_error !== '') {
            throw new CanonicalRelationalQueryFailed('count_containers query failed.');
        }

        return (int) $count;
    }

    /**
     * @return list<array{id:int,public_id:string,family_id:int,variant_key:string,title:string,details:?string,created_at:string,updated_at:string}>
     * @throws CanonicalRelationalQueryFailed
     */
    public function list_containers(int $family_id, string $variant_key, int $page, int $per_page): array {
        $this->assert_pagination($page, $per_page);
        $offset = ($page - 1) * $per_page;
        $table = $this->containers_table();
        $this->clear_error_state();
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, public_id, family_id, variant_key, title, details, created_at, updated_at
                 FROM `{$table}`
                 WHERE family_id = %d AND variant_key = %s
                 ORDER BY updated_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                $family_id,
                $variant_key,
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        if ($rows === false || $this->wpdb->last_error !== '') {
            throw new CanonicalRelationalQueryFailed('list_containers query failed.');
        }

        $mapped = [];
        foreach ((array) $rows as $row) {
            $item = $this->map_container_row(is_array($row) ? $row : null);
            if ($item !== null) {
                $mapped[] = $item;
            }
        }

        return $mapped;
    }

    /**
     * @return array{id:int,public_id:string,family_id:int,variant_key:string,title:string,details:?string,created_at:string,updated_at:string}|null
     * @throws CanonicalRelationalQueryFailed
     */
    public function find_container(int $family_id, string $variant_key, int $container_id): ?array {
        $table = $this->containers_table();
        $this->clear_error_state();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, public_id, family_id, variant_key, title, details, created_at, updated_at
                 FROM `{$table}`
                 WHERE family_id = %d AND variant_key = %s AND id = %d
                 LIMIT 1",
                $family_id,
                $variant_key,
                $container_id
            ),
            ARRAY_A
        );

        if ($row === false || $this->wpdb->last_error !== '') {
            throw new CanonicalRelationalQueryFailed('find_container query failed.');
        }

        return $this->map_container_row(is_array($row) ? $row : null);
    }

    /**
     * @throws CanonicalRelationalQueryFailed
     */
    public function count_records(int $container_id): int {
        $table = $this->records_table();
        $this->clear_error_state();
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE container_id = %d",
                $container_id
            )
        );

        if ($count === false || $count === null || $this->wpdb->last_error !== '') {
            throw new CanonicalRelationalQueryFailed('count_records query failed.');
        }

        return (int) $count;
    }

    /**
     * @return list<array{id:int,public_id:string,container_id:int,title:string,details:?string,created_at:string,updated_at:string}>
     * @throws CanonicalRelationalQueryFailed
     */
    public function list_records(int $container_id, int $page, int $per_page): array {
        $this->assert_pagination($page, $per_page);
        $offset = ($page - 1) * $per_page;
        $table = $this->records_table();
        $this->clear_error_state();
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, public_id, container_id, title, details, created_at, updated_at
                 FROM `{$table}`
                 WHERE container_id = %d
                 ORDER BY updated_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                $container_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        if ($rows === false || $this->wpdb->last_error !== '') {
            throw new CanonicalRelationalQueryFailed('list_records query failed.');
        }

        $mapped = [];
        foreach ((array) $rows as $row) {
            $item = $this->map_record_row(is_array($row) ? $row : null);
            if ($item !== null) {
                $mapped[] = $item;
            }
        }

        return $mapped;
    }

    /**
     * @return array{id:int,public_id:string,container_id:int,title:string,details:?string,created_at:string,updated_at:string}|null
     * @throws CanonicalRelationalQueryFailed
     */
    public function find_record(int $container_id, int $record_id): ?array {
        $table = $this->records_table();
        $this->clear_error_state();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, public_id, container_id, title, details, created_at, updated_at
                 FROM `{$table}`
                 WHERE container_id = %d AND id = %d
                 LIMIT 1",
                $container_id,
                $record_id
            ),
            ARRAY_A
        );

        if ($row === false || $this->wpdb->last_error !== '') {
            throw new CanonicalRelationalQueryFailed('find_record query failed.');
        }

        return $this->map_record_row(is_array($row) ? $row : null);
    }

    /**
     * @return array{id:int,public_id:string,family_id:int,variant_key:string,title:string,details:?string,created_at:string,updated_at:string}
     * @throws CanonicalRelationalQueryFailed
     */
    public function create_container(int $family_id, string $variant_key, string $title, ?string $details): array {
        $table = $this->containers_table();
        $now = $this->utc_now();
        $public_id = $this->generate_public_id();

        $data = [
            'public_id' => $public_id,
            'family_id' => $family_id,
            'variant_key' => $variant_key,
            'title' => $title,
            'details' => $details,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $formats = [
            '%s',
            '%d',
            '%s',
            '%s',
            $details === null ? null : '%s',
            '%s',
            '%s',
        ];

        $this->clear_error_state();
        $result = $this->wpdb->insert($table, $data, $formats);
        if ($result === false) {
            throw new CanonicalRelationalQueryFailed('create_container insert failed.');
        }

        $id = (int) $this->wpdb->insert_id;
        if ($id < 1) {
            throw new CanonicalRelationalQueryFailed('create_container insert_id invalid.');
        }

        $row = $this->find_container($family_id, $variant_key, $id);
        if ($row === null) {
            throw new CanonicalRelationalQueryFailed('create_container row missing after insert.');
        }

        return $row;
    }

    /**
     * @return array{id:int,public_id:string,family_id:int,variant_key:string,title:string,details:?string,created_at:string,updated_at:string}|null
     * @throws CanonicalRelationalQueryFailed
     */
    public function update_container(
        int $family_id,
        string $variant_key,
        int $container_id,
        string $title,
        ?string $details
    ): ?array {
        $table = $this->containers_table();
        $now = $this->utc_now();

        $this->clear_error_state();
        $result = $this->wpdb->update(
            $table,
            [
                'title' => $title,
                'details' => $details,
                'updated_at' => $now,
            ],
            [
                'family_id' => $family_id,
                'variant_key' => $variant_key,
                'id' => $container_id,
            ],
            [
                '%s',
                $details === null ? null : '%s',
                '%s',
            ],
            ['%d', '%s', '%d']
        );

        if ($result === false) {
            throw new CanonicalRelationalQueryFailed('update_container failed.');
        }

        $row = $this->find_container($family_id, $variant_key, $container_id);
        if ($row === null) {
            return null;
        }

        return $row;
    }

    /**
     * @throws CanonicalRelationalQueryFailed
     */
    public function delete_container(int $family_id, string $variant_key, int $container_id): bool {
        $table = $this->containers_table();
        $this->clear_error_state();
        $result = $this->wpdb->delete(
            $table,
            [
                'family_id' => $family_id,
                'variant_key' => $variant_key,
                'id' => $container_id,
            ],
            ['%d', '%s', '%d']
        );

        if ($result === false) {
            throw new CanonicalRelationalQueryFailed('delete_container failed.');
        }

        return ((int) $result) > 0;
    }

    /**
     * @return array{id:int,public_id:string,container_id:int,title:string,details:?string,created_at:string,updated_at:string}
     * @throws CanonicalRelationalQueryFailed
     * @throws CanonicalRelationalAmbiguousOutcome
     */
    public function create_record(
        int $family_id,
        string $variant_key,
        int $container_id,
        string $title,
        ?string $details
    ): array {
        $container = $this->find_container($family_id, $variant_key, $container_id);
        if ($container === null) {
            throw new CanonicalRelationalQueryFailed(
                'create_record parent container not found.',
                CanonicalRelationalQueryFailed::CODE_CONTAINER_NOT_FOUND
            );
        }

        $now = $this->utc_now();
        $public_id = $this->generate_public_id();
        $table = $this->records_table();

        $this->begin_transaction();
        $resource_id = null;

        try {
            $this->clear_error_state();
            $result = $this->wpdb->insert(
                $table,
                [
                    'public_id' => $public_id,
                    'container_id' => $container_id,
                    'title' => $title,
                    'details' => $details,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    '%s',
                    '%d',
                    '%s',
                    $details === null ? null : '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($result === false) {
                $this->rollback_confirmed('create_record insert failed.');
            }

            $resource_id = (int) $this->wpdb->insert_id;
            if ($resource_id < 1) {
                $this->rollback_confirmed('create_record insert_id invalid.');
            }

            $this->touch_container_or_fail($family_id, $variant_key, $container_id, $now, 'create', 'record', $resource_id);
            $this->commit_or_ambiguous('create', 'record', $resource_id, $container_id);

            $row = $this->find_record($container_id, $resource_id);
            if ($row === null) {
                throw new CanonicalRelationalQueryFailed('create_record row missing after commit.');
            }

            return $row;
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            throw $e;
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($resource_id !== null && $resource_id >= 1) {
                $this->rollback_after_possible_mutation('create', 'record', $resource_id, $container_id);
            } else {
                $this->rollback_confirmed('create_record unexpected failure.');
            }
            throw new CanonicalRelationalQueryFailed('create_record unexpected failure.');
        }
    }

    /**
     * @return array{id:int,public_id:string,container_id:int,title:string,details:?string,created_at:string,updated_at:string}|null
     * @throws CanonicalRelationalQueryFailed
     * @throws CanonicalRelationalAmbiguousOutcome
     */
    public function update_record(
        int $family_id,
        string $variant_key,
        int $container_id,
        int $record_id,
        string $title,
        ?string $details
    ): ?array {
        $container = $this->find_container($family_id, $variant_key, $container_id);
        if ($container === null) {
            throw new CanonicalRelationalQueryFailed(
                'update_record parent container not found.',
                CanonicalRelationalQueryFailed::CODE_CONTAINER_NOT_FOUND
            );
        }

        $now = $this->utc_now();
        $table = $this->records_table();

        $this->begin_transaction();
        $mutation_possible = false;

        try {
            $this->clear_error_state();
            $result = $this->wpdb->update(
                $table,
                [
                    'title' => $title,
                    'details' => $details,
                    'updated_at' => $now,
                ],
                [
                    'container_id' => $container_id,
                    'id' => $record_id,
                ],
                [
                    '%s',
                    $details === null ? null : '%s',
                    '%s',
                ],
                ['%d', '%d']
            );

            if ($result === false) {
                $this->rollback_confirmed('update_record failed.');
            }

            $mutation_possible = true;

            if ((int) $result === 0) {
                $existing = $this->find_record($container_id, $record_id);
                if ($existing === null) {
                    $this->rollback_confirmed(
                        'update_record target not found.',
                        CanonicalRelationalQueryFailed::CODE_RECORD_NOT_FOUND
                    );
                }
            }

            $this->touch_container_or_fail($family_id, $variant_key, $container_id, $now, 'update', 'record', $record_id);
            $this->commit_or_ambiguous('update', 'record', $record_id, $container_id);

            return $this->find_record($container_id, $record_id);
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            throw $e;
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($mutation_possible) {
                $this->rollback_after_possible_mutation('update', 'record', $record_id, $container_id);
            } else {
                $this->rollback_confirmed('update_record unexpected failure.');
            }
            throw new CanonicalRelationalQueryFailed('update_record unexpected failure.');
        }
    }

    /**
     * @throws CanonicalRelationalQueryFailed
     * @throws CanonicalRelationalAmbiguousOutcome
     */
    public function delete_record(
        int $family_id,
        string $variant_key,
        int $container_id,
        int $record_id
    ): bool {
        $container = $this->find_container($family_id, $variant_key, $container_id);
        if ($container === null) {
            throw new CanonicalRelationalQueryFailed(
                'delete_record parent container not found.',
                CanonicalRelationalQueryFailed::CODE_CONTAINER_NOT_FOUND
            );
        }

        $now = $this->utc_now();
        $table = $this->records_table();

        $this->begin_transaction();
        $mutation_possible = false;

        try {
            $this->clear_error_state();
            $result = $this->wpdb->delete(
                $table,
                [
                    'container_id' => $container_id,
                    'id' => $record_id,
                ],
                ['%d', '%d']
            );

            if ($result === false) {
                $this->rollback_confirmed('delete_record failed.');
            }

            if ((int) $result === 0) {
                $this->rollback_confirmed(
                    'delete_record target not found.',
                    CanonicalRelationalQueryFailed::CODE_RECORD_NOT_FOUND
                );
            }

            $mutation_possible = true;
            $this->touch_container_or_fail($family_id, $variant_key, $container_id, $now, 'delete', 'record', $record_id);
            $this->commit_or_ambiguous('delete', 'record', $record_id, $container_id);

            return true;
        } catch (CanonicalRelationalAmbiguousOutcome $e) {
            throw $e;
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($mutation_possible) {
                $this->rollback_after_possible_mutation('delete', 'record', $record_id, $container_id);
            } else {
                $this->rollback_confirmed('delete_record unexpected failure.');
            }
            throw new CanonicalRelationalQueryFailed('delete_record unexpected failure.');
        }
    }

    private function families_table(): string {
        return $this->wpdb->prefix . AA_Canonical_Schema::TABLE_FAMILIES;
    }

    private function containers_table(): string {
        return $this->wpdb->prefix . AA_Canonical_Schema::TABLE_CONTAINERS;
    }

    private function records_table(): string {
        return $this->wpdb->prefix . AA_Canonical_Schema::TABLE_RECORDS;
    }

    private function utc_now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    private function generate_public_id(): string {
        if (!function_exists('wp_generate_uuid4')) {
            throw new CanonicalRelationalQueryFailed('wp_generate_uuid4 unavailable.');
        }

        return (string) wp_generate_uuid4();
    }

    private function clear_error_state(): void {
        $this->wpdb->last_error = '';
    }

    private function assert_pagination(int $page, int $per_page): void {
        if ($page < 1) {
            throw new \InvalidArgumentException('[invalid_page_contract] page must be >= 1.');
        }
        if ($per_page !== self::PAGE_SIZE) {
            throw new \InvalidArgumentException('[invalid_page_contract] per_page must be 15.');
        }
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{id:int,public_id:string,family_id:int,variant_key:string,title:string,details:?string,created_at:string,updated_at:string}|null
     */
    private function map_container_row(?array $row): ?array {
        if (!is_array($row) || !isset($row['id']) || (int) $row['id'] < 1) {
            return null;
        }

        $details = $row['details'] ?? null;

        return [
            'id' => (int) $row['id'],
            'public_id' => (string) ($row['public_id'] ?? ''),
            'family_id' => (int) ($row['family_id'] ?? 0),
            'variant_key' => (string) ($row['variant_key'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'details' => ($details === null || $details === '') ? null : (string) $details,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{id:int,public_id:string,container_id:int,title:string,details:?string,created_at:string,updated_at:string}|null
     */
    private function map_record_row(?array $row): ?array {
        if (!is_array($row) || !isset($row['id']) || (int) $row['id'] < 1) {
            return null;
        }

        $details = $row['details'] ?? null;

        return [
            'id' => (int) $row['id'],
            'public_id' => (string) ($row['public_id'] ?? ''),
            'container_id' => (int) ($row['container_id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'details' => ($details === null || $details === '') ? null : (string) $details,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @throws CanonicalRelationalQueryFailed
     */
    private function begin_transaction(): void {
        $this->clear_error_state();
        if ($this->wpdb->query('START TRANSACTION') === false) {
            throw new CanonicalRelationalQueryFailed('START TRANSACTION failed.');
        }
    }

    /**
     * @throws CanonicalRelationalAmbiguousOutcome
     */
    private function commit_or_ambiguous(
        string $operation,
        string $resource_type,
        int $resource_id,
        ?int $container_id
    ): void {
        $this->clear_error_state();
        if ($this->wpdb->query('COMMIT') === false) {
            $this->best_effort_rollback();
            throw new CanonicalRelationalAmbiguousOutcome(
                $operation,
                $resource_type,
                $resource_id,
                $container_id,
                'COMMIT failed after mutation.'
            );
        }
    }

    /**
     * @throws CanonicalRelationalQueryFailed
     * @throws CanonicalRelationalAmbiguousOutcome
     */
    private function rollback_confirmed(
        string $message,
        string $code_key = CanonicalRelationalQueryFailed::CODE_SQL
    ): void {
        $this->clear_error_state();
        if ($this->wpdb->query('ROLLBACK') === false) {
            // Sin IDs de mutación conocidos: no AmbiguousOutcome; fallo confirmado de control.
            throw new CanonicalRelationalQueryFailed('ROLLBACK failed: ' . $message, $code_key);
        }

        throw new CanonicalRelationalQueryFailed($message, $code_key);
    }

    /**
     * @throws CanonicalRelationalAmbiguousOutcome
     * @throws CanonicalRelationalQueryFailed
     */
    /**
     * @throws CanonicalRelationalAmbiguousOutcome
     * @throws CanonicalRelationalQueryFailed
     */
    private function rollback_after_possible_mutation(
        string $operation,
        string $resource_type,
        int $resource_id,
        ?int $container_id,
        string $code_key = CanonicalRelationalQueryFailed::CODE_SQL
    ): void {
        $this->clear_error_state();
        if ($this->wpdb->query('ROLLBACK') === false) {
            throw new CanonicalRelationalAmbiguousOutcome(
                $operation,
                $resource_type,
                $resource_id,
                $container_id,
                'ROLLBACK failed after possible mutation.'
            );
        }

        throw new CanonicalRelationalQueryFailed('Rolled back after mutation failure.', $code_key);
    }

    private function best_effort_rollback(): void {
        $this->clear_error_state();
        $this->wpdb->query('ROLLBACK');
    }

    /**
     * @throws CanonicalRelationalQueryFailed
     * @throws CanonicalRelationalAmbiguousOutcome
     */
    private function touch_container_or_fail(
        int $family_id,
        string $variant_key,
        int $container_id,
        string $now,
        string $operation,
        string $resource_type,
        int $resource_id
    ): void {
        $table = $this->containers_table();
        $this->clear_error_state();
        $result = $this->wpdb->update(
            $table,
            ['updated_at' => $now],
            [
                'family_id' => $family_id,
                'variant_key' => $variant_key,
                'id' => $container_id,
            ],
            ['%s'],
            ['%d', '%s', '%d']
        );

        if ($result === false) {
            $this->rollback_after_possible_mutation($operation, $resource_type, $resource_id, $container_id);
        }

        if ((int) $result === 0) {
            try {
                $parent = $this->find_container($family_id, $variant_key, $container_id);
            } catch (CanonicalRelationalQueryFailed $e) {
                $this->rollback_after_possible_mutation($operation, $resource_type, $resource_id, $container_id);
            }

            if ($parent !== null) {
                return;
            }

            $this->rollback_after_possible_mutation(
                $operation,
                $resource_type,
                $resource_id,
                $container_id,
                CanonicalRelationalQueryFailed::CODE_CONTAINER_NOT_FOUND
            );
        }
    }
}
