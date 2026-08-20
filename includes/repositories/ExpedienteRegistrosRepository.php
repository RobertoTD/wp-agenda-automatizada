<?php
/**
 * Expediente Registros Repository — SQL puro para registros de expediente.
 *
 * MC2: insert + list. MC3: find by id+client + update title/body.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ExpedienteRegistrosRepository {

    public const LIST_LIMIT = 100;

    /**
     * @return string
     */
    private static function table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'aa_expediente_registros';
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array{id:int,client_id:int,title:string,body:string,recorded_at:string,created_at:string,updated_at:?string}|null
     */
    private static function map_row(?array $row): ?array {
        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        $updated = $row['updated_at'] ?? null;

        return [
            'id' => (int) $row['id'],
            'client_id' => (int) ($row['client_id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'recorded_at' => (string) ($row['recorded_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => ($updated === null || $updated === '') ? null : (string) $updated,
        ];
    }

    /**
     * Proyección pública para lectura scoped por expediente_id.
     *
     * @param array<string,mixed>|null $row
     * @return array{id:int,title:string,body:string,recorded_at:string,created_at:string,updated_at:?string}|null
     */
    private static function map_expediente_row(?array $row): ?array {
        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        $updated = $row['updated_at'] ?? null;

        return [
            'id' => (int) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'recorded_at' => (string) ($row['recorded_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => ($updated === null || $updated === '') ? null : (string) $updated,
        ];
    }

    /**
     * Lista registros del cliente en el blog actual (ORDER BY recorded_at DESC, id DESC).
     *
     * @return list<array{id:int,client_id:int,title:string,body:string,recorded_at:string,created_at:string,updated_at:?string}>
     */
    public static function list_by_client_id(int $client_id, int $limit = self::LIST_LIMIT): array {
        if ($client_id < 1) {
            return [];
        }

        $limit = max(1, min($limit, self::LIST_LIMIT));

        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, client_id, title, body, recorded_at, created_at, updated_at
                 FROM {$table}
                 WHERE client_id = %d
                 ORDER BY recorded_at DESC, id DESC
                 LIMIT %d",
                $client_id,
                $limit
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteRegistrosRepository] list error: ' . $wpdb->last_error);
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

    public static function count_by_expediente_id(int $expediente_id): int {
        if ($expediente_id < 1) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE expediente_id = %d",
                $expediente_id
            )
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteRegistrosRepository] count_by_expediente_id error: ' . $wpdb->last_error);
            return 0;
        }

        return (int) $count;
    }

    /**
     * Lista registros del expediente en el blog actual (ORDER BY recorded_at DESC, id DESC).
     *
     * @return list<array{id:int,title:string,body:string,recorded_at:string,created_at:string,updated_at:?string}>
     */
    public static function list_by_expediente_id(int $expediente_id, int $limit, int $offset = 0): array {
        if ($expediente_id < 1 || $limit < 1) {
            return [];
        }

        $limit = min($limit, self::LIST_LIMIT);
        $offset = max(0, $offset);

        global $wpdb;
        $table = self::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, body, recorded_at, created_at, updated_at
                 FROM {$table}
                 WHERE expediente_id = %d
                 ORDER BY recorded_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                $expediente_id,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteRegistrosRepository] list_by_expediente_id error: ' . $wpdb->last_error);
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $mapped = self::map_expediente_row(is_array($row) ? $row : null);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * Busca un registro que pertenece al cliente en el blog actual.
     *
     * @return array{id:int,client_id:int,title:string,body:string,recorded_at:string,created_at:string,updated_at:?string}|null
     */
    public static function find_by_id_for_client(int $record_id, int $client_id): ?array {
        if ($record_id < 1 || $client_id < 1) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, client_id, title, body, recorded_at, created_at, updated_at
                 FROM {$table}
                 WHERE id = %d AND client_id = %d
                 LIMIT 1",
                $record_id,
                $client_id
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteRegistrosRepository] find error: ' . $wpdb->last_error);
            return null;
        }

        return self::map_row(is_array($row) ? $row : null);
    }

    /**
     * Actualiza solo title, body y updated_at. WHERE exige id + client_id.
     *
     * @return true|\WP_Error true incluso si $wpdb->update() === 0 (sin cambios de valor)
     */
    public static function update_title_body(
        int $record_id,
        int $client_id,
        string $title,
        string $body,
        string $updated_at
    ) {
        if ($record_id < 1 || $client_id < 1 || $title === '' || $body === '' || $updated_at === '') {
            return new WP_Error('invalid_registro_data', 'Datos de registro incompletos.');
        }

        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->update(
            $table,
            [
                'title' => $title,
                'body' => $body,
                'updated_at' => $updated_at,
            ],
            [
                'id' => $record_id,
                'client_id' => $client_id,
            ],
            ['%s', '%s', '%s'],
            ['%d', '%d']
        );

        if ($result === false) {
            error_log('[ExpedienteRegistrosRepository] update error: ' . $wpdb->last_error);

            return new WP_Error('db_error', 'Error al actualizar el registro.');
        }

        // 0 = ninguna columna cambió de valor; no es fallo SQL.
        return true;
    }

    /**
     * Inserta un registro. recorded_at/created_at deben venir ya asignados por la capa superior.
     *
     * @param array{client_id:int,title:string,body:string,recorded_at:string,created_at:string} $data
     * @return array{id:int,client_id:int,title:string,body:string,recorded_at:string,created_at:string,updated_at:?string}|\WP_Error
     */
    public static function insert(array $data) {
        global $wpdb;

        $client_id = (int) ($data['client_id'] ?? 0);
        $title = (string) ($data['title'] ?? '');
        $body = (string) ($data['body'] ?? '');
        $recorded_at = (string) ($data['recorded_at'] ?? '');
        $created_at = (string) ($data['created_at'] ?? '');

        if ($client_id < 1 || $title === '' || $body === '' || $recorded_at === '' || $created_at === '') {
            return new WP_Error('invalid_registro_data', 'Datos de registro incompletos.');
        }

        $table = self::table_name();
        $result = $wpdb->insert(
            $table,
            [
                'client_id' => $client_id,
                'title' => $title,
                'body' => $body,
                'recorded_at' => $recorded_at,
                'created_at' => $created_at,
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            error_log('[ExpedienteRegistrosRepository] insert error: ' . $wpdb->last_error);

            return new WP_Error('db_error', 'Error al guardar el registro.');
        }

        $id = (int) $wpdb->insert_id;
        if ($id < 1) {
            return new WP_Error('db_error', 'No se pudo obtener el ID del registro.');
        }

        return [
            'id' => $id,
            'client_id' => $client_id,
            'title' => $title,
            'body' => $body,
            'recorded_at' => $recorded_at,
            'created_at' => $created_at,
            'updated_at' => null,
        ];
    }

    /**
     * Inserta un registro scoped a expediente padre (client_id forzado a NULL).
     * No acepta client_id: el owner es solo expediente_id.
     *
     * @param array{expediente_id:int,title:string,body:string,recorded_at:string,created_at:string} $data
     * @return array{id:int,title:string,body:string,recorded_at:string,created_at:string,updated_at:?string}|\WP_Error
     */
    public static function insert_for_expediente(array $data) {
        global $wpdb;

        $expediente_id = (int) ($data['expediente_id'] ?? 0);
        $title = (string) ($data['title'] ?? '');
        $body = (string) ($data['body'] ?? '');
        $recorded_at = (string) ($data['recorded_at'] ?? '');
        $created_at = (string) ($data['created_at'] ?? '');

        if ($expediente_id < 1 || $title === '' || $body === '' || $recorded_at === '' || $created_at === '') {
            return new WP_Error('invalid_registro_data', 'Datos de registro incompletos.');
        }

        $table = self::table_name();
        $result = $wpdb->insert(
            $table,
            [
                'client_id' => null,
                'expediente_id' => $expediente_id,
                'title' => $title,
                'body' => $body,
                'recorded_at' => $recorded_at,
                'created_at' => $created_at,
            ],
            [null, '%d', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            error_log('[ExpedienteRegistrosRepository] insert_for_expediente error: ' . $wpdb->last_error);

            return new WP_Error('db_error', 'Error al guardar el registro.');
        }

        $id = (int) $wpdb->insert_id;
        if ($id < 1) {
            return new WP_Error('db_error', 'No se pudo obtener el ID del registro.');
        }

        $mapped = self::map_expediente_row([
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'recorded_at' => $recorded_at,
            'created_at' => $created_at,
            'updated_at' => null,
        ]);

        if ($mapped === null) {
            return new WP_Error('db_error', 'No se pudo mapear el registro creado.');
        }

        return $mapped;
    }

    /**
     * MC5c2: elimina un registro scoped a cliente. true solo si se borró
     * exactamente una fila (SQL error → false).
     */
    public static function delete_by_id_for_client(int $record_id, int $client_id): bool {
        if ($record_id < 1 || $client_id < 1) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();

        $deleted = $wpdb->delete(
            $table,
            [
                'id' => $record_id,
                'client_id' => $client_id,
            ],
            ['%d', '%d']
        );

        if ($wpdb->last_error) {
            error_log('[ExpedienteRegistrosRepository] delete_by_id_for_client error');
            return false;
        }

        return (int) $deleted === 1;
    }
}
