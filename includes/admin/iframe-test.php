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
        'options-general.php',           // Parent: Ajustes
        'Iframe Test',                   // Título de la página
        'Iframe Test',                   // Título del menú
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
        <h1>Prueba de Iframe Aislado</h1>
        <p>El contenido dentro del iframe está completamente aislado del CSS/JS del admin.</p>
        
        <iframe 
            id="aa-isolated-iframe"
            src="<?php echo esc_url($iframe_url); ?>"
            style="width: 100%; height: 400px; border: 2px solid #0073aa;"
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
    
    // Enviar headers de HTML completo
    header('Content-Type: text/html; charset=utf-8');
    
    // Renderizar HTML completamente aislado
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contenido Aislado</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f0f0f0;
        }
        .container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hello World</h1>
        <p>Este contenido está completamente aislado del admin de WordPress.</p>
    </div>
</body>
</html>
    <?php
    
    // Terminar ejecución (crítico para evitar output adicional de WP)
    die();
}
