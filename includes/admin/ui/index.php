<?php
/**
 * Admin UI Router
 *
 * Responsibilities:
 * - Validate access
 * - Resolve shell access (free / legal gate / full) before the operational shell
 * - Resolve active UI module
 * - Delegate rendering to shared layout or blocking legal-gate screen
 *
 * This file contains NO HTML and NO business logic beyond the access branch.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/legal/ResolveShellAccessUseCase.php';
require_once dirname(__DIR__, 2) . '/domain/legal/class-aa-shell-access.php';

$shell_access = (new ResolveShellAccessUseCase())->execute();

if (($shell_access['access'] ?? '') === AA_Shell_Access::ACCESS_LEGAL_GATE) {
    $legal_gate_view = isset($shell_access['legal']) && is_array($shell_access['legal'])
        ? $shell_access['legal']
        : [
            'success' => false,
            'error'   => [
                'code'    => 'legal_gate_backend_error',
                'message' => 'Estado legal no disponible.',
            ],
            'data'    => [],
        ];
    require __DIR__ . '/legal-gate/index.php';
}

// Operational shell requires manage_options (gate above may already have exited).
// free and full both open the current full shell in this microcycle.
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado', 'Error', ['response' => 403]);
}

// Allowed UI modules (whitelist)
$allowed_modules = [
    'dashboard',
    'settings',
    'account',
    'calendar',
    'clients',
    'assignments',
    'learning',
    'training',
];

// Resolve requested module
$requested_module = isset($_GET['module'])
    ? sanitize_key($_GET['module'])
    : 'calendar';

// Fallback to default module
$active_module = in_array($requested_module, $allowed_modules, true)
    ? $requested_module
    : 'calendar';

// Resolve module path
$module_path = __DIR__ . '/modules/' . $active_module . '/index.php';

// Final safety check
if (!file_exists($module_path)) {
    wp_die('UI module not found', 'Error', ['response' => 404]);
}

// Variables $active_module and $module_path are now available in parent scope
// Delegate rendering to layout (variables will be accessible in layout.php)
require __DIR__ . '/shared/layout.php';
