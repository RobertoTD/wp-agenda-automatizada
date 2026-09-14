<?php
/**
 * Canonical Image Upload Operations Repository — SQL de aa_canonical_image_upload_operations.
 *
 * Persistencia de admisiones y suma de reservas. Sin TX propia; participa en
 * la conexión/TX del caller. Credenciales solo para Application servidor.
 *
 * Vigencia de reserva (IMG-3a):
 * - Con `backend_intent_exp_ms`: vigente sii `backend_intent_exp_ms > now_ms`
 *   (instante exacto; mismo ancla que resume/commit futuros).
 * - Históricas sin `backend_intent_exp_ms`: vigente sii `expires_at > now_utc`
 *   derivado de la misma referencia temporal del caller.
 * Filas admitted incompletas (sin intent/URLs) siguen contando hasta ese
 * vencimiento; no son resumibles y no se borran aquí.
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
if (!class_exists('CanonicalImageUploadOperationConflict')) {
    require_once dirname(__DIR__) . '/application/storage/CanonicalImageUploadOperationConflict.php';
}

final class CanonicalImageUploadOperationsRepository {

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
     * Insert-only. Colisión de PK → CanonicalImageUploadOperationConflict (sin overwrite).
     *
     * @param array{
     *   upload_operation_id:string,
     *   record_id:int,
     *   storage_path:string,
     *   content_sha256:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   status:string,
     *   expires_at:string,
     *   backend_intent_exp_ms:int,
     *   upload_intent:string,
     *   upload_objects_json:string,
     *   created_at:string,
     *   updated_at:string
     * } $row
     *
     * @throws CanonicalImageUploadOperationConflict
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function insert_admitted(array $row): void {
        $table = AA_Canonical_Schema::image_upload_operations_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $result = $this->wpdb->insert(
            $table,
            [
                'upload_operation_id' => (string) $row['upload_operation_id'],
                'record_id' => (int) $row['record_id'],
                'storage_path' => (string) $row['storage_path'],
                'content_sha256' => (string) $row['content_sha256'],
                'mime_type' => (string) $row['mime_type'],
                'byte_size' => (int) $row['byte_size'],
                'width' => (int) $row['width'],
                'height' => (int) $row['height'],
                'status' => AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED,
                'expires_at' => (string) $row['expires_at'],
                'backend_intent_exp_ms' => (int) $row['backend_intent_exp_ms'],
                'upload_intent' => (string) $row['upload_intent'],
                'upload_objects_json' => (string) $row['upload_objects_json'],
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($result !== false) {
            return;
        }

        $error = is_string($this->wpdb->last_error) ? $this->wpdb->last_error : '';
        if ($this->is_duplicate_key_error($error)) {
            throw new CanonicalImageUploadOperationConflict(
                'upload_operation_id already exists; admission is insert-only'
            );
        }

        throw new CanonicalImageUploadPersistenceFailed('Failed to INSERT image upload operation.');
    }

    /**
     * Lectura interna completa (incluye credenciales). Solo Application servidor.
     *
     * @return array<string, mixed>|null
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function find_by_operation_id(string $upload_operation_id): ?array {
        $table = AA_Canonical_Schema::image_upload_operations_table_name();
        $this->assert_table_exists($table);

        $op = trim($upload_operation_id);
        if ($op === '') {
            return null;
        }

        $this->clear_error_state();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT upload_operation_id, record_id, storage_path, content_sha256, mime_type,
                        byte_size, width, height, status, expires_at, backend_intent_exp_ms,
                        upload_intent, upload_objects_json, created_at, updated_at
                 FROM `{$table}` WHERE upload_operation_id = %s LIMIT 1",
                $op
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT image upload operation.');
        }

        if (!is_array($row)) {
            return null;
        }

        return $row;
    }

    /**
     * Admisión apta para resume: admitted, credenciales completas, vencimiento exacto vigente.
     *
     * @return array<string, mixed>|null
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function find_admitted_resumable(string $upload_operation_id, int $now_ms): ?array {
        $row = $this->find_by_operation_id($upload_operation_id);
        if ($row === null) {
            return null;
        }

        if ((string) ($row['status'] ?? '') !== AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED) {
            return null;
        }

        $intent = isset($row['upload_intent']) ? trim((string) $row['upload_intent']) : '';
        $objects = isset($row['upload_objects_json']) ? trim((string) $row['upload_objects_json']) : '';
        if ($intent === '' || $objects === '') {
            return null;
        }

        if (!isset($row['backend_intent_exp_ms']) || $row['backend_intent_exp_ms'] === null || $row['backend_intent_exp_ms'] === '') {
            return null;
        }

        $exp_ms = (int) $row['backend_intent_exp_ms'];
        if ($exp_ms <= $now_ms) {
            return null;
        }

        return $row;
    }

    /**
     * Pasa a cleanup_needed y anula credenciales; conserva path/metadatos de purge.
     * Libera la reserva; no revoca permisos ya emitidos por Storage.
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function mark_cleanup_needed(string $upload_operation_id, string $updated_at_utc): void {
        $table = AA_Canonical_Schema::image_upload_operations_table_name();
        $this->assert_table_exists($table);

        $op = trim($upload_operation_id);
        if ($op === '') {
            throw new CanonicalImageUploadPersistenceFailed('upload_operation_id required for cleanup.');
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE `{$safe}` SET status = %s, upload_intent = NULL, upload_objects_json = NULL, updated_at = %s
                 WHERE upload_operation_id = %s",
                AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_CLEANUP_NEEDED,
                $updated_at_utc,
                $op
            )
        );

        if ($result === false) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to mark image upload operation cleanup_needed.');
        }
    }

    /**
     * DELETE por PK. Sin commit propio. Devuelve filas afectadas (0|1).
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function delete_by_operation_id(string $upload_operation_id): int {
        $table = AA_Canonical_Schema::image_upload_operations_table_name();
        $this->assert_table_exists($table);

        $op = trim($upload_operation_id);
        if ($op === '') {
            throw new CanonicalImageUploadPersistenceFailed('upload_operation_id required for delete.');
        }

        $this->clear_error_state();
        $result = $this->wpdb->delete($table, ['upload_operation_id' => $op], ['%s']);
        if ($result === false) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to DELETE image upload operation.');
        }

        return (int) $result;
    }

    /**
     * Suma byte_size de reservas admitted vigentes del blog actual.
     * $exclude_operation_id solo descuenta si esa fila es reserva válida ahora.
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function sum_reserved_byte_size(int $now_ms, string $now_utc, ?string $exclude_operation_id = null): int {
        $table = AA_Canonical_Schema::image_upload_operations_table_name();
        $this->assert_table_exists($table);

        $status = AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED;
        $exclude = is_string($exclude_operation_id) ? trim($exclude_operation_id) : '';

        // Vigencia: backend_intent_exp_ms exacto cuando existe; si no, expires_at (históricas).
        $sql = "SELECT COALESCE(SUM(byte_size), 0) FROM `{$table}`
            WHERE status = %s
              AND (
                    (backend_intent_exp_ms IS NOT NULL AND backend_intent_exp_ms > %d)
                 OR (backend_intent_exp_ms IS NULL AND expires_at > %s)
              )";
        $params = [$status, $now_ms, $now_utc];

        if ($exclude !== '') {
            $sql .= ' AND upload_operation_id <> %s';
            $params[] = $exclude;
        }

        $this->clear_error_state();
        $prepared = $this->wpdb->prepare($sql, ...$params);
        if (!is_string($prepared) || $prepared === '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to prepare reserved byte_size SUM.');
        }

        $sum = $this->wpdb->get_var($prepared);
        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SUM reserved image upload byte_size.');
        }
        if ($sum === null || $sum === false) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SUM reserved image upload byte_size.');
        }

        $value = (int) $sum;
        if ($value < 0) {
            throw new CanonicalImageUploadPersistenceFailed('Reserved byte_size sum was negative.');
        }

        return $value;
    }

    /**
     * Página de captura de ops admitted|cleanup_needed (keyset por upload_operation_id).
     * Incluye reservas vencidas: el vencimiento comercial no las excluye del inventario.
     *
     * @return list<array<string, mixed>>
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function list_capture_page_after_operation_id(
        string $scope,
        int $target_id,
        ?string $after_operation_id,
        int $limit
    ): array {
        $ops_table = AA_Canonical_Schema::image_upload_operations_table_name();
        $records_table = AA_Canonical_Schema::records_table_name();
        $this->assert_table_exists($ops_table);
        $this->assert_table_exists($records_table);

        if ($target_id < 1 || $limit < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Invalid ops capture page arguments.');
        }

        $after = is_string($after_operation_id) ? strtolower(trim($after_operation_id)) : '';
        $ops_safe = str_replace('`', '``', $ops_table);
        $records_safe = str_replace('`', '``', $records_table);
        $admitted = AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED;
        $cleanup = AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_CLEANUP_NEEDED;
        $select = "SELECT o.upload_operation_id, o.record_id, o.storage_path, o.content_sha256, o.byte_size, o.status";

        $this->clear_error_state();
        if ($scope === 'record') {
            $sql = $this->wpdb->prepare(
                "{$select}
                 FROM `{$ops_safe}` o
                 WHERE o.record_id = %d
                   AND o.status IN (%s, %s)
                   AND o.upload_operation_id > %s
                 ORDER BY o.upload_operation_id ASC
                 LIMIT %d",
                $target_id,
                $admitted,
                $cleanup,
                $after,
                $limit
            );
        } elseif ($scope === 'container') {
            $sql = $this->wpdb->prepare(
                "{$select}
                 FROM `{$ops_safe}` o
                 INNER JOIN `{$records_safe}` r ON r.id = o.record_id
                 WHERE r.container_id = %d
                   AND o.status IN (%s, %s)
                   AND o.upload_operation_id > %s
                 ORDER BY o.upload_operation_id ASC
                 LIMIT %d",
                $target_id,
                $admitted,
                $cleanup,
                $after,
                $limit
            );
        } else {
            throw new CanonicalImageUploadPersistenceFailed('Invalid ops capture scope.');
        }

        if (!is_string($sql) || $sql === '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to prepare ops capture page.');
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if ($this->wpdb->last_error !== '' || !is_array($rows)) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT ops capture page.');
        }

        return $rows;
    }

    private function is_duplicate_key_error(string $error): bool {
        $lower = strtolower($error);
        return strpos($lower, 'duplicate') !== false || strpos($lower, '1062') !== false;
    }

    /**
     * @throws CanonicalImageUploadSchemaNotReady
     */
    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($this->wpdb->last_error !== '' || $found !== $table) {
            throw new CanonicalImageUploadSchemaNotReady('Required image upload operations table missing: ' . $table);
        }
    }

    private function clear_error_state(): void {
        $this->wpdb->last_error = '';
    }
}
