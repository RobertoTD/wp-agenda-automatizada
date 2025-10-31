<?php
if (!defined('ABSPATH')) exit;

// ===============================
// 🔹 Crear el rol personalizado al activar el plugin
// ===============================
function aa_create_assistant_role() {
    add_role('aa_asistente', 'Asistente de Agenda', [
        'read' => true, // ✅ CRÍTICO: Permite acceso al backend
        'aa_view_panel' => true, // Capacidad personalizada
    ]);
}
register_activation_hook(plugin_dir_path(__FILE__) . 'wp-agenda-automatizada.php', 'aa_create_assistant_role');

// ===============================
// 🔹 Ocultar menús del admin para asistentes
// ===============================
add_action('admin_menu', function() {
    $user = wp_get_current_user();
    
    if (in_array('aa_asistente', $user->roles)) {
        // 🔹 Remover todos los menús predeterminados
        remove_menu_page('index.php');                  // Dashboard
        remove_menu_page('edit.php');                   // Posts
        remove_menu_page('upload.php');                 // Media
        remove_menu_page('edit.php?post_type=page');    // Pages
        remove_menu_page('edit-comments.php');          // Comments
        remove_menu_page('themes.php');                 // Appearance
        remove_menu_page('plugins.php');                // Plugins
        remove_menu_page('users.php');                  // Users
        remove_menu_page('tools.php');                  // Tools
        remove_menu_page('options-general.php');        // Settings
        remove_menu_page('agenda-automatizada-settings'); // ✅ Ocultar menú principal del plugin
    }
}, 999);

// ===============================
// 🔹 Redirigir asistentes al panel cuando entren al admin
// ===============================
add_action('admin_init', function() {
    $user = wp_get_current_user();
    
    if (in_array('aa_asistente', $user->roles)) {
        global $pagenow;
        
        // ✅ Permitir acceso solo a su panel y a admin-ajax.php
        $allowed_pages = ['admin.php', 'admin-ajax.php', 'admin-post.php'];
        
        if (!in_array($pagenow, $allowed_pages)) {
            wp_redirect(admin_url('admin.php?page=aa_asistant_panel'));
            exit;
        }
        
        // ✅ Si está en admin.php pero NO en su panel, redirigir
        if ($pagenow === 'admin.php' && 
            (!isset($_GET['page']) || $_GET['page'] !== 'aa_asistant_panel')) {
            wp_redirect(admin_url('admin.php?page=aa_asistant_panel'));
            exit;
        }
    }
});

// ===============================
// 🔹 Ocultar barra superior de WordPress para asistentes
// ===============================
add_action('after_setup_theme', function() {
    $user = wp_get_current_user();
    if (in_array('aa_asistente', $user->roles)) {
        show_admin_bar(false);
    }
});

// ===============================
// 🔹 Permitir a asistentes acceder a AJAX endpoints
// ===============================
add_filter('user_has_cap', function($allcaps, $caps, $args, $user) {
    // Si es asistente y está intentando hacer AJAX, permitir
    if (in_array('aa_asistente', $user->roles)) {
        $allcaps['read'] = true;
        $allcaps['aa_view_panel'] = true;
    }
    return $allcaps;
}, 10, 4);