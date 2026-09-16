<?php
/**
 * Card de contenedor canónico (shell).
 *
 * Expects: $card_title, $card_details (?string; solo payload de edición),
 * optional $card_records_url (string),
 * optional $show_edit_container (bool), $card_container_id (int),
 * optional $card_family_key (string), $card_family_label (string),
 * optional $card_family_icon_key (string),
 * optional $card_announce_family (bool) — sr-only del nombre de familia (p. ej. «Todas las listas»).
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
$card_announce_family = !empty($card_announce_family);
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

$show_family_icon = ($card_family_icon_key !== '');
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
if ($show_family_icon && $card_announce_family && $card_family_label !== '') {
    $family_sr_once = '<span class="sr-only">' . esc_html($card_family_label) . ': </span>';
}
?>
<li>
    <article class="aa-shell-container-card bg-white rounded-xl shadow-sm border border-gray-200 p-5 h-full flex flex-col">
        <div class="flex items-center gap-2 min-w-0">
            <?php if ($show_family_icon) : ?>
                <span class="flex items-center justify-center w-6 h-6 flex-shrink-0 text-indigo-600" aria-hidden="true">
                    <?php echo $family_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup fijo interno ?>
                </span>
            <?php endif; ?>
            <h4 class="text-base font-semibold text-gray-900 leading-snug min-w-0 flex-1 break-words">
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
            <?php if ($edit_payload_attr !== '') : ?>
                <div class="aa-shell-container-options relative shrink-0">
                    <button
                        type="button"
                        class="aa-shell-container-options-trigger aa-options-trigger-flat"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-label="<?php echo esc_attr('Opciones de la lista: ' . $card_title); ?>"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </button>
                    <div
                        class="aa-shell-container-options-popup hidden absolute right-0 top-full z-30 mt-2 w-[12rem] box-border rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                        hidden
                    >
                        <button
                            type="button"
                            class="aa-shell-edit-container-btn flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:bg-gray-50 focus:ring-2 focus:ring-inset focus:ring-indigo-500/30"
                            data-aa-container="<?php echo $edit_payload_attr; ?>"
                            aria-label="<?php echo esc_attr('Editar lista: ' . $card_title); ?>"
                        >
                            Editar
                        </button>
                        <button
                            type="button"
                            class="aa-shell-delete-container-btn flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 hover:bg-gray-50 focus:outline-none focus:bg-gray-50 focus:ring-2 focus:ring-inset focus:ring-red-500/30"
                            data-aa-container="<?php echo $edit_payload_attr; ?>"
                            aria-label="<?php echo esc_attr('Eliminar lista: ' . $card_title); ?>"
                        >
                            Eliminar
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </article>
</li>
