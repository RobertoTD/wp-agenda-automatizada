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
}
