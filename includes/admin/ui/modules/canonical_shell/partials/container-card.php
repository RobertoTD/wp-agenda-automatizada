<?php
/**
 * Card de contenedor canónico (shell).
 *
 * Expects: $card_title, $card_details (?string), $card_iso, $card_display,
 * optional $card_records_url (string),
 * optional $show_edit_container (bool), $card_container_id (int),
 * optional $card_family_key (string), $card_family_label (string).
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$card_records_url = isset($card_records_url) && is_string($card_records_url) ? $card_records_url : '';
$show_edit_container = !empty($show_edit_container);
$card_container_id = isset($card_container_id) ? (int) $card_container_id : 0;
$card_family_key = isset($card_family_key) && is_string($card_family_key) ? $card_family_key : '';
$card_family_label = isset($card_family_label) && is_string($card_family_label) ? $card_family_label : '';
$edit_payload_attr = '';
if ($show_edit_container && $card_container_id >= 1 && $card_family_key !== '') {
    $edit_payload = wp_json_encode(
        [
            'id' => $card_container_id,
            'title' => (string) $card_title,
            'details' => is_string($card_details) ? $card_details : '',
            'family_key' => $card_family_key,
        ],
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if (is_string($edit_payload) && $edit_payload !== '') {
        $edit_payload_attr = esc_attr($edit_payload);
    }
}
?>
<li>
    <article class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 h-full flex flex-col">
        <?php if ($card_family_label !== '') : ?>
            <p class="text-xs text-gray-500 mb-1"><?php echo esc_html($card_family_label); ?></p>
        <?php endif; ?>
        <h4 class="text-base font-semibold text-gray-900 leading-snug">
            <?php if ($card_records_url !== '') : ?>
                <a
                    href="<?php echo esc_url($card_records_url); ?>"
                    class="text-indigo-700 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 rounded"
                >
                    <?php echo esc_html($card_title); ?>
                    <span class="sr-only"> — ver registros</span>
                </a>
            <?php else : ?>
                <?php echo esc_html($card_title); ?>
            <?php endif; ?>
        </h4>
        <?php if (is_string($card_details) && $card_details !== '') : ?>
            <p class="mt-2 text-sm text-gray-600 whitespace-pre-wrap"><?php echo esc_html($card_details); ?></p>
        <?php endif; ?>
        <?php if ($card_iso !== '' && $card_display !== '') : ?>
            <p class="mt-3 text-xs text-gray-500">
                <time datetime="<?php echo esc_attr($card_iso); ?>"><?php echo esc_html($card_display); ?></time>
            </p>
        <?php endif; ?>
        <?php if ($edit_payload_attr !== '') : ?>
            <div class="mt-4 pt-3 border-t border-gray-100 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="aa-shell-edit-container-btn inline-flex items-center px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    data-aa-container="<?php echo $edit_payload_attr; ?>"
                    aria-label="<?php echo esc_attr('Editar lista: ' . $card_title); ?>"
                >
                    Editar
                </button>
                <button
                    type="button"
                    class="aa-shell-delete-container-btn inline-flex items-center px-3 py-1.5 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                    data-aa-container="<?php echo $edit_payload_attr; ?>"
                    aria-label="<?php echo esc_attr('Eliminar lista: ' . $card_title); ?>"
                >
                    Eliminar
                </button>
            </div>
        <?php endif; ?>
    </article>
</li>
