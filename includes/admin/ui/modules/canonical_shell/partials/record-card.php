<?php
/**
 * Card de registro canónico (shell).
 *
 * Expects: $card_title, $card_details (?string), $card_iso, $card_display.
 * Optional actions: $show_edit_record (bool), $card_record_id (int).
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$show_edit_record = !empty($show_edit_record);
$card_record_id = isset($card_record_id) ? (int) $card_record_id : 0;
$edit_payload_attr = '';
if ($show_edit_record && $card_record_id >= 1) {
    $edit_payload = wp_json_encode(
        [
            'id' => $card_record_id,
            'title' => (string) $card_title,
            'details' => is_string($card_details) ? $card_details : '',
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
        <h4 class="text-base font-semibold text-gray-900 leading-snug">
            <?php echo esc_html($card_title); ?>
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
                    class="aa-shell-edit-record-btn inline-flex items-center px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    data-aa-record="<?php echo $edit_payload_attr; ?>"
                    aria-label="<?php echo esc_attr('Editar registro: ' . $card_title); ?>"
                >
                    Editar
                </button>
                <button
                    type="button"
                    class="aa-shell-delete-record-btn inline-flex items-center px-3 py-1.5 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                    data-aa-record="<?php echo $edit_payload_attr; ?>"
                    aria-label="<?php echo esc_attr('Eliminar registro: ' . $card_title); ?>"
                >
                    Eliminar
                </button>
            </div>
        <?php endif; ?>
    </article>
</li>
