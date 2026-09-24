<?php
/** @var array{page:int,total_pages:int,total:int} $aa_clean_page_data */
/** @var Closure $aa_clean_url */
/** @var string $aa_clean_route_kind */
/** @var int $aa_clean_container_id */
if ((int) $aa_clean_page_data['total_pages'] <= 1) {
    return;
}

$aa_clean_current_page = (int) $aa_clean_page_data['page'];
$aa_clean_total_pages = (int) $aa_clean_page_data['total_pages'];
?>
<nav class="mt-5 flex items-center justify-between gap-3 border-t border-gray-200 pt-4" aria-label="Paginación">
    <div class="text-sm text-gray-600">
        <?php echo esc_html(sprintf('Página %d de %d', $aa_clean_current_page, $aa_clean_total_pages)); ?>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($aa_clean_current_page > 1) : ?>
            <a class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:border-gray-400 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-slate-400/50" href="<?php echo esc_url($aa_clean_url($aa_clean_route_kind, $aa_clean_container_id, $aa_clean_current_page - 1)); ?>">Anterior</a>
        <?php endif; ?>
        <?php if ($aa_clean_current_page < $aa_clean_total_pages) : ?>
            <a class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:border-gray-400 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-slate-400/50" href="<?php echo esc_url($aa_clean_url($aa_clean_route_kind, $aa_clean_container_id, $aa_clean_current_page + 1)); ?>">Siguiente</a>
        <?php endif; ?>
    </div>
</nav>
