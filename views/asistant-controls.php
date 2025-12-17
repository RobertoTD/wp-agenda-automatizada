<?php
if (!defined('ABSPATH')) exit;

// 🔹 Pestaña del asistente (visible solo para admin y asistentes)
function aa_render_asistant_panel() {
    $user = wp_get_current_user();
    
    if (!in_array('aa_asistente', $user->roles) && !current_user_can('administrator')) {
        wp_die('No tienes permisos para acceder a esta sección.');
    }

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
    
    // Campo de Duración
    echo '<div class="aa-form-cita-group">';
    echo '<label for="cita-duracion">Duración *</label>';
    echo '<select id="cita-duracion" name="duracion" required>';
    
    // Obtener duración por defecto desde configuración
    $duracion_default = intval(get_option('aa_slot_duration', 60));
    
    $duraciones = [30, 60, 90];
    foreach ($duraciones as $duracion) {
        $selected = ($duracion === $duracion_default) ? ' selected' : '';
        echo '<option value="' . esc_attr($duracion) . '"' . $selected . '>' . esc_html($duracion) . ' min</option>';
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
    // 🔹 TABLA DE PRÓXIMAS CITAS (MODULARIZADA)
    // ===============================
    require_once plugin_dir_path(__FILE__) . 'templates/proximas-citas-template.php';

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
        
        // 🔹 Wrapper para scroll horizontal
        echo '<div class="aa-clientes-table-wrapper">';
        echo '<table class="widefat aa-clientes-table">';
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
        echo '</div>'; // 🔹 Cerrar wrapper
        
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

    // ===============================
    // 🔹 HISTORIAL DE CITAS
    // ===============================
    echo '<hr style="margin: 40px 0;">';
    echo '<h2>📅 Historial de Citas</h2>';
    
    // Filtros de búsqueda
    echo '<div class="aa-historial-filtros">';
    
    echo '<input type="text" id="aa-buscar-historial" placeholder="Buscar por nombre, teléfono o correo...">';
    
    echo '<select id="aa-ordenar-historial">';
    echo '<option value="fecha_desc">Más recientes primero</option>';
    echo '<option value="fecha_asc">Más antiguas primero</option>';
    echo '<option value="cliente_asc">Cliente (A-Z)</option>';
    echo '<option value="cliente_desc">Cliente (Z-A)</option>';
    echo '</select>';
    
    echo '<button id="aa-btn-buscar-historial" class="aa-btn-nuevo-cliente">🔍 Buscar</button>';
    echo '<button id="aa-btn-limpiar-historial" class="aa-btn-cancelar-form">✕ Limpiar</button>';
    
    echo '</div>';
    
    // Tabla de resultados
    echo '<div id="aa-historial-container">';
    echo '<p style="text-align: center; color: #999;">Cargando historial...</p>';
    echo '</div>';
    
    // Paginación
    echo '<div class="aa-paginacion" id="aa-historial-paginacion"></div>';

    echo '</div>';
}