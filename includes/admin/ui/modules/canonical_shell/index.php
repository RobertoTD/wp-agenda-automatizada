<?php
/**
 * Canonical Shell Base — Root paralelo (SB1-2B / SB1-3B).
 *
 * Presenta view data ya compuesto. No resuelve servicios ni registra adaptadores.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Modules\CanonicalShell
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$route_state = isset($aa_shell_route_state) && is_string($aa_shell_route_state)
    ? $aa_shell_route_state
    : 'missing_identity';

$route_message = isset($aa_shell_route_message) && is_string($aa_shell_route_message)
    ? $aa_shell_route_message
    : '';

$view = (isset($aa_shell_view) && is_array($aa_shell_view)) ? $aa_shell_view : null;

$shell_view = is_array($view) && isset($view['shell_view']) && is_string($view['shell_view'])
    ? $view['shell_view']
    : 'containers';
$family_label = is_array($view) ? (string) ($view['family_label'] ?? '') : '';
$qualified_key = is_array($view) ? (string) ($view['qualified_key'] ?? '') : '';
$read_state = is_array($view) ? (string) ($view['read_state'] ?? '') : '';
$lists_scope = is_array($view) && isset($view['lists_scope']) && is_string($view['lists_scope'])
    ? $view['lists_scope']
    : '';
$is_all_lists_scope = ($lists_scope === 'all');
$available_families = is_array($view) && isset($view['available_families']) && is_array($view['available_families'])
    ? $view['available_families']
    : [];
$is_preview = is_array($view) && !empty($view['is_preview']);
$preview_banner = is_array($view) && isset($view['preview_banner']) && is_string($view['preview_banner'])
    ? $view['preview_banner']
    : '';
$preview_enabled = is_array($view) && !empty($view['preview_enabled']);
$preview_url = is_array($view) && isset($view['preview_url']) && is_string($view['preview_url'])
    ? $view['preview_url']
    : '';
$items_view = is_array($view) && isset($view['items_view']) && is_array($view['items_view'])
    ? $view['items_view']
    : [];
$page_num = is_array($view) && isset($view['page']) ? (int) $view['page'] : null;
$total_pages = is_array($view) && isset($view['total_pages']) ? (int) $view['total_pages'] : null;
$has_previous = is_array($view) && !empty($view['has_previous']);
$has_next = is_array($view) && !empty($view['has_next']);
$prev_url = is_array($view) && isset($view['prev_url']) ? (string) $view['prev_url'] : '';
$next_url = is_array($view) && isset($view['next_url']) ? (string) $view['next_url'] : '';
$back_url = is_array($view) && isset($view['back_url']) ? (string) $view['back_url'] : '';
$parent_container = is_array($view) && isset($view['container']) && is_array($view['container'])
    ? $view['container']
    : null;
$capability_contributions = is_array($view) && isset($view['capability_contributions']) && is_array($view['capability_contributions'])
    ? $view['capability_contributions']
    : [];
$containers_page_num = is_array($view) && isset($view['containers_page'])
    ? (int) $view['containers_page']
    : null;
$family_icon_key = is_array($view) && isset($view['family_icon_key']) && is_string($view['family_icon_key'])
    ? $view['family_icon_key']
    : '';
if (
    $family_icon_key === ''
    && isset($aa_canonical_family)
    && $aa_canonical_family instanceof AA_Canonical_Family_Definition
) {
    $family_icon_key = $aa_canonical_family->icon_key();
}

if (
    $family_label === ''
    && isset($aa_canonical_family)
    && $aa_canonical_family instanceof AA_Canonical_Family_Definition
) {
    $family_label = $aa_canonical_family->label();
    $qualified_key = $aa_canonical_family->key();
}

$page_title = 'Shell canónico';
if ($is_preview) {
    $page_title = 'Shell canónico · Demostración';
} elseif ($is_all_lists_scope) {
    $page_title = 'Todas las listas';
} elseif ($family_label !== '') {
    $page_title = $family_label;
}

$state_labels = [
    'missing_identity'       => 'Desarrollo',
    'invalid_request'        => 'Solicitud no válida',
    'not_found'              => 'No encontrado',
    'preview_unavailable'    => 'No disponible',
    'preview'                => 'Demostración',
    'resolved'               => 'Resuelto',
    'family_disabled'        => 'Desactivado',
    'family_not_provisioned' => 'No provisionado',
    'schema_not_ready'       => 'Esquema no listo',
    'enablement_unavailable' => 'No disponible',
];
$state_label = $state_labels[$route_state] ?? 'Estado';

$show_read_ui = is_array($view) && in_array($route_state, ['resolved', 'preview'], true);
$is_records = ($shell_view === 'records');
$is_family_disabled = ($route_state === 'family_disabled');

$create_family_key = '';
if (
    isset($aa_canonical_family)
    && $aa_canonical_family instanceof AA_Canonical_Family_Definition
) {
    $create_family_key = $aa_canonical_family->key();
}

$normalized_available_families = [];
foreach ($available_families as $family_row) {
    if (!is_array($family_row)) {
        continue;
    }
    $fk = isset($family_row['family_key']) ? (string) $family_row['family_key'] : '';
    $fl = isset($family_row['label']) ? (string) $family_row['label'] : '';
    if ($fk === '' || $fl === '') {
        continue;
    }
    $normalized_available_families[] = [
        'family_key' => $fk,
        'label' => $fl,
    ];
}

// Política de familia inicial al crear: solo en JS (resolveInitialCreateFamilyKey).
// PHP no preasigna create_family_key en alcance «Todas» (evita defaults duplicados).

$can_create_from_all = $is_all_lists_scope && $normalized_available_families !== [];
$show_family_select_on_create = $is_all_lists_scope && count($normalized_available_families) > 1;

$show_create_ui = $show_read_ui
    && !$is_preview
    && !$is_records
    && $route_state === 'resolved'
    && in_array($read_state, ['empty', 'resolved_page'], true)
    && ($create_family_key !== '' || $can_create_from_all);

$settings_url = admin_url('admin-post.php?action=aa_iframe_content&module=settings');
$can_open_settings = function_exists('current_user_can') && current_user_can('manage_options');

$create_container_id = 0;
if (is_array($view) && isset($view['container_id'])) {
    $create_container_id = (int) $view['container_id'];
}
$create_container_title = '';
if (is_array($parent_container) && isset($parent_container['title'])) {
    $create_container_title = (string) $parent_container['title'];
}

$show_create_record_ui = $show_read_ui
    && !$is_preview
    && $is_records
    && $route_state === 'resolved'
    && in_array($read_state, ['empty', 'resolved_page'], true)
    && $create_family_key !== ''
    && $create_container_id >= 1;

// Editar lista desde records: misma puerta de escritura que crear registro (familia + contenedor padre).
$show_edit_container_on_records = $show_create_record_ui;

$show_container_write_ui = $show_create_ui || $show_edit_container_on_records;

$list_retire_in_progress = false;
$orphan_container_purges = [];
$open_image_purges_on_records = [];
$shell_images_read_url_uc = null;
$aa_shell_resolve_card_image_summary_url = static function (
    ?array $card_capabilities,
    int $card_record_id,
    string $family_key,
    int $container_id,
    &$read_url_uc
): ?string {
    if ($card_record_id < 1 || $family_key === '' || $container_id < 1) {
        return null;
    }
    if (!class_exists('AA_Canonical_Images_Shell_Presenter')) {
        return null;
    }
    $images_card = AA_Canonical_Images_Shell_Presenter::card_view($card_capabilities);
    if (!is_array($images_card) || ($images_card['kind'] ?? '') !== 'gallery') {
        return null;
    }
    $image_id = isset($images_card['image_id']) ? (int) $images_card['image_id'] : 0;
    if ($image_id < 1) {
        return null;
    }
    if ($read_url_uc === null) {
        if (!class_exists('GetCanonicalRecordImageReadUrlUseCase')) {
            require_once dirname(__DIR__, 4) . '/application/canonical/images/GetCanonicalRecordImageReadUrlUseCase.php';
        }
        if (!class_exists('GetCanonicalRecordImageReadUrlUseCase')) {
            return null;
        }
        try {
            $read_url_uc = new GetCanonicalRecordImageReadUrlUseCase();
        } catch (\Throwable $e) {
            return null;
        }
    }

    return AA_Canonical_Images_Shell_Presenter::resolve_summary_url(
        $read_url_uc,
        $family_key,
        $container_id,
        $card_record_id,
        $image_id
    );
};
try {
    if (!class_exists('CanonicalPurgeRunsRepository')) {
        require_once dirname(__DIR__, 4) . '/repositories/CanonicalPurgeRunsRepository.php';
    }
    $purge_runs_ui = new CanonicalPurgeRunsRepository();
    if ($is_records && $create_container_id >= 1) {
        $open_list_purge = $purge_runs_ui->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
            $create_container_id
        );
        $list_retire_in_progress = is_array($open_list_purge);

        if ($show_read_ui && !$is_preview && $route_state === 'resolved'
            && in_array($read_state, ['empty', 'resolved_page'], true)
        ) {
            $record_ids_for_purge_banner = [];
            if (is_array($items_view)) {
                foreach ($items_view as $item_for_purge) {
                    if (!is_array($item_for_purge)) {
                        continue;
                    }
                    $rid = isset($item_for_purge['id']) ? (int) $item_for_purge['id'] : 0;
                    if ($rid >= 1) {
                        $record_ids_for_purge_banner[] = $rid;
                    }
                }
            }
            $record_id_set = array_fill_keys($record_ids_for_purge_banner, true);
            foreach ($purge_runs_ui->list_open_by_scope(CanonicalPurgeRunsRepository::SCOPE_IMAGE) as $open_img_run) {
                if ((int) ($open_img_run['container_id'] ?? 0) !== $create_container_id) {
                    continue;
                }
                if ((string) ($open_img_run['family_key'] ?? '') !== $create_family_key) {
                    continue;
                }
                $run_record_id = (int) ($open_img_run['record_id'] ?? 0);
                if ($run_record_id >= 1 && !isset($record_id_set[$run_record_id])) {
                    // Banner de recuperación aunque el registro no esté en la página actual.
                }
                $open_image_purges_on_records[] = $open_img_run;
            }
        }
    }
    if (!$is_records && $show_read_ui && !$is_preview && $route_state === 'resolved'
        && in_array($read_state, ['empty', 'resolved_page'], true)
    ) {
        if (!class_exists('CanonicalRelationalRepository')) {
            require_once dirname(__DIR__, 4) . '/repositories/CanonicalRelationalRepository.php';
        }
        $rel_ui = new CanonicalRelationalRepository();
        foreach ($purge_runs_ui->list_open_by_scope(CanonicalPurgeRunsRepository::SCOPE_CONTAINER) as $open_run) {
            $run_family = (string) ($open_run['family_key'] ?? '');
            if ($run_family === '') {
                continue;
            }
            if (!$is_all_lists_scope && $create_family_key !== '' && $run_family !== $create_family_key) {
                continue;
            }
            $run_cid = (int) ($open_run['container_id'] ?? 0);
            if ($run_cid < 1) {
                continue;
            }
            $run_fid = $rel_ui->resolve_family_id($run_family);
            if ($run_fid === null || $rel_ui->find_container($run_fid, $run_cid) !== null) {
                continue;
            }
            $orphan_container_purges[] = $open_run;
        }
    }
} catch (\Throwable $e) {
    $list_retire_in_progress = false;
    $orphan_container_purges = [];
    $open_image_purges_on_records = [];
}

$show_record_fab = $show_create_record_ui && !$list_retire_in_progress;

$is_records_fill = $show_read_ui
    && !$is_preview
    && $is_records
    && $route_state === 'resolved'
    && in_array($read_state, ['empty', 'resolved_page'], true);
?>

<div
    id="aa-canonical-shell-root"
    class="max-w-5xl mx-auto py-2<?php echo $show_create_ui ? ' pb-24' : ''; ?><?php echo $is_records_fill ? ' aa-shell-records-fill-root' : ''; ?>"
    data-aa-page-title="<?php echo esc_attr($page_title); ?>"
    data-aa-shell-route-state="<?php echo esc_attr($route_state); ?>"
    data-aa-shell-view="<?php echo esc_attr($shell_view); ?>"
    <?php if ($is_all_lists_scope) : ?>
    data-aa-lists-scope="all"
    <?php endif; ?>
    <?php if ($read_state !== '') : ?>
    data-aa-shell-read-state="<?php echo esc_attr($read_state); ?>"
    <?php endif; ?>
    <?php if ($is_preview) : ?>
    data-aa-shell-preview="1"
    <?php endif; ?>
    <?php if ($qualified_key !== '') : ?>
    data-aa-canonical-qualified="<?php echo esc_attr($qualified_key); ?>"
    <?php endif; ?>
>
    <?php
    // Título semántico: el header compartido es button/span, no heading de contenido.
    $aa_shell_heading_text = $page_title;
    if ($show_read_ui && $is_all_lists_scope) {
        $aa_shell_heading_text = 'Todas las listas';
    } elseif ($show_read_ui && $family_label !== '') {
        $aa_shell_heading_text = $family_label;
    }
    ?>
    <h1 class="sr-only"><?php echo esc_html($aa_shell_heading_text); ?></h1>

    <?php if ($is_preview) : ?>
        <header class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            <div class="flex items-start justify-between flex-wrap gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 leading-tight">
                        <?php echo esc_html($aa_shell_heading_text); ?>
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Módulo paralelo provisional. No sustituye la UI de familias existentes.
                    </p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                    Demostración
                </span>
            </div>
        </header>
    <?php endif; ?>

    <?php if ($is_preview && $preview_banner !== '') : ?>
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
            <?php echo esc_html($preview_banner); ?>
        </div>
    <?php endif; ?>

    <?php if ($show_read_ui) : ?>

        <?php if ($is_records) : ?>

            <?php if (!$is_records_fill && $back_url !== '') : ?>
                <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
                    <p class="m-0">
                        <a
                            href="<?php echo esc_url($back_url); ?>"
                            class="inline-flex items-center text-sm font-medium text-indigo-700 hover:underline"
                        >Volver a contenedores</a>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($read_state === 'contract_error') : ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center" role="alert">
                    <h3 class="text-base font-semibold text-gray-900 mb-2">No se pudo cargar la lista</h3>
                    <p class="text-sm text-gray-500 max-w-lg mx-auto">
                        Ocurrió un problema al preparar los registros. Inténtalo de nuevo más tarde.
                    </p>
                </div>

            <?php elseif ($read_state === 'read_adapter_pending') : ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" role="status">
                    <h3 class="text-base font-semibold text-gray-900 mb-2">Lectura pendiente</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        La lectura canónica de esta familia aún no está conectada.
                    </p>
                    <?php if ($qualified_key !== '') : ?>
                        <p class="text-xs text-gray-400 mb-4">
                            <code class="bg-gray-100 text-gray-700 px-2 py-1 rounded font-mono"><?php echo esc_html($qualified_key); ?></code>
                        </p>
                    <?php endif; ?>
                    <?php if ($preview_enabled && $preview_url !== '') : ?>
                        <p class="text-sm">
                            <a
                                href="<?php echo esc_url($preview_url); ?>"
                                class="text-indigo-700 font-medium hover:underline"
                            >Ver demostración del shell</a>
                        </p>
                    <?php endif; ?>
                </div>

            <?php elseif ($read_state === 'container_not_found') : ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center" role="status">
                    <h3 class="text-base font-semibold text-gray-900 mb-2">Contenedor no encontrado</h3>
                    <p class="text-sm text-gray-500">El contenedor solicitado no existe o no está disponible.</p>
                </div>

            <?php elseif ($read_state === 'empty' || $read_state === 'resolved_page') : ?>
                <?php
                $parent_title = is_array($parent_container) ? (string) ($parent_container['title'] ?? '') : '';
                $parent_details = is_array($parent_container) && array_key_exists('details', $parent_container)
                    ? $parent_container['details']
                    : null;
                $parent_iso = is_array($parent_container) ? (string) ($parent_container['updated_at_iso'] ?? '') : '';
                $parent_display = is_array($parent_container) ? (string) ($parent_container['updated_at_display'] ?? '') : '';
                $parent_has_details_text = is_string($parent_details) && $parent_details !== '';
                $parent_has_updated = ($parent_iso !== '' && $parent_display !== '');
                $parent_has_details_block = $parent_has_details_text || $parent_has_updated;
                $amount_list_details = AA_Canonical_Amount_Shell_Presenter::list_details_view($capability_contributions);
                $list_heading = $parent_title !== '' ? $parent_title : 'Contenedor';
                $parent_heading_icon_svg = '';
                $show_parent_heading_icon = false;
                if ($family_icon_key !== '') {
                    if (!class_exists('AA_Canonical_Family_Icon_Markup')) {
                        require_once __DIR__ . '/class-aa-canonical-family-icon-markup.php';
                    }
                    $parent_heading_icon_svg = AA_Canonical_Family_Icon_Markup::svg($family_icon_key);
                    $show_parent_heading_icon = ($parent_heading_icon_svg !== '');
                }
                $edit_list_payload_attr = '';
                if ($show_edit_container_on_records && $create_container_id >= 1 && $create_family_key !== '') {
                    $edit_list_payload = wp_json_encode(
                        [
                            'id' => $create_container_id,
                            'title' => $parent_title,
                            'details' => is_string($parent_details) ? $parent_details : '',
                            'family_key' => $create_family_key,
                        ],
                        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    );
                    if (is_string($edit_list_payload) && $edit_list_payload !== '') {
                        $edit_list_payload_attr = esc_attr($edit_list_payload);
                    }
                }
                ?>

                <?php if ($is_records_fill) : ?>
                    <section
                        class="aa-shell-list-panel bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
                        aria-labelledby="aa-shell-parent-heading"
                    >
                        <header class="aa-shell-list-panel-header px-4 py-3 border-b border-gray-100 bg-white">
                            <div class="flex items-center gap-1 min-w-0">
                                <?php if ($show_parent_heading_icon) : ?>
                                    <span class="flex items-center justify-center w-6 h-6 flex-shrink-0 text-gray-900" aria-hidden="true">
                                        <?php echo $parent_heading_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup fijo interno ?>
                                    </span>
                                <?php endif; ?>
                                <h2 id="aa-shell-parent-heading" class="text-lg font-semibold text-gray-900 leading-snug truncate min-w-0 flex-1">
                                    <?php echo esc_html($list_heading); ?>
                                </h2>
                                <?php if ($edit_list_payload_attr !== '') : ?>
                                    <div class="aa-shell-container-options relative shrink-0">
                                        <button
                                            type="button"
                                            class="aa-shell-container-options-trigger aa-options-trigger-flat"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            aria-label="<?php echo esc_attr('Opciones de la lista: ' . $list_heading); ?>"
                                        >
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                        </button>
                                        <div
                                            class="aa-shell-container-options-popup hidden absolute right-0 top-full z-30 mt-2 w-[12rem] box-border rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                                            hidden
                                        >
                                            <button
                                                type="button"
                                                class="aa-shell-edit-container-btn flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:bg-gray-50 focus:ring-2 focus:ring-inset focus:ring-indigo-500/30"
                                                data-aa-container="<?php echo $edit_list_payload_attr; ?>"
                                                aria-label="<?php echo esc_attr('Editar lista: ' . $list_heading); ?>"
                                            >
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                class="aa-shell-delete-container-btn flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 hover:bg-gray-50 focus:outline-none focus:bg-gray-50 focus:ring-2 focus:ring-inset focus:ring-red-500/30"
                                                data-aa-container="<?php echo $edit_list_payload_attr; ?>"
                                                aria-label="<?php echo esc_attr('Eliminar lista: ' . $list_heading); ?>"
                                            >
                                                Eliminar
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-3 flex-wrap">
                                <p class="m-0 min-w-0">
                                    <?php if ($back_url !== '') : ?>
                                        <a
                                            href="<?php echo esc_url($back_url); ?>"
                                            class="inline-flex items-center text-sm font-medium text-indigo-700 hover:underline"
                                        >Volver a contenedores</a>
                                    <?php endif; ?>
                                </p>
                                <?php if ($parent_has_details_block) : ?>
                                    <div class="aa-shell-list-details-control shrink-0">
                                        <button
                                            type="button"
                                            id="aa-shell-list-details-toggle"
                                            class="text-sm font-medium text-indigo-700 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded"
                                            aria-expanded="false"
                                            aria-controls="aa-shell-list-details"
                                        >Detalles</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </header>
                        <div class="aa-shell-list-panel-body p-4<?php echo $show_record_fab ? ' aa-shell-list-panel-body--fab' : ''; ?>">
                            <?php if ($list_retire_in_progress) : ?>
                                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
                                    Esta lista se está eliminando. Los registros que aún ves no están listos; no se puede añadir contenido. Pulsa Eliminar lista y Continuar para terminar. No está borrada del todo.
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($open_image_purges_on_records)) : ?>
                                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
                                    <p class="m-0 mb-2">Hay eliminaciones de imagen incompletas. Pulsa Continuar para recuperar el protocolo.</p>
                                    <ul class="m-0 p-0 list-none space-y-2">
                                        <?php foreach ($open_image_purges_on_records as $open_img_run) : ?>
                                            <?php
                                            $resume_image_id = (int) ($open_img_run['target_id'] ?? 0);
                                            if ($resume_image_id < 1) {
                                                continue;
                                            }
                                            $resume_record_id = (int) ($open_img_run['record_id'] ?? 0);
                                            $resume_payload = wp_json_encode(
                                                [
                                                    'id' => $resume_image_id,
                                                    'record_id' => $resume_record_id,
                                                ],
                                                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                                            );
                                            if (!is_string($resume_payload) || $resume_payload === '') {
                                                continue;
                                            }
                                            ?>
                                            <li class="flex flex-wrap items-center gap-2">
                                                <span>Imagen #<?php echo esc_html((string) $resume_image_id); ?></span>
                                                <button
                                                    type="button"
                                                    class="aa-shell-resume-image-delete-btn inline-flex items-center px-3 py-1.5 text-xs font-semibold text-amber-950 bg-amber-100 border border-amber-300 rounded-lg hover:bg-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-500"
                                                    data-aa-image="<?php echo esc_attr($resume_payload); ?>"
                                                >Continuar</button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <?php if ($parent_has_details_block) : ?>
                                <div
                                    id="aa-shell-list-details"
                                    class="mb-4 pb-4 border-b border-gray-100 hidden"
                                    hidden
                                >
                                    <?php if ($parent_has_details_text) : ?>
                                        <p class="text-sm text-gray-600 whitespace-pre-wrap m-0"><?php echo esc_html($parent_details); ?></p>
                                    <?php endif; ?>
                                    <?php if ($parent_has_updated) : ?>
                                        <p class="<?php echo $parent_has_details_text ? 'mt-2' : ''; ?> text-xs text-gray-500 m-0">
                                            <time datetime="<?php echo esc_attr($parent_iso); ?>"><?php echo esc_html($parent_display); ?></time>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (is_array($amount_list_details) && ($amount_list_details['kind'] ?? '') === 'value') : ?>
                                        <?php
                                        $list_sum_classes = 'aa-shell-list-amount-sum text-base font-semibold m-0';
                                        $list_sum_classes .= !empty($amount_list_details['is_negative'])
                                            ? ' text-red-800'
                                            : ' text-gray-900';
                                        $list_sum_mt = ($parent_has_details_text || $parent_has_updated) ? ' mt-2' : '';
                                        ?>
                                        <p class="<?php echo esc_attr($list_sum_classes . $list_sum_mt); ?>">
                                            <span class="sr-only">Total: </span>
                                            <span aria-hidden="true">Total: $</span><?php echo esc_html((string) $amount_list_details['display']); ?>
                                        </p>
                                    <?php elseif (is_array($amount_list_details) && ($amount_list_details['kind'] ?? '') === 'error') : ?>
                                        <p
                                            class="aa-shell-list-amount-sum-error text-sm text-amber-800 <?php echo ($parent_has_details_text || $parent_has_updated) ? 'mt-2' : ''; ?> m-0"
                                            role="status"
                                        >
                                            Total no disponible
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($read_state === 'empty') : ?>
                                <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center" role="status">
                                    <h3 class="text-base font-semibold text-gray-900 mb-2">Sin registros</h3>
                                    <p class="text-sm text-gray-500 m-0">No hay registros para mostrar en este contenedor.</p>
                                </div>
                            <?php else : ?>
                                <h3 class="sr-only">Registros</h3>
                                <ul
                                    id="aa-shell-records-list"
                                    class="aa-shell-records-list space-y-2"
                                    aria-label="Registros canónicos"
                                >
                                    <?php foreach ($items_view as $item) : ?>
                                        <?php
                                        $card_title = isset($item['title']) ? (string) $item['title'] : '';
                                        $card_details = array_key_exists('details', $item) ? $item['details'] : null;
                                        $card_iso = isset($item['updated_at_iso']) ? (string) $item['updated_at_iso'] : '';
                                        $card_display = isset($item['updated_at_display']) ? (string) $item['updated_at_display'] : '';
                                        $card_record_id = isset($item['id']) ? (int) $item['id'] : 0;
                                        $card_capabilities = isset($item['capabilities']) && is_array($item['capabilities'])
                                            ? $item['capabilities']
                                            : null;
                                        $show_edit_record = $show_record_fab;
                                        $shell_record_presentation = 'compact';
                                        $show_image_actions = $show_create_record_ui && !$list_retire_in_progress;
                                        $card_image_summary_url = $aa_shell_resolve_card_image_summary_url(
                                            $card_capabilities,
                                            $card_record_id,
                                            $create_family_key,
                                            $create_container_id,
                                            $shell_images_read_url_uc
                                        );
                                        require __DIR__ . '/partials/record-card.php';
                                        ?>
                                    <?php endforeach; ?>
                                </ul>
                                <div
                                    id="aa-shell-records-scroll-extender"
                                    class="aa-shell-records-scroll-extender"
                                    aria-hidden="true"
                                ></div>

                                <?php if ($has_previous || $has_next) : ?>
                                    <nav class="mt-6 flex items-center justify-between gap-3" aria-label="Paginación de registros">
                                        <div>
                                            <?php if ($has_previous && $prev_url !== '') : ?>
                                                <a
                                                    href="<?php echo esc_url($prev_url); ?>"
                                                    class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                                                >Anterior</a>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs text-gray-500" aria-current="page">
                                            <?php
                                            $total_display = ($total_pages !== null && $total_pages > 0) ? $total_pages : 1;
                                            $current_display = $page_num !== null ? $page_num : 1;
                                            echo esc_html('Página ' . $current_display . ' de ' . $total_display);
                                            ?>
                                        </p>
                                        <div>
                                            <?php if ($has_next && $next_url !== '') : ?>
                                                <a
                                                    href="<?php echo esc_url($next_url); ?>"
                                                    class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                                                >Siguiente</a>
                                            <?php endif; ?>
                                        </div>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php if ($parent_has_details_block) : ?>
                        <script>
                        (function () {
                            var toggle = document.getElementById('aa-shell-list-details-toggle');
                            var panel = document.getElementById('aa-shell-list-details');
                            if (!toggle || !panel) {
                                return;
                            }
                            toggle.addEventListener('click', function () {
                                var open = toggle.getAttribute('aria-expanded') === 'true';
                                var next = !open;
                                toggle.setAttribute('aria-expanded', next ? 'true' : 'false');
                                if (next) {
                                    panel.classList.remove('hidden');
                                    panel.removeAttribute('hidden');
                                } else {
                                    panel.classList.add('hidden');
                                    panel.setAttribute('hidden', '');
                                }
                                document.dispatchEvent(new CustomEvent('aa-shell-list-details-toggle'));
                            });
                        })();
                        </script>
                    <?php endif; ?>
                    <?php if ($read_state === 'resolved_page') : ?>
                        <script src="<?php echo function_exists('aa_asset_url')
                            ? aa_asset_url('includes/admin/ui/modules/canonical_shell/canonical-shell-records-compact.js')
                            : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/canonical-shell-records-compact.js'); ?>"></script>
                    <?php endif; ?>

                <?php else : ?>
                    <section class="mb-4" aria-labelledby="aa-shell-parent-heading">
                        <div class="flex items-center gap-1 min-w-0">
                            <?php if ($show_parent_heading_icon) : ?>
                                <span class="flex items-center justify-center w-6 h-6 flex-shrink-0 text-gray-900" aria-hidden="true">
                                    <?php echo $parent_heading_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup fijo interno ?>
                                </span>
                            <?php endif; ?>
                            <h3 id="aa-shell-parent-heading" class="text-lg font-semibold text-gray-900 m-0 truncate min-w-0 flex-1">
                                <?php echo esc_html($list_heading); ?>
                            </h3>
                            <?php if ($edit_list_payload_attr !== '') : ?>
                                <div class="aa-shell-container-options relative shrink-0">
                                    <button
                                        type="button"
                                        class="aa-shell-container-options-trigger aa-options-trigger-flat"
                                        aria-haspopup="true"
                                        aria-expanded="false"
                                        aria-label="<?php echo esc_attr('Opciones de la lista: ' . $list_heading); ?>"
                                    >
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </button>
                                    <div
                                        class="aa-shell-container-options-popup hidden absolute right-0 top-full z-30 mt-2 w-[12rem] box-border rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                                        hidden
                                    >
                                        <button
                                            type="button"
                                            class="aa-shell-edit-container-btn flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:bg-gray-50 focus:ring-2 focus:ring-inset focus:ring-indigo-500/30"
                                            data-aa-container="<?php echo $edit_list_payload_attr; ?>"
                                            aria-label="<?php echo esc_attr('Editar lista: ' . $list_heading); ?>"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="aa-shell-delete-container-btn flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 hover:bg-gray-50 focus:outline-none focus:bg-gray-50 focus:ring-2 focus:ring-inset focus:ring-red-500/30"
                                            data-aa-container="<?php echo $edit_list_payload_attr; ?>"
                                            aria-label="<?php echo esc_attr('Eliminar lista: ' . $list_heading); ?>"
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($parent_has_details_text) : ?>
                            <p class="mt-2 text-sm text-gray-600 whitespace-pre-wrap"><?php echo esc_html($parent_details); ?></p>
                        <?php endif; ?>
                        <?php if ($parent_has_updated) : ?>
                            <p class="mt-2 text-xs text-gray-500">
                                <time datetime="<?php echo esc_attr($parent_iso); ?>"><?php echo esc_html($parent_display); ?></time>
                            </p>
                        <?php endif; ?>
                    </section>
                    <?php if ($list_retire_in_progress) : ?>
                        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
                            Esta lista se está eliminando. Los registros que aún ves no están listos; no se puede añadir contenido. Pulsa Eliminar lista y Continuar para terminar. No está borrada del todo.
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($open_image_purges_on_records)) : ?>
                        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
                            <p class="m-0 mb-2">Hay eliminaciones de imagen incompletas. Pulsa Continuar para recuperar el protocolo.</p>
                            <ul class="m-0 p-0 list-none space-y-2">
                                <?php foreach ($open_image_purges_on_records as $open_img_run) : ?>
                                    <?php
                                    $resume_image_id = (int) ($open_img_run['target_id'] ?? 0);
                                    if ($resume_image_id < 1) {
                                        continue;
                                    }
                                    $resume_record_id = (int) ($open_img_run['record_id'] ?? 0);
                                    $resume_payload = wp_json_encode(
                                        [
                                            'id' => $resume_image_id,
                                            'record_id' => $resume_record_id,
                                        ],
                                        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                                    );
                                    if (!is_string($resume_payload) || $resume_payload === '') {
                                        continue;
                                    }
                                    ?>
                                    <li class="flex flex-wrap items-center gap-2">
                                        <span>Imagen #<?php echo esc_html((string) $resume_image_id); ?></span>
                                        <button
                                            type="button"
                                            class="aa-shell-resume-image-delete-btn inline-flex items-center px-3 py-1.5 text-xs font-semibold text-amber-950 bg-amber-100 border border-amber-300 rounded-lg hover:bg-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-500"
                                            data-aa-image="<?php echo esc_attr($resume_payload); ?>"
                                        >Continuar</button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($read_state === 'empty') : ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center" role="status">
                            <h3 class="text-base font-semibold text-gray-900 mb-2">Sin registros</h3>
                            <p class="text-sm text-gray-500">No hay registros para mostrar en este contenedor.</p>
                        </div>
                    <?php else : ?>
                        <h3 class="sr-only">Registros</h3>
                        <ul class="grid gap-4 sm:grid-cols-2" aria-label="Registros canónicos">
                            <?php foreach ($items_view as $item) : ?>
                                <?php
                                $card_title = isset($item['title']) ? (string) $item['title'] : '';
                                $card_details = array_key_exists('details', $item) ? $item['details'] : null;
                                $card_iso = isset($item['updated_at_iso']) ? (string) $item['updated_at_iso'] : '';
                                $card_display = isset($item['updated_at_display']) ? (string) $item['updated_at_display'] : '';
                                $card_record_id = isset($item['id']) ? (int) $item['id'] : 0;
                                $card_capabilities = isset($item['capabilities']) && is_array($item['capabilities'])
                                    ? $item['capabilities']
                                    : null;
                                $show_edit_record = $show_record_fab;
                                $shell_record_presentation = 'card';
                                $show_image_actions = $show_create_record_ui && !$list_retire_in_progress;
                                $card_image_summary_url = $aa_shell_resolve_card_image_summary_url(
                                    $card_capabilities,
                                    $card_record_id,
                                    $create_family_key,
                                    $create_container_id,
                                    $shell_images_read_url_uc
                                );
                                require __DIR__ . '/partials/record-card.php';
                                ?>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($has_previous || $has_next) : ?>
                            <nav class="mt-6 flex items-center justify-between gap-3" aria-label="Paginación de registros">
                                <div>
                                    <?php if ($has_previous && $prev_url !== '') : ?>
                                        <a
                                            href="<?php echo esc_url($prev_url); ?>"
                                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                                        >Anterior</a>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500" aria-current="page">
                                    <?php
                                    $total_display = ($total_pages !== null && $total_pages > 0) ? $total_pages : 1;
                                    $current_display = $page_num !== null ? $page_num : 1;
                                    echo esc_html('Página ' . $current_display . ' de ' . $total_display);
                                    ?>
                                </p>
                                <div>
                                    <?php if ($has_next && $next_url !== '') : ?>
                                        <a
                                            href="<?php echo esc_url($next_url); ?>"
                                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                                        >Siguiente</a>
                                    <?php endif; ?>
                                </div>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>

            <?php endif; ?>

        <?php else : ?>

            <?php if ($orphan_container_purges !== []) : ?>
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900" role="status">
                    <p class="m-0 mb-2">Una eliminación de lista no terminó y el recurso ya no está en el listado. Pulsa Continuar para cerrar la corrida. No se restauran datos.</p>
                    <?php foreach ($orphan_container_purges as $orphan_run) : ?>
                        <?php
                        $orphan_cid = (int) ($orphan_run['container_id'] ?? 0);
                        $orphan_fk = (string) ($orphan_run['family_key'] ?? '');
                        if ($orphan_cid < 1 || $orphan_fk === '') {
                            continue;
                        }
                        $orphan_payload = wp_json_encode(
                            [
                                'id' => $orphan_cid,
                                'title' => 'Lista en eliminación',
                                'details' => '',
                                'family_key' => $orphan_fk,
                            ],
                            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                        );
                        if (!is_string($orphan_payload) || $orphan_payload === '') {
                            continue;
                        }
                        ?>
                        <button
                            type="button"
                            class="aa-shell-delete-container-btn mt-1 inline-flex items-center px-3 py-1.5 text-xs font-semibold text-amber-900 bg-white border border-amber-300 rounded-lg hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            data-aa-container="<?php echo esc_attr($orphan_payload); ?>"
                        >Continuar</button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($read_state === 'contract_error') : ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center" role="alert">
                    <h3 class="text-base font-semibold text-gray-900 mb-2">No se pudo cargar la lista</h3>
                    <p class="text-sm text-gray-500 max-w-lg mx-auto">
                        Ocurrió un problema al preparar los contenedores. Inténtalo de nuevo más tarde.
                    </p>
                </div>

            <?php elseif ($read_state === 'read_adapter_pending') : ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" role="status">
                    <h3 class="text-base font-semibold text-gray-900 mb-2">Lectura pendiente</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        La lectura canónica de esta familia aún no está conectada.
                    </p>
                    <?php if ($qualified_key !== '') : ?>
                        <p class="text-xs text-gray-400 mb-4">
                            <code class="bg-gray-100 text-gray-700 px-2 py-1 rounded font-mono"><?php echo esc_html($qualified_key); ?></code>
                        </p>
                    <?php endif; ?>
                    <?php if ($preview_enabled && $preview_url !== '') : ?>
                        <p class="text-sm">
                            <a
                                href="<?php echo esc_url($preview_url); ?>"
                                class="text-indigo-700 font-medium hover:underline"
                            >Ver demostración del shell</a>
                        </p>
                    <?php endif; ?>
                </div>

            <?php elseif ($read_state === 'empty') : ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center" role="status">
                    <?php if ($is_all_lists_scope && $normalized_available_families === []) : ?>
                        <h3 class="text-base font-semibold text-gray-900 mb-2">Sin tipos de registros</h3>
                        <p class="text-sm text-gray-500 max-w-lg mx-auto">
                            No hay tipos de registros activados en esta instalación.
                        </p>
                        <?php if ($can_open_settings) : ?>
                            <p class="mt-4 text-sm">
                                <a
                                    href="<?php echo esc_url($settings_url); ?>"
                                    class="text-indigo-700 font-medium hover:underline"
                                >Abrir Configuración</a>
                            </p>
                        <?php endif; ?>
                    <?php elseif ($is_all_lists_scope) : ?>
                        <h3 class="text-base font-semibold text-gray-900 mb-2">Sin listas</h3>
                        <p class="text-sm text-gray-500">Aún no hay listas en los tipos de registros activados.</p>
                    <?php else : ?>
                        <h3 class="text-base font-semibold text-gray-900 mb-2">Sin contenedores</h3>
                        <p class="text-sm text-gray-500">Aún no hay contenedores en este tipo de registro.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($read_state === 'resolved_page') : ?>
                <h3 class="sr-only">Contenedores</h3>
                <ul class="grid gap-4 sm:grid-cols-2" aria-label="Contenedores canónicos">
                    <?php foreach ($items_view as $item) : ?>
                        <?php
                        $card_title = isset($item['title']) ? (string) $item['title'] : '';
                        $card_details = array_key_exists('details', $item) ? $item['details'] : null;
                        $card_iso = isset($item['updated_at_iso']) ? (string) $item['updated_at_iso'] : '';
                        $card_display = isset($item['updated_at_display']) ? (string) $item['updated_at_display'] : '';
                        $card_records_url = isset($item['records_url']) ? (string) $item['records_url'] : '';
                        $card_container_id = isset($item['id']) ? (int) $item['id'] : 0;
                        $card_family_key = isset($item['family_key']) ? (string) $item['family_key'] : $create_family_key;
                        $card_family_label = isset($item['family_label']) ? (string) $item['family_label'] : '';
                        $card_family_icon_key = isset($item['family_icon_key']) ? (string) $item['family_icon_key'] : '';
                        $card_announce_family = $is_all_lists_scope;
                        $show_edit_container = $show_container_write_ui && $card_family_key !== '';
                        $card_capabilities = ['status' => 'unavailable'];
                        if (
                            $show_edit_container
                            && $card_container_id >= 1
                            && $card_family_key !== ''
                            && class_exists('CanonicalCapabilityConfigRepository')
                            && class_exists('ReadContainerCapabilityConfigUseCase')
                            && class_exists('AA_Canonical_Capability_Registry_Bootstrap')
                            && class_exists('AA_Canonical_Core_Bootstrap')
                        ) {
                            try {
                                $card_caps_repo = new CanonicalCapabilityConfigRepository();
                                $card_caps_uc = new ReadContainerCapabilityConfigUseCase(
                                    $card_caps_repo,
                                    AA_Canonical_Core_Bootstrap::instance(),
                                    AA_Canonical_Capability_Registry_Bootstrap::bootstrap()
                                );
                                $card_caps_snap = $card_caps_uc->execute($card_family_key, $card_container_id);
                                $card_active = [];
                                $card_assigned = [];
                                foreach ($card_caps_snap->capabilities() as $snap_key => $snap_meta) {
                                    if (!is_string($snap_key) || $snap_key === '' || !is_array($snap_meta)) {
                                        continue;
                                    }
                                    if (!empty($snap_meta['assigned'])) {
                                        $card_assigned[] = $snap_key;
                                    }
                                    if (!empty($snap_meta['active'])) {
                                        $card_active[] = $snap_key;
                                    }
                                }
                                $card_capabilities = [
                                    'status' => 'ok',
                                    'active' => $card_active,
                                    'assigned' => $card_assigned,
                                ];
                            } catch (Throwable $e) {
                                $card_capabilities = ['status' => 'unavailable'];
                            }
                        }
                        require __DIR__ . '/partials/container-card.php';
                        ?>
                    <?php endforeach; ?>
                </ul>

                <?php if ($has_previous || $has_next) : ?>
                    <nav class="mt-6 flex items-center justify-between gap-3" aria-label="Paginación de contenedores">
                        <div>
                            <?php if ($has_previous && $prev_url !== '') : ?>
                                <a
                                    href="<?php echo esc_url($prev_url); ?>"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                                >Anterior</a>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-500" aria-current="page">
                            <?php
                            $total_display = ($total_pages !== null && $total_pages > 0) ? $total_pages : 1;
                            $current_display = $page_num !== null ? $page_num : 1;
                            echo esc_html('Página ' . $current_display . ' de ' . $total_display);
                            ?>
                        </p>
                        <div>
                            <?php if ($has_next && $next_url !== '') : ?>
                                <a
                                    href="<?php echo esc_url($next_url); ?>"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                                >Siguiente</a>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>

        <?php endif; ?>

    <?php else : ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center" role="status">
            <h3 class="text-base font-semibold text-gray-900 mb-2">
                <?php echo esc_html($state_label); ?>
            </h3>
            <?php if ($is_family_disabled) : ?>
                <p class="text-sm text-gray-500 max-w-lg mx-auto">
                    Este tipo de registro está desactivado.
                </p>
                <p class="text-sm text-gray-500 max-w-lg mx-auto mt-2">
                    Puedes activarlo en Ajustes, en la sección “Tipos de registros”.
                </p>
            <?php else : ?>
                <p class="text-sm text-gray-500 max-w-lg mx-auto">
                    <?php echo esc_html($route_message !== '' ? $route_message : 'Estado controlado del shell base.'); ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($show_create_ui) : ?>
<div id="aa-shell-fab-stack" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <button
        type="button"
        id="aa-shell-open-create-btn"
        class="inline-flex items-center gap-2 px-4 py-3 text-base font-bold text-white bg-violet-600 hover:bg-violet-700 active:bg-violet-800 rounded-full shadow-lg shadow-violet-600/30 hover:shadow-xl hover:shadow-violet-600/35 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-violet-500/40"
        aria-label="Nueva lista"
    >
        <span>Nueva lista</span>
    </button>
</div>
<?php endif; ?>

<?php if ($show_record_fab) : ?>
<div id="aa-shell-fab-stack" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <button
        type="button"
        id="aa-shell-open-create-record-btn"
        class="inline-flex items-center gap-2 px-4 py-3 text-base font-bold text-white bg-violet-600 hover:bg-violet-700 active:bg-violet-800 rounded-full shadow-lg shadow-violet-600/30 hover:shadow-xl hover:shadow-violet-600/35 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-violet-500/40"
        aria-label="Nuevo registro"
    >
        <span>Nuevo registro</span>
    </button>
</div>
<?php endif; ?>

<?php if ($show_container_write_ui) : ?>
    <?php
    if (!class_exists('CanonicalCreateContainerAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalCreateContainerAjax.php';
    }
    if (!class_exists('CanonicalUpdateContainerAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalUpdateContainerAjax.php';
    }
    if (!class_exists('CanonicalDeleteContainerAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalDeleteContainerAjax.php';
    }
    if (!class_exists('CanonicalCreateContainerCommand')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/CanonicalCreateContainerCommand.php';
    }
    if (!class_exists('CanonicalCapabilityConfigRepository')) {
        require_once dirname(__DIR__, 4) . '/repositories/CanonicalCapabilityConfigRepository.php';
    }
    if (!class_exists('CanonicalCapabilitySchemaNotReady')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/capabilities/CanonicalCapabilitySchemaNotReady.php';
    }
    if (!class_exists('CanonicalCapabilityPersistenceFailed')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/capabilities/CanonicalCapabilityPersistenceFailed.php';
    }
    if (!class_exists('AA_Canonical_Capability_Definition')) {
        require_once dirname(__DIR__, 4) . '/domain/canonical/class-aa-canonical-capability-definition.php';
    }
    if (!class_exists('AA_Canonical_Capability_Registry')) {
        require_once dirname(__DIR__, 4) . '/domain/canonical/class-aa-canonical-capability-registry.php';
    }
    if (!class_exists('AA_Canonical_Capability_Registry_Bootstrap')) {
        require_once dirname(__DIR__, 4) . '/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
    }
    if (!class_exists('CanonicalFamilyUnknown')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/CanonicalFamilyUnknown.php';
    }
    if (!class_exists('CanonicalFamilyNotProvisioned')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/CanonicalFamilyNotProvisioned.php';
    }
    if (!class_exists('CanonicalContainerNotFound')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/CanonicalContainerNotFound.php';
    }
    if (!class_exists('ReadContainerCapabilityConfigUseCase')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/capabilities/ReadContainerCapabilityConfigUseCase.php';
    }
    if (!class_exists('CanonicalContainerCapabilityConfigSnapshot')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/capabilities/CanonicalContainerCapabilityConfigSnapshot.php';
    }

    // Familia real del contexto (records / filtro familiar) antes que available_families:
    // lists_scope=all es solo retorno; en records no trae available_families.
    $families_for_capability_options = [];
    if ($create_family_key !== '') {
        $families_for_capability_options[] = [
            'family_key' => $create_family_key,
            'label' => $family_label !== '' ? $family_label : $create_family_key,
        ];
    } elseif ($is_all_lists_scope) {
        $families_for_capability_options = $normalized_available_families;
    }

    $family_capability_options = [];
    $capability_registry_for_shell = null;
    $capability_config_repo_for_shell = null;
    try {
        if (class_exists('AA_Canonical_Capability_Registry_Bootstrap')) {
            $capability_registry_for_shell = AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
        }
        if (class_exists('CanonicalCapabilityConfigRepository')) {
            $capability_config_repo_for_shell = new CanonicalCapabilityConfigRepository();
        }
    } catch (Throwable $e) {
        $capability_registry_for_shell = null;
        $capability_config_repo_for_shell = null;
    }

    foreach ($families_for_capability_options as $family_option_row) {
        $option_family_key = (string) ($family_option_row['family_key'] ?? '');
        if ($option_family_key === '') {
            continue;
        }
        $family_capability_options[$option_family_key] = [];
        if ($capability_config_repo_for_shell === null || $capability_registry_for_shell === null) {
            continue;
        }
        try {
            $option_family_id = $capability_config_repo_for_shell->resolve_family_id($option_family_key);
            if ($option_family_id === null) {
                continue;
            }
            $repertoire_rows = $capability_config_repo_for_shell->list_family_capabilities($option_family_id);
            foreach ($repertoire_rows as $repertoire_row) {
                $cap_key = isset($repertoire_row['capability_key'])
                    ? (string) $repertoire_row['capability_key']
                    : '';
                if ($cap_key === '') {
                    continue;
                }
                try {
                    $cap_def = $capability_registry_for_shell->get($cap_key);
                } catch (OutOfBoundsException $e) {
                    continue;
                }
                if (!$cap_def->is_ready()) {
                    continue;
                }
                if ($cap_key === 'amount') {
                    $cap_label = 'Importe';
                } elseif ($cap_key === 'whatsapp') {
                    $cap_label = 'WhatsApp';
                } elseif ($cap_key === 'phone') {
                    $cap_label = 'Teléfono';
                } elseif ($cap_key === 'email') {
                    $cap_label = 'Email';
                } elseif ($cap_key === 'dossier') {
                    $cap_label = 'Expediente';
                } elseif ($cap_key === 'images') {
                    $cap_label = 'Imágenes';
                } else {
                    $cap_label = $cap_key;
                }
                $family_capability_options[$option_family_key][] = [
                    'key' => $cap_key,
                    'label' => $cap_label,
                    'is_default' => !empty($repertoire_row['is_default']),
                ];
            }
            // Bloque contacto: whatsapp → phone → email; resto conserva orden de repertorio.
            $cap_opts = $family_capability_options[$option_family_key];
            $block_order = ['whatsapp', 'phone', 'email'];
            $block_by_key = [];
            $rest = [];
            foreach ($cap_opts as $cap_item) {
                $k = (string) ($cap_item['key'] ?? '');
                if (in_array($k, $block_order, true)) {
                    $block_by_key[$k] = $cap_item;
                } else {
                    $rest[] = $cap_item;
                }
            }
            $block = [];
            foreach ($block_order as $block_key) {
                if (isset($block_by_key[$block_key])) {
                    $block[] = $block_by_key[$block_key];
                }
            }
            $family_capability_options[$option_family_key] = array_merge($rest, $block);
        } catch (Throwable $e) {
            $family_capability_options[$option_family_key] = [];
        }
    }

    $edit_container_capabilities_boot = null;
    if ($show_edit_container_on_records) {
        $edit_container_capabilities_boot = ['status' => 'unavailable'];
        if (
            $capability_config_repo_for_shell !== null
            && $capability_registry_for_shell !== null
            && class_exists('AA_Canonical_Core_Bootstrap')
            && class_exists('ReadContainerCapabilityConfigUseCase')
        ) {
            try {
                $read_container_caps_uc = new ReadContainerCapabilityConfigUseCase(
                    $capability_config_repo_for_shell,
                    AA_Canonical_Core_Bootstrap::instance(),
                    $capability_registry_for_shell
                );
                $container_caps_snapshot = $read_container_caps_uc->execute(
                    $create_family_key,
                    $create_container_id
                );
                $active_keys = [];
                $assigned_keys = [];
                foreach ($container_caps_snapshot->capabilities() as $snap_key => $snap_meta) {
                    if (!is_string($snap_key) || $snap_key === '' || !is_array($snap_meta)) {
                        continue;
                    }
                    if (!empty($snap_meta['assigned'])) {
                        $assigned_keys[] = $snap_key;
                    }
                    if (!empty($snap_meta['active'])) {
                        $active_keys[] = $snap_key;
                    }
                }
                $edit_container_capabilities_boot = [
                    'status' => 'ok',
                    'active' => $active_keys,
                    'assigned' => $assigned_keys,
                ];
            } catch (Throwable $e) {
                $edit_container_capabilities_boot = ['status' => 'unavailable'];
            }
        }
    }
    ?>
    <div
        id="aa-shell-container-modal"
        class="fixed inset-0 z-[300] flex items-center justify-center p-4 hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="aa-shell-container-modal-title"
        aria-describedby="aa-shell-container-modal-desc"
        aria-hidden="true"
    >
        <div id="aa-shell-container-modal-backdrop" class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-center justify-between mb-2">
                <h3 id="aa-shell-container-modal-title" class="text-lg font-bold text-gray-900 leading-tight">
                    Nueva lista
                </h3>
                <button
                    type="button"
                    id="aa-shell-container-modal-close-btn"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    aria-label="Cerrar modal"
                >
                    ✕
                </button>
            </div>
            <p id="aa-shell-container-modal-desc" class="text-sm text-gray-500 mb-4">
                <?php echo esc_html($is_all_lists_scope ? 'Todas las listas' : $family_label); ?>
            </p>

            <form id="aa-shell-container-form" novalidate>
                <div
                    id="aa-shell-container-status"
                    class="hidden mb-4 p-3 rounded-lg text-xs font-medium"
                    role="status"
                    aria-live="polite"
                ></div>

                <div class="space-y-4">
                    <?php if ($show_family_select_on_create) : ?>
                        <div id="aa-shell-container-family-field">
                            <label for="aa-shell-container-family" class="block text-xs font-semibold text-gray-700 mb-1">
                                Tipo de registro <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="aa-shell-container-family"
                                name="family_key"
                                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Selecciona un tipo</option>
                                <?php foreach ($normalized_available_families as $family_option) : ?>
                                    <option value="<?php echo esc_attr($family_option['family_key']); ?>">
                                        <?php echo esc_html($family_option['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p id="aa-shell-container-family-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                        </div>
                    <?php endif; ?>
                    <div>
                        <label for="aa-shell-container-title" class="block text-xs font-semibold text-gray-700 mb-1">
                            Nombre de la lista <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="aa-shell-container-title"
                            name="title"
                            maxlength="200"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autocomplete="off"
                            required
                        />
                        <p id="aa-shell-container-title-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                    </div>
                    <div>
                        <label for="aa-shell-container-details" class="block text-xs font-semibold text-gray-700 mb-1">
                            Detalles (opcional)
                        </label>
                        <textarea
                            id="aa-shell-container-details"
                            name="details"
                            rows="3"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                    <details class="rounded-lg border border-gray-200 bg-gray-50/60 open:bg-white">
                        <summary class="cursor-pointer select-none px-3 py-2 text-xs font-semibold text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-lg">
                            Campos y funciones
                        </summary>
                        <div class="px-3 pb-3 pt-1 space-y-2">
                            <p
                                id="aa-shell-container-capabilities-status"
                                class="hidden text-xs font-medium text-amber-900"
                                role="status"
                                aria-live="polite"
                            ></p>
                            <div
                                id="aa-shell-container-capabilities"
                                class="space-y-2"
                            ></div>
                        </div>
                    </details>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        id="aa-shell-container-modal-cancel-btn"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        id="aa-shell-container-submit-btn"
                        class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Crear lista
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        id="aa-shell-delete-container-modal"
        class="fixed inset-0 z-[300] flex items-center justify-center p-4 hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="aa-shell-delete-container-modal-title"
        aria-describedby="aa-shell-delete-container-message"
        aria-hidden="true"
    >
        <div id="aa-shell-delete-container-modal-backdrop" class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-center justify-between mb-2">
                <h3 id="aa-shell-delete-container-modal-title" class="text-lg font-bold text-gray-900 leading-tight">
                    Eliminar lista
                </h3>
                <button
                    type="button"
                    id="aa-shell-delete-container-modal-close-btn"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    aria-label="Cerrar modal"
                >
                    ✕
                </button>
            </div>
            <p id="aa-shell-delete-container-message" class="text-sm text-gray-600 mb-4">
                Se eliminará permanentemente “<span id="aa-shell-delete-container-title"></span>”, todos los registros que contiene y sus imágenes asociadas. Esta eliminación es irremediable.
            </p>
            <div
                id="aa-shell-delete-container-status"
                class="hidden mb-4 p-3 rounded-lg text-xs font-medium"
                role="status"
                aria-live="polite"
            ></div>
            <div class="mt-6 flex items-center justify-end gap-3 flex-wrap">
                <button
                    type="button"
                    id="aa-shell-delete-container-reload-btn"
                    class="hidden px-4 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    Recargar listas
                </button>
                <button
                    type="button"
                    id="aa-shell-delete-container-modal-cancel-btn"
                    class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    Cerrar
                </button>
                <button
                    type="button"
                    id="aa-shell-delete-container-abort-btn"
                    class="hidden px-4 py-2 text-xs font-medium text-amber-900 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                >
                    Cancelar eliminación
                </button>
                <button
                    type="button"
                    id="aa-shell-delete-container-confirm-btn"
                    class="px-4 py-2 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Eliminar lista
                </button>
            </div>
        </div>
    </div>

    <script>
    window.AA_CANONICAL_SHELL_CONTAINER_FORM = {
        ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        createAction: <?php echo wp_json_encode(CanonicalCreateContainerAjax::ACTION); ?>,
        createNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalCreateContainerAjax::NONCE_ACTION)); ?>,
        updateAction: <?php echo wp_json_encode(CanonicalUpdateContainerAjax::ACTION); ?>,
        updateNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalUpdateContainerAjax::NONCE_ACTION)); ?>,
        deleteAction: <?php echo wp_json_encode(CanonicalDeleteContainerAjax::ACTION); ?>,
        deleteNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalDeleteContainerAjax::NONCE_ACTION)); ?>,
        familyKey: <?php echo wp_json_encode($create_family_key); ?>,
        listsScope: <?php echo wp_json_encode($is_all_lists_scope ? 'all' : ''); ?>,
        shellView: <?php echo wp_json_encode($is_records ? 'records' : 'containers'); ?>,
        page: <?php echo wp_json_encode($page_num !== null && $page_num > 1 ? $page_num : null); ?>,
        containersPage: <?php echo wp_json_encode($containers_page_num !== null && $containers_page_num > 1 ? $containers_page_num : null); ?>,
        availableFamilies: <?php echo wp_json_encode($normalized_available_families); ?>,
        requireFamilySelect: <?php echo $show_family_select_on_create ? 'true' : 'false'; ?>,
        maxTitleLength: <?php echo (int) CanonicalCreateContainerCommand::MAX_TITLE_LENGTH; ?>,
        familyCapabilityOptions: <?php echo wp_json_encode($family_capability_options); ?>,
        editContainerCapabilities: <?php echo wp_json_encode($edit_container_capabilities_boot); ?>
    };
    </script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/canonical-shell-container-form.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/canonical-shell-container-form.js'); ?>"></script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/canonical-shell-container-options.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/canonical-shell-container-options.js'); ?>"></script>
<?php endif; ?>

<?php if ($show_record_fab) : ?>
    <?php
    if (!class_exists('CanonicalCreateRecordAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalCreateRecordAjax.php';
    }
    if (!class_exists('CanonicalUpdateRecordAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalUpdateRecordAjax.php';
    }
    if (!class_exists('CanonicalDeleteRecordAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalDeleteRecordAjax.php';
    }
    if (!class_exists('CanonicalDeleteRecordImageAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalDeleteRecordImageAjax.php';
    }
    if (!class_exists('CanonicalAttachRecordImageAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalAttachRecordImageAjax.php';
    }
    if (!class_exists('CanonicalSignRecordImageReadAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalSignRecordImageReadAjax.php';
    }
    if (!class_exists('CanonicalOpenContactDossierAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalOpenContactDossierAjax.php';
    }
    if (!class_exists('CanonicalCreateRecordCommand')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/CanonicalCreateRecordCommand.php';
    }
    ?>
    <div
        id="aa-shell-record-modal"
        class="fixed inset-0 z-[300] flex items-center justify-center p-4 hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="aa-shell-record-modal-title"
        aria-describedby="aa-shell-record-modal-desc"
        aria-hidden="true"
    >
        <div id="aa-shell-record-modal-backdrop" class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-center justify-between mb-2">
                <h3 id="aa-shell-record-modal-title" class="text-lg font-bold text-gray-900 leading-tight">
                    Nuevo registro
                </h3>
                <button
                    type="button"
                    id="aa-shell-record-modal-close-btn"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    aria-label="Cerrar modal"
                >
                    ✕
                </button>
            </div>
            <p id="aa-shell-record-modal-desc" class="text-sm text-gray-500 mb-4">
                <?php echo esc_html($create_container_title !== '' ? $create_container_title : 'Contenedor'); ?>
            </p>

            <form id="aa-shell-record-form" novalidate>
                <div
                    id="aa-shell-record-status"
                    class="hidden mb-4 p-3 rounded-lg text-xs font-medium"
                    role="status"
                    aria-live="polite"
                ></div>

                <div class="space-y-4">
                    <div>
                        <label for="aa-shell-record-title" class="block text-xs font-semibold text-gray-700 mb-1">
                            Título del registro <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="aa-shell-record-title"
                            name="title"
                            maxlength="200"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autocomplete="off"
                            required
                        />
                        <p id="aa-shell-record-title-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                    </div>
                    <?php
                    $whatsapp_offered = !empty($capability_contributions['whatsapp']['offered']);
                    $phone_offered = !empty($capability_contributions['phone']['offered']);
                    $email_offered = !empty($capability_contributions['email']['offered']);
                    if ($whatsapp_offered) :
                        $whatsapp_country_options = AA_Canonical_Phone_Normalizer::country_options();
                        ?>
                    <div
                        id="aa-shell-record-whatsapp-field"
                        class="aa-shell-capability-field hidden"
                        data-aa-capability-key="whatsapp"
                        hidden
                    >
                        <label for="aa-shell-record-whatsapp" class="block text-xs font-semibold text-gray-700 mb-1">
                            WhatsApp (opcional)
                        </label>
                        <div class="aa-shell-whatsapp-row flex gap-2">
                            <select
                                id="aa-shell-record-whatsapp-country"
                                name="whatsapp_country"
                                class="aa-form-country-select shrink-0 text-sm border border-gray-300 rounded-lg px-2 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-50 disabled:text-gray-500"
                                disabled
                            >
                                <?php foreach ($whatsapp_country_options as $whatsapp_opt) : ?>
                                    <option value="<?php echo esc_attr((string) $whatsapp_opt['code']); ?>">
                                        <?php echo esc_html((string) $whatsapp_opt['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input
                                type="tel"
                                id="aa-shell-record-whatsapp"
                                name="whatsapp_national"
                                inputmode="tel"
                                autocomplete="tel-national"
                                class="aa-form-input-phone min-w-0 flex-1 text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-50 disabled:text-gray-500"
                                disabled
                            />
                        </div>
                        <p id="aa-shell-record-whatsapp-help" class="hidden mt-1 text-xs text-gray-500"></p>
                        <p id="aa-shell-record-whatsapp-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                        <p id="aa-shell-record-whatsapp-unavailable" class="hidden mt-1 text-xs text-amber-800 font-medium" role="status">
                            WhatsApp no está disponible ahora. Puedes guardar el título y los detalles.
                        </p>
                    </div>
                    <?php endif; ?>
                    <?php
                    if ($phone_offered) :
                        $phone_country_options = AA_Canonical_Phone_Normalizer::country_options();
                        ?>
                    <div
                        id="aa-shell-record-phone-field"
                        class="aa-shell-capability-field hidden"
                        data-aa-capability-key="phone"
                        hidden
                    >
                        <label for="aa-shell-record-phone" class="block text-xs font-semibold text-gray-700 mb-1">
                            Teléfono (opcional)
                        </label>
                        <div class="aa-shell-phone-row flex gap-2">
                            <select
                                id="aa-shell-record-phone-country"
                                name="phone_country"
                                class="aa-form-country-select shrink-0 text-sm border border-gray-300 rounded-lg px-2 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-50 disabled:text-gray-500"
                                disabled
                            >
                                <?php foreach ($phone_country_options as $phone_opt) : ?>
                                    <option value="<?php echo esc_attr((string) $phone_opt['code']); ?>">
                                        <?php echo esc_html((string) $phone_opt['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input
                                type="tel"
                                id="aa-shell-record-phone"
                                name="phone_national"
                                inputmode="tel"
                                autocomplete="tel-national"
                                class="aa-form-input-phone min-w-0 flex-1 text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-50 disabled:text-gray-500"
                                disabled
                            />
                        </div>
                        <p id="aa-shell-record-phone-help" class="hidden mt-1 text-xs text-gray-500"></p>
                        <p id="aa-shell-record-phone-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                        <p id="aa-shell-record-phone-unavailable" class="hidden mt-1 text-xs text-amber-800 font-medium" role="status">
                            El teléfono no está disponible ahora. Puedes guardar el título y los detalles.
                        </p>
                    </div>
                    <?php endif; ?>
                    <?php if ($email_offered) : ?>
                    <div
                        id="aa-shell-record-email-field"
                        class="aa-shell-capability-field hidden"
                        data-aa-capability-key="email"
                        hidden
                    >
                        <label for="aa-shell-record-email" class="block text-xs font-semibold text-gray-700 mb-1">
                            Email (opcional)
                        </label>
                        <input
                            type="email"
                            id="aa-shell-record-email"
                            name="email"
                            autocomplete="email"
                            inputmode="email"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-50 disabled:text-gray-500"
                            disabled
                        />
                        <p id="aa-shell-record-email-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                        <p id="aa-shell-record-email-unavailable" class="hidden mt-1 text-xs text-amber-800 font-medium" role="status">
                            El correo no está disponible ahora. Puedes guardar el título y los detalles.
                        </p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label for="aa-shell-record-details" class="block text-xs font-semibold text-gray-700 mb-1">
                            Detalles (opcional)
                        </label>
                        <textarea
                            id="aa-shell-record-details"
                            name="details"
                            rows="3"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                    <div
                        id="aa-shell-record-capability-fields"
                        data-aa-capability-fields
                        class="space-y-4"
                    >
                        <?php
                        $amount_offered = !empty($capability_contributions['amount']['offered']);
                        $images_offered = !empty($capability_contributions['images']['offered']);
                        if ($amount_offered) :
                            ?>
                        <div
                            id="aa-shell-record-amount-field"
                            class="aa-shell-capability-field hidden"
                            data-aa-capability-key="amount"
                            hidden
                        >
                            <label for="aa-shell-record-amount" class="block text-xs font-semibold text-gray-700 mb-1">
                                Importe (opcional)
                            </label>
                            <input
                                type="text"
                                id="aa-shell-record-amount"
                                name="amount"
                                inputmode="decimal"
                                autocomplete="off"
                                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-50 disabled:text-gray-500"
                            />
                            <p id="aa-shell-record-amount-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                            <p id="aa-shell-record-amount-unavailable" class="hidden mt-1 text-xs text-amber-800 font-medium" role="status">
                                El importe no está disponible ahora. Puedes guardar el título y los detalles.
                            </p>
                        </div>
                        <?php endif; ?>
                        <?php if ($images_offered) : ?>
                        <div
                            id="aa-shell-record-images-field"
                            class="aa-shell-capability-field hidden"
                            data-aa-capability-key="images"
                            hidden
                        >
                            <span class="block text-xs font-semibold text-gray-700 mb-1">
                                Imagen (opcional)
                            </span>
                            <div class="flex flex-wrap items-center gap-2">
                                <label
                                    id="aa-shell-record-image-trigger"
                                    for="aa-shell-record-image-input"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg cursor-pointer hover:bg-indigo-100 focus-within:ring-2 focus-within:ring-indigo-500"
                                >
                                    Adjuntar imagen
                                </label>
                                <input
                                    type="file"
                                    id="aa-shell-record-image-input"
                                    accept="image/jpeg,image/png,image/webp,image/*"
                                    class="sr-only"
                                    disabled
                                />
                                <button
                                    type="button"
                                    id="aa-shell-record-image-remove"
                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-600 hover:text-gray-900"
                                >
                                    Quitar
                                </button>
                                <button
                                    type="button"
                                    id="aa-shell-record-image-retry"
                                    class="hidden inline-flex items-center px-2 py-1 text-xs font-semibold text-amber-900 bg-amber-50 border border-amber-200 rounded hover:bg-amber-100"
                                    hidden
                                    disabled
                                >
                                    Reintentar imagen
                                </button>
                            </div>
                            <div
                                id="aa-shell-record-image-preview-wrap"
                                class="hidden mt-2 flex items-start gap-3"
                                hidden
                            >
                                <img
                                    id="aa-shell-record-image-preview"
                                    class="rounded border border-gray-200 max-w-[5rem] max-h-[5rem] object-cover"
                                    alt=""
                                    width="80"
                                    height="80"
                                />
                                <p id="aa-shell-record-image-preview-meta" class="text-xs text-gray-600 m-0"></p>
                            </div>
                            <p id="aa-shell-record-images-status" class="hidden mt-1 text-xs text-gray-600 font-medium" role="status"></p>
                            <p id="aa-shell-record-images-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        id="aa-shell-record-modal-cancel-btn"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        id="aa-shell-record-submit-btn"
                        class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Crear registro
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        id="aa-shell-delete-record-modal"
        class="fixed inset-0 z-[300] flex items-center justify-center p-4 hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="aa-shell-delete-record-modal-title"
        aria-describedby="aa-shell-delete-record-message"
        aria-hidden="true"
    >
        <div id="aa-shell-delete-record-modal-backdrop" class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-center justify-between mb-2">
                <h3 id="aa-shell-delete-record-modal-title" class="text-lg font-bold text-gray-900 leading-tight">
                    Eliminar registro
                </h3>
                <button
                    type="button"
                    id="aa-shell-delete-record-modal-close-btn"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                    aria-label="Cerrar confirmación de eliminación"
                >
                    ✕
                </button>
            </div>
            <p id="aa-shell-delete-record-message" class="text-sm text-gray-600 mb-4">
                Se eliminará permanentemente “<span id="aa-shell-delete-record-title"></span>” y sus imágenes asociadas. Esta eliminación es irremediable.
            </p>

            <div
                id="aa-shell-delete-record-status"
                class="hidden mb-4 p-3 rounded-lg text-xs font-medium"
                role="status"
                aria-live="polite"
            ></div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <button
                    type="button"
                    id="aa-shell-delete-record-reload-btn"
                    class="hidden px-4 py-2 text-xs font-semibold text-amber-900 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                >
                    Recargar lista
                </button>
                <button
                    type="button"
                    id="aa-shell-delete-record-modal-cancel-btn"
                    class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    Cerrar
                </button>
                <button
                    type="button"
                    id="aa-shell-delete-record-abort-btn"
                    class="hidden px-4 py-2 text-xs font-medium text-amber-900 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                >
                    Cancelar eliminación
                </button>
                <button
                    type="button"
                    id="aa-shell-delete-record-confirm-btn"
                    class="px-4 py-2 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Eliminar registro
                </button>
            </div>
        </div>
    </div>

    <div
        id="aa-shell-delete-image-modal"
        class="fixed inset-0 z-[60] hidden"
        aria-hidden="true"
        role="dialog"
        aria-modal="true"
        aria-labelledby="aa-shell-delete-image-modal-title"
    >
        <div id="aa-shell-delete-image-modal-backdrop" class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-xl bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <h3 id="aa-shell-delete-image-modal-title" class="text-lg font-bold text-gray-900 leading-tight">
                            Eliminar imagen
                        </h3>
                        <button
                            type="button"
                            id="aa-shell-delete-image-modal-close-btn"
                            class="text-gray-400 hover:text-gray-600 p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                            aria-label="Cerrar confirmación de eliminación de imagen"
                        >
                            ✕
                        </button>
                    </div>
                    <p id="aa-shell-delete-image-message" class="text-sm text-gray-600 mb-4">
                        Se eliminará la imagen #<span id="aa-shell-delete-image-id-label"></span> del registro. El registro y el resto de imágenes se conservan. La limpieza remota de objetos asociados continúa en segundo plano.
                    </p>

                    <div
                        id="aa-shell-delete-image-status"
                        class="hidden mb-4 p-3 rounded-lg text-xs font-medium"
                        role="status"
                        aria-live="polite"
                    ></div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <button
                            type="button"
                            id="aa-shell-delete-image-reload-btn"
                            class="hidden px-4 py-2 text-xs font-semibold text-amber-900 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        >
                            Recargar lista
                        </button>
                        <button
                            type="button"
                            id="aa-shell-delete-image-modal-cancel-btn"
                            class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            Cerrar
                        </button>
                        <button
                            type="button"
                            id="aa-shell-delete-image-abort-btn"
                            class="hidden px-4 py-2 text-xs font-medium text-amber-900 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        >
                            Cancelar eliminación
                        </button>
                        <button
                            type="button"
                            id="aa-shell-delete-image-confirm-btn"
                            class="px-4 py-2 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Eliminar imagen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        id="aa-shell-image-viewer-modal"
        class="fixed inset-0 z-[310] flex items-center justify-center p-4 hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="aa-shell-image-viewer-modal-title"
        aria-hidden="true"
    >
        <div id="aa-shell-image-viewer-modal-backdrop" class="fixed inset-0 bg-black/60 transition-opacity" aria-hidden="true"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-3xl w-full p-4 z-10">
            <div class="flex items-center justify-between mb-2">
                <h3 id="aa-shell-image-viewer-modal-title" class="text-lg font-bold text-gray-900 leading-tight">
                    Imagen
                </h3>
                <button
                    type="button"
                    id="aa-shell-image-viewer-modal-close-btn"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    aria-label="Cerrar visor"
                >
                    ✕
                </button>
            </div>
            <div id="aa-shell-image-viewer-body" class="aa-shell-image-viewer min-h-[12rem] flex items-center justify-center">
                <p id="aa-shell-image-viewer-status" class="text-sm text-gray-500 m-0" role="status">Cargando imagen…</p>
            </div>
        </div>
    </div>

    <script>
    window.AA_CANONICAL_SHELL_RECORD_FORM = {
        ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        createAction: <?php echo wp_json_encode(CanonicalCreateRecordAjax::ACTION); ?>,
        createNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalCreateRecordAjax::NONCE_ACTION)); ?>,
        updateAction: <?php echo wp_json_encode(CanonicalUpdateRecordAjax::ACTION); ?>,
        updateNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalUpdateRecordAjax::NONCE_ACTION)); ?>,
        deleteAction: <?php echo wp_json_encode(CanonicalDeleteRecordAjax::ACTION); ?>,
        deleteNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalDeleteRecordAjax::NONCE_ACTION)); ?>,
        deleteImageAction: <?php echo wp_json_encode(CanonicalDeleteRecordImageAjax::ACTION); ?>,
        deleteImageNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalDeleteRecordImageAjax::NONCE_ACTION)); ?>,
        attachImageAction: <?php echo wp_json_encode(CanonicalAttachRecordImageAjax::ACTION); ?>,
        attachImageNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalAttachRecordImageAjax::NONCE_ACTION)); ?>,
        signReadAction: <?php echo wp_json_encode(CanonicalSignRecordImageReadAjax::ACTION); ?>,
        signReadNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalSignRecordImageReadAjax::NONCE_ACTION)); ?>,
        familyKey: <?php echo wp_json_encode($create_family_key); ?>,
        containerId: <?php echo (int) $create_container_id; ?>,
        listsScope: <?php echo wp_json_encode($is_all_lists_scope ? 'all' : ''); ?>,
        page: <?php echo wp_json_encode($page_num !== null && $page_num > 1 ? $page_num : null); ?>,
        containersPage: <?php echo wp_json_encode($containers_page_num !== null && $containers_page_num > 1 ? $containers_page_num : null); ?>,
        maxTitleLength: <?php echo (int) CanonicalCreateRecordCommand::MAX_TITLE_LENGTH; ?>,
        capabilityContributions: <?php
            $boot_caps = is_array($view) && isset($view['capability_contributions']) && is_array($view['capability_contributions'])
                ? $view['capability_contributions']
                : [];
            echo wp_json_encode($boot_caps);
        ?>
    };
    </script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-amount-field.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-amount-field.js'); ?>"></script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-whatsapp-field.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-whatsapp-field.js'); ?>"></script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-phone-field.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-phone-field.js'); ?>"></script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-email-field.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-email-field.js'); ?>"></script>
    <script>
    window.AA_CANONICAL_SHELL_DOSSIER = {
        ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        action: <?php echo wp_json_encode(CanonicalOpenContactDossierAjax::ACTION); ?>,
        nonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalOpenContactDossierAjax::NONCE_ACTION)); ?>,
        familyKey: <?php echo wp_json_encode($create_family_key); ?>,
        containerId: <?php echo (int) $create_container_id; ?>,
        listsScope: <?php echo wp_json_encode($is_all_lists_scope ? 'all' : ''); ?>,
        page: <?php echo wp_json_encode($page_num !== null && $page_num > 1 ? $page_num : null); ?>,
        containersPage: <?php echo wp_json_encode($containers_page_num !== null && $containers_page_num > 1 ? $containers_page_num : null); ?>
    };
    </script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-dossier-action.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-dossier-action.js'); ?>"></script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-field.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-field.js'); ?>"></script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-gallery.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-gallery.js'); ?>"></script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js'); ?>"></script>
<?php endif; ?>
