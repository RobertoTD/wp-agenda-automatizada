<?php
/**
 * Shared Layout - Main HTML structure for admin UI
 * 
 * This file provides:
 * - HTML document structure
 * - Tailwind CSS inclusion
 * - Shared header/navigation
 * - Module content area
 * - Shared footer
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// Get module name and path from parent scope (set in index.php)
$active_module = isset($active_module) ? $active_module : 'settings';
$module_path = isset($module_path) ? $module_path : '';

// Send headers
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Automatizada - Admin</title>
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/css/admin.css'); ?>">
    
    <!-- Shared Admin JS -->
    <script src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/js/main.js'); ?>" defer></script>
</head>
<body class="flex flex-col min-h-screen" style="background-color: rgb(240, 240, 241);">
    <div id="aa-admin-app" class="w-full">
        <?php require_once __DIR__ . '/header.php'; ?>
        
        <main id="aa-admin-content" class="flex-1 px-4 py-8">

            <?php
            // Load module content
            if (file_exists($module_path)) {
                require_once $module_path;
            } else {
                echo '<div class="p-4 bg-red-50 text-red-700 rounded">Módulo no encontrado.</div>';
            }
            ?>
        </main>
        
        <?php require_once __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
<?php
// Terminate execution to prevent WordPress output
die();

