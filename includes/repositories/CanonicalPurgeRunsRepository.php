<?php
/**
 * Canonical Purge Runs Repository — corridas durables de retiro (IMG-5 inc. 2).
 *
 * Abre/reanuda corridas, checkpoints de lectura de fuentes y estado de captura.
 * UNIQUE parcial de corrida abierta = invariante de Application (no UNIQUE SQL).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__) . '/infrastructure/wp/CanonicalSchema.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__) . '/application/storage/CanonicalImageUploadPersistenceFailed.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__) . '/application/storage/CanonicalImageUploadSchemaNotReady.php';
}

final class CanonicalPurgeRunsRepository {

    public const SCOPE_RECORD = 'record';
    public const SCOPE_CONTAINER = 'container';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_INCOMPLETE = 'incomplete';

    public const CONFLICT_ITEM_METADATA = 'item_metadata_conflict';
    public const CONFLICT_ITEM_INCOMPLETE = 'item_incomplete';

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
     * True si existe purge abierta sobre el registro o su contenedor.
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function has_blocking_purge(int $record_id, int $container_id): bool {
        $table = AA_Canonical_Schema::purge_runs_table_name();
        $this->assert_table_exists($table);

        if ($record_id < 1 || $container_id < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Invalid purge target ids.');
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM `{$safe}`
                 WHERE status IN (%s, %s)
                   AND (
                        (scope = %s AND target_id = %d)
                     OR (scope = %s AND target_id = %d)
                   )
                 LIMIT 1",
                self::STATUS_IN_PROGRESS,
                self::STATUS_INCOMPLETE,
                self::SCOPE_RECORD,
                $record_id,
                self::SCOPE_CONTAINER,
                $container_id
            )
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to query blocking purge runs.');
        }

        return $found !== null && $found !== false && (int) $found >= 1;
    }

    /**
     * True si hay cualquier corrida abierta cuyo container_id coincida.
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function has_blocking_purge_for_container(int $container_id): bool {
        $table = AA_Canonical_Schema::purge_runs_table_name();
        $this->assert_table_exists($table);

        if ($container_id < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Invalid purge container id.');
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM `{$safe}`
                 WHERE status IN (%s, %s)
                   AND container_id = %d
                 LIMIT 1",
                self::STATUS_IN_PROGRESS,
                self::STATUS_INCOMPLETE,
                $container_id
            )
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to query container blocking purge runs.');
        }

        return $found !== null && $found !== false && (int) $found >= 1;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function find_open_by_scope_target(string $scope, int $target_id): ?array {
        $table = AA_Canonical_Schema::purge_runs_table_name();
        $this->assert_table_exists($table);

        if ($target_id < 1) {
            return null;
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM `{$safe}`
                 WHERE scope = %s AND target_id = %d AND status IN (%s, %s)
                 ORDER BY id ASC
                 LIMIT 1",
                $scope,
                $target_id,
                self::STATUS_IN_PROGRESS,
                self::STATUS_INCOMPLETE
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT open purge run.');
        }

        return is_array($row) ? $row : null;
    }

    /**
     * Corrida abierta solapada (contenedor vs registro hijo), excluyendo el mismo alcance.
     *
     * @return array<string, mixed>|null
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function find_overlapping_open_run(string $scope, int $target_id, int $container_id): ?array {
        $table = AA_Canonical_Schema::purge_runs_table_name();
        $this->assert_table_exists($table);

        if ($container_id < 1) {
            return null;
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);

        if ($scope === self::SCOPE_RECORD) {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM `{$safe}`
                     WHERE status IN (%s, %s)
                       AND scope = %s
                       AND target_id = %d
                     ORDER BY id ASC
                     LIMIT 1",
                    self::STATUS_IN_PROGRESS,
                    self::STATUS_INCOMPLETE,
                    self::SCOPE_CONTAINER,
                    $container_id
                ),
                ARRAY_A
            );
        } else {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM `{$safe}`
                     WHERE status IN (%s, %s)
                       AND container_id = %d
                       AND NOT (scope = %s AND target_id = %d)
                     ORDER BY id ASC
                     LIMIT 1",
                    self::STATUS_IN_PROGRESS,
                    self::STATUS_INCOMPLETE,
                    $container_id,
                    $scope,
                    $target_id
                ),
                ARRAY_A
            );
        }

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT overlapping purge run.');
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function find_by_id(int $purge_run_id): ?array {
        $table = AA_Canonical_Schema::purge_runs_table_name();
        $this->assert_table_exists($table);

        if ($purge_run_id < 1) {
            return null;
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM `{$safe}` WHERE id = %d LIMIT 1",
                $purge_run_id
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT purge run by id.');
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{
     *   scope:string,
     *   target_id:int,
     *   container_id:int,
     *   family_key:string,
     *   mandate_id:string,
     *   created_at:string,
     *   updated_at:string
     * } $row
     * @return array<string, mixed>
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function insert_open_run(array $row): array {
        $table = AA_Canonical_Schema::purge_runs_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $result = $this->wpdb->insert(
            $table,
            [
                'scope' => (string) $row['scope'],
                'target_id' => (int) $row['target_id'],
                'family_key' => (string) $row['family_key'],
                'status' => self::STATUS_IN_PROGRESS,
                'cursor_kind' => AA_Canonical_Schema::PURGE_CURSOR_KIND_SOURCE_KEYSET,
                'cursor_id' => 0,
                'deleted_ok' => 0,
                'failed_count' => 0,
                'mandate_id' => (string) $row['mandate_id'],
                'container_id' => (int) $row['container_id'],
                'capture_status' => AA_Canonical_Schema::PURGE_CAPTURE_STATUS_PENDING,
                'images_read_after_id' => 0,
                'images_source_exhausted' => 0,
                'ops_source_exhausted' => 0,
                'capture_complete' => 0,
                'batches_prepared' => 0,
                'prepared_batch_count' => 0,
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
            ],
            [
                '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s',
                '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s',
            ]
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to INSERT purge run.');
        }

        $id = (int) $this->wpdb->insert_id;
        if ($id < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Purge run insert_id invalid.');
        }

        $created = $this->find_by_id($id);
        if ($created === null) {
            throw new CanonicalImageUploadPersistenceFailed('Purge run missing after INSERT.');
        }

        return $created;
    }

    /**
     * @param array{
     *   images_read_after_id:int,
     *   ops_read_after_operation_id:?string,
     *   images_source_exhausted:int,
     *   ops_source_exhausted:int,
     *   capture_status:string,
     *   capture_conflict_code:?string,
     *   capture_complete:int,
     *   status:string,
     *   updated_at:string
     * } $fields
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function update_capture_progress(int $purge_run_id, array $fields): void {
        $table = AA_Canonical_Schema::purge_runs_table_name();
        $this->assert_table_exists($table);

        if ($purge_run_id < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Invalid purge_run_id for capture progress.');
        }

        $ops_id = $fields['ops_read_after_operation_id'];
        $ops_sql = $ops_id === null || $ops_id === ''
            ? 'ops_read_after_operation_id = NULL'
            : $this->wpdb->prepare('ops_read_after_operation_id = %s', (string) $ops_id);

        $conflict = $fields['capture_conflict_code'];
        $conflict_sql = $conflict === null || $conflict === ''
            ? 'capture_conflict_code = NULL'
            : $this->wpdb->prepare('capture_conflict_code = %s', (string) $conflict);

        $safe = str_replace('`', '``', $table);
        $sql = $this->wpdb->prepare(
            "UPDATE `{$safe}`
             SET images_read_after_id = %d,
                 images_source_exhausted = %d,
                 ops_source_exhausted = %d,
                 capture_status = %s,
                 capture_complete = %d,
                 status = %s,
                 updated_at = %s,
                 {$ops_sql},
                 {$conflict_sql}
             WHERE id = %d",
            (int) $fields['images_read_after_id'],
            (int) $fields['images_source_exhausted'],
            (int) $fields['ops_source_exhausted'],
            (string) $fields['capture_status'],
            (int) $fields['capture_complete'],
            (string) $fields['status'],
            (string) $fields['updated_at'],
            $purge_run_id
        );

        $this->clear_error_state();
        $result = $this->wpdb->query($sql);

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to UPDATE purge capture progress.');
        }
    }

    /**
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function mark_batches_prepared(
        int $purge_run_id,
        int $prepared_batch_count,
        string $updated_at
    ): void {
        $table = AA_Canonical_Schema::purge_runs_table_name();
        $this->assert_table_exists($table);

        if ($purge_run_id < 1 || $prepared_batch_count < 0) {
            throw new CanonicalImageUploadPersistenceFailed('Invalid batch preparation arguments.');
        }

        $this->clear_error_state();
        $result = $this->wpdb->update(
            $table,
            [
                'batches_prepared' => 1,
                'prepared_batch_count' => $prepared_batch_count,
                'capture_complete' => 1,
                'capture_status' => AA_Canonical_Schema::PURGE_CAPTURE_STATUS_PENDING,
                'status' => self::STATUS_INCOMPLETE,
                'updated_at' => $updated_at,
            ],
            ['id' => $purge_run_id],
            ['%d', '%d', '%d', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to mark purge batches prepared.');
        }
    }

    /**
     * @throws CanonicalImageUploadSchemaNotReady
     */
    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($this->wpdb->last_error !== '' || $found !== $table) {
            throw new CanonicalImageUploadSchemaNotReady('Required purge runs table missing: ' . $table);
        }
    }

    private function clear_error_state(): void {
        $this->wpdb->last_error = '';
    }
}
