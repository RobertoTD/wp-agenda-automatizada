<?php
/**
 * Iframe Test - Sistema de UI aislada en admin WordPress
 * 
 * Este archivo:
 * 1. Registra una página en el menú del admin
 * 2. Renderiza un iframe que apunta a admin-post.php
 * 3. El handler de admin-post devuelve HTML completo aislado
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// ================================
// 🔹 REGISTRAR PÁGINA EN EL MENÚ ADMIN
// ================================
add_action('admin_menu', 'aa_register_iframe_test_page');

function aa_register_iframe_test_page() {
    add_submenu_page(
        'agenda-automatizada-settings',  // Parent: Menú principal del plugin
        'Configuración de Agenda Automatizada',  // Título de la página
        'Configuración de Agenda Automatizada',  // Título del menú
        'manage_options',                // Capacidad requerida
        'aa-iframe-test',                // Slug único
        'aa_render_iframe_test_page'     // Callback de renderizado
    );
}

// ================================
// 🔹 RENDERIZAR PÁGINA CON IFRAME
// ================================
function aa_render_iframe_test_page() {
    // Construir URL del iframe usando admin-post.php
    $iframe_url = admin_url('admin-post.php?action=aa_iframe_content');
    
    ?>
    <div class="wrap">
        <iframe 
            id="aa-isolated-iframe"
            src="<?php echo esc_url($iframe_url); ?>"
            style="width: 100%; height: 800px; border: none;"
        ></iframe>
    </div>
    <?php
}

// ================================
// 🔹 HANDLER: Contenido del iframe (admin-post.php)
// ================================
// Para usuarios logueados
add_action('admin_post_aa_iframe_content', 'aa_handle_iframe_content');

// Para usuarios no logueados (opcional, quitar si no se necesita)
// add_action('admin_post_nopriv_aa_iframe_content', 'aa_handle_iframe_content');

function aa_handle_iframe_content() {
    // Verificar que el usuario tiene permisos
    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado', 'Error', ['response' => 403]);
    }
    
    // Load the admin UI entry point
    $ui_path = plugin_dir_path(__FILE__) . 'ui/index.php';
    
    if (file_exists($ui_path)) {
        require_once $ui_path;
    } else {
        wp_die('UI no encontrada', 'Error', ['response' => 404]);
    }
    
    // Note: die() is called inside the UI layout to prevent WordPress output
}
