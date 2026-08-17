<?php
/**
 * Admin UI Router
 *
 * Responsibilities:
 * - Resolve active UI module and validate operational access
 * - Serve HTML for normal navigation WITHOUT waiting on the legal backend
 *   (shell general → legal gate asíncrono y fail-open mientras no haya confirmación)
 * - Resolve shell access synchronously ONLY in two authoritative, fail-closed
 *   cases: Expedientes URLs (`module=expedientes` OR `clients&view=expediente`)
 *   and the internal legal-gate marker.
 *
 * This file contains NO HTML and NO business logic beyond the access branch.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/legal/ResolveShellAccessUseCase.php';
require_once dirname(__DIR__, 2) . '/domain/legal/class-aa-shell-access.php';

// Whitelisted UI modules.
$allowed_modules = [
    'dashboard',
    'settings',
    'account',
    'calendar',
    'clients',
    'expedientes',
    'assignments',
    'learning',
    'training',
];

$requested_module = isset($_GET['module']) ? sanitize_key($_GET['module']) : 'calendar';
$active_module    = in_array($requested_module, $allowed_modules, true) ? $requested_module : 'calendar';
$view_raw         = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : '';

// Canonical URL for the current module/view (marker and nonce removed). Rebuilt
// from known-safe params to avoid open redirects.
$aa_canonical_url = admin_url('admin-post.php?action=aa_iframe_content&module=' . $active_module);
if ($view_raw !== '') {
    $aa_canonical_url = add_query_arg('view', $view_raw, $aa_canonical_url);
}

/*
 * Directed legal-gate load (internal marker `aa_gate=1`).
 *
 * The marker NEVER grants access: it only re-runs the authoritative resolver.
 * It must be accompanied by the existing legal nonce; otherwise it is stripped
 * and we return to the canonical URL. Normal navigation never sets this marker,
 * so the resolver stays off the blocking path for calendar/clients/etc.
 *
 * - resolution still legal_gate  → render legal-gate/index.php exactly as today
 * - any other resolution          → strip marker and redirect to canonical URL
 *   (prevents the marker from persisting and re-triggering sync resolutions).
 */
$aa_gate_marker = isset($_GET['aa_gate']) && (string) $_GET['aa_gate'] === '1';
if ($aa_gate_marker) {
    $aa_gate_nonce = isset($_GET['_wpnonce'])
        ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce']))
        : '';

    if (!wp_verify_nonce($aa_gate_nonce, 'aa_legal_gate_nonce')) {
        wp_safe_redirect($aa_canonical_url);
        exit;
    }

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
        exit;
    }

    // No longer gated: drop the marker and return to the canonical shell URL.
    wp_safe_redirect($aa_canonical_url);
    exit;
}

// Operational shell requires manage_options.
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado', 'Error', ['response' => 403]);
}

/*
 * Expedientes URL gate (authoritative, synchronous, fail-closed).
 *
 * One branch covers both surfaces so ResolveShellAccessUseCase still runs
 * exactly twice in this file (legal-gate marker + this gate):
 * - module=expedientes (parent entity)
 * - clients&view=expediente (legacy client expediente)
 *
 * Only shell access === full may open either URL. Every other module renders
 * immediately (fail-open) and reconciles access asynchronously.
 */
if (
    $active_module === 'expedientes'
    || ($active_module === 'clients' && $view_raw === 'expediente')
) {
    $shell_access = (new ResolveShellAccessUseCase())->execute();
    if (($shell_access['access'] ?? '') !== AA_Shell_Access::ACCESS_FULL) {
        wp_die('Acceso denegado', 'Error', ['response' => 403]);
    }
}

// Resolve module path.
$module_path = __DIR__ . '/modules/' . $active_module . '/index.php';
if (!file_exists($module_path)) {
    wp_die('UI module not found', 'Error', ['response' => 404]);
}

// Delegate rendering to layout (variables are accessible in layout.php).
require __DIR__ . '/shared/layout.php';
