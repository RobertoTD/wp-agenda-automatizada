<?php
if (!defined('ABSPATH')) exit;

// ===============================
// 🔹 Normalización y validación de teléfono (función central)
// ===============================

/**
 * Normaliza un teléfono al formato canónico: solo dígitos con código de país.
 * Soportados: 52 (MX 12 dígitos), 1 (US 11 dígitos), 34 (ES 11 dígitos).
 * Compatibilidad: 10 dígitos → asume México (52); 11 dígitos con 1 o 34 al inicio → acepta.
 *
 * @param string $telefono Valor crudo (p. ej. 525512345678, 5512345678 o con espacios/guiones)
 * @return string|WP_Error Teléfono canónico (solo dígitos) o WP_Error
 */
function aa_normalize_telefono($telefono) {
    $digits = preg_replace('/\D/', '', $telefono);

    // Formato canónico: 52 + 10 (12), 1 + 10 (11), 34 + 9 (11)
    if (strlen($digits) === 12 && strpos($digits, '52') === 0) {
        return $digits;
    }
    if (strlen($digits) === 11 && strpos($digits, '34') === 0) {
        return $digits;
    }
    if (strlen($digits) === 11 && strpos($digits, '1') === 0) {
        return $digits;
    }

    // Compatibilidad reserva pública: 10 dígitos → México
    if (strlen($digits) === 10) {
        return '52' . $digits;
    }

    return new WP_Error(
        'telefono_invalido',
        'Teléfono inválido. Debe incluir código de país (52/1/34) y longitud válida.'
    );
}

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
        correo varchar(255) NOT NULL DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY telefono (telefono),
        KEY correo (correo)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    error_log("✅ Tabla aa_clientes creada/actualizada");
}

/**
 * Migración: correo ahora es opcional en aa_clientes.
 * - Quita UNIQUE KEY en correo.
 * - Pone DEFAULT '' en la columna correo.
 * Se ejecuta una sola vez en admin_init.
 */
function aa_migrate_correo_optional() {
    if (get_option('aa_correo_optional_migrated')) return;

    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';

    // 1. Verificar si existe UNIQUE KEY 'correo'
    $indexes = $wpdb->get_results(
        "SHOW INDEX FROM $table WHERE Key_name = 'correo' AND Non_unique = 0"
    );

    if (!empty($indexes)) {
        $wpdb->query("ALTER TABLE $table DROP INDEX correo");
        error_log("✅ [Migración] UNIQUE KEY 'correo' eliminado de aa_clientes");
    }

    // 2. Modificar columna para permitir DEFAULT ''
    $wpdb->query("ALTER TABLE $table MODIFY correo varchar(255) NOT NULL DEFAULT ''");

    // 3. Agregar índice normal (no único) si no existe
    $idx = $wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = 'correo'");
    if (empty($idx)) {
        $wpdb->query("ALTER TABLE $table ADD INDEX correo (correo)");
    }

    update_option('aa_correo_optional_migrated', true);
    error_log("✅ [Migración] correo ahora es opcional en aa_clientes");
}
add_action('admin_init', 'aa_migrate_correo_optional');

/**
 * Migración: teléfono es identidad única en aa_clientes.
 * - Normaliza teléfonos existentes (solo dígitos).
 * - Resuelve duplicados por teléfono (mantiene canonical, re-apunta reservas).
 * - Agrega UNIQUE KEY telefono.
 * Se ejecuta una sola vez en admin_init.
 */
function aa_migrate_telefono_unique() {
    if (get_option('aa_telefono_unique_migrated')) return;

    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';
    $table_reservas = $wpdb->prefix . 'aa_reservas';

    error_log("🔄 [Migración telefono_unique] Iniciando...");

    // 1. Normalizar todos los teléfonos existentes (solo dígitos)
    $all_clients = $wpdb->get_results("SELECT id, telefono FROM $table ORDER BY id ASC");
    foreach ($all_clients as $client) {
        $normalized = preg_replace('/\D/', '', $client->telefono);
        if ($normalized !== $client->telefono) {
            $wpdb->update($table, ['telefono' => $normalized], ['id' => $client->id]);
            error_log("   Normalizado: ID {$client->id} '{$client->telefono}' → '{$normalized}'");
        }
    }

    // 2. Detectar y resolver duplicados por teléfono normalizado
    $duplicates = $wpdb->get_results(
        "SELECT telefono, GROUP_CONCAT(id ORDER BY 
            CASE WHEN correo != '' THEN 0 ELSE 1 END, id ASC) AS ids,
            COUNT(*) AS cnt
         FROM $table 
         GROUP BY telefono 
         HAVING cnt > 1"
    );

    foreach ($duplicates as $dup) {
        $ids = array_map('intval', explode(',', $dup->ids));
        $canonical_id = $ids[0]; // El primero: tiene correo y/o es más antiguo
        $to_remove = array_slice($ids, 1);

        error_log("   Duplicado tel '{$dup->telefono}': canonical ID {$canonical_id}, eliminando IDs: " . implode(',', $to_remove));

        // Re-apuntar reservas de los duplicados al canonical
        foreach ($to_remove as $old_id) {
            $wpdb->update($table_reservas, ['id_cliente' => $canonical_id], ['id_cliente' => $old_id]);
        }

        // Eliminar registros duplicados
        $placeholders = implode(',', array_fill(0, count($to_remove), '%d'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE id IN ($placeholders)",
            ...$to_remove
        ));
    }

    // 3. Quitar índice normal de telefono si existe, antes de poner UNIQUE
    $existing_idx = $wpdb->get_results(
        "SHOW INDEX FROM $table WHERE Key_name = 'telefono'"
    );
    if (!empty($existing_idx)) {
        $wpdb->query("ALTER TABLE $table DROP INDEX telefono");
    }

    // 4. Agregar UNIQUE KEY
    $wpdb->query("ALTER TABLE $table ADD UNIQUE KEY telefono (telefono)");

    update_option('aa_telefono_unique_migrated', true);
    error_log("✅ [Migración telefono_unique] Completada. UNIQUE KEY telefono agregado.");
}
add_action('admin_init', 'aa_migrate_telefono_unique');

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
// 🔹 Buscar o crear cliente (teléfono es identidad única)
// ===============================
/**
 * Busca un cliente por teléfono (identidad única).
 * - Si existe: devuelve su ID sin modificar datos.
 * - Si no existe: crea uno nuevo.
 * NOTA: Para flujos admin (crear/editar) se usan los AJAX handlers directamente.
 *       Esta función se mantiene solo como legacy/interna.
 *       Los flujos frontend usan ClienteService::getOrCreate().
 *
 * @param string $nombre
 * @param string $telefono  Ya normalizado (10 dígitos)
 * @param string $correo    Opcional
 * @return int|WP_Error     ID del cliente o WP_Error
 */
function aa_get_or_create_cliente($nombre, $telefono, $correo) {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';

    // 1. Buscar por teléfono (identidad única)
    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT id, correo FROM $table WHERE telefono = %s LIMIT 1",
        $telefono
    ));

    if ($cliente) {
        error_log("✅ Cliente existente por teléfono ID: {$cliente->id} (tel: $telefono)");
        return (int) $cliente->id;
    }

    // 2. Crear nuevo cliente
    $result = $wpdb->insert($table, [
        'nombre' => $nombre,
        'telefono' => $telefono,
        'correo' => $correo,
        'created_at' => current_time('mysql')
    ]);

    if ($result === false) {
        error_log("❌ Error al crear cliente: " . $wpdb->last_error);
        return new WP_Error('db_error', 'Error al crear el cliente: ' . $wpdb->last_error);
    }

    $nuevo_id = $wpdb->insert_id;
    error_log("✅ Nuevo cliente creado ID: $nuevo_id (tel: $telefono, correo: " . ($correo ?: 'sin correo') . ")");
    return (int) $nuevo_id;
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
// 🔹 Buscar clientes con paginación (para módulo iframe)
// Ordena por total_citas DESC por defecto
// Usa exclusivamente aa_clientes como fuente de verdad
// ===============================
function aa_search_clientes($query = '', $limit = 10, $offset = 0) {
    global $wpdb;
    $table_clientes = $wpdb->prefix . 'aa_clientes';
    $table_reservas = $wpdb->prefix . 'aa_reservas';
    
    // Sanitizar parámetros
    $limit = absint($limit);
    $offset = absint($offset);
    $query = sanitize_text_field($query);
    
    // Construir WHERE clause si hay query (solo busca en aa_clientes)
    if (!empty($query)) {
        $search_term = '%' . $wpdb->esc_like($query) . '%';
        $where = $wpdb->prepare(
            "WHERE c.nombre LIKE %s OR c.correo LIKE %s OR c.telefono LIKE %s",
            $search_term,
            $search_term,
            $search_term
        );
    } else {
        $where = '';
    }
    
    // Query usando exclusivamente aa_clientes como fuente de verdad
    // Calcula total_citas mediante relación con aa_reservas usando id_cliente
    $sql = "SELECT c.id, c.nombre, c.telefono, c.correo, c.created_at, COUNT(r.id) as total_citas 
            FROM $table_clientes c 
            LEFT JOIN $table_reservas r ON c.id = r.id_cliente 
            $where 
            GROUP BY c.id, c.nombre, c.telefono, c.correo, c.created_at
            ORDER BY total_citas DESC, c.created_at DESC 
            LIMIT %d OFFSET %d";
    
    $prepared_sql = $wpdb->prepare($sql, $limit, $offset);
    $results = $wpdb->get_results($prepared_sql);
    
    return $results ? $results : [];
}

// ===============================
// 🔹 AJAX: Buscar clientes (para módulo iframe)
// ===============================
add_action('wp_ajax_aa_search_clientes', 'aa_ajax_search_clientes');
function aa_ajax_search_clientes() {
    // Verificar permisos
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    // Obtener parámetros
    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;
    $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
    
    // Validar límites
    if ($limit < 1 || $limit > 100) {
        $limit = 10;
    }
    
    // Buscar clientes (ya incluye total_citas del JOIN)
    $clients_raw = aa_search_clientes($query, $limit, $offset);
    
    // Construir array de datos para cada cliente
    $clients_data = [];
    foreach ($clients_raw as $cliente) {
        $clients_data[] = [
            'id' => (int) $cliente->id,
            'nombre' => $cliente->nombre,
            'telefono' => $cliente->telefono,
            'correo' => $cliente->correo,
            'created_at' => date('d/m/Y', strtotime($cliente->created_at)),
            'total_citas' => (int) $cliente->total_citas
        ];
    }
    
    // Calcular si hay más resultados
    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';
    
    // Contar total de resultados con el mismo query
    if (!empty($query)) {
        $search_term = '%' . $wpdb->esc_like($query) . '%';
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE nombre LIKE %s OR correo LIKE %s OR telefono LIKE %s",
            $search_term,
            $search_term,
            $search_term
        );
        $total = (int) $wpdb->get_var($count_sql);
    } else {
        $total = aa_count_clientes();
    }
    
    // Calcular paginación
    $has_next = ($offset + $limit) < $total;
    $has_prev = $offset > 0;
    
    // Preparar respuesta
    $response = [
        'clients' => $clients_data,
        'offset' => $offset,
        'limit' => $limit,
        'has_next' => $has_next,
        'has_prev' => $has_prev,
        'total' => $total
    ];
    
    wp_send_json_success($response);
}

// ===============================
// 🔹 AJAX: Crear nuevo cliente (admin)
// ===============================
add_action('wp_ajax_aa_crear_cliente', 'aa_ajax_crear_cliente');
function aa_ajax_crear_cliente() {
    check_ajax_referer('aa_crear_cliente');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $nombre = sanitize_text_field($_POST['nombre']);
    $telefono_raw = sanitize_text_field($_POST['telefono']);
    $correo = isset($_POST['correo']) ? sanitize_email($_POST['correo']) : '';
    
    if (empty($nombre) || empty($telefono_raw)) {
        wp_send_json_error(['message' => 'Nombre y teléfono son obligatorios.']);
    }

    // Normalizar teléfono
    $telefono = aa_normalize_telefono($telefono_raw);
    if (is_wp_error($telefono)) {
        wp_send_json_error(['message' => $telefono->get_error_message()]);
    }

    // Verificar unicidad de teléfono
    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';
    $existente = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE telefono = %s LIMIT 1",
        $telefono
    ));
    if ($existente) {
        wp_send_json_error(['message' => "El cliente con teléfono $telefono ya existe."]);
    }

    // Crear cliente
    $result = $wpdb->insert($table, [
        'nombre'     => $nombre,
        'telefono'   => $telefono,
        'correo'     => $correo,
        'created_at' => current_time('mysql')
    ]);

    if ($result === false) {
        wp_send_json_error(['message' => 'Error al guardar el cliente.']);
    }

    $cliente_id = $wpdb->insert_id;
    wp_send_json_success([
        'message' => 'Cliente guardado correctamente.',
        'cliente_id' => $cliente_id,
        'cliente' => [
            'id' => $cliente_id,
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correo' => $correo
        ]
    ]);
}

// ===============================
// 🔹 AJAX: Crear cliente desde cita (admin)
// ===============================
add_action('wp_ajax_aa_crear_cliente_desde_cita', 'aa_ajax_crear_cliente_desde_cita');
function aa_ajax_crear_cliente_desde_cita() {
    check_ajax_referer('aa_crear_cliente_desde_cita');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $reserva_id = intval($_POST['reserva_id']);
    $nombre = sanitize_text_field($_POST['nombre']);
    $telefono_raw = sanitize_text_field($_POST['telefono']);
    $correo = isset($_POST['correo']) ? sanitize_email($_POST['correo']) : '';
    
    if (!$reserva_id || empty($nombre) || empty($telefono_raw)) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
    }

    // Normalizar teléfono
    $telefono = aa_normalize_telefono($telefono_raw);
    if (is_wp_error($telefono)) {
        wp_send_json_error(['message' => $telefono->get_error_message()]);
    }

    // Buscar o crear cliente (teléfono como identidad)
    $cliente_id = aa_get_or_create_cliente($nombre, $telefono, $correo);
    if (is_wp_error($cliente_id)) {
        wp_send_json_error(['message' => $cliente_id->get_error_message()]);
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
// 🔹 AJAX: Editar cliente (admin)
// ===============================
add_action('wp_ajax_aa_editar_cliente', 'aa_ajax_editar_cliente');
function aa_ajax_editar_cliente() {
    check_ajax_referer('aa_editar_cliente');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $cliente_id = intval($_POST['cliente_id']);
    $nombre = sanitize_text_field($_POST['nombre']);
    $telefono_raw = sanitize_text_field($_POST['telefono']);
    $correo = isset($_POST['correo']) ? sanitize_email($_POST['correo']) : '';
    
    if (!$cliente_id || empty($nombre) || empty($telefono_raw)) {
        wp_send_json_error(['message' => 'Nombre y teléfono son obligatorios.']);
    }

    // Normalizar teléfono
    $telefono = aa_normalize_telefono($telefono_raw);
    if (is_wp_error($telefono)) {
        wp_send_json_error(['message' => $telefono->get_error_message()]);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_clientes';

    // Verificar que el teléfono no esté usado por OTRO cliente
    $tel_existente = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE telefono = %s AND id != %d LIMIT 1",
        $telefono,
        $cliente_id
    ));
    if ($tel_existente) {
        wp_send_json_error(['message' => "El teléfono $telefono ya está registrado en otro cliente."]);
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