<?php
/**
 * Canonical Shell Base — Root paralelo provisional (SB1-1).
 *
 * Solo presenta estado de resolución e identidad canónica.
 * No carga datos, assets ni Application de ninguna familia.
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

$family_label = '';
$variant_label = '';
$family_key = '';
$variant_key = '';
$qualified_key = '';

if (
    isset($aa_canonical_family)
    && $aa_canonical_family instanceof AA_Canonical_Family_Definition
    && isset($aa_canonical_variant)
    && $aa_canonical_variant instanceof AA_Canonical_Variant_Definition
) {
    $family_label = $aa_canonical_family->label();
    $variant_label = $aa_canonical_variant->label();
    $family_key = $aa_canonical_family->key();
    $variant_key = $aa_canonical_variant->key();
    $qualified_key = $aa_canonical_variant->qualified_key();
}

$page_title = 'Shell canónico';
if ($route_state === 'resolved' && $family_label !== '') {
    $page_title = 'Shell canónico · ' . $family_label;
}

$state_labels = [
    'missing_identity'    => 'Desarrollo',
    'incomplete_identity' => 'Identidad incompleta',
    'invalid_request'     => 'Solicitud no válida',
    'not_found'           => 'No encontrado',
    'resolved'            => 'Resuelto',
];
$state_label = $state_labels[$route_state] ?? 'Estado';
?>

<div
    id="aa-canonical-shell-root"
    class="max-w-5xl mx-auto py-2"
    data-aa-page-title="<?php echo esc_attr($page_title); ?>"
    data-aa-shell-route-state="<?php echo esc_attr($route_state); ?>"
    <?php if ($family_key !== '') : ?>
    data-aa-canonical-family="<?php echo esc_attr($family_key); ?>"
    <?php endif; ?>
    <?php if ($variant_key !== '') : ?>
    data-aa-canonical-variant="<?php echo esc_attr($variant_key); ?>"
    <?php endif; ?>
    <?php if ($qualified_key !== '') : ?>
    data-aa-canonical-qualified="<?php echo esc_attr($qualified_key); ?>"
    <?php endif; ?>
>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 leading-tight">
                    Shell canónico
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Módulo paralelo provisional. No sustituye la UI de familias existentes.
                </p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                <?php echo esc_html($state_label); ?>
            </span>
        </div>
    </div>

    <?php if ($route_state === 'resolved') : ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Familia</dt>
                    <dd class="mt-1 text-base font-medium text-gray-900"><?php echo esc_html($family_label); ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Variante</dt>
                    <dd class="mt-1 text-base font-medium text-gray-900"><?php echo esc_html($variant_label); ?></dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Identidad</dt>
                    <dd class="mt-1">
                        <code class="text-sm bg-gray-100 text-gray-700 px-2 py-1 rounded font-mono"><?php echo esc_html($qualified_key); ?></code>
                    </dd>
                </div>
            </dl>
            <p class="mt-4 text-sm text-gray-500" role="status">
                <?php echo esc_html($route_message); ?> Sin datos de familia en este ciclo.
            </p>
        </div>
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
