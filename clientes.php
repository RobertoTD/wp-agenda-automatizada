<?php
if (!defined('ABSPATH')) exit;

// ===============================
// 🔹 Crear tabla de clientes
// ===============================
function aa_create_clientes_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        nombre varchar(255) NOT NULL,
        telefono varchar(50) NOT NULL,
        correo varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY correo (correo),
        KEY telefono (telefono)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    error_log("✅ Tabla aa_clientes creada/actualizada");
}

// ===============================
// 🔹 Agregar columna id_cliente a tabla de reservas
// ===============================
function aa_add_cliente_column_to_reservas() {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'id_cliente'",
            DB_NAME,
            $table
        )
    );
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN id_cliente bigint(20) unsigned NULL AFTER correo");
        $wpdb->query("ALTER TABLE $table ADD INDEX idx_id_cliente (id_cliente)");
        error_log("✅ Columna id_cliente agregada a aa_reservas");
    }
}

// ===============================
// 🔹 Buscar o crear cliente (SOLO por correo)
// ===============================
function aa_get_or_create_cliente($nombre, $telefono, $correo) {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';
    
    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table WHERE correo = %s LIMIT 1",
        $correo
    ));
    
    if ($cliente) {
        $wpdb->update(
            $table,
            [
                'nombre' => $nombre,
                'telefono' => $telefono,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $cliente->id]
        );
        error_log("✅ Cliente existente actualizado ID: {$cliente->id} (correo: $correo)");
        return $cliente->id;
    } else {
        $wpdb->insert($table, [
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correo' => $correo,
            'created_at' => current_time('mysql')
        ]);
        $nuevo_id = $wpdb->insert_id;
        error_log("✅ Nuevo cliente creado ID: $nuevo_id (correo: $correo)");
        return $nuevo_id;
    }
}

// ===============================
// 🔹 Obtener información completa del cliente
// ===============================
function aa_get_cliente_by_id($cliente_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $cliente_id
    ));
}

// ===============================
// 🔹 Obtener historial de reservas de un cliente
// ===============================
function aa_get_cliente_reservas($cliente_id, $limit = 10) {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE id_cliente = %d ORDER BY fecha DESC LIMIT %d",
        $cliente_id,
        $limit
    ));
}

// ===============================
// 🔹 Listar todos los clientes
// ===============================
function aa_get_all_clientes($limit = 50, $offset = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $limit,
        $offset
    ));
}

// ===============================
// 🔹 Contar total de clientes
// ===============================
function aa_count_clientes() {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';
    
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
}

// ===============================
// 🔹 AJAX: Crear nuevo cliente
// ===============================
add_action('wp_ajax_aa_crear_cliente', 'aa_ajax_crear_cliente');
function aa_ajax_crear_cliente() {
    check_ajax_referer('aa_crear_cliente');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $nombre = sanitize_text_field($_POST['nombre']);
    $telefono = sanitize_text_field($_POST['telefono']);
    $correo = sanitize_email($_POST['correo']);
    
    if (empty($nombre) || empty($telefono) || empty($correo)) {
        wp_send_json_error(['message' => 'Todos los campos son obligatorios.']);
    }
    
    $cliente_id = aa_get_or_create_cliente($nombre, $telefono, $correo);
    
    if ($cliente_id) {
        wp_send_json_success([
            'message' => 'Cliente guardado correctamente.',
            'cliente_id' => $cliente_id
        ]);
    } else {
        wp_send_json_error(['message' => 'Error al guardar el cliente.']);
    }
}

// ===============================
// 🔹 AJAX: Crear cliente desde cita
// ===============================
add_action('wp_ajax_aa_crear_cliente_desde_cita', 'aa_ajax_crear_cliente_desde_cita');
function aa_ajax_crear_cliente_desde_cita() {
    check_ajax_referer('aa_crear_cliente_desde_cita');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $reserva_id = intval($_POST['reserva_id']);
    $nombre = sanitize_text_field($_POST['nombre']);
    $telefono = sanitize_text_field($_POST['telefono']);
    $correo = sanitize_email($_POST['correo']);
    
    if (!$reserva_id || empty($nombre) || empty($telefono) || empty($correo)) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
    }
    
    $cliente_id = aa_get_or_create_cliente($nombre, $telefono, $correo);
    
    if (!$cliente_id) {
        wp_send_json_error(['message' => 'Error al crear el cliente.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    $updated = $wpdb->update(
        $table,
        ['id_cliente' => $cliente_id],
        ['id' => $reserva_id]
    );
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Error al vincular cliente con la cita.']);
    }
    
    wp_send_json_success([
        'message' => 'Cliente creado y vinculado correctamente.',
        'cliente_id' => $cliente_id
    ]);
}

// ===============================
// 🔹 AJAX: Editar cliente
// ===============================
add_action('wp_ajax_aa_editar_cliente', 'aa_ajax_editar_cliente');
function aa_ajax_editar_cliente() {
    check_ajax_referer('aa_editar_cliente');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $cliente_id = intval($_POST['cliente_id']);
    $nombre = sanitize_text_field($_POST['nombre']);
    $telefono = sanitize_text_field($_POST['telefono']);
    $correo = sanitize_email($_POST['correo']);
    
    if (!$cliente_id || empty($nombre) || empty($telefono) || empty($correo)) {
        wp_send_json_error(['message' => 'Todos los campos son obligatorios.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';
    
    // Verificar si el correo ya existe en otro cliente
    $correo_existente = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE correo = %s AND id != %d LIMIT 1",
        $correo,
        $cliente_id
    ));
    
    if ($correo_existente) {
        wp_send_json_error(['message' => 'El correo electrónico ya está registrado en otro cliente.']);
    }
    
    // Actualizar cliente
    $updated = $wpdb->update(
        $table,
        [
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correo' => $correo,
            'updated_at' => current_time('mysql')
        ],
        ['id' => $cliente_id]
    );
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Error al actualizar el cliente.']);
    }
    
    // Actualizar reservas asociadas
    $table_reservas = $wpdb->prefix . 'aa_reservas';
    $wpdb->update(
        $table_reservas,
        [
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correo' => $correo
        ],
        ['id_cliente' => $cliente_id]
    );
    
    wp_send_json_success([
        'message' => 'Cliente actualizado correctamente.',
        'cliente_id' => $cliente_id
    ]);
}