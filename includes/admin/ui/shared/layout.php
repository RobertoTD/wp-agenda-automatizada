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
 * - Shared scripts (utils, services, adapters)
 * - Transversal modals (reservation, etc.)
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
    
    <!-- Tailwind CSS (usando constante global para URL limpia) -->
    <link rel="stylesheet" href="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/assets/css/admin.css'); ?>">
    
    <!-- Flatpickr CSS (requerido por calendario y modales) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <!-- Shared Admin JS -->
    <script src="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/assets/js/main.js'); ?>" defer></script>
    
    <!-- Shared Utils -->
    <script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/utils/dateUtils.js'); ?>" defer></script>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js" defer></script>
    
    <!-- Shared Services -->
    <script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/adminCalendarService.js'); ?>" defer></script>
    <script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/confirmService.js'); ?>" defer></script>
    
    <!-- Shared Controllers -->
    <script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/controllers/adminConfirmController.js'); ?>" defer></script>
    
    <!-- Shared UI Adapters -->
    <script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/ui-adapters/datePickerAdapter.js'); ?>" defer></script>
    
    <!-- Transversal Modal: Reservation -->
    <script src="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/modals/reservation/reservation.js'); ?>" defer></script>
    
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

    <!-- Shared Modals -->
    <?php require_once __DIR__ . '/modals.php'; ?>
    
    <!-- Transversal Modal Templates (hidden, used by JS) -->
    <div id="aa-modal-templates" class="hidden">
        <?php require_once dirname(__DIR__) . '/modals/reservation/index.php'; ?>
    </div>

</body>
</html>
<?php
// Terminate execution to prevent WordPress output
die();

