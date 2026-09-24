<?php
/** @var array{id:int,title:string,details:?string,updated_at:string} $aa_clean_list */
/** @var Closure $aa_clean_url */
/** @var Closure $aa_clean_updated_label */
$aa_clean_list_details = trim((string) ($aa_clean_list['details'] ?? ''));
?>
<a class="group block rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-px hover:border-gray-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-slate-400/50" href="<?php echo esc_url($aa_clean_url('records', (int) $aa_clean_list['id'], 1)); ?>">
    <h2 class="text-base font-semibold text-gray-950 transition group-hover:text-slate-700"><?php echo esc_html($aa_clean_list['title']); ?></h2>
    <?php if ($aa_clean_list_details !== '') : ?>
        <p class="aa-canonical-clean-preview mt-2 text-sm leading-6 text-gray-600"><?php echo esc_html(wp_strip_all_tags($aa_clean_list_details)); ?></p>
    <?php endif; ?>
    <p class="mt-3 text-xs text-gray-500"><?php echo esc_html('Actualizada ' . $aa_clean_updated_label((string) $aa_clean_list['updated_at'])); ?></p>
</a>
