<?php
/**
 * ClienteService
 * 
 * Centraliza la lógica de búsqueda y creación de clientes para WP Agenda Automatizada.
 * 
 * Reglas de búsqueda:
 * - El teléfono es la clave primaria de búsqueda
 * - El correo es secundaria
 * - Si existe cliente por teléfono, usar ese
 * - Si no hay teléfono o no existe, buscar por correo
 * - Si no existe ninguno, crear cliente nuevo
 * - NO eliminar clientes duplicados
 * - NO modificar el esquema de base de datos
 */

if (!defined('ABSPATH')) exit;

class ClienteService {
    
    /**
     * Obtiene o crea un cliente basado en teléfono (prioritario) o correo.
     * 
     * @param array $data Array con las claves: 'nombre', 'telefono', 'correo'
     * @return int ID del cliente (existente o nuevo)
     * @throws Exception Si no se puede crear o encontrar el cliente
     */
    public static function getOrCreate(array $data): int {
        global $wpdb;
        
        // Sanitizar y validar datos de entrada
        $nombre = isset($data['nombre']) ? sanitize_text_field($data['nombre']) : '';
        $telefono = isset($data['telefono']) ? sanitize_text_field($data['telefono']) : '';
        $correo = isset($data['correo']) ? sanitize_email($data['correo']) : '';
        
        // Validar que al menos nombre esté presente
        if (empty($nombre)) {
            throw new Exception('El nombre es obligatorio para crear o buscar un cliente.');
        }
        
        $table = $wpdb->prefix . 'aa_clientes';
        
        // 1. Buscar por teléfono (clave primaria de búsqueda)
        if (!empty($telefono)) {
            $cliente = self::findByTelefono($telefono);
            if ($cliente) {
                // Retornar ID del cliente existente sin modificar datos
                return (int) $cliente->id;
            }
        }
        
        // 2. Si no hay teléfono o no existe, buscar por correo (clave secundaria)
        if (!empty($correo)) {
            $cliente = self::findByCorreo($correo);
            if ($cliente) {
                // Retornar ID del cliente existente sin modificar datos
                return (int) $cliente->id;
            }
        }
        
        // 3. Si no existe ninguno, crear cliente nuevo
        return self::createCliente($nombre, $telefono, $correo);
    }
    
    /**
     * Busca un cliente por teléfono.
     * 
     * @param string $telefono Teléfono a buscar
     * @return object|null Cliente encontrado o null
     */
    private static function findByTelefono(string $telefono): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_clientes';
        
        $telefono_sanitizado = sanitize_text_field($telefono);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, nombre, telefono, correo FROM $table WHERE telefono = %s LIMIT 1",
            $telefono_sanitizado
        ));
    }
    
    /**
     * Busca un cliente por correo.
     * 
     * @param string $correo Correo a buscar
     * @return object|null Cliente encontrado o null
     */
    private static function findByCorreo(string $correo): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_clientes';
        
        $correo_sanitizado = sanitize_email($correo);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, nombre, telefono, correo FROM $table WHERE correo = %s LIMIT 1",
            $correo_sanitizado
        ));
    }
    
    /**
     * Crea un nuevo cliente en la base de datos.
     * 
     * @param string $nombre Nombre del cliente
     * @param string $telefono Teléfono del cliente
     * @param string $correo Correo del cliente
     * @return int ID del cliente creado
     * @throws Exception Si no se puede crear el cliente
     */
    private static function createCliente(string $nombre, string $telefono, string $correo): int {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_clientes';
        
        // Validar que el correo no esté duplicado (si se proporciona)
        if (!empty($correo)) {
            $correo_existente = self::findByCorreo($correo);
            if ($correo_existente) {
                // Si existe por correo, retornar ese ID (no debería llegar aquí, pero por seguridad)
                return (int) $correo_existente->id;
            }
        }
        
        // Preparar datos para inserción
        $insert_data = [
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correo' => $correo,
            'created_at' => current_time('mysql')
        ];
        
        // Insertar cliente
        $result = $wpdb->insert(
            $table,
            $insert_data,
            ['%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            error_log("❌ Error al crear cliente: " . $wpdb->last_error);
            throw new Exception('Error al crear el cliente en la base de datos: ' . $wpdb->last_error);
        }
        
        $cliente_id = $wpdb->insert_id;
        
        if (!$cliente_id) {
            error_log("❌ Cliente creado pero no se obtuvo insert_id");
            throw new Exception('Error: No se pudo obtener el ID del cliente creado.');
        }
        
        error_log("✅ Nuevo cliente creado ID: $cliente_id (nombre: $nombre, tel: $telefono, correo: $correo)");
        
        return (int) $cliente_id;
    }
}

