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
 * This file contains NO HTML. Logic is limited to the access branch and the
 * pre-layout resolution of `module=expedientes&view=detail` (real parent row).
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

/*
 * D2: legacy clients&view=expediente → detail canónico si ya hay padre.
 * Solo tras gate ACCESS_FULL. Sin layout/HTML antes del redirect.
 * false → vista virtual legacy; null/malformado → 500 fail-closed.
 */
if ($active_module === 'clients' && $view_raw === 'expediente') {
    $aa_d2_client_id_raw = array_key_exists('client_id', $_GET) ? $_GET['client_id'] : null;
    $aa_d2_client_id = 0;
    if (is_scalar($aa_d2_client_id_raw) && !is_bool($aa_d2_client_id_raw)) {
        $aa_d2_client_id = absint(wp_unslash((string) $aa_d2_client_id_raw));
    }

    if ($aa_d2_client_id > 0) {
        require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';

        $aa_d2_parent = ExpedientesRepository::find_by_client_id($aa_d2_client_id);

        if ($aa_d2_parent === null) {
            wp_die('No se pudo abrir el expediente.', 'Error', ['response' => 500]);
            return;
        }

        if (is_array($aa_d2_parent)) {
            if (!class_exists('AA_Expediente_Id_Policy')) {
                require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
            }

            $aa_d2_expediente_id = AA_Expediente_Id_Policy::normalize($aa_d2_parent['id'] ?? null);
            $aa_d2_owner_id = AA_Expediente_Id_Policy::normalize($aa_d2_parent['client_id'] ?? null);

            if (
                $aa_d2_expediente_id === null
                || $aa_d2_owner_id === null
                || $aa_d2_owner_id !== $aa_d2_client_id
            ) {
                wp_die('No se pudo abrir el expediente.', 'Error', ['response' => 500]);
                return;
            }

            $aa_d2_canonical_url = add_query_arg(
                [
                    'action' => 'aa_iframe_content',
                    'module' => 'expedientes',
                    'view' => 'detail',
                    'expediente_id' => $aa_d2_expediente_id,
                ],
                admin_url('admin-post.php')
            );

            if (wp_safe_redirect($aa_d2_canonical_url, 302)) {
                exit;
            }

            wp_die('No se pudo abrir el expediente.', 'Error', ['response' => 500]);
            return;
        }
        // false: sin padre → continuar a la vista virtual legacy.
    }
}

/*
 * Parent detail (module=expedientes&view=detail). Gate above already ran.
 * Strict id parsing lives in GetExpedienteUseCase. Site-scoped via table prefix.
 */
$aa_expediente_detail = null;
$aa_expediente_records_view = null;
if ($active_module === 'expedientes' && $view_raw === 'detail') {
    require_once dirname(__DIR__, 2) . '/application/expediente/GetExpedienteUseCase.php';
    require_once dirname(__DIR__, 2) . '/application/expediente/ListExpedienteRegistrosUseCase.php';

    $aa_expediente_detail_result = (new GetExpedienteUseCase())->execute([
        'expediente_id' => array_key_exists('expediente_id', $_GET)
            ? wp_unslash($_GET['expediente_id'])
            : null,
    ]);

    if (empty($aa_expediente_detail_result['success'])) {
        $aa_detail_error = (string) ($aa_expediente_detail_result['error']['code'] ?? '');
        if ($aa_detail_error === 'not_found') {
            wp_die('Expediente no encontrado', 'Error', ['response' => 404]);
        }
        wp_die('Expediente no válido', 'Error', ['response' => 400]);
    }

    $aa_expediente_detail = $aa_expediente_detail_result['data'] ?? null;
    if (!is_array($aa_expediente_detail)) {
        wp_die('Expediente no encontrado', 'Error', ['response' => 404]);
    }

    $aa_detail_id = (int) ($aa_expediente_detail['id'] ?? 0);
    if ($aa_detail_id < 1) {
        wp_die('Expediente no encontrado', 'Error', ['response' => 404]);
    }

    $aa_records_page_input = array_key_exists('records_page', $_GET)
        ? wp_unslash($_GET['records_page'])
        : null;
    $aa_expediente_records_result = (new ListExpedienteRegistrosUseCase())->execute([
        'expediente_id' => $aa_detail_id,
        'page' => $aa_records_page_input,
    ]);
    if (empty($aa_expediente_records_result['success'])) {
        wp_die('No se pudieron cargar los registros del expediente.', 'Error', ['response' => 500]);
    }

    $aa_records_data = is_array($aa_expediente_records_result['data'] ?? null)
        ? $aa_expediente_records_result['data']
        : [];
    $aa_records_page = (int) ($aa_records_data['page'] ?? 1);
    $aa_records_page = $aa_records_page > 0 ? $aa_records_page : 1;
    $aa_records_total_pages = (int) ($aa_records_data['total_pages'] ?? 0);
    $aa_records_has_previous = !empty($aa_records_data['has_previous']) && $aa_records_page > 1;
    $aa_records_has_next = !empty($aa_records_data['has_next'])
        && ($aa_records_total_pages < 1 || $aa_records_page < $aa_records_total_pages);
    $aa_records_base_query = [
        'action' => 'aa_iframe_content',
        'module' => 'expedientes',
        'view' => 'detail',
        'expediente_id' => (string) $aa_detail_id,
    ];

    $aa_records_prev_url = '';
    if ($aa_records_has_previous) {
        $aa_records_prev_url = add_query_arg(
            array_merge($aa_records_base_query, ['records_page' => (string) ($aa_records_page - 1)]),
            admin_url('admin-post.php')
        );
    }

    $aa_records_next_url = '';
    if ($aa_records_has_next) {
        $aa_records_next_url = add_query_arg(
            array_merge($aa_records_base_query, ['records_page' => (string) ($aa_records_page + 1)]),
            admin_url('admin-post.php')
        );
    }

    $aa_expediente_records_view = [
        'records' => is_array($aa_records_data['records'] ?? null) ? $aa_records_data['records'] : [],
        'page' => $aa_records_page,
        'per_page' => (int) ($aa_records_data['per_page'] ?? 15),
        'total' => (int) ($aa_records_data['total'] ?? 0),
        'total_pages' => $aa_records_total_pages,
        'has_previous' => $aa_records_has_previous,
        'has_next' => $aa_records_has_next,
        'prev_url' => $aa_records_prev_url,
        'next_url' => $aa_records_next_url,
    ];
}

// Resolve module path.
$module_path = __DIR__ . '/modules/' . $active_module . '/index.php';
if (!file_exists($module_path)) {
    wp_die('UI module not found', 'Error', ['response' => 404]);
}

// Delegate rendering to layout (variables are accessible in layout.php).
require __DIR__ . '/shared/layout.php';
