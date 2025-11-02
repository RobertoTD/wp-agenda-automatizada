<?php
if (!defined('ABSPATH')) exit;

// 🔹 Pestaña del asistente (visible solo para admin y asistentes)
function aa_render_asistant_panel() {
    $user = wp_get_current_user();
    
    if (!in_array('aa_asistente', $user->roles) && !current_user_can('administrator')) {
        wp_die('No tienes permisos para acceder a esta sección.');
    }

    // 🔹 Encolar estilos del panel
    wp_enqueue_style('aa-asistant-panel-styles', plugin_dir_url(__FILE__) . 'css/styles.css');

    echo '<div class="wrap aa-asistant-panel">';
    echo '<h1>🗓️ Panel del Asistente</h1>';
    echo '<p>Bienvenido, <strong>' . esc_html($user->display_name) . '</strong>.</p>';

    // ===============================
    // 🔹 FORMULARIO DE NUEVA CITA
    // ===============================
    echo '<button class="aa-btn-nueva-cita" id="btn-toggle-form-nueva-cita">+ Crear nueva cita</button>';
    
    echo '<div class="aa-form-nueva-cita" id="form-nueva-cita">';
    echo '<h3>📅 Nueva Cita</h3>';
    echo '<form id="form-crear-cita-admin">';
    
    // Campo de Cliente
    echo '<div class="aa-form-cita-group">';
    echo '<label for="cita-cliente">Cliente *</label>';
    echo '<select id="cita-cliente" name="cliente_id" required>';
    echo '<option value="">-- Selecciona un cliente --</option>';
    
    $clientes = aa_get_all_clientes(200);
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
    
    // Campo de Servicio
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
    
    // Campo de Fecha
    echo '<div class="aa-form-cita-group">';
    echo '<label for="cita-fecha">Fecha y hora *</label>';
    echo '<input type="text" id="cita-fecha" name="fecha" required readonly>';
    echo '<div id="slot-container-admin"></div>';
    echo '</div>';
    
    echo '<button type="submit" class="aa-btn-agendar-cita">✓ Agendar Cita</button>';
    echo '<button type="button" class="aa-btn-cancelar-cita-form" id="btn-cancelar-cita-form">Cancelar</button>';
    echo '</form>';
    echo '</div>';

    // ===============================
    // 🔹 TABLA DE PRÓXIMAS CITAS
    // ===============================
    echo '<h2>Próximas citas</h2>';
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    $now = current_time('mysql');
    
    $reservas = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE fecha >= %s ORDER BY fecha ASC LIMIT 20",
        $now
    ));

    if ($reservas) {
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
            
            if ($reserva->estado === 'pending') {
                echo '<button class="aa-btn-confirmar" 
                        data-id="' . $reserva->id . '" 
                        data-nombre="' . esc_attr($reserva->nombre) . '" 
                        data-correo="' . esc_attr($reserva->correo) . '">
                    ✓ Confirmar
                </button> ';
            }
            
            if ($reserva->estado === 'confirmed' || $reserva->estado === 'pending') {
                echo '<button class="aa-btn-cancelar" 
                        data-id="' . $reserva->id . '" 
                        data-nombre="' . esc_attr($reserva->nombre) . '" 
                        data-correo="' . esc_attr($reserva->correo) . '">
                    ✕ Cancelar
                </button> ';
            }
            
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
    
    echo '<button class="aa-btn-nuevo-cliente" id="btn-toggle-form-cliente">+ Crear nuevo cliente</button>';
    
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
    
    // Lista de clientes
    $clientes = aa_get_all_clientes(20);
    
    if ($clientes) {
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
        
        // Modal de edición
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
    
    $updated = $wpdb->update($table, ['estado' => 'confirmed'], ['id' => $id]);
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Error al actualizar en BD.']);
    }
    
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
    
    $updated = $wpdb->update($table, ['estado' => 'cancelled'], ['id' => $id]);
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Error al actualizar en BD.']);
    }
    
    wp_send_json_success(['message' => 'Cita cancelada correctamente.']);
}