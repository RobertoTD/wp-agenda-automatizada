<?php
/**
 * Plugin Name: WP Agenda Automatizada
 * Description: Formulario para agendar citas que se conecta con n8n y redirige a WhatsApp.
 * Version: 1.0
 * Author: RobertoTD
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// Registrar scripts y estilos
function wpaa_enqueue_scripts() {
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), null, true);
    wp_enqueue_script('flatpickr-es', 'https://npmcdn.com/flatpickr/dist/l10n/es.js', array('flatpickr-js'), null, true);
    wp_enqueue_style('wpaa-styles', plugin_dir_url(__FILE__) . 'css/styles.css');
    wp_enqueue_script('wpaa-script', plugin_dir_url(__FILE__) . 'js/form-handler.js', array('jquery', 'flatpickr-js'), false, true);
    wp_localize_script('wpaa-script', 'wpaa_vars', [
        'webhook_url' => 'https://deoia.app.n8n.cloud/webhook-test/disponibilidad-citas'
    ]);
}
add_action('wp_enqueue_scripts', 'wpaa_enqueue_scripts');

// Shortcode para insertar el formulario
function wpaa_render_form() {
    ob_start(); ?>
    <form id="agenda-form">
        <label for="servicio">Servicio:</label>
        <select id="servicio" name="servicio" required>
            <option value="">Selecciona</option>
            <option value="Cejas">Cejas</option>
            <option value="Micropigmentación">Micropigmentación</option>
            <option value="Línea">Línea</option>
            <option value="Labios">Labios</option>
            <option value="Otro">Otro</option>
        </select>

        <label for="fecha">Fecha deseada:</label>
        <input type="text" id="fecha" name="fecha" required>

        <label for="nombre">Nombre:</label>
        <input type="text" name="nombre" id="nombre" required>

        <label for="telefono">Teléfono:</label>
        <input type="tel" name="telefono" id="telefono" required>

        <label for="correo">Correo (opcional):</label>
        <input type="email" name="correo" id="correo">

        <button type="submit">Agendar</button>
    </form>
    <div id="respuesta-agenda"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('agenda_automatizada', 'wpaa_render_form');

// Incluir los controles de configuración
require_once plugin_dir_path(__FILE__) . 'admin-controls.php';

// Incluir proxy de disponibilidad (asegura que se encole el JS que hace la petición)
require_once plugin_dir_path(__FILE__) . 'availability-proxy.php';

// Encolar JS del admin
add_action('admin_enqueue_scripts', function($hook) {
    // Solo cargar en la página de configuración de nuestro plugin
    if ($hook === 'toplevel_page_agenda-automatizada-settings') {
        wp_enqueue_script(
            'aa-admin-schedule',
            plugin_dir_url(__FILE__) . 'js/admin-schedule.js',
            [],
            '1.1',
            true
        );
    }

    
});

// Encolar Flatpickr (CSS + JS + traducción ES)
add_action('wp_enqueue_scripts', function() {
    // CSS principal
    wp_enqueue_style(
        'flatpickr-css',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
        [],
        null
    );

    // JS principal
    wp_enqueue_script(
        'flatpickr-js',
        'https://cdn.jsdelivr.net/npm/flatpickr',
        [],
        null,
        true
    );

    // JS de traducción al español
    wp_enqueue_script(
        'flatpickr-locale-es',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js',
        ['flatpickr-js'],
        null,
        true
    );
});
