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
$variant_label = is_array($view) ? (string) ($view['variant_label'] ?? '') : '';
$qualified_key = is_array($view) ? (string) ($view['qualified_key'] ?? '') : '';
$read_state = is_array($view) ? (string) ($view['read_state'] ?? '') : '';
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

if (
    $family_label === ''
    && isset($aa_canonical_family)
    && $aa_canonical_family instanceof AA_Canonical_Family_Definition
    && isset($aa_canonical_variant)
    && $aa_canonical_variant instanceof AA_Canonical_Variant_Definition
) {
    $family_label = $aa_canonical_family->label();
    $variant_label = $aa_canonical_variant->label();
    $qualified_key = $aa_canonical_variant->qualified_key();
}

$page_title = 'Shell canónico';
if ($is_preview) {
    $page_title = 'Shell canónico · Demostración';
} elseif ($family_label !== '') {
    $page_title = 'Shell canónico · ' . $family_label;
}

$state_labels = [
    'missing_identity'       => 'Desarrollo',
    'incomplete_identity'    => 'Identidad incompleta',
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
$create_variant_key = '';
if (
    isset($aa_canonical_family)
    && $aa_canonical_family instanceof AA_Canonical_Family_Definition
    && isset($aa_canonical_variant)
    && $aa_canonical_variant instanceof AA_Canonical_Variant_Definition
) {
    $create_family_key = $aa_canonical_family->key();
    $create_variant_key = $aa_canonical_variant->key();
}

$show_create_ui = $show_read_ui
    && !$is_preview
    && !$is_records
    && $route_state === 'resolved'
    && in_array($read_state, ['empty', 'resolved_page'], true)
    && $create_family_key !== ''
    && $create_variant_key !== '';

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
    && $create_variant_key !== ''
    && $create_container_id >= 1;
?>

<div
    id="aa-canonical-shell-root"
    class="max-w-5xl mx-auto py-2"
    data-aa-page-title="<?php echo esc_attr($page_title); ?>"
    data-aa-shell-route-state="<?php echo esc_attr($route_state); ?>"
    data-aa-shell-view="<?php echo esc_attr($shell_view); ?>"
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
    <header class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 leading-tight">
                    Shell canónico
                </h2>
                <?php if ($show_read_ui && $family_label !== '') : ?>
                    <p class="text-sm text-gray-500 mt-1">
                        <?php echo esc_html($family_label); ?>
                        <?php if ($variant_label !== '') : ?>
                            · <?php echo esc_html($variant_label); ?>
                        <?php endif; ?>
                    </p>
                <?php else : ?>
                    <p class="text-sm text-gray-500 mt-1">
                        Módulo paralelo provisional. No sustituye la UI de familias existentes.
                    </p>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                    <?php echo esc_html($is_preview ? 'Demostración' : $state_label); ?>
                </span>
                <?php if ($show_create_ui) : ?>
                    <button
                        type="button"
                        id="aa-shell-open-create-btn"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Nueva lista</span>
                    </button>
                <?php endif; ?>
                <?php if ($show_create_record_ui) : ?>
                    <button
                        type="button"
                        id="aa-shell-open-create-record-btn"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Nuevo registro</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($is_preview && $preview_banner !== '') : ?>
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
            <?php echo esc_html($preview_banner); ?>
        </div>
    <?php endif; ?>

    <?php if ($show_read_ui) : ?>

        <?php if ($is_records) : ?>

            <?php if ($back_url !== '') : ?>
                <p class="mb-4">
                    <a
                        href="<?php echo esc_url($back_url); ?>"
                        class="inline-flex items-center text-sm font-medium text-indigo-700 hover:underline"
                    >Volver a contenedores</a>
                </p>
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
                ?>
                <section class="mb-4" aria-labelledby="aa-shell-parent-heading">
                    <h3 id="aa-shell-parent-heading" class="text-lg font-semibold text-gray-900">
                        <?php echo esc_html($parent_title !== '' ? $parent_title : 'Contenedor'); ?>
                    </h3>
                    <?php if (is_string($parent_details) && $parent_details !== '') : ?>
                        <p class="mt-2 text-sm text-gray-600 whitespace-pre-wrap"><?php echo esc_html($parent_details); ?></p>
                    <?php endif; ?>
                    <?php if ($parent_iso !== '' && $parent_display !== '') : ?>
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
                            $show_edit_record = $show_create_record_ui;
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
                    <h3 class="text-base font-semibold text-gray-900 mb-2">Sin contenedores</h3>
                        <p class="text-sm text-gray-500">Aún no hay contenedores en este tipo de registro.</p>
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
    <?php
    if (!class_exists('CanonicalCreateContainerAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalCreateContainerAjax.php';
    }
    if (!class_exists('CanonicalCreateContainerCommand')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/CanonicalCreateContainerCommand.php';
    }
    ?>
    <div
        id="aa-shell-create-modal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="aa-shell-create-modal-title"
        aria-describedby="aa-shell-create-modal-desc"
        aria-hidden="true"
    >
        <div id="aa-shell-create-modal-backdrop" class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-center justify-between mb-2">
                <h3 id="aa-shell-create-modal-title" class="text-lg font-bold text-gray-900 leading-tight">
                    Nueva lista
                </h3>
                <button
                    type="button"
                    id="aa-shell-create-modal-close-btn"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    aria-label="Cerrar modal"
                >
                    ✕
                </button>
            </div>
            <p id="aa-shell-create-modal-desc" class="text-sm text-gray-500 mb-4">
                <?php echo esc_html($family_label); ?>
                <?php if ($variant_label !== '') : ?>
                    · <?php echo esc_html($variant_label); ?>
                <?php endif; ?>
            </p>

            <form id="aa-shell-create-form" novalidate>
                <div
                    id="aa-shell-create-status"
                    class="hidden mb-4 p-3 rounded-lg text-xs font-medium"
                    role="status"
                    aria-live="polite"
                ></div>

                <div class="space-y-4">
                    <div>
                        <label for="aa-shell-create-title" class="block text-xs font-semibold text-gray-700 mb-1">
                            Nombre de la lista <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="aa-shell-create-title"
                            name="title"
                            maxlength="200"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autocomplete="off"
                            required
                        />
                        <p id="aa-shell-create-title-error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                    </div>
                    <div>
                        <label for="aa-shell-create-details" class="block text-xs font-semibold text-gray-700 mb-1">
                            Detalles (opcional)
                        </label>
                        <textarea
                            id="aa-shell-create-details"
                            name="details"
                            rows="3"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        id="aa-shell-create-modal-cancel-btn"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        id="aa-shell-create-submit-btn"
                        class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Crear lista
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    window.AA_CANONICAL_SHELL_CREATE = {
        ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        action: <?php echo wp_json_encode(CanonicalCreateContainerAjax::ACTION); ?>,
        nonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalCreateContainerAjax::NONCE_ACTION)); ?>,
        familyKey: <?php echo wp_json_encode($create_family_key); ?>,
        variantKey: <?php echo wp_json_encode($create_variant_key); ?>,
        maxTitleLength: <?php echo (int) CanonicalCreateContainerCommand::MAX_TITLE_LENGTH; ?>
    };
    </script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/canonical-shell-create-container.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/canonical-shell-create-container.js'); ?>"></script>
<?php endif; ?>

<?php if ($show_create_record_ui) : ?>
    <?php
    if (!class_exists('CanonicalCreateRecordAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalCreateRecordAjax.php';
    }
    if (!class_exists('CanonicalUpdateRecordAjax')) {
        require_once dirname(__DIR__, 4) . '/http/ajax/CanonicalUpdateRecordAjax.php';
    }
    if (!class_exists('CanonicalCreateRecordCommand')) {
        require_once dirname(__DIR__, 4) . '/application/canonical/CanonicalCreateRecordCommand.php';
    }
    ?>
    <div
        id="aa-shell-record-modal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden"
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

    <script>
    window.AA_CANONICAL_SHELL_RECORD_FORM = {
        ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        createAction: <?php echo wp_json_encode(CanonicalCreateRecordAjax::ACTION); ?>,
        createNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalCreateRecordAjax::NONCE_ACTION)); ?>,
        updateAction: <?php echo wp_json_encode(CanonicalUpdateRecordAjax::ACTION); ?>,
        updateNonce: <?php echo wp_json_encode(wp_create_nonce(CanonicalUpdateRecordAjax::NONCE_ACTION)); ?>,
        familyKey: <?php echo wp_json_encode($create_family_key); ?>,
        variantKey: <?php echo wp_json_encode($create_variant_key); ?>,
        containerId: <?php echo (int) $create_container_id; ?>,
        maxTitleLength: <?php echo (int) CanonicalCreateRecordCommand::MAX_TITLE_LENGTH; ?>
    };
    </script>
    <script src="<?php echo function_exists('aa_asset_url')
        ? aa_asset_url('includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js')
        : esc_url((defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : '') . 'includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js'); ?>"></script>
<?php endif; ?>
