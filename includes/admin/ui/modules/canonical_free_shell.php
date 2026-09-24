<?php
/**
 * FH-2A — universal read surface for the canonical shell.
 *
 * It is intentionally read-only. CRUD controls return in FH-2B, once their
 * modal and confirmation surface can be introduced without legacy coupling.
 */
defined('ABSPATH') or die('No direct access');
if (!current_user_can('manage_options')) { wp_die('Permisos insuficientes.', 'Error', ['response' => 403]); }
if (!class_exists('CanonicalCoreUseCase')) require_once dirname(__DIR__, 3) . '/application/canonical/core/CanonicalCoreUseCase.php';
if (!class_exists('CanonicalCoreRepository')) require_once dirname(__DIR__, 3) . '/repositories/CanonicalCoreRepository.php';
if (!class_exists('AA_Canonical_Clean_Shell_Read_Route')) require_once __DIR__ . '/canonical_clean_shell/class-aa-canonical-clean-shell-read-route.php';

$aa_clean_core = new CanonicalCoreUseCase(new CanonicalCoreRepository());
$aa_clean_route = AA_Canonical_Clean_Shell_Read_Route::from_query($_GET);
$aa_clean_base_url = admin_url('admin-post.php?action=aa_iframe_content&module=canonical_shell');
$aa_clean_url = static function (string $kind, int $container_id = 0, int $page = 1) use ($aa_clean_base_url): string {
    $args = [];
    if ($kind === AA_Canonical_Clean_Shell_Read_Route::RECORDS && $container_id > 0) {
        $args['view'] = 'records';
        $args['container_id'] = (string) $container_id;
    }
    if ($page > 1) {
        $args['page'] = (string) $page;
    }
    return $args === [] ? $aa_clean_base_url : add_query_arg($args, $aa_clean_base_url);
};
$aa_clean_updated_label = static function (string $value): string {
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        return function_exists('wp_date')
            ? (string) wp_date('j M Y, H:i', $date->getTimestamp(), $timezone)
            : $date->setTimezone($timezone)->format('j M Y, H:i');
    } catch (Throwable $e) {
        return 'sin fecha';
    }
};
?>
<section class="mx-auto max-w-4xl px-3 py-5 sm:px-5 sm:py-7" data-aa-canonical-clean-shell="1">
<?php if ($aa_clean_route['kind'] === AA_Canonical_Clean_Shell_Read_Route::INVALID_RECORDS) : ?>
    <div class="rounded-xl border border-gray-200 bg-white p-6 text-center shadow-sm">
        <h1 class="text-lg font-semibold text-gray-950">Lista no encontrada</h1>
        <p class="mt-2 text-sm text-gray-600">La dirección de esta lista no es válida.</p>
        <a class="mt-4 inline-flex text-sm text-gray-700 underline decoration-gray-300 underline-offset-4 hover:text-gray-950" href="<?php echo esc_url($aa_clean_url(AA_Canonical_Clean_Shell_Read_Route::ROOT)); ?>">Volver a todas las listas</a>
    </div>
<?php elseif ($aa_clean_route['kind'] === AA_Canonical_Clean_Shell_Read_Route::RECORDS) : ?>
    <?php $aa_clean_list = $aa_clean_core->list($aa_clean_route['container_id']); ?>
    <?php if ($aa_clean_list === null) : ?>
        <div class="rounded-xl border border-gray-200 bg-white p-6 text-center shadow-sm">
            <h1 class="text-lg font-semibold text-gray-950">Lista no encontrada</h1>
            <p class="mt-2 text-sm text-gray-600">Esta lista ya no existe o no está disponible.</p>
            <a class="mt-4 inline-flex text-sm text-gray-700 underline decoration-gray-300 underline-offset-4 hover:text-gray-950" href="<?php echo esc_url($aa_clean_url(AA_Canonical_Clean_Shell_Read_Route::ROOT)); ?>">Volver a todas las listas</a>
        </div>
    <?php else : ?>
        <?php $aa_clean_page_data = $aa_clean_core->records_page($aa_clean_route['container_id'], $aa_clean_route['page']); ?>
        <header class="mb-5">
            <a class="text-sm text-gray-600 transition hover:text-gray-950 focus:outline-none focus:ring-2 focus:ring-slate-400/50" href="<?php echo esc_url($aa_clean_url(AA_Canonical_Clean_Shell_Read_Route::ROOT)); ?>">← Todas las listas</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-gray-950"><?php echo esc_html($aa_clean_list['title']); ?></h1>
            <?php if (trim((string) $aa_clean_list['details']) !== '') : ?><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-gray-600"><?php echo esc_html($aa_clean_list['details']); ?></p><?php endif; ?>
        </header>
        <?php if ($aa_clean_page_data['items'] === []) : ?>
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center">
                <h2 class="text-base font-semibold text-gray-900">Esta lista no tiene registros</h2>
                <p class="mt-2 text-sm text-gray-600">Los registros aparecerán aquí cuando se creen.</p>
            </div>
        <?php else : ?>
            <div class="space-y-3">
                <?php foreach ($aa_clean_page_data['items'] as $aa_clean_record) require __DIR__ . '/canonical_clean_shell/partials/record-disclosure.php'; ?>
            </div>
            <?php $aa_clean_route_kind = AA_Canonical_Clean_Shell_Read_Route::RECORDS; $aa_clean_container_id = $aa_clean_route['container_id']; require __DIR__ . '/canonical_clean_shell/partials/pagination.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
<?php else : ?>
    <?php $aa_clean_page_data = $aa_clean_core->lists_page($aa_clean_route['page']); ?>
    <header class="mb-5"><h1 class="text-2xl font-semibold tracking-tight text-gray-950">Todas las listas</h1></header>
    <?php if ($aa_clean_page_data['items'] === []) : ?>
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center">
            <h2 class="text-base font-semibold text-gray-900">No hay listas todavía</h2>
            <p class="mt-2 text-sm text-gray-600">La creación de listas volverá en el siguiente ciclo del shell.</p>
        </div>
    <?php else : ?>
        <div class="space-y-3">
            <?php foreach ($aa_clean_page_data['items'] as $aa_clean_list) require __DIR__ . '/canonical_clean_shell/partials/list-card.php'; ?>
        </div>
        <?php $aa_clean_route_kind = AA_Canonical_Clean_Shell_Read_Route::ROOT; $aa_clean_container_id = 0; require __DIR__ . '/canonical_clean_shell/partials/pagination.php'; ?>
    <?php endif; ?>
<?php endif; ?>
</section>
