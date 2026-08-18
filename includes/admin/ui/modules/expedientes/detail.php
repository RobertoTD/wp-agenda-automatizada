<?php
/**
 * Expedientes — vista de detalle de un expediente padre real.
 *
 * Espera $aa_expediente_detail ya resuelto por el router (GetExpedienteUseCase).
 * Sin AJAX, FAB, buscador, menú vacío ni acciones de registros.
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$aa_detail = (isset($aa_expediente_detail) && is_array($aa_expediente_detail))
    ? $aa_expediente_detail
    : [];

$aa_detail_id = (int) ($aa_detail['id'] ?? 0);
$aa_detail_title = (string) ($aa_detail['title'] ?? '');
$aa_detail_description_raw = $aa_detail['description'] ?? null;
$aa_detail_has_description = is_string($aa_detail_description_raw)
    && trim($aa_detail_description_raw) !== '';
$aa_detail_description = $aa_detail_has_description
    ? (string) $aa_detail_description_raw
    : 'Sin descripción';
$aa_detail_category = $aa_detail['category'] ?? [];
$aa_detail_category_name = is_array($aa_detail_category)
    ? (string) ($aa_detail_category['name'] ?? '')
    : '';
if ($aa_detail_category_name === '') {
    $aa_detail_category_name = '—';
}

$aa_detail_created_raw = (string) ($aa_detail['created_at'] ?? '');
$aa_detail_created_display = '—';
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $aa_detail_created_raw, $aa_detail_created_match)) {
    $aa_detail_created_display = $aa_detail_created_match[3]
        . '/' . $aa_detail_created_match[2]
        . '/' . $aa_detail_created_match[1];
} elseif ($aa_detail_created_raw !== '') {
    $aa_detail_created_display = $aa_detail_created_raw;
}

$aa_detail_page_title = $aa_detail_title !== '' ? $aa_detail_title : 'Expediente';
$aa_expedientes_list_url = admin_url('admin-post.php?action=aa_iframe_content&module=expedientes');
?>

<div
    id="aa-expediente-detail-root"
    class="max-w-5xl mx-auto py-2"
    data-aa-page-title="<?php echo esc_attr($aa_detail_page_title); ?>"
    <?php if ($aa_detail_id > 0) : ?>data-expediente-id="<?php echo esc_attr((string) $aa_detail_id); ?>"<?php endif; ?>
>
    <p class="aa-expediente-detail-back mb-3">
        <a
            href="<?php echo esc_url($aa_expedientes_list_url); ?>"
            class="aa-expediente-detail-back-link text-sm font-semibold text-indigo-700 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 rounded"
        >Volver a Expedientes</a>
    </p>

    <div class="aa-expediente-detail-panel aa-expediente-panel bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden">
        <div id="aa-expediente-detail-header" class="px-4 py-5 bg-white rounded-t-xl">
            <div class="flex items-center min-w-0">
                <span class="flex items-center justify-center w-8 h-8 text-gray-600 shrink-0" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                </span>
                <h3 class="min-w-0 truncate text-lg font-semibold text-gray-600">
                    <?php echo esc_html($aa_detail_title !== '' ? $aa_detail_title : 'Sin título'); ?>
                </h3>
            </div>
        </div>
        <div class="p-4 aa-expediente-detail-body">
            <p class="aa-expediente-detail-description text-sm text-gray-600 mb-3">
                <?php echo esc_html($aa_detail_description); ?>
            </p>
            <div class="aa-expediente-detail-meta space-y-1 text-sm font-medium text-gray-600">
                <div>
                    <span class="font-semibold">Categoría:</span>
                    <span class="text-gray-500"><?php echo esc_html($aa_detail_category_name); ?></span>
                </div>
                <div>
                    <span class="font-semibold">Creado:</span>
                    <span class="text-gray-500"><?php echo esc_html($aa_detail_created_display); ?></span>
                </div>
            </div>
            <div
                id="aa-expediente-detail-registros"
                class="aa-expediente-detail-registros mt-4"
                <?php if ($aa_detail_id > 0) : ?>data-expediente-id="<?php echo esc_attr((string) $aa_detail_id); ?>"<?php endif; ?>
                aria-live="polite"
            >
                <p class="text-sm text-gray-500">Los registros estarán disponibles próximamente</p>
            </div>
        </div>
    </div>
</div>
