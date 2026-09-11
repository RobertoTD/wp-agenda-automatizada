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
$containers_page_num = is_array($view) && isset($view['containers_page'])
    ? (int) $view['containers_page']
    : null;

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

$show_container_write_ui = $show_create_ui;

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
                $list_heading = $parent_title !== '' ? $parent_title : 'Contenedor';
                ?>

                <?php if ($is_records_fill) : ?>
                    <section
                        class="aa-shell-list-panel bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
                        aria-labelledby="aa-shell-parent-heading"
                    >
                        <header class="aa-shell-list-panel-header px-4 py-3 border-b border-gray-100 bg-white">
                            <h2 id="aa-shell-parent-heading" class="text-lg font-semibold text-gray-900 leading-snug truncate">
                                <?php echo esc_html($list_heading); ?>
                            </h2>
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
                                    <button
                                        type="button"
                                        id="aa-shell-list-details-toggle"
                                        class="shrink-0 text-sm font-medium text-indigo-700 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded"
                                        aria-expanded="false"
                                        aria-controls="aa-shell-list-details"
                                    >Detalles</button>
                                <?php endif; ?>
                            </div>
                        </header>
                        <div class="aa-shell-list-panel-body p-4<?php echo $show_create_record_ui ? ' aa-shell-list-panel-body--fab' : ''; ?>">
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
                                        $show_edit_record = $show_create_record_ui;
                                        $shell_record_presentation = 'compact';
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
                        <h3 id="aa-shell-parent-heading" class="text-lg font-semibold text-gray-900">
                            <?php echo esc_html($list_heading); ?>
                        </h3>
                        <?php if ($parent_has_details_text) : ?>
                            <p class="mt-2 text-sm text-gray-600 whitespace-pre-wrap"><?php echo esc_html($parent_details); ?></p>
                        <?php endif; ?>
                        <?php if ($parent_has_updated) : ?>
                            <p class="mt-2 text-xs text-gray-500">
                                <time datetime="<?php echo esc_attr($parent_iso); ?>"><?php echo esc_html($parent_display); ?></time>
                            </p>
                        <?php endif; ?>
                    </section>

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
                                $show_edit_record = $show_create_record_ui;
                                $shell_record_presentation = 'card';
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
                        $card_family_label = ($is_all_lists_scope && isset($item['family_label']))
                            ? (string) $item['family_label']
                            : '';
                        $show_edit_container = $show_container_write_ui && $card_family_key !== '';
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

<?php if ($show_create_record_ui) : ?>
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
                Se eliminará permanentemente “<span id="aa-shell-delete-container-title"></span>” y todos los registros que contiene. Esta acción no se puede deshacer.
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
                    Cancelar
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
        page: <?php echo wp_json_encode($page_num !== null && $page_num > 1 ? $page_num : null); ?>,
        availableFamilies: <?php echo wp_json_encode($normalized_available_families); ?>,
        requireFamilySelect: <?php echo $show_family_select_on_create ? 'true' : 'false'; ?>,
        maxTitleLength: <?php echo (int) CanonicalCreateContainerCommand::MAX_TITLE_LENGTH; ?>
    };
    </script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/canonical-shell-container-form.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/canonical-shell-container-form.js'); ?>"></script>
<?php endif; ?>

<?php if ($show_create_record_ui) : ?>
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
                        $capability_contributions = is_array($view) && isset($view['capability_contributions']) && is_array($view['capability_contributions'])
                            ? $view['capability_contributions']
                            : [];
                        $amount_offered = !empty($capability_contributions['amount']['offered']);
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
                Se eliminará permanentemente “<span id="aa-shell-delete-record-title"></span>”. Esta acción no se puede deshacer.
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
                    Cancelar
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

    <script>
    window.AA_CANONICAL_SHELL_RECORD_FORM = {
        ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        createAction: <?php echo wp_json_encode(CanonicalCreateRecordAjax::ACTION); ?>,
        createNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalCreateRecordAjax::NONCE_ACTION)); ?>,
        updateAction: <?php echo wp_json_encode(CanonicalUpdateRecordAjax::ACTION); ?>,
        updateNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalUpdateRecordAjax::NONCE_ACTION)); ?>,
        deleteAction: <?php echo wp_json_encode(CanonicalDeleteRecordAjax::ACTION); ?>,
        deleteNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalDeleteRecordAjax::NONCE_ACTION)); ?>,
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
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js'); ?>"></script>
<?php endif; ?>
