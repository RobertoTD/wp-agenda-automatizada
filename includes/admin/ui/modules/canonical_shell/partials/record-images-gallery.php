<?php
/**
 * Galería canónica de imágenes (panel expandido / cuerpo de card).
 *
 * Expects: $card_record_id, $images_card (kind gallery), $show_image_actions (bool).
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$images_card = isset($images_card) && is_array($images_card) ? $images_card : null;
$show_image_actions = !empty($show_image_actions);
$card_record_id = isset($card_record_id) ? (int) $card_record_id : 0;

if (!is_array($images_card) || ($images_card['kind'] ?? '') !== 'gallery') {
    return;
}

$image_ids = isset($images_card['image_ids']) && is_array($images_card['image_ids'])
    ? $images_card['image_ids']
    : [];
$normalized_ids = [];
foreach ($image_ids as $raw_id) {
    $iid = (int) $raw_id;
    if ($iid >= 1) {
        $normalized_ids[] = $iid;
    }
}
if ($normalized_ids === [] || $card_record_id < 1) {
    return;
}

$selected_id = (int) ($images_card['image_id'] ?? $normalized_ids[0]);
if (!in_array($selected_id, $normalized_ids, true)) {
    $selected_id = $normalized_ids[0];
}
$count = count($normalized_ids);
$selected_index = array_search($selected_id, $normalized_ids, true);
$selected_pos = is_int($selected_index) ? ($selected_index + 1) : 1;

$selected_payload = wp_json_encode(
    [
        'id' => $selected_id,
        'record_id' => $card_record_id,
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if (!is_string($selected_payload) || $selected_payload === '') {
    return;
}
?>
<div
    class="aa-shell-record-gallery mt-2"
    data-aa-gallery
    data-aa-gallery-record-id="<?php echo esc_attr((string) $card_record_id); ?>"
    data-aa-selected-id="<?php echo esc_attr((string) $selected_id); ?>"
>
    <div class="aa-shell-record-gallery-main-wrap">
        <button
            type="button"
            class="aa-shell-record-gallery-main"
            data-aa-image-id="<?php echo esc_attr((string) $selected_id); ?>"
            data-aa-read-version="display"
            aria-label="<?php echo esc_attr('Ver imagen ampliada'); ?>"
        >
            <span class="aa-shell-record-gallery-main-status sr-only">Cargando imagen</span>
        </button>
        <?php if ($show_image_actions) : ?>
            <button
                type="button"
                class="aa-shell-record-gallery-delete aa-shell-delete-image-btn"
                data-aa-image="<?php echo esc_attr($selected_payload); ?>"
                aria-label="<?php echo esc_attr('Eliminar imagen'); ?>"
                title="<?php echo esc_attr('Eliminar imagen'); ?>"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9zm-1 12h12V8H6v13z"/>
                </svg>
            </button>
        <?php endif; ?>
    </div>
    <?php if ($count > 1) : ?>
        <div
            class="aa-shell-record-gallery-strip"
            role="group"
            aria-label="<?php echo esc_attr('Imágenes del registro'); ?>"
        >
            <?php foreach ($normalized_ids as $index => $iid) : ?>
                <?php
                $is_selected = ($iid === $selected_id);
                $pos = $index + 1;
                ?>
                <button
                    type="button"
                    class="aa-shell-record-gallery-mini<?php echo $is_selected ? ' aa-shell-record-gallery-mini-selected' : ''; ?>"
                    data-aa-image-id="<?php echo esc_attr((string) $iid); ?>"
                    data-aa-read-version="gallery"
                    aria-label="<?php echo esc_attr('Ver imagen ' . $pos . ' de ' . $count); ?>"
                    aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
                ></button>
            <?php endforeach; ?>
        </div>
        <p class="aa-shell-record-gallery-counter" data-aa-gallery-counter>
            <?php echo esc_html($selected_pos . ' de ' . $count); ?>
        </p>
    <?php endif; ?>
    <p class="aa-shell-record-gallery-error hidden" data-aa-gallery-error role="status" hidden></p>
</div>
