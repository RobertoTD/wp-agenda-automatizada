<?php
/**
 * Card de registro canónico (shell).
 *
 * Expects: $card_title, $card_details (?string), $card_iso, $card_display.
 * Optional actions: $show_edit_record (bool), $card_record_id (int).
 * Optional presentation: $shell_record_presentation ('card'|'compact').
 * Optional capabilities: $card_capabilities (array|null) — mapa por clave del item.
 * Optional images: $card_image_summary_url (?string), $show_image_actions (bool).
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$show_edit_record = !empty($show_edit_record);
$card_record_id = isset($card_record_id) ? (int) $card_record_id : 0;
$shell_record_presentation = isset($shell_record_presentation) && is_string($shell_record_presentation)
    ? $shell_record_presentation
    : 'card';
$is_compact = ($shell_record_presentation === 'compact');
$card_capabilities = isset($card_capabilities) && is_array($card_capabilities)
    ? $card_capabilities
    : null;
$show_image_actions = !empty($show_image_actions);

$amount_card = AA_Canonical_Amount_Shell_Presenter::card_view($card_capabilities);
$amount_edit = AA_Canonical_Amount_Shell_Presenter::edit_payload_fragment($card_capabilities);
$images_card = AA_Canonical_Images_Shell_Presenter::card_view($card_capabilities);
$images_edit = AA_Canonical_Images_Shell_Presenter::edit_payload_fragment($card_capabilities);
$card_image_summary_url = isset($card_image_summary_url) && is_string($card_image_summary_url)
    ? $card_image_summary_url
    : null;

$has_gallery = is_array($images_card) && ($images_card['kind'] ?? '') === 'gallery';
$summary_image_id = $has_gallery && isset($images_card['image_id']) ? (int) $images_card['image_id'] : 0;
$show_summary = $has_gallery
    && $summary_image_id >= 1
    && is_string($card_image_summary_url)
    && $card_image_summary_url !== '';

$edit_payload_attr = '';
if ($show_edit_record && $card_record_id >= 1) {
    $edit_payload = [
        'id' => $card_record_id,
        'title' => (string) $card_title,
        'details' => is_string($card_details) ? $card_details : '',
    ];
    $edit_caps = [];
    if ($amount_edit !== null) {
        $edit_caps['amount'] = $amount_edit;
    }
    if ($images_edit !== null) {
        $edit_caps['images'] = $images_edit;
    }
    if ($edit_caps !== []) {
        $edit_payload['capabilities'] = $edit_caps;
    }
    $edit_json = wp_json_encode(
        $edit_payload,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if (is_string($edit_json) && $edit_json !== '') {
        $edit_payload_attr = esc_attr($edit_json);
    }
}

$has_details_text = is_string($card_details) && $card_details !== '';
$has_updated = ($card_iso !== '' && $card_display !== '');
$amount_has_value = is_array($amount_card) && ($amount_card['kind'] ?? '') === 'value';
$amount_color_class = ($amount_has_value && !empty($amount_card['is_negative']))
    ? 'text-red-800'
    : 'text-gray-900';
$amount_value_classes = 'aa-shell-record-amount text-base font-semibold m-0 ' . $amount_color_class;
$amount_header_classes = 'aa-shell-record-amount aa-shell-record-amount--header text-base font-semibold ' . $amount_color_class;
$panel_id = $card_record_id >= 1
    ? ('aa-shell-record-panel-' . $card_record_id)
    : ('aa-shell-record-panel-' . uniqid('', false));
?>
<?php if ($is_compact) : ?>
<li
    class="aa-shell-record"
    data-aa-shell-record
    <?php if ($card_record_id >= 1) : ?>data-aa-record-id="<?php echo esc_attr((string) $card_record_id); ?>"<?php endif; ?>
>
    <div class="aa-shell-record-anchor relative">
        <div class="aa-shell-record-header relative">
            <button
                type="button"
                class="aa-shell-record-toggle w-full text-left"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr($panel_id); ?>"
            >
                <?php if ($show_summary) : ?>
                    <span
                        class="aa-shell-record-image-summary aa-shell-record-image-summary--header"
                        data-aa-summary-for-record="<?php echo esc_attr((string) $card_record_id); ?>"
                        data-aa-image-id="<?php echo esc_attr((string) $summary_image_id); ?>"
                    >
                        <img
                            class="aa-shell-record-image-summary__img"
                            src="<?php echo esc_url($card_image_summary_url); ?>"
                            alt=""
                            width="40"
                            height="40"
                            loading="lazy"
                            decoding="async"
                        />
                    </span>
                <?php endif; ?>
                <span class="aa-shell-record-title"><?php echo esc_html($card_title); ?></span>
                <?php if ($amount_has_value) : ?>
                    <span class="<?php echo esc_attr($amount_header_classes); ?>">
                        <span aria-hidden="true">$</span><?php echo esc_html((string) $amount_card['value']); ?>
                    </span>
                <?php endif; ?>
            </button>
            <?php if ($edit_payload_attr !== '') : ?>
                <div class="aa-shell-record-options absolute inset-y-0 right-0 w-12 flex items-center justify-center pointer-events-none">
                    <button
                        type="button"
                        class="aa-shell-record-options-trigger aa-options-trigger-flat pointer-events-auto"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-label="<?php echo esc_attr('Opciones del registro: ' . $card_title); ?>"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </button>
                </div>
                <div
                    class="aa-shell-record-options-menu hidden absolute right-0 top-full z-30 mt-2 w-[12rem] max-w-full min-w-0 box-border rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                    role="menu"
                    hidden
                >
                    <button
                        type="button"
                        class="aa-shell-edit-record-btn flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:bg-gray-50 focus:ring-2 focus:ring-inset focus:ring-indigo-500/30"
                        role="menuitem"
                        data-aa-record="<?php echo $edit_payload_attr; ?>"
                        aria-label="<?php echo esc_attr('Editar registro: ' . $card_title); ?>"
                    >
                        Editar
                    </button>
                    <button
                        type="button"
                        class="aa-shell-delete-record-btn flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 hover:bg-gray-50 focus:outline-none focus:bg-gray-50 focus:ring-2 focus:ring-inset focus:ring-red-500/30"
                        role="menuitem"
                        data-aa-record="<?php echo $edit_payload_attr; ?>"
                        aria-label="<?php echo esc_attr('Eliminar registro: ' . $card_title); ?>"
                    >
                        Eliminar
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div
            id="<?php echo esc_attr($panel_id); ?>"
            class="aa-shell-record-panel"
            hidden
        >
            <?php if ($has_details_text) : ?>
                <p class="aa-shell-record-details text-sm text-gray-700 whitespace-pre-wrap m-0"><?php echo esc_html($card_details); ?></p>
            <?php endif; ?>
            <?php if (is_array($amount_card) && ($amount_card['kind'] ?? '') === 'value') : ?>
                <p class="<?php echo esc_attr($amount_value_classes . ($has_details_text ? ' mt-2' : '')); ?>">
                    <span class="sr-only">Importe: </span><span aria-hidden="true">$</span><?php echo esc_html((string) $amount_card['value']); ?>
                </p>
            <?php elseif (is_array($amount_card) && ($amount_card['kind'] ?? '') === 'error') : ?>
                <p class="aa-shell-record-amount-error text-sm text-amber-800 <?php echo $has_details_text ? 'mt-2' : ''; ?> m-0" role="status">
                    No se pudo cargar el importe.
                </p>
            <?php endif; ?>
            <?php if ($has_gallery) : ?>
                <?php require __DIR__ . '/record-images-gallery.php'; ?>
            <?php elseif (is_array($images_card) && ($images_card['kind'] ?? '') === 'error') : ?>
                <p class="aa-shell-record-images-error text-sm text-amber-800 <?php echo ($has_details_text || is_array($amount_card)) ? 'mt-2' : ''; ?> m-0" role="status">
                    No se pudieron cargar las imágenes.
                </p>
            <?php endif; ?>
            <?php if ($has_updated) : ?>
                <p class="aa-shell-record-updated text-xs text-gray-500 <?php echo ($has_details_text || is_array($amount_card) || $has_gallery) ? 'mt-2' : ''; ?> m-0">
                    <time datetime="<?php echo esc_attr($card_iso); ?>"><?php echo esc_html($card_display); ?></time>
                </p>
            <?php endif; ?>
        </div>
    </div>
</li>
<?php else : ?>
<li>
    <article class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 h-full flex flex-col">
        <h4 class="text-base font-semibold text-gray-900 leading-snug">
            <?php echo esc_html($card_title); ?>
        </h4>
        <?php if ($has_details_text) : ?>
            <p class="mt-2 text-sm text-gray-600 whitespace-pre-wrap"><?php echo esc_html($card_details); ?></p>
        <?php endif; ?>
        <?php if (is_array($amount_card) && ($amount_card['kind'] ?? '') === 'value') : ?>
            <p class="<?php echo esc_attr($amount_value_classes . ' mt-2'); ?>">
                <span class="sr-only">Importe: </span><span aria-hidden="true">$</span><?php echo esc_html((string) $amount_card['value']); ?>
            </p>
        <?php elseif (is_array($amount_card) && ($amount_card['kind'] ?? '') === 'error') : ?>
            <p class="aa-shell-record-amount-error mt-2 text-sm text-amber-800 m-0" role="status">
                No se pudo cargar el importe.
            </p>
        <?php endif; ?>
        <?php if ($show_summary) : ?>
            <div
                class="aa-shell-record-image-summary mt-2"
                data-aa-summary-for-record="<?php echo esc_attr((string) $card_record_id); ?>"
                data-aa-image-id="<?php echo esc_attr((string) $summary_image_id); ?>"
            >
                <img
                    class="aa-shell-record-image-summary__img rounded border border-gray-200 max-w-[6rem] max-h-[6rem] object-cover"
                    src="<?php echo esc_url($card_image_summary_url); ?>"
                    alt=""
                    width="96"
                    height="96"
                    loading="lazy"
                    decoding="async"
                />
            </div>
        <?php endif; ?>
        <?php if ($has_gallery) : ?>
            <?php require __DIR__ . '/record-images-gallery.php'; ?>
        <?php elseif (is_array($images_card) && ($images_card['kind'] ?? '') === 'error') : ?>
            <p class="aa-shell-record-images-error mt-2 text-sm text-amber-800 m-0" role="status">
                No se pudieron cargar las imágenes.
            </p>
        <?php endif; ?>
        <?php if ($has_updated) : ?>
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
<?php endif; ?>
