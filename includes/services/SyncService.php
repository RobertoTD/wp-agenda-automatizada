<?php
/**
 * Sync Service
 * 
 * Servicio para gestionar el estado de sincronización con Google Calendar.
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Services
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class SyncService
 * 
 * Maneja la lógica de negocio relacionada con el estado de sincronización.
 */
class SyncService {

    /**
     * Actualiza el estado de sincronización de Google Calendar.
     *
     * @param string $status Estado de sincronización ('invalid' o 'valid')
     * @return array Array con 'success' (bool) y 'message' (string)
     * @throws Exception Si el estado no es válido o la actualización falla
     */
    public static function update_sync_status($status) {
        // Validación estricta: solo se acepta 'invalid' en este momento
        if ($status !== 'invalid') {
            return array(
                'success' => false,
                'message' => 'Solo se acepta el estado "invalid" en este momento'
            );
        }

        // Intentar actualizar la opción de estado de sincronización
        $updated = update_option('aa_estado_gsync', 'invalid');

        // Verificar si la actualización fue exitosa
        if (!$updated && get_option('aa_estado_gsync') !== 'invalid') {
            return array(
                'success' => false,
                'message' => 'No se pudo actualizar el estado de sincronización'
            );
        }

        // Registrar el evento en logs
        if (function_exists('error_log')) {
            error_log(sprintf(
                '[WP Agenda] Estado de sincronización actualizado a: %s en %s',
                $status,
                current_time('mysql')
            ));
        }

        // Retornar éxito
        return array(
            'success' => true,
            'message' => 'Estado de sincronización actualizado correctamente',
            'status'  => $status
        );
    }

    /**
     * Obtiene el estado actual de sincronización.
     *
     * @return string Estado actual ('valid' o 'invalid')
     */
    public static function get_sync_status() {
        return get_option('aa_estado_gsync', 'valid');
    }

    /**
     * Restablece el estado de sincronización a válido.
     *
     * @return bool True si se actualizó correctamente, false en caso contrario
     */
    public static function reset_sync_status() {
        $updated = update_option('aa_estado_gsync', 'valid');
        
        if ($updated || get_option('aa_estado_gsync') === 'valid') {
            if (function_exists('error_log')) {
                error_log(sprintf(
                    '[WP Agenda] Estado de sincronización restablecido a: valid en %s',
                    current_time('mysql')
                ));
            }
            return true;
        }
        
        return false;
    }
}
