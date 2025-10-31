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

    // 🔹 Obtener citas futuras solamente (no las que ya pasaron)
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    $now = current_time('mysql'); // Hora actual de WordPress
    
    $reservas = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE fecha >= %s ORDER BY fecha ASC LIMIT 20",
        $now
    ));

    if ($reservas) {
        echo '<h2>Próximas citas</h2>';
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
        echo '<h3 style="margin-top: 30px;">Lista de clientes</h3>';
        echo '<table class="widefat">';
        echo '<thead><tr><th>Nombre</th><th>Teléfono</th><th>Correo</th><th>Fecha de registro</th><th>Total de citas</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($clientes as $cliente) {
            $total_citas = count(aa_get_cliente_reservas($cliente->id, 100));
            
            echo '<tr>';
            echo '<td>' . esc_html($cliente->nombre) . '</td>';
            echo '<td>' . esc_html($cliente->telefono) . '</td>';
            echo '<td>' . esc_html($cliente->correo) . '</td>';
            echo '<td>' . esc_html(date('d/m/Y', strtotime($cliente->created_at))) . '</td>';
            echo '<td>' . $total_citas . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
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
