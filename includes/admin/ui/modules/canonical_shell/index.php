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
    'missing_identity'     => 'Desarrollo',
    'incomplete_identity'  => 'Identidad incompleta',
    'invalid_request'      => 'Solicitud no válida',
    'not_found'            => 'No encontrado',
    'preview_unavailable'  => 'No disponible',
    'preview'              => 'Demostración',
    'resolved'             => 'Resuelto',
];
$state_label = $state_labels[$route_state] ?? 'Estado';

$show_read_ui = is_array($view) && in_array($route_state, ['resolved', 'preview'], true);
$is_records = ($shell_view === 'records');
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
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                <?php echo esc_html($is_preview ? 'Demostración' : $state_label); ?>
            </span>
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
                    <p class="text-sm text-gray-500">No hay contenedores para mostrar en esta vista.</p>
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
            <p class="text-sm text-gray-500 max-w-lg mx-auto">
                <?php echo esc_html($route_message !== '' ? $route_message : 'Estado controlado del shell base.'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>
