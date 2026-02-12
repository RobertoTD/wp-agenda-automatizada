<?php
/**
 * ClienteService
 * 
 * Centraliza la lógica de búsqueda y creación de clientes para WP Agenda Automatizada.
 * 
 * Reglas de negocio:
 * - Teléfono es identidad única (UNIQUE KEY en BD, exactamente 10 dígitos).
 * - Correo es opcional.
 * - Si el teléfono ya existe:
 *     - Coincide correo (o ambos vacíos) → usar cliente existente sin modificar.
 *     - No coincide correo → rechazar (WP_Error).
 * - Si el teléfono no existe → crear cliente nuevo.
 * - En frontend NO se actualizan datos de clientes existentes.
 */

if (!defined('ABSPATH')) exit;

class ClienteService {
    
    /**
     * Obtiene o crea un cliente basado en teléfono (identidad única).
     * Aplica reglas de correo mismatch para flujos frontend/público.
     * 
     * @param array $data Array con las claves: 'nombre', 'telefono', 'correo'
     * @return int|WP_Error ID del cliente (existente o nuevo) o WP_Error
     */
    public static function getOrCreate(array $data) {
        // Sanitizar datos de entrada
        $nombre   = isset($data['nombre'])   ? sanitize_text_field($data['nombre'])   : '';
        $telefono = isset($data['telefono']) ? sanitize_text_field($data['telefono']) : '';
        $correo   = isset($data['correo'])   ? sanitize_email($data['correo'])        : '';
        
        // Validar nombre obligatorio
        if (empty($nombre)) {
            return new WP_Error('nombre_requerido', 'El nombre es obligatorio.');
        }

        // Validar y normalizar teléfono
        if (empty($telefono)) {
            return new WP_Error('telefono_requerido', 'El teléfono es obligatorio.');
        }

        $telefono = aa_normalize_telefono($telefono);
        if (is_wp_error($telefono)) {
            return $telefono;
        }
        
        // 1. Buscar por teléfono (identidad única)
        $cliente = self::findByTelefono($telefono);
        
        if ($cliente) {
            // Cliente existe → verificar coincidencia de correo
            $correo_almacenado = $cliente->correo ?? '';
            $correo_entrante   = $correo;

            // Coincide si: ambos vacíos, o ambos iguales
            $coincide = ($correo_almacenado === $correo_entrante);

            // Caso especial: si el almacenado es vacío y el entrante también → coincide
            // Caso especial: si el almacenado es vacío y el entrante tiene valor → mismatch
            // Caso especial: si el almacenado tiene valor y el entrante es vacío → mismatch
            if (!$coincide) {
                return new WP_Error(
                    'correo_mismatch',
                    'Ese teléfono ya tiene otro correo asociado.'
                );
            }

            // Correo coincide → devolver cliente existente sin modificar
            error_log("✅ [ClienteService] Cliente existente por teléfono ID: {$cliente->id} (tel: $telefono)");
            return (int) $cliente->id;
        }
        
        // 2. Teléfono no existe → crear cliente nuevo
        return self::createCliente($nombre, $telefono, $correo);
    }
    
    /**
     * Busca un cliente por teléfono (ya normalizado).
     * 
     * @param string $telefono Teléfono normalizado (10 dígitos)
     * @return object|null Cliente encontrado o null
     */
    public static function findByTelefono(string $telefono): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_clientes';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, nombre, telefono, correo FROM $table WHERE telefono = %s LIMIT 1",
            $telefono
        ));
    }
    
    /**
     * Busca un cliente por correo.
     * 
     * @param string $correo Correo a buscar
     * @return object|null Cliente encontrado o null
     */
    public static function findByCorreo(string $correo): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_clientes';
        
        $correo_sanitizado = sanitize_email($correo);
        if (empty($correo_sanitizado)) return null;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, nombre, telefono, correo FROM $table WHERE correo = %s LIMIT 1",
            $correo_sanitizado
        ));
    }
    
    /**
     * Crea un nuevo cliente en la base de datos.
     * 
     * @param string $nombre   Nombre del cliente
     * @param string $telefono Teléfono normalizado (10 dígitos)
     * @param string $correo   Correo del cliente (puede ser '')
     * @return int|WP_Error    ID del cliente creado o WP_Error
     */
    private static function createCliente(string $nombre, string $telefono, string $correo) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_clientes';
        
        $result = $wpdb->insert(
            $table,
            [
                'nombre'     => $nombre,
                'telefono'   => $telefono,
                'correo'     => $correo,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            error_log("❌ [ClienteService] Error al crear cliente: " . $wpdb->last_error);
            return new WP_Error('db_error', 'Error al crear el cliente: ' . $wpdb->last_error);
        }
        
        $cliente_id = $wpdb->insert_id;
        
        if (!$cliente_id) {
            error_log("❌ [ClienteService] Cliente creado pero no se obtuvo insert_id");
            return new WP_Error('db_error', 'Error: No se pudo obtener el ID del cliente creado.');
        }
        
        error_log("✅ [ClienteService] Nuevo cliente creado ID: $cliente_id (nombre: $nombre, tel: $telefono, correo: " . ($correo ?: 'vacío') . ")");
        
        return (int) $cliente_id;
    }
}
