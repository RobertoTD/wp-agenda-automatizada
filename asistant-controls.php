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

    // 🔹 Ejemplo de tabla de citas
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    $reservas = $wpdb->get_results("SELECT * FROM $table ORDER BY fecha DESC LIMIT 10");

    if ($reservas) {
        echo '<h2>Últimas citas</h2>';
        echo '<table class="widefat">';
        echo '<thead><tr><th>ID</th><th>Cliente</th><th>Servicio</th><th>Fecha</th><th>Estado</th></tr></thead>';
        echo '<tbody>';
        foreach ($reservas as $reserva) {
            echo '<tr>';
            echo '<td>' . esc_html($reserva->id) . '</td>';
            echo '<td>' . esc_html($reserva->nombre) . '</td>';
            echo '<td>' . esc_html($reserva->servicio) . '</td>';
            echo '<td>' . esc_html($reserva->fecha) . '</td>';
            echo '<td>' . esc_html($reserva->estado) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No hay citas registradas.</p>';
    }

    echo '</div>';
}
