<?php
/**
 * Card de contenedor canónico (shell).
 *
 * Expects: $card_title, $card_details (?string; solo payload de edición),
 * optional $card_records_url (string),
 * optional $show_edit_container (bool), $card_container_id (int),
 * optional $card_family_key (string), $card_family_label (string),
 * optional $card_family_icon_key (string) — solo «Todas las listas».
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$card_records_url = isset($card_records_url) && is_string($card_records_url) ? $card_records_url : '';
$show_edit_container = !empty($show_edit_container);
$card_container_id = isset($card_container_id) ? (int) $card_container_id : 0;
$card_family_key = isset($card_family_key) && is_string($card_family_key) ? $card_family_key : '';
$card_family_label = isset($card_family_label) && is_string($card_family_label) ? $card_family_label : '';
$card_family_icon_key = isset($card_family_icon_key) && is_string($card_family_icon_key) ? $card_family_icon_key : '';
$card_capabilities = (isset($card_capabilities) && is_array($card_capabilities))
    ? $card_capabilities
    : null;
$edit_payload_attr = '';
if ($show_edit_container && $card_container_id >= 1 && $card_family_key !== '') {
    $edit_payload_data = [
        'id' => $card_container_id,
        'title' => (string) $card_title,
        'details' => is_string($card_details) ? $card_details : '',
        'family_key' => $card_family_key,
    ];
    if (is_array($card_capabilities) && isset($card_capabilities['status'])) {
        $edit_payload_data['capabilities'] = $card_capabilities;
    }
    $edit_payload = wp_json_encode(
        $edit_payload_data,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if (is_string($edit_payload) && $edit_payload !== '') {
        $edit_payload_attr = esc_attr($edit_payload);
    }
}

$show_family_icon = ($card_family_label !== '' && $card_family_icon_key !== '');
$family_icon_svg = '';
if ($show_family_icon) {
    if (!class_exists('AA_Canonical_Family_Icon_Markup')) {
        require_once dirname(__DIR__) . '/class-aa-canonical-family-icon-markup.php';
    }
    $family_icon_svg = AA_Canonical_Family_Icon_Markup::svg($card_family_icon_key);
    if ($family_icon_svg === '') {
        $show_family_icon = false;
    }
}

$family_sr_once = '';
if ($show_family_icon) {
    $family_sr_once = '<span class="sr-only">' . esc_html($card_family_label) . ': </span>';
}
?>
<li>
    <article class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 h-full flex flex-col">
        <?php if ($show_family_icon) : ?>
            <div class="flex items-center gap-1 min-w-0">
                <span class="flex items-center justify-center w-6 h-6 flex-shrink-0 text-indigo-600" aria-hidden="true">
                    <?php echo $family_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup fijo interno ?>
                </span>
                <h4 class="text-base font-semibold text-gray-900 leading-snug min-w-0 break-words">
                    <?php if ($card_records_url !== '') : ?>
                        <a
                            href="<?php echo esc_url($card_records_url); ?>"
                            class="text-indigo-700 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 rounded"
                        >
                            <?php echo $family_sr_once; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado ?>
                            <?php echo esc_html($card_title); ?>
                            <span class="sr-only"> — ver registros</span>
                        </a>
                    <?php else : ?>
                        <?php echo $family_sr_once; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado ?>
                        <?php echo esc_html($card_title); ?>
                    <?php endif; ?>
                </h4>
            </div>
        <?php else : ?>
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
