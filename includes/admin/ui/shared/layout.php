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

// Get module name from parent scope
$module = $module ?? 'settings';
$module_path = $module_path ?? '';

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
<body class="bg-gray-50">
    <div id="aa-admin-app" class="min-h-screen">
        <?php require_once __DIR__ . '/header.php'; ?>
        
        <main class="container mx-auto px-4 py-6">
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

