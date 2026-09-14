<?php
/**
 * Canonical Record Images Repository — SQL de aa_canonical_record_images.
 *
 * Suma de consumo confirmado + lectura/escritura de confirmación (IMG-3b).
 * Sin TX propia; participa en la conexión/TX del caller.
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

final class CanonicalRecordImagesRepository {

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
     * Suma de byte_size de originales confirmados del blog actual ($wpdb->prefix).
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function sum_byte_size_total(): int {
        $table = AA_Canonical_Schema::record_images_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $sum = $this->wpdb->get_var("SELECT COALESCE(SUM(byte_size), 0) FROM `{$table}`");

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SUM record images byte_size.');
        }

        if ($sum === null || $sum === false) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SUM record images byte_size.');
        }

        $value = (int) $sum;
        if ($value < 0) {
            throw new CanonicalImageUploadPersistenceFailed('Record images byte_size sum was negative.');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function find_by_upload_operation_id(string $upload_operation_id): ?array {
        $table = AA_Canonical_Schema::record_images_table_name();
        $this->assert_table_exists($table);

        $op = trim($upload_operation_id);
        if ($op === '') {
            return null;
        }

        $this->clear_error_state();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, record_id, upload_operation_id, storage_path, content_sha256,
                        mime_type, byte_size, width, height, created_at
                 FROM `{$table}` WHERE upload_operation_id = %s LIMIT 1",
                $op
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT record image by operation.');
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function find_by_id(int $image_id): ?array {
        $table = AA_Canonical_Schema::record_images_table_name();
        $this->assert_table_exists($table);

        if ($image_id < 1) {
            return null;
        }

        $this->clear_error_state();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, record_id, upload_operation_id, storage_path, content_sha256,
                        mime_type, byte_size, width, height, created_at
                 FROM `{$table}` WHERE id = %d LIMIT 1",
                $image_id
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT record image by id.');
        }

        return is_array($row) ? $row : null;
    }

    /**
     * INSERT de imagen confirmada. Sin commit propio.
     *
     * @param array{
     *   record_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   content_sha256:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * } $row
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function insert_confirmed(array $row): int {
        $table = AA_Canonical_Schema::record_images_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $result = $this->wpdb->insert(
            $table,
            [
                'record_id' => (int) $row['record_id'],
                'upload_operation_id' => (string) $row['upload_operation_id'],
                'storage_path' => (string) $row['storage_path'],
                'content_sha256' => (string) $row['content_sha256'],
                'mime_type' => (string) $row['mime_type'],
                'byte_size' => (int) $row['byte_size'],
                'width' => (int) $row['width'],
                'height' => (int) $row['height'],
                'created_at' => (string) $row['created_at'],
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to INSERT confirmed record image.');
        }

        $id = (int) $this->wpdb->insert_id;
        if ($id < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Confirmed record image insert_id invalid.');
        }

        return $id;
    }

    /**
     * Metadata pública por lote para registros de un contenedor.
     * Pertenencia vía JOIN a records. Orden por registro: id DESC.
     * Lista vacía de IDs → mapa vacío sin SQL.
     *
     * @param list<int> $record_ids
     * @return array<int, list<array{id:int,record_id:int,width:int,height:int,byte_size:int,created_at:string}>>
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function find_public_rows_by_record_ids_for_container(int $container_id, array $record_ids): array {
        $images_table = AA_Canonical_Schema::record_images_table_name();
        $records_table = AA_Canonical_Schema::records_table_name();
        $this->assert_table_exists($images_table);
        $this->assert_table_exists($records_table);

        if ($container_id < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Invalid container_id for image batch read.');
        }

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
            $out[$id] = [];
        }

        if ($ids === []) {
            return $out;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params = array_merge([$container_id], $ids);

        $images_safe = str_replace('`', '``', $images_table);
        $records_safe = str_replace('`', '``', $records_table);

        $this->clear_error_state();
        $sql = $this->wpdb->prepare(
            "SELECT i.id, i.record_id, i.width, i.height, i.byte_size, i.created_at
             FROM `{$images_safe}` i
             INNER JOIN `{$records_safe}` r ON r.id = i.record_id
             WHERE r.container_id = %d
               AND i.record_id IN ({$placeholders})
             ORDER BY i.record_id ASC, i.id DESC",
            $params
        );

        if (!is_string($sql) || $sql === '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to prepare image batch SELECT.');
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if ($rows === false || $this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT record images batch.');
        }
        if (!is_array($rows)) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT record images batch.');
        }

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['record_id'], $row['id'])) {
                continue;
            }
            $rid = (int) $row['record_id'];
            if (!array_key_exists($rid, $out)) {
                continue;
            }
            $out[$rid][] = [
                'id' => (int) $row['id'],
                'record_id' => $rid,
                'width' => (int) ($row['width'] ?? 0),
                'height' => (int) ($row['height'] ?? 0),
                'byte_size' => (int) ($row['byte_size'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Página de captura (keyset por id). No es el cursor del inventario copiado.
     *
     * @return list<array<string, mixed>>
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function list_capture_page_after_id(
        string $scope,
        int $target_id,
        int $after_id,
        int $limit
    ): array {
        $images_table = AA_Canonical_Schema::record_images_table_name();
        $records_table = AA_Canonical_Schema::records_table_name();
        $this->assert_table_exists($images_table);
        $this->assert_table_exists($records_table);

        if ($target_id < 1 || $after_id < 0 || $limit < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Invalid image capture page arguments.');
        }

        $images_safe = str_replace('`', '``', $images_table);
        $records_safe = str_replace('`', '``', $records_table);
        $select = "SELECT i.id, i.record_id, i.upload_operation_id, i.storage_path, i.content_sha256, i.byte_size";

        $this->clear_error_state();
        if ($scope === 'record') {
            $sql = $this->wpdb->prepare(
                "{$select}
                 FROM `{$images_safe}` i
                 WHERE i.record_id = %d AND i.id > %d
                 ORDER BY i.id ASC
                 LIMIT %d",
                $target_id,
                $after_id,
                $limit
            );
        } elseif ($scope === 'container') {
            $sql = $this->wpdb->prepare(
                "{$select}
                 FROM `{$images_safe}` i
                 INNER JOIN `{$records_safe}` r ON r.id = i.record_id
                 WHERE r.container_id = %d AND i.id > %d
                 ORDER BY i.id ASC
                 LIMIT %d",
                $target_id,
                $after_id,
                $limit
            );
        } else {
            throw new CanonicalImageUploadPersistenceFailed('Invalid image capture scope.');
        }

        if (!is_string($sql) || $sql === '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to prepare image capture page.');
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if ($this->wpdb->last_error !== '' || !is_array($rows)) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SELECT image capture page.');
        }

        return $rows;
    }

    /**
     * @throws CanonicalImageUploadSchemaNotReady
     */
    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($this->wpdb->last_error !== '' || $found !== $table) {
            throw new CanonicalImageUploadSchemaNotReady('Required images table missing: ' . $table);
        }
    }

    private function clear_error_state(): void {
        $this->wpdb->last_error = '';
    }
}
