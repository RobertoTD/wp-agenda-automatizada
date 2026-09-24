<?php
/** @var array{id:int,title:string,details:?string,updated_at:string} $aa_clean_record */
/** @var Closure $aa_clean_updated_label */
$aa_clean_record_details = trim((string) ($aa_clean_record['details'] ?? ''));
?>
<details class="aa-canonical-clean-record rounded-xl border border-gray-200 bg-white shadow-sm transition open:border-slate-200 open:shadow-md">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-slate-400/50">
        <span class="min-w-0">
            <span class="block truncate text-base font-semibold text-gray-950"><?php echo esc_html($aa_clean_record['title']); ?></span>
            <span class="mt-1 block text-xs text-gray-500"><?php echo esc_html('Actualizado ' . $aa_clean_updated_label((string) $aa_clean_record['updated_at'])); ?></span>
        </span>
        <svg class="aa-canonical-clean-record-chevron h-4 w-4 shrink-0 text-gray-500 transition-transform" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
    </summary>
    <div class="border-t border-gray-100 px-5 pb-5 pt-4 text-sm leading-6 text-gray-700">
        <?php if ($aa_clean_record_details !== '') : ?>
            <p class="whitespace-pre-wrap"><?php echo esc_html($aa_clean_record_details); ?></p>
        <?php else : ?>
            <p class="text-gray-500">Sin detalles.</p>
        <?php endif; ?>
        <div class="mt-4 flex gap-3 border-t border-gray-100 pt-3"><button class="text-sm text-gray-700 underline decoration-gray-300 underline-offset-4 hover:text-gray-950" type="button" data-aa-clean-action="edit-record" data-aa-clean-list-id="<?php echo (int) $aa_clean_record['container_id']; ?>" data-aa-clean-record-id="<?php echo (int) $aa_clean_record['id']; ?>" data-aa-clean-title="<?php echo esc_attr($aa_clean_record['title']); ?>" data-aa-clean-details="<?php echo esc_attr($aa_clean_record_details); ?>">Editar</button><button class="text-sm text-red-700 underline decoration-red-200 underline-offset-4 hover:text-red-900" type="button" data-aa-clean-action="delete-record" data-aa-clean-list-id="<?php echo (int) $aa_clean_record['container_id']; ?>" data-aa-clean-record-id="<?php echo (int) $aa_clean_record['id']; ?>" data-aa-clean-title="<?php echo esc_attr($aa_clean_record['title']); ?>">Eliminar</button></div>
    </div>
</details>
