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
require_once dirname(__DIR__, 2) . '/application/canonical/ResolveCanonicalRouteUseCase.php';

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
    'canonical_shell',
];

$requested_module = isset($_GET['module']) ? sanitize_key($_GET['module']) : 'calendar';
// LEGACY-X: module=canonical retirado — mismo tratamiento que módulo no encontrado.
if ($requested_module === 'canonical') {
    wp_die('UI module not found', 'Error', ['response' => 404]);
}
$active_module    = in_array($requested_module, $allowed_modules, true) ? $requested_module : 'calendar';
$view_raw         = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : '';

$aa_canonical_family = null;
$aa_shell_route_state = null;
$aa_shell_route_message = null;
$aa_shell_view = null;

if ($active_module === 'canonical_shell') {
    if (!class_exists('AA_Canonical_Shell_Base_Url_Policy')) {
        require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
    }
    if (!class_exists('AA_Canonical_Shell_View_Composer')) {
        require_once dirname(__DIR__, 2) . '/infrastructure/canonical/class-aa-canonical-shell-view-composer.php';
    }

    $family_present = array_key_exists('family', $_GET);
    $variant_present = array_key_exists('variant', $_GET);
    $shell_mode_present = array_key_exists('shell_mode', $_GET);
    $shell_mode_raw = $shell_mode_present ? wp_unslash($_GET['shell_mode']) : null;
    $shell_mode = is_string($shell_mode_raw) ? sanitize_key($shell_mode_raw) : '';
    $family_input = $family_present ? wp_unslash($_GET['family']) : null;
    $variant_input = $variant_present ? wp_unslash($_GET['variant']) : null;

    $view_present = array_key_exists('view', $_GET);
    $view_raw = $view_present ? wp_unslash($_GET['view']) : null;
    $view = is_string($view_raw) ? sanitize_key($view_raw) : '';
    $container_id_present = array_key_exists('container_id', $_GET);
    $containers_page_present = array_key_exists('containers_page', $_GET);
    $lists_scope_present = array_key_exists('lists_scope', $_GET);
    $records_view_present = array_key_exists('records_view', $_GET);

    $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_module_url();
    $aa_shell_view = null;

    $shell_page = 1;
    $page_invalid = false;
    if (array_key_exists('page', $_GET)) {
        $parsed_page = AA_Canonical_Shell_Base_Url_Policy::parse_present_page_value(
            wp_unslash($_GET['page'])
        );
        if ($parsed_page === null) {
            $page_invalid = true;
        } else {
            $shell_page = $parsed_page;
        }
    }

    $shell_container_id = null;
    $shell_containers_page = 1;
    $shell_lists_scope = null;
    $shell_records_view = null;
    $shell_capability_views = [];
    $records_transport_invalid = false;
    $records_transport_message = '';

    if ($view_present) {
        if (!is_string($view_raw) || $view !== AA_Canonical_Shell_Base_Url_Policy::VIEW_RECORDS) {
            $records_transport_invalid = true;
            $records_transport_message = 'El parámetro view no es válido.';
        } elseif (!$container_id_present) {
            $records_transport_invalid = true;
            $records_transport_message = 'El parámetro container_id es obligatorio para view=records.';
        } else {
            $parsed_container_id = AA_Canonical_Shell_Base_Url_Policy::parse_present_positive_id(
                wp_unslash($_GET['container_id'])
            );
            if ($parsed_container_id === null) {
                $records_transport_invalid = true;
                $records_transport_message = 'El parámetro container_id no es válido.';
            } else {
                $shell_container_id = $parsed_container_id;
            }
        }

        if (!$records_transport_invalid && $containers_page_present) {
            $parsed_containers_page = AA_Canonical_Shell_Base_Url_Policy::parse_present_page_value(
                wp_unslash($_GET['containers_page'])
            );
            if ($parsed_containers_page === null) {
                $records_transport_invalid = true;
                $records_transport_message = 'El parámetro containers_page no es válido.';
            } else {
                $shell_containers_page = $parsed_containers_page;
            }
        }
    } elseif ($container_id_present || $containers_page_present) {
        $records_transport_invalid = true;
        $records_transport_message = 'container_id y containers_page solo se admiten con view=records.';
    }

    if (!$records_transport_invalid && $lists_scope_present) {
        $parsed_lists_scope = AA_Canonical_Shell_Base_Url_Policy::parse_present_lists_scope(
            wp_unslash($_GET['lists_scope'])
        );
        if ($parsed_lists_scope === null) {
            $records_transport_invalid = true;
            $records_transport_message = 'El parámetro lists_scope no es válido.';
        } elseif ($view !== AA_Canonical_Shell_Base_Url_Policy::VIEW_RECORDS) {
            $records_transport_invalid = true;
            $records_transport_message = 'lists_scope solo se admite con view=records.';
        } else {
            $shell_lists_scope = $parsed_lists_scope;
        }
    }
    if (!$records_transport_invalid && $records_view_present) {
        $raw_records_view = wp_unslash($_GET['records_view']);
        if (!is_string($raw_records_view) || !AA_Canonical_Key::is_valid($raw_records_view) || $view !== AA_Canonical_Shell_Base_Url_Policy::VIEW_RECORDS || $shell_mode !== '') {
            $records_transport_invalid = true;
            $records_transport_message = 'El parámetro records_view no es válido.';
        } else {
            $shell_records_view = $raw_records_view;
        }
    }

    if (!$records_transport_invalid && array_key_exists('capability_views', $_GET)) {
        try {
            if ($view !== AA_Canonical_Shell_Base_Url_Policy::VIEW_RECORDS || $shell_mode !== '') {
                throw new InvalidArgumentException('Capability views require productive records.');
            }
            $shell_capability_views = AA_Canonical_Shell_Base_Url_Policy::parse_capability_views(wp_unslash($_GET['capability_views']));
        } catch (InvalidArgumentException $e) {
            $records_transport_invalid = true;
            $records_transport_message = 'El parámetro capability_views no es válido.';
        }
    }

    $is_records_view = (
        !$page_invalid
        && !$records_transport_invalid
        && $view === AA_Canonical_Shell_Base_Url_Policy::VIEW_RECORDS
        && $shell_container_id !== null
    );

    if ($page_invalid) {
        $aa_shell_route_state = 'invalid_request';
        $aa_shell_route_message = 'El parámetro page no es válido.';
        if (function_exists('status_header')) {
            status_header(400);
        }
    } elseif ($records_transport_invalid) {
        $aa_shell_route_state = 'invalid_request';
        $aa_shell_route_message = $records_transport_message;
        if (function_exists('status_header')) {
            status_header(400);
        }
    } elseif ($shell_mode === AA_Canonical_Shell_Base_Url_Policy::SHELL_MODE_PREVIEW) {
        if ($family_present) {
            $aa_shell_route_state = 'invalid_request';
            $aa_shell_route_message = 'El modo preview no admite family.';
            if (function_exists('status_header')) {
                status_header(400);
            }
        } elseif (!AA_Canonical_Shell_View_Composer::is_preview_enabled()) {
            $aa_shell_route_state = 'preview_unavailable';
            $aa_shell_route_message = 'La demostración del shell no está disponible.';
            if (function_exists('status_header')) {
                status_header(404);
            }
        } elseif ($is_records_view) {
            $aa_shell_route_state = 'preview';
            $aa_shell_route_message = 'Demostración del shell.';
            $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_preview_records_url(
                $shell_container_id,
                $shell_page > 1 ? $shell_page : null,
                $shell_containers_page > 1 ? $shell_containers_page : null
            );
            $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_preview_records(
                $shell_container_id,
                $shell_page,
                $shell_containers_page
            );
        } else {
            $aa_shell_route_state = 'preview';
            $aa_shell_route_message = 'Demostración del shell.';
            $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_preview_url(
                $shell_page > 1 ? $shell_page : null
            );
            $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_preview($shell_page);
        }
    } elseif ($shell_mode_present && $shell_mode !== '') {
        $aa_shell_route_state = 'invalid_request';
        $aa_shell_route_message = 'Modo de shell no reconocido.';
        if (function_exists('status_header')) {
            status_header(400);
        }
    } elseif (!$family_present) {
        if ($is_records_view || $view_present) {
            $aa_shell_route_state = 'invalid_request';
            $aa_shell_route_message = 'view=records requiere family.';
            if (function_exists('status_header')) {
                status_header(400);
            }
        } else {
            $canonical_registry = null;
            if (class_exists('AA_Canonical_Core_Bootstrap')) {
                try {
                    $canonical_registry = AA_Canonical_Core_Bootstrap::instance();
                } catch (\Throwable $e) {
                    $canonical_registry = null;
                }
            }

            if ($canonical_registry === null) {
                wp_die('El núcleo canónico no está disponible.', 'Error', ['response' => 500]);
            }

            if (!class_exists('AA_Canonical_Family_Enablement_Nav')) {
                require_once dirname(__DIR__, 2) . '/infrastructure/canonical/class-aa-canonical-family-enablement-nav.php';
            }
            if (!class_exists('AA_Canonical_Access_Policy')) {
                require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-canonical-access-policy.php';
            }

            $enabled_families = [];
            $all_scope_gate_state = null;
            $all_scope_gate_message = '';
            if (class_exists('AA_Canonical_Family_Enablement_Store')
                && class_exists('ReadCanonicalFamilyEnablementUseCase')
            ) {
                try {
                    $aa_enablement_snapshot = (new ReadCanonicalFamilyEnablementUseCase(
                        new AA_Canonical_Family_Enablement_Store()
                    ))->execute($canonical_registry);
                    $enabled_families = AA_Canonical_Family_Enablement_Nav::available_families(
                        $canonical_registry,
                        $aa_enablement_snapshot
                    );
                } catch (CanonicalFamilyEnablementSchemaNotReady $e) {
                    $all_scope_gate_state = 'schema_not_ready';
                    $all_scope_gate_message = 'El esquema canónico no está listo en esta instalación.';
                } catch (CanonicalFamilyEnablementPersistenceFailed $e) {
                    $all_scope_gate_state = 'enablement_unavailable';
                    $all_scope_gate_message = 'No se pudo consultar el estado de habilitación de las familias.';
                }
            }

            if ($all_scope_gate_state !== null) {
                $aa_shell_route_state = $all_scope_gate_state;
                $aa_shell_route_message = $all_scope_gate_message;
                if (function_exists('status_header')) {
                    status_header(200);
                }
            } elseif (count($enabled_families) === 1) {
                // Ciclo 2B.1: una sola familia disponible → URL familiar canónica (antes de HTML).
                $only_family = $enabled_families[0];
                $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_url(
                    $only_family->key(),
                    $shell_page > 1 ? $shell_page : null
                );
                if (wp_safe_redirect($aa_canonical_url, 302)) {
                    exit;
                }
                wp_die('No se pudo normalizar la ruta de Listas.', 'Error', ['response' => 500]);
            } else {
                $aa_shell_route_state = 'resolved';
                $aa_shell_route_message = 'Listado general de listas.';
                $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_module_url(
                    $shell_page > 1 ? $shell_page : null
                );
                $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_all_containers(
                    $enabled_families,
                    $shell_page
                );
            }
        }
    } else {
        $canonical_registry = null;
        if (class_exists('AA_Canonical_Core_Bootstrap')) {
            try {
                $canonical_registry = AA_Canonical_Core_Bootstrap::instance();
            } catch (\Throwable $e) {
                $canonical_registry = null;
            }
        }

        if ($canonical_registry === null) {
            wp_die('El núcleo canónico no está disponible.', 'Error', ['response' => 500]);
        }

        $route_result = (new ResolveCanonicalRouteUseCase($canonical_registry))->execute([
            'family_key' => $family_input,
        ]);

        if (!$route_result['success']) {
            $error_code = (string) ($route_result['error']['code'] ?? '');
            if ($error_code === 'unknown_family') {
                $aa_shell_route_state = 'not_found';
                $aa_shell_route_message = (string) ($route_result['error']['message'] ?? 'Familia no encontrada.');
                if (function_exists('status_header')) {
                    status_header(404);
                }
            } elseif ($error_code === 'canonical_unavailable') {
                wp_die('El núcleo canónico no está disponible.', 'Error', ['response' => 500]);
            } else {
                $aa_shell_route_state = 'invalid_request';
                $aa_shell_route_message = (string) ($route_result['error']['message'] ?? 'Solicitud canónica no válida.');
                if (function_exists('status_header')) {
                    status_header(400);
                }
            }
        } else {
            $aa_canonical_family = $route_result['data']['family'];

            $aa_enablement_gate_state = null;
            $aa_enablement_gate_message = '';
            if (class_exists('AA_Canonical_Family_Enablement_Store')
                && class_exists('ReadCanonicalFamilyEnablementUseCase')
            ) {
                try {
                    $aa_enablement_snapshot = (new ReadCanonicalFamilyEnablementUseCase(
                        new AA_Canonical_Family_Enablement_Store()
                    ))->execute($canonical_registry);

                    if (!$aa_enablement_snapshot->is_provisioned($aa_canonical_family->key())) {
                        $aa_enablement_gate_state = 'family_not_provisioned';
                        $aa_enablement_gate_message = 'Esta familia aún no está provisionada en la instalación.';
                    } elseif (!$aa_enablement_snapshot->is_enabled($aa_canonical_family->key())) {
                        $aa_enablement_gate_state = 'family_disabled';
                        $aa_enablement_gate_message = "Este tipo de registro está desactivado.\nPuedes activarlo en Ajustes, en la sección “Tipos de registros”.";
                    }
                } catch (CanonicalFamilyEnablementSchemaNotReady $e) {
                    $aa_enablement_gate_state = 'schema_not_ready';
                    $aa_enablement_gate_message = 'El esquema canónico no está listo en esta instalación.';
                } catch (CanonicalFamilyEnablementPersistenceFailed $e) {
                    $aa_enablement_gate_state = 'enablement_unavailable';
                    $aa_enablement_gate_message = 'No se pudo consultar el estado de habilitación de la familia.';
                }
            }

            if ($aa_enablement_gate_state !== null) {
                $aa_shell_route_state = $aa_enablement_gate_state;
                $aa_shell_route_message = $aa_enablement_gate_message;
                if (function_exists('status_header')) {
                    status_header(200);
                }
            } elseif ($is_records_view) {
                try {
                    $resolution = AA_Canonical_Shell_View_Composer::resolve_records_query(
                        $aa_canonical_family, $shell_container_id, $shell_capability_views, $shell_records_view
                    );
                    $effective_page = $resolution['criteria_changed'] ? 1 : $shell_page;
                    $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_records_url(
                        $aa_canonical_family->key(), $shell_container_id, $effective_page,
                        $shell_containers_page, $shell_lists_scope, 'simple', $resolution['selections']
                    );
                    if ($resolution['redirect'] || $shell_records_view === null) {
                        if (!wp_safe_redirect($aa_canonical_url)) { throw new RuntimeException('Canonical redirect failed.'); }
                        exit;
                    }
                    $aa_shell_route_state = 'resolved';
                    $aa_shell_route_message = 'Ruta canónica resuelta.';
                    $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_family_records(
                        $aa_canonical_family, $shell_container_id, $effective_page,
                        $shell_containers_page, $shell_lists_scope, 'simple', $resolution['selections'], $resolution
                    );
                } catch (CanonicalContainerNotFound $e) {
                    $aa_shell_route_state = 'container_not_found';
                    $aa_shell_route_message = 'La lista no existe.';
                    if (function_exists('status_header')) { status_header(404); }
                } catch (InvalidArgumentException $e) {
                    $aa_shell_route_state = 'invalid_request';
                    $aa_shell_route_message = 'La vista solicitada no es válida.';
                    if (function_exists('status_header')) { status_header(400); }
                } catch (\Throwable $e) {
                    $aa_shell_route_state = 'contract_error';
                    $aa_shell_route_message = 'No se pudo resolver la consulta de registros.';
                    if (function_exists('status_header')) { status_header(500); }
                }
            } else {
                $aa_shell_route_state = 'resolved';
                $aa_shell_route_message = 'Ruta canónica resuelta.';
                $aa_canonical_url = AA_Canonical_Shell_Base_Url_Policy::build_url(
                    $aa_canonical_family->key(),
                    $shell_page > 1 ? $shell_page : null
                );
                $aa_shell_view = AA_Canonical_Shell_View_Composer::compose_family(
                    $aa_canonical_family,
                    $shell_page
                );
            }
        }
    }
} else {
    // Canonical URL for the current module/view (marker and nonce removed). Rebuilt
    // from known-safe params to avoid open redirects.
    $aa_canonical_url = admin_url('admin-post.php?action=aa_iframe_content&module=' . $active_module);
    if ($view_raw !== '') {
        $aa_canonical_url = add_query_arg('view', $view_raw, $aa_canonical_url);
    }
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

// Operational shell capability check.
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

// Delegate rendering to appropriate layout.
if ($active_module === 'canonical_shell') {
    require __DIR__ . '/shared/canonical-layout.php';
} else {
    require __DIR__ . '/shared/layout.php';
}
