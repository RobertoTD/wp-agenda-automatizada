<?php
/**
 * Clients Repository — capa canónica de acceso SQL a clientes.
 *
 * Reglas:
 *   - Cualquier método SQL NUEVO de clientes se añade aquí.
 *   - Las reglas de negocio viven fuera del repository.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) exit;

final class ClientsRepository {
    /**
     * Cuenta clientes registrados.
     *
     * Query pura para prerequisitos de reserva; la decisión de negocio
     * vive fuera del repository.
     *
     * @return int
     */
    public static function count_registered_clients() {
        global $wpdb;

        $table = $wpdb->prefix . 'aa_clientes';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($wpdb->last_error) {
            error_log('[ClientsRepository] Error al contar clientes registrados: ' . $wpdb->last_error);
            return 0;
        }

        return (int) $count;
    }

    /**
     * Busca un cliente por ID en la tabla del blog actual ($wpdb->prefix).
     *
     * No acepta blog_id externo: el aislamiento es el prefijo de tabla del sitio.
     * Un ID de otro sitio no existe en esta tabla → null (igual que inexistente).
     *
     * @param int $client_id
     * @return array{id:int,nombre:string,telefono:string,correo:string}|null
     */
    public static function find_by_id(int $client_id): ?array {
        if ($client_id < 1) {
            return null;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'aa_clientes';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, nombre, telefono, correo FROM {$table} WHERE id = %d LIMIT 1",
                $client_id
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ClientsRepository] Error al buscar cliente por id: ' . $wpdb->last_error);

            return null;
        }

        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'nombre' => (string) ($row['nombre'] ?? ''),
            'telefono' => (string) ($row['telefono'] ?? ''),
            'correo' => (string) ($row['correo'] ?? ''),
        ];
    }

    /**
     * Busca un cliente registrado por teléfono canónico.
     *
     * @param string $telefono Teléfono en formato canónico.
     * @return array{id:int,nombre:string,telefono:string,correo:string}|null
     */
    public static function find_by_telefono(string $telefono): ?array {
        global $wpdb;

        $table = $wpdb->prefix . 'aa_clientes';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, nombre, telefono, correo FROM {$table} WHERE telefono = %s LIMIT 1",
                $telefono
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('[ClientsRepository] Error al buscar cliente por teléfono: ' . $wpdb->last_error);

            return null;
        }

        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'nombre' => (string) ($row['nombre'] ?? ''),
            'telefono' => (string) ($row['telefono'] ?? ''),
            'correo' => (string) ($row['correo'] ?? ''),
        ];
    }

    /**
     * Inserta un cliente registrado.
     *
     * @param array{nombre:string,telefono:string,correo?:string} $data
     * @return int|\WP_Error
     */
    public static function insert_registered_client(array $data) {
        global $wpdb;

        $table = $wpdb->prefix . 'aa_clientes';
        $nombre = sanitize_text_field((string) ($data['nombre'] ?? ''));
        $telefono = sanitize_text_field((string) ($data['telefono'] ?? ''));
        $correo = sanitize_email((string) ($data['correo'] ?? ''));

        if ($nombre === '' || $telefono === '') {
            return new WP_Error('invalid_client_data', 'Nombre y teléfono son obligatorios.');
        }

        $result = $wpdb->insert(
            $table,
            [
                'nombre' => $nombre,
                'telefono' => $telefono,
                'correo' => $correo,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            error_log('[ClientsRepository] Error al insertar cliente: ' . $wpdb->last_error);

            return new WP_Error('db_error', 'Error al insertar cliente: ' . $wpdb->last_error);
        }

        $client_id = (int) $wpdb->insert_id;

        if ($client_id < 1) {
            return new WP_Error('db_error', 'No se pudo obtener el ID del cliente insertado.');
        }

        return $client_id;
    }
}
