<?php
/**
 * Expediente Adjuntos Repository — SQL puro para metadatos de adjuntos finalizados.
 *
 * MC4a2: insert idempotente + list/find. MC5c1: delete scoped.
 * P1 / DB 18: client_id nullable (snapshot legacy); pertenencia = record_id.
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
     * Normaliza client_id de fila SQL: NULL → null; entero ≥1 → int.
     * 0 / inválidos → null de fila completa vía map_row (no identidad).
     *
     * @param mixed $raw
     * @return array{ok:true,id:?int}|array{ok:false}
     */
    private static function normalize_row_client_id($raw): array {
        if ($raw === null) {
            return ['ok' => true, 'id' => null];
        }

        if (is_int($raw)) {
            if ($raw < 1) {
                return ['ok' => false];
            }

            return ['ok' => true, 'id' => $raw];
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '' || !ctype_digit($trimmed)) {
                return ['ok' => false];
            }
            $n = (int) $trimmed;
            if ($n < 1 || (string) $n !== $trimmed) {
                return ['ok' => false];
            }

            return ['ok' => true, 'id' => $n];
        }

        if (is_float($raw)) {
            return ['ok' => false];
        }

        return ['ok' => false];
    }

    /**
     * Input de insert/delete: int ≥1 | null. Rechaza 0, negativos, otros.
     *
     * @param mixed $raw
     * @return array{ok:true,id:?int}|array{ok:false}
     */
    private static function normalize_input_client_id($raw): array {
        if ($raw === null) {
            return ['ok' => true, 'id' => null];
        }

        if (is_int($raw)) {
            if ($raw < 1) {
                return ['ok' => false];
            }

            return ['ok' => true, 'id' => $raw];
        }

        if (is_string($raw) && ctype_digit(trim($raw))) {
            $n = (int) trim($raw);
            if ($n < 1) {
                return ['ok' => false];
            }

            return ['ok' => true, 'id' => $n];
        }

        return ['ok' => false];
    }

    /**
     * @param ?int $a
     * @param ?int $b
     */
    private static function client_ids_match($a, $b): bool {
        if ($a === null && $b === null) {
            return true;
        }

        if (!is_int($a) || !is_int($b) || $a < 1 || $b < 1) {
            return false;
        }

        return $a === $b;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{
     *   id:int,
     *   record_id:int,
     *   client_id:?int,
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

        $client = self::normalize_row_client_id(
            array_key_exists('client_id', $row) ? $row['client_id'] : null
        );
        if (!$client['ok']) {
            return null;
        }

        $record_id = (int) ($row['record_id'] ?? 0);
        if ($record_id < 1) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'record_id' => $record_id,
            'client_id' => $client['id'],
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
     *   client_id:?int,
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
     *   client_id:?int,
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
            && self::client_ids_match(
                $candidate['client_id'] ?? null,
                $existing['client_id'] ?? null
            )
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
     *   client_id:?int,
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
     *   client_id:?int,
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
        $client_norm = self::normalize_input_client_id(
            array_key_exists('client_id', $data) ? $data['client_id'] : 0
        );
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
            || !$client_norm['ok']
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

        $client_id = $client_norm['id'];

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

        $insert_row = [
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
        $formats = [
            '%d',
            $client_id === null ? null : '%d',
            '%s',
            '%s',
            '%s',
            '%d',
            '%d',
            '%d',
            '%s',
        ];

        $result = $wpdb->insert($table, $insert_row, $formats);

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

    /**
     * ¿Existe alguna fila de adjunto para el record_id (sin filtrar por client_id)?
     * Fail-closed para rama general: detectar adjuntos inconsistentes.
     *
     * @return bool|null true = al menos una fila; false = ninguna; null = error SQL
     */
    public static function has_any_by_record_id(int $record_id): ?bool {
        if ($record_id < 1) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();

        $hit = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$table} WHERE record_id = %d LIMIT 1",
                $record_id
            )
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] has_any_by_record_id error');
            return null;
        }

        return $hit !== null;
    }

    /**
     * MC5d2: bytes contabilizados de todos los adjuntos finalizados de la
     * instalación actual (la tabla del prefijo del blog es el alcance; no se
     * acepta scope externo). Es metadata local finalizada, no una auditoría
     * física de Storage: una fila conservada por fallo parcial reintentable
     * (MC5c1/MC5c2) sigue contando hasta que el reintento la elimina.
     *
     * Distinción para enforcement de cuota:
     * - consulta correcta sin filas → 0
     * - consulta fallida / resultado nulo → null (el caller debe fallar cerrado)
     *
     * @return int|null
     */
    public static function sum_byte_size_total(): ?int {
        global $wpdb;
        $table = self::table_name();

        $sum = $wpdb->get_var("SELECT COALESCE(SUM(byte_size), 0) FROM {$table}");

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] sum_byte_size_total error');
            return null;
        }

        if ($sum === null) {
            error_log('[ExpedienteAdjuntosRepository] sum_byte_size_total null result');
            return null;
        }

        $sum = (int) $sum;

        return $sum > 0 ? $sum : 0;
    }

    /**
     * @return string
     */
    private static function registros_table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'aa_expediente_registros';
    }

    /**
     * Keyset de adjuntos unidos por expediente_id (JOIN canónico, Ciclo B).
     *
     * @return list<array{
     *   id:int,
     *   record_id:int,
     *   client_id:mixed,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   record_client_id:mixed,
     *   record_expediente_id:int
     * }>|WP_Error
     */
    public static function list_joined_page_by_expediente_id(
        int $expediente_id,
        int $after_id,
        int $limit = 100
    ) {
        if ($expediente_id < 1 || $limit < 1) {
            return [];
        }

        if ($after_id < 0) {
            $after_id = 0;
        }

        global $wpdb;
        $adjuntos = self::table_name();
        $registros = self::registros_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id, a.record_id, a.client_id, a.upload_operation_id, a.storage_path,
                        r.client_id AS record_client_id, r.expediente_id AS record_expediente_id
                 FROM {$adjuntos} a
                 INNER JOIN {$registros} r ON r.id = a.record_id
                 WHERE r.expediente_id = %d AND a.id > %d
                 ORDER BY a.id ASC
                 LIMIT %d",
                $expediente_id,
                $after_id,
                $limit
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] list_joined_page_by_expediente_id error');
            return new WP_Error('db_error', 'No se pudo listar los adjuntos.');
        }

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'record_id' => (int) ($row['record_id'] ?? 0),
                'client_id' => array_key_exists('client_id', $row) ? $row['client_id'] : null,
                'upload_operation_id' => (string) ($row['upload_operation_id'] ?? ''),
                'storage_path' => (string) ($row['storage_path'] ?? ''),
                'record_client_id' => array_key_exists('record_client_id', $row) ? $row['record_client_id'] : null,
                'record_expediente_id' => (int) ($row['record_expediente_id'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * ¿Existe algún adjunto unido por expediente_id? (Ciclo B)
     *
     * @return bool|WP_Error
     */
    public static function has_any_joined_by_expediente_id(int $expediente_id) {
        if ($expediente_id < 1) {
            return false;
        }

        global $wpdb;
        $adjuntos = self::table_name();
        $registros = self::registros_table_name();

        $hit = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1
                 FROM {$adjuntos} a
                 INNER JOIN {$registros} r ON r.id = a.record_id
                 WHERE r.expediente_id = %d
                 LIMIT 1",
                $expediente_id
            )
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] has_any_joined_by_expediente_id error');
            return new WP_Error('db_error', 'No se pudo verificar adjuntos.');
        }

        return $hit !== null && $hit !== false && (string) $hit !== '';
    }

    /**
     * DELETE de metadata con identidad exacta (Ciclo B / P1 null-safe).
     *
     * @param array{
     *   id:int,
     *   record_id:int,
     *   client_id:?int,
     *   upload_operation_id:string,
     *   storage_path:string
     * } $identity
     * @return true|false|WP_Error true = 1 fila; false = 0; WP_Error = SQL
     */
    public static function delete_by_exact_identity(array $identity) {
        $id = (int) ($identity['id'] ?? 0);
        $record_id = (int) ($identity['record_id'] ?? 0);
        $client_norm = self::normalize_input_client_id(
            array_key_exists('client_id', $identity) ? $identity['client_id'] : 0
        );
        $operation_id = (string) ($identity['upload_operation_id'] ?? '');
        $storage_path = (string) ($identity['storage_path'] ?? '');

        if ($id < 1 || $record_id < 1 || !$client_norm['ok'] || $operation_id === '' || $storage_path === '') {
            return false;
        }

        global $wpdb;
        $table = self::table_name();
        $client_id = $client_norm['id'];

        if ($client_id === null) {
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table}
                     WHERE id = %d
                       AND record_id = %d
                       AND client_id IS NULL
                       AND upload_operation_id = %s
                       AND storage_path = %s",
                    $id,
                    $record_id,
                    $operation_id,
                    $storage_path
                )
            );
        } else {
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table}
                     WHERE id = %d
                       AND record_id = %d
                       AND client_id = %d
                       AND upload_operation_id = %s
                       AND storage_path = %s",
                    $id,
                    $record_id,
                    $client_id,
                    $operation_id,
                    $storage_path
                )
            );
        }

        if ($deleted === false || $wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] delete_by_exact_identity error');
            return new WP_Error('db_error', 'No se pudo eliminar el adjunto.');
        }

        return (int) $deleted === 1;
    }

    /**
     * P1: adjunto por id scoped a record_id (sin exigir client_id).
     *
     * @return array<string,mixed>|null
     */
    public static function find_by_id_for_record(int $attachment_id, int $record_id): ?array {
        if ($attachment_id < 1 || $record_id < 1) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, record_id, client_id, upload_operation_id, storage_path,
                        mime_type, byte_size, width, height, created_at
                 FROM {$table}
                 WHERE id = %d AND record_id = %d
                 LIMIT 1",
                $attachment_id,
                $record_id
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] find_by_id_for_record error');
            return null;
        }

        return self::map_row(is_array($row) ? $row : null);
    }

    /**
     * P1/P2: listado canónico por record_ids sin filtrar client_id.
     *
     * @param list<int> $record_ids
     * @return array<int, list<array<string,mixed>>>|null null = error SQL
     */
    public static function list_by_record_ids_for_records(array $record_ids): ?array {
        $ids = [];
        foreach ($record_ids as $rid) {
            $n = (int) $rid;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
        $ids = array_values($ids);
        if ($ids === []) {
            return [];
        }

        global $wpdb;
        $table = self::table_name();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, record_id, client_id, upload_operation_id, storage_path,
                        mime_type, byte_size, width, height, created_at
                 FROM {$table}
                 WHERE record_id IN ({$placeholders})
                 ORDER BY record_id ASC, id DESC",
                ...$ids
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteAdjuntosRepository] list_by_record_ids_for_records error');
            return null;
        }

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $mapped = self::map_row(is_array($row) ? $row : null);
            if ($mapped === null) {
                continue;
            }
            $rid = (int) $mapped['record_id'];
            if (!isset($out[$rid])) {
                $out[$rid] = [];
            }
            $out[$rid][] = $mapped;
        }

        return $out;
    }
}
