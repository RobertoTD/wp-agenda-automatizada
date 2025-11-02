<?php
if (!defined('ABSPATH')) exit;

// 🔹 Pestaña del asistente (visible solo para admin y asistentes)
function aa_render_asistant_panel() {
    $user = wp_get_current_user();
    
    if (!in_array('aa_asistente', $user->roles) && !current_user_can('administrator')) {
        wp_die('No tienes permisos para acceder a esta sección.');
    }

    echo '<div class="wrap">';
    echo '<h1>🗓️ Panel del Asistente</h1>';
    echo '<p>Bienvenido, <strong>' . esc_html($user->display_name) . '</strong>.</p>';
    echo '<p>Aquí se mostrarán las citas, clientes, confirmaciones y reportes.</p>';

    // ===============================
    // 🔹 FORMULARIO DE NUEVA CITA
    // ===============================
    echo '<style>
        .aa-btn-nueva-cita { background: #3498db; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
        .aa-btn-nueva-cita:hover { background: #2980b9; }
        .aa-form-nueva-cita { display: none; background: #f9f9f9; padding: 25px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 25px; max-width: 600px; }
        .aa-form-nueva-cita.visible { display: block; }
        .aa-form-cita-group { margin-bottom: 20px; }
        .aa-form-cita-group label { display: block; font-weight: bold; margin-bottom: 8px; color: #333; }
        .aa-form-cita-group select,
        .aa-form-cita-group input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .aa-form-cita-group #slot-container-admin { margin-top: 10px; }
        .aa-btn-agendar-cita { background: #27ae60; color: white; border: none; padding: 12px 25px; cursor: pointer; border-radius: 4px; font-size: 15px; }
        .aa-btn-agendar-cita:hover { background: #229954; }
        .aa-btn-cancelar-cita-form { background: #95a5a6; color: white; border: none; padding: 12px 25px; cursor: pointer; border-radius: 4px; margin-left: 10px; font-size: 15px; }
        .aa-btn-cancelar-cita-form:hover { background: #7f8c8d; }
        .aa-btn-crear-cliente-desde-cita { background: #9b59b6; color: white; border: none; padding: 5px 12px; cursor: pointer; border-radius: 3px; font-size: 12px; }
        .aa-btn-crear-cliente-desde-cita:hover { background: #8e44ad; }
    </style>';
    
    echo '<button class="aa-btn-nueva-cita" id="btn-toggle-form-nueva-cita">+ Crear nueva cita</button>';
    
    echo '<div class="aa-form-nueva-cita" id="form-nueva-cita">';
    echo '<h3>📅 Nueva Cita</h3>';
    echo '<form id="form-crear-cita-admin">';
    
    // 🔹 Campo de Cliente (select con búsqueda)
    echo '<div class="aa-form-cita-group">';
    echo '<label for="cita-cliente">Cliente *</label>';
    echo '<select id="cita-cliente" name="cliente_id" required>';
    echo '<option value="">-- Selecciona un cliente --</option>';
    
    $clientes = aa_get_all_clientes(200); // Obtener todos los clientes
    foreach ($clientes as $cliente) {
        echo '<option value="' . esc_attr($cliente->id) . '" 
                data-nombre="' . esc_attr($cliente->nombre) . '"
                data-telefono="' . esc_attr($cliente->telefono) . '"
                data-correo="' . esc_attr($cliente->correo) . '">';
        echo esc_html($cliente->nombre) . ' (' . esc_html($cliente->telefono) . ')';
        echo '</option>';
    }
    echo '</select>';
    echo '</div>';
    
    // 🔹 Campo de Motivo/Servicio
    echo '<div class="aa-form-cita-group">';
    echo '<label for="cita-servicio">Motivo de la cita *</label>';
    echo '<select id="cita-servicio" name="servicio" required>';
    
    $motivos_json = get_option('aa_google_motivo', json_encode(['Cita general']));
    $motivos = json_decode($motivos_json, true);
    
    if (is_array($motivos) && !empty($motivos)) {
        foreach ($motivos as $motivo) {
            echo '<option value="' . esc_attr($motivo) . '">' . esc_html($motivo) . '</option>';
        }
    } else {
        echo '<option value="Cita general">Cita general</option>';
    }
    echo '</select>';
    echo '</div>';
    
    // 🔹 Campo de Fecha
    echo '<div class="aa-form-cita-group">';
    echo '<label for="cita-fecha">Fecha y hora *</label>';
    echo '<input type="text" id="cita-fecha" name="fecha" required readonly>';
    echo '<div id="slot-container-admin"></div>';
    echo '</div>';
    
    echo '<button type="submit" class="aa-btn-agendar-cita">✓ Agendar Cita</button>';
    echo '<button type="button" class="aa-btn-cancelar-cita-form" id="btn-cancelar-cita-form">Cancelar</button>';
    echo '</form>';
    echo '</div>';

    // 🔹 Obtener citas futuras solamente (no las que ya pasaron)
    echo '<h2>Próximas citas</h2>';
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    $now = current_time('mysql'); // Hora actual de WordPress
    
    $reservas = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE fecha >= %s ORDER BY fecha ASC LIMIT 20",
        $now
    ));

    if ($reservas) {
        echo '<style>
            .aa-btn-confirmar { background: #2ecc71; color: white; border: none; padding: 5px 12px; cursor: pointer; border-radius: 3px; }
            .aa-btn-confirmar:hover { background: #27ae60; }
            .aa-btn-cancelar { background: #e74c3c; color: white; border: none; padding: 5px 12px; cursor: pointer; border-radius: 3px; }
            .aa-btn-cancelar:hover { background: #c0392b; }
            .aa-estado-confirmed { color: #27ae60; font-weight: bold; }
            .aa-estado-pending { color: #f39c12; font-weight: bold; }
            .aa-estado-cancelled { color: #e74c3c; font-weight: bold; }
            .aa-btn-nuevo-cliente { background: #3498db; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 3px; margin-bottom: 15px; }
            .aa-btn-nuevo-cliente:hover { background: #2980b9; }
            .aa-form-nuevo-cliente { display: none; background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; }
            .aa-form-nuevo-cliente.visible { display: block; }
            .aa-form-group { margin-bottom: 15px; }
            .aa-form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
            .aa-form-group input { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
            .aa-btn-guardar-cliente { background: #2ecc71; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 3px; }
            .aa-btn-guardar-cliente:hover { background: #27ae60; }
            .aa-btn-cancelar-form { background: #95a5a6; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 3px; margin-left: 10px; }
            .aa-btn-cancelar-form:hover { background: #7f8c8d; }
        </style>';
        
        echo '<table class="widefat">';
        echo '<thead><tr><th>Cliente</th><th>Teléfono</th><th>Servicio</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($reservas as $reserva) {
            $estado_class = 'aa-estado-' . strtolower($reserva->estado);
            $estado_texto = $reserva->estado === 'confirmed' ? 'Confirmada' : 
                           ($reserva->estado === 'pending' ? 'Pendiente' : 'Cancelada');
            
            echo '<tr>';
            echo '<td>' . esc_html($reserva->nombre) . '</td>';
            echo '<td>' . esc_html($reserva->telefono) . '</td>';
            echo '<td>' . esc_html($reserva->servicio) . '</td>';
            echo '<td>' . esc_html(date('d/m/Y H:i', strtotime($reserva->fecha))) . '</td>';
            echo '<td class="' . $estado_class . '">' . $estado_texto . '</td>';
            echo '<td>';
            
            // 🔹 Botón CONFIRMAR (solo para citas pendientes)
            if ($reserva->estado === 'pending') {
                echo '<button class="aa-btn-confirmar" 
                        data-id="' . $reserva->id . '" 
                        data-nombre="' . esc_attr($reserva->nombre) . '" 
                        data-correo="' . esc_attr($reserva->correo) . '">
                    ✓ Confirmar
                </button> ';
            }
            
            // 🔹 Botón CANCELAR (solo para citas confirmadas o pendientes)
            if ($reserva->estado === 'confirmed' || $reserva->estado === 'pending') {
                echo '<button class="aa-btn-cancelar" 
                        data-id="' . $reserva->id . '" 
                        data-nombre="' . esc_attr($reserva->nombre) . '" 
                        data-correo="' . esc_attr($reserva->correo) . '">
                    ✕ Cancelar
                </button> ';
            }
            
            // 🔹 Botón "+ CLIENTE" (solo si id_cliente es NULL)
            if (empty($reserva->id_cliente)) {
                echo '<button class="aa-btn-crear-cliente-desde-cita" 
                        data-reserva-id="' . $reserva->id . '" 
                        data-nombre="' . esc_attr($reserva->nombre) . '" 
                        data-telefono="' . esc_attr($reserva->telefono) . '" 
                        data-correo="' . esc_attr($reserva->correo) . '">
                    + Cliente
                </button>';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
    } else {
        echo '<p>No hay citas próximas registradas.</p>';
    }

    // ===============================
    // 🔹 SECCIÓN DE CLIENTES
    // ===============================
    echo '<hr style="margin: 40px 0;">';
    echo '<h2>👥 Clientes</h2>';
    
    // Botón para mostrar formulario
    echo '<button class="aa-btn-nuevo-cliente" id="btn-toggle-form-cliente">+ Crear nuevo cliente</button>';
    
    // Formulario oculto
    echo '<div class="aa-form-nuevo-cliente" id="form-nuevo-cliente">';
    echo '<h3>Nuevo Cliente</h3>';
    echo '<form id="form-crear-cliente">';
    
    echo '<div class="aa-form-group">';
    echo '<label for="cliente-nombre">Nombre completo *</label>';
    echo '<input type="text" id="cliente-nombre" name="nombre" required>';
    echo '</div>';
    
    echo '<div class="aa-form-group">';
    echo '<label for="cliente-telefono">Teléfono *</label>';
    echo '<input type="tel" id="cliente-telefono" name="telefono" required>';
    echo '</div>';
    
    echo '<div class="aa-form-group">';
    echo '<label for="cliente-correo">Correo electrónico *</label>';
    echo '<input type="email" id="cliente-correo" name="correo" required>';
    echo '</div>';
    
    echo '<button type="submit" class="aa-btn-guardar-cliente">Guardar Cliente</button>';
    echo '<button type="button" class="aa-btn-cancelar-form" id="btn-cancelar-form">Cancelar</button>';
    echo '</form>';
    echo '</div>';
    
    // Listar clientes existentes
    $clientes = aa_get_all_clientes(20);
    
    if ($clientes) {
        echo '<style>
            .aa-btn-editar-cliente { background: #3498db; color: white; border: none; padding: 5px 12px; cursor: pointer; border-radius: 3px; font-size: 12px; }
            .aa-btn-editar-cliente:hover { background: #2980b9; }
            .aa-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; }
            .aa-modal-overlay.visible { display: flex; align-items: center; justify-content: center; }
            .aa-modal-content { background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .aa-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .aa-modal-header h3 { margin: 0; }
            .aa-modal-close { background: transparent; border: none; font-size: 24px; cursor: pointer; color: #666; }
            .aa-modal-close:hover { color: #333; }
        </style>';
        
        echo '<h3 style="margin-top: 30px;">Lista de clientes</h3>';
        echo '<table class="widefat">';
        echo '<thead><tr><th>Nombre</th><th>Teléfono</th><th>Correo</th><th>Fecha de registro</th><th>Total de citas</th><th>Acciones</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($clientes as $cliente) {
            $total_citas = count(aa_get_cliente_reservas($cliente->id, 100));
            
            echo '<tr>';
            echo '<td>' . esc_html($cliente->nombre) . '</td>';
            echo '<td>' . esc_html($cliente->telefono) . '</td>';
            echo '<td>' . esc_html($cliente->correo) . '</td>';
            echo '<td>' . esc_html(date('d/m/Y', strtotime($cliente->created_at))) . '</td>';
            echo '<td>' . $total_citas . '</td>';
            echo '<td>';
            echo '<button class="aa-btn-editar-cliente" 
                    data-id="' . $cliente->id . '"
                    data-nombre="' . esc_attr($cliente->nombre) . '"
                    data-telefono="' . esc_attr($cliente->telefono) . '"
                    data-correo="' . esc_attr($cliente->correo) . '">
                ✏️ Editar
            </button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // 🔹 Modal para editar cliente
        echo '<div class="aa-modal-overlay" id="modal-editar-cliente">';
        echo '<div class="aa-modal-content">';
        echo '<div class="aa-modal-header">';
        echo '<h3>✏️ Editar Cliente</h3>';
        echo '<button class="aa-modal-close" id="btn-cerrar-modal">&times;</button>';
        echo '</div>';
        echo '<form id="form-editar-cliente">';
        echo '<input type="hidden" id="editar-cliente-id" name="cliente_id">';
        
        echo '<div class="aa-form-group">';
        echo '<label for="editar-cliente-nombre">Nombre completo *</label>';
        echo '<input type="text" id="editar-cliente-nombre" name="nombre" required>';
        echo '</div>';
        
        echo '<div class="aa-form-group">';
        echo '<label for="editar-cliente-telefono">Teléfono *</label>';
        echo '<input type="tel" id="editar-cliente-telefono" name="telefono" required>';
        echo '</div>';
        
        echo '<div class="aa-form-group">';
        echo '<label for="editar-cliente-correo">Correo electrónico *</label>';
        echo '<input type="email" id="editar-cliente-correo" name="correo" required>';
        echo '</div>';
        
        echo '<button type="submit" class="aa-btn-guardar-cliente">💾 Guardar Cambios</button>';
        echo '<button type="button" class="aa-btn-cancelar-form" id="btn-cancelar-edicion">Cancelar</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        
    } else {
        echo '<p>No hay clientes registrados.</p>';
    }

    echo '</div>';
}

// ===============================
// 🔹 AJAX: Confirmar cita
// ===============================
add_action('wp_ajax_aa_confirmar_cita', 'aa_ajax_confirmar_cita');
function aa_ajax_confirmar_cita() {
    check_ajax_referer('aa_confirmar_cita');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $id = intval($_POST['id']);
    if (!$id) {
        wp_send_json_error(['message' => 'ID inválido.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // Actualizar estado en BD
    $updated = $wpdb->update($table, ['estado' => 'confirmed'], ['id' => $id]);
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Error al actualizar en BD.']);
    }
    
    // 🔹 TODO: Aquí puedes enviar correo de confirmación al backend
    // $reserva = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    // aa_enviar_correo_confirmacion($reserva);
    
    wp_send_json_success(['message' => 'Cita confirmada correctamente.']);
}

// ===============================
// 🔹 AJAX: Cancelar cita
// ===============================
add_action('wp_ajax_aa_cancelar_cita', 'aa_ajax_cancelar_cita');
function aa_ajax_cancelar_cita() {
    check_ajax_referer('aa_cancelar_cita');
    
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
    
    $id = intval($_POST['id']);
    if (!$id) {
        wp_send_json_error(['message' => 'ID inválido.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // Actualizar estado en BD
    $updated = $wpdb->update($table, ['estado' => 'cancelled'], ['id' => $id]);
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Error al actualizar en BD.']);
    }
    
    // 🔹 TODO: Aquí puedes enviar correo de cancelación al backend
    // $reserva = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    // aa_enviar_correo_cancelacion($reserva);
    
    wp_send_json_success(['message' => 'Cita cancelada correctamente.']);
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
    
    // Usar función existente para crear/actualizar cliente
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
    
    // 🔹 Crear o buscar cliente
    $cliente_id = aa_get_or_create_cliente($nombre, $telefono, $correo);
    
    if (!$cliente_id) {
        wp_send_json_error(['message' => 'Error al crear el cliente.']);
    }
    
    // 🔹 Actualizar la reserva con el id_cliente
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
    
    // 🔹 Verificar si el nuevo correo ya existe en otro cliente
    $correo_existente = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE correo = %s AND id != %d LIMIT 1",
        $correo,
        $cliente_id
    ));
    
    if ($correo_existente) {
        wp_send_json_error(['message' => 'El correo electrónico ya está registrado en otro cliente.']);
    }
    
    // 🔹 Actualizar cliente
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
    
    // 🔹 Actualizar también las reservas asociadas (opcional pero recomendado)
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
