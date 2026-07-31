<?php
/**
 * Expediente Adjuntos Repository — SQL puro para metadatos de adjuntos finalizados.
 *
 * MC4a2: insert idempotente + list/find. MC5c1: delete scoped.
 * Binario vive en Supabase Storage.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ExpedienteAdjuntosRepository {

    public const MIME_JPEG = 'image/jpeg';
    public const MAX_BYTES = 1048576;

    /**
     * @return string
     */
    private static function table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'aa_expediente_adjuntos';
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{
     *   id:int,
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * }|null
     */
    private static function map_row(?array $row): ?array {
        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'record_id' => (int) ($row['record_id'] ?? 0),
            'client_id' => (int) ($row['client_id'] ?? 0),
            'upload_operation_id' => (string) ($row['upload_operation_id'] ?? ''),
            'storage_path' => (string) ($row['storage_path'] ?? ''),
            'mime_type' => (string) ($row['mime_type'] ?? ''),
            'byte_size' => (int) ($row['byte_size'] ?? 0),
            'width' => (int) ($row['width'] ?? 0),
            'height' => (int) ($row['height'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @param array{
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * } $candidate
     * @param array{
     *   id:int,
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * } $existing
     */
    private static function canonical_meta_matches(array $candidate, array $existing): bool {
        return (int) $candidate['record_id'] === (int) $existing['record_id']
            && (int) $candidate['client_id'] === (int) $existing['client_id']
            && (string) $candidate['upload_operation_id'] === (string) $existing['upload_operation_id']
            && (string) $candidate['storage_path'] === (string) $existing['storage_path']
            && (string) $candidate['mime_type'] === (string) $existing['mime_type']
            && (int) $candidate['byte_size'] === (int) $existing['byte_size']
            && (int) $candidate['width'] === (int) $existing['width']
            && (int) $candidate['height'] === (int) $existing['height'];
    }

    /**
     * @return array{
     *   id:int,
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * }|null
     */
    public static function find_by_upload_operation_id(string $upload_operation_id): ?array {
        $op = trim($upload_operation_id);
        if ($op === '') {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, record_id, client_id, upload_operation_id, storage_path,
                        mime_type, byte_size, width, height, created_at
                 FROM {$table}
                 WHERE upload_operation_id = %s
                 LIMIT 1",
                $op
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] find_by_upload_operation_id error');
            return null;
        }

        return self::map_row(is_array($row) ? $row : null);
    }

    /**
     * @return array{
     *   id:int,
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * }|null
     */
    public static function find_by_storage_path(string $storage_path): ?array {
        $path = trim($storage_path);
        if ($path === '') {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, record_id, client_id, upload_operation_id, storage_path,
                        mime_type, byte_size, width, height, created_at
                 FROM {$table}
                 WHERE storage_path = %s
                 LIMIT 1",
                $path
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] find_by_storage_path error');
            return null;
        }

        return self::map_row(is_array($row) ? $row : null);
    }

    /**
     * @return array{
     *   id:int,
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * }|null
     */
    public static function find_by_id_for_client(int $attachment_id, int $client_id): ?array {
        if ($attachment_id < 1 || $client_id < 1) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, record_id, client_id, upload_operation_id, storage_path,
                        mime_type, byte_size, width, height, created_at
                 FROM {$table}
                 WHERE id = %d AND client_id = %d
                 LIMIT 1",
                $attachment_id,
                $client_id
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] find_by_id_for_client error');
            return null;
        }

        return self::map_row(is_array($row) ? $row : null);
    }

    /**
     * @return list<array{
     *   id:int,
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * }>
     */
    public static function list_by_record_for_client(int $record_id, int $client_id): array {
        if ($record_id < 1 || $client_id < 1) {
            return [];
        }

        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, record_id, client_id, upload_operation_id, storage_path,
                        mime_type, byte_size, width, height, created_at
                 FROM {$table}
                 WHERE record_id = %d AND client_id = %d
                 ORDER BY id ASC",
                $record_id,
                $client_id
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] list_by_record_for_client error');
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $mapped = self::map_row(is_array($row) ? $row : null);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * Último adjunto (MAX(id)) por registro para un conjunto de record IDs.
     *
     * Una sola consulta bulk (sin N+1): subquery GROUP BY record_id + self-join.
     * Regla del adjunto principal: id AUTO_INCREMENT es monótono y total;
     * created_at tiene resolución de segundos y puede empatar.
     *
     * @param list<int> $record_ids
     * @return array<int, array{
     *   id:int,
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * }> Mapa record_id => adjunto.
     */
    public static function find_latest_by_record_ids(array $record_ids, int $client_id): array {
        if ($client_id < 1) {
            return [];
        }

        $ids = [];
        foreach ($record_ids as $rid) {
            $rid = (int) $rid;
            if ($rid > 0) {
                $ids[$rid] = $rid;
            }
        }

        if ($ids === []) {
            return [];
        }

        $ids = array_values($ids);

        global $wpdb;
        $table = self::table_name();

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id, a.record_id, a.client_id, a.upload_operation_id, a.storage_path,
                        a.mime_type, a.byte_size, a.width, a.height, a.created_at
                 FROM {$table} a
                 INNER JOIN (
                     SELECT record_id, MAX(id) AS max_id
                     FROM {$table}
                     WHERE client_id = %d AND record_id IN ({$placeholders})
                     GROUP BY record_id
                 ) latest ON latest.max_id = a.id",
                array_merge([$client_id], $ids)
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] find_latest_by_record_ids error');
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $mapped = self::map_row(is_array($row) ? $row : null);
            if ($mapped !== null) {
                $out[(int) $mapped['record_id']] = $mapped;
            }
        }

        return $out;
    }

    /**
     * Todos los adjuntos de un conjunto de registros, agrupados por record_id
     * y ordenados id DESC dentro de cada grupo (MC5a).
     *
     * Una sola consulta bulk (sin N+1). Los registros sin adjuntos simplemente
     * no aparecen en el mapa: el caller trata la ausencia como lista vacía.
     *
     * @param list<int> $record_ids
     * @return array<int, list<array{
     *   id:int,
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * }>> Mapa record_id => adjuntos ordenados id DESC.
     */
    public static function list_by_record_ids(array $record_ids, int $client_id): array {
        if ($client_id < 1) {
            return [];
        }

        $ids = [];
        foreach ($record_ids as $rid) {
            $rid = (int) $rid;
            if ($rid > 0) {
                $ids[$rid] = $rid;
            }
        }

        if ($ids === []) {
            return [];
        }

        $ids = array_values($ids);

        global $wpdb;
        $table = self::table_name();

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, record_id, client_id, upload_operation_id, storage_path,
                        mime_type, byte_size, width, height, created_at
                 FROM {$table}
                 WHERE client_id = %d AND record_id IN ({$placeholders})
                 ORDER BY record_id ASC, id DESC",
                array_merge([$client_id], $ids)
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] list_by_record_ids error');
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $mapped = self::map_row(is_array($row) ? $row : null);
            if ($mapped !== null) {
                $out[(int) $mapped['record_id']][] = $mapped;
            }
        }

        return $out;
    }

    /**
     * Inserta un adjunto finalizado. Idempotente si operation_id y storage_path
     * apuntan a la misma fila con metadatos canónicos idénticos.
     *
     * @param array{
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at?:string
     * } $data
     * @return array{
     *   id:int,
     *   record_id:int,
     *   client_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   created_at:string
     * }|\WP_Error
     */
    public static function insert_finalized(array $data) {
        $record_id = (int) ($data['record_id'] ?? 0);
        $client_id = (int) ($data['client_id'] ?? 0);
        $upload_operation_id = trim((string) ($data['upload_operation_id'] ?? ''));
        $storage_path = trim((string) ($data['storage_path'] ?? ''));
        $mime_type = trim((string) ($data['mime_type'] ?? ''));
        $byte_size = (int) ($data['byte_size'] ?? 0);
        $width = (int) ($data['width'] ?? 0);
        $height = (int) ($data['height'] ?? 0);
        $created_at = trim((string) ($data['created_at'] ?? ''));

        if ($created_at === '') {
            $created_at = current_time('mysql');
        }

        if (
            $record_id < 1
            || $client_id < 1
            || $upload_operation_id === ''
            || $storage_path === ''
            || $mime_type !== self::MIME_JPEG
            || $byte_size < 1
            || $byte_size > self::MAX_BYTES
            || $width < 1
            || $height < 1
            || strlen($storage_path) > 191
        ) {
            return new WP_Error('invalid_adjunto_data', 'Datos de adjunto incompletos o inválidos.');
        }

        $candidate = [
            'record_id' => $record_id,
            'client_id' => $client_id,
            'upload_operation_id' => $upload_operation_id,
            'storage_path' => $storage_path,
            'mime_type' => $mime_type,
            'byte_size' => $byte_size,
            'width' => $width,
            'height' => $height,
            'created_at' => $created_at,
        ];

        $by_op = self::find_by_upload_operation_id($upload_operation_id);
        $by_path = self::find_by_storage_path($storage_path);

        if ($by_op !== null && $by_path !== null) {
            if ((int) $by_op['id'] !== (int) $by_path['id']) {
                return new WP_Error(
                    'adjunto_identity_conflict',
                    'Conflicto de identidad entre upload_operation_id y storage_path.'
                );
            }
            if (!self::canonical_meta_matches($candidate, $by_op)) {
                return new WP_Error(
                    'adjunto_meta_conflict',
                    'El adjunto existente no coincide con los metadatos canónicos.'
                );
            }

            return $by_op;
        }

        if ($by_op !== null) {
            if (!self::canonical_meta_matches($candidate, $by_op)) {
                return new WP_Error(
                    'adjunto_meta_conflict',
                    'El adjunto existente no coincide con los metadatos canónicos.'
                );
            }

            return $by_op;
        }

        if ($by_path !== null) {
            if (!self::canonical_meta_matches($candidate, $by_path)) {
                return new WP_Error(
                    'adjunto_meta_conflict',
                    'El adjunto existente no coincide con los metadatos canónicos.'
                );
            }

            return $by_path;
        }

        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->insert(
            $table,
            [
                'record_id' => $record_id,
                'client_id' => $client_id,
                'upload_operation_id' => $upload_operation_id,
                'storage_path' => $storage_path,
                'mime_type' => $mime_type,
                'byte_size' => $byte_size,
                'width' => $width,
                'height' => $height,
                'created_at' => $created_at,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
        );

        if ($result === false) {
            // Carrera: reconsultar por ambas claves.
            $by_op = self::find_by_upload_operation_id($upload_operation_id);
            $by_path = self::find_by_storage_path($storage_path);

            if ($by_op !== null && $by_path !== null && (int) $by_op['id'] === (int) $by_path['id']) {
                if (self::canonical_meta_matches($candidate, $by_op)) {
                    return $by_op;
                }

                return new WP_Error(
                    'adjunto_meta_conflict',
                    'El adjunto existente no coincide con los metadatos canónicos.'
                );
            }

            if ($by_op !== null && self::canonical_meta_matches($candidate, $by_op)) {
                return $by_op;
            }
            if ($by_path !== null && self::canonical_meta_matches($candidate, $by_path)) {
                return $by_path;
            }

            error_log('[ExpedienteAdjuntosRepository] insert error');

            return new WP_Error('db_error', 'Error al guardar el adjunto.');
        }

        $id = (int) $wpdb->insert_id;
        if ($id < 1) {
            return new WP_Error('db_error', 'No se pudo obtener el ID del adjunto.');
        }

        $candidate['id'] = $id;

        return $candidate;
    }

    /**
     * MC5c1: elimina una fila scoped a cliente. Devuelve true solo si se
     * borró exactamente una fila.
     */
    public static function delete_by_id_for_client(int $attachment_id, int $client_id): bool {
        if ($attachment_id < 1 || $client_id < 1) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();

        $deleted = $wpdb->delete(
            $table,
            [
                'id' => $attachment_id,
                'client_id' => $client_id,
            ],
            ['%d', '%d']
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] delete_by_id_for_client error');
            return false;
        }

        return (int) $deleted === 1;
    }

    /**
     * MC5c2: elimina todas las filas de adjuntos de un registro scoped a
     * cliente. Éxito idempotente: true si no quedan filas (incluso si ya
     * estaban vacías). false solo ante error SQL o filas residuales.
     */
    public static function delete_by_record_for_client(int $record_id, int $client_id): bool {
        if ($record_id < 1 || $client_id < 1) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->delete(
            $table,
            [
                'record_id' => $record_id,
                'client_id' => $client_id,
            ],
            ['%d', '%d']
        );

        if ($result === false || $wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] delete_by_record_for_client error');
            return false;
        }

        $remaining = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE record_id = %d AND client_id = %d",
                $record_id,
                $client_id
            )
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] delete_by_record_for_client count error');
            return false;
        }

        return $remaining === 0;
    }
}
