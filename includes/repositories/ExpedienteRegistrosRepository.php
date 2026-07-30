<?php
/**
 * Expediente Registros Repository — SQL puro para registros de expediente.
 *
 * MC2: insert + list by client. Sin update/delete.
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
}
