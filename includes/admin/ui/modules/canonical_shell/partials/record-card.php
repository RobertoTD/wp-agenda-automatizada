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
$whatsapp_card = AA_Canonical_Whatsapp_Shell_Presenter::card_view($card_capabilities);
$whatsapp_edit = AA_Canonical_Whatsapp_Shell_Presenter::edit_payload_fragment($card_capabilities);
$phone_card = AA_Canonical_Phone_Shell_Presenter::card_view($card_capabilities);
$phone_edit = AA_Canonical_Phone_Shell_Presenter::edit_payload_fragment($card_capabilities);
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
    if ($whatsapp_edit !== null) {
        $edit_caps['whatsapp'] = $whatsapp_edit;
    }
    if ($phone_edit !== null) {
        $edit_caps['phone'] = $phone_edit;
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
$whatsapp_has_value = is_array($whatsapp_card) && ($whatsapp_card['kind'] ?? '') === 'value';
$whatsapp_has_error = is_array($whatsapp_card) && ($whatsapp_card['kind'] ?? '') === 'error';
$phone_has_value = is_array($phone_card) && ($phone_card['kind'] ?? '') === 'value';
$phone_has_error = is_array($phone_card) && ($phone_card['kind'] ?? '') === 'error';
$amount_has_value = is_array($amount_card) && ($amount_card['kind'] ?? '') === 'value';
$amount_color_class = ($amount_has_value && !empty($amount_card['is_negative']))
    ? 'text-red-800'
    : 'text-gray-900';
$amount_value_classes = 'aa-shell-record-amount text-base font-semibold m-0 ' . $amount_color_class;
$amount_header_classes = 'aa-shell-record-amount aa-shell-record-amount--header text-base font-semibold ' . $amount_color_class;
$whatsapp_link_classes = 'aa-shell-record-whatsapp inline-flex items-center gap-1.5 text-sm text-gray-700 no-underline hover:underline m-0';
$phone_value_classes = 'aa-shell-record-phone text-sm text-gray-700 m-0';
$whatsapp_svg = '<svg class="aa-shell-record-whatsapp__icon shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
$panel_id = $card_record_id >= 1
    ? ('aa-shell-record-panel-' . $card_record_id)
    : ('aa-shell-record-panel-' . uniqid('', false));
$contact_block_shown = $whatsapp_has_value || $whatsapp_has_error || $phone_has_value || $phone_has_error;
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
            <?php if ($whatsapp_has_value) : ?>
                <p class="m-0">
                    <a
                        class="<?php echo esc_attr($whatsapp_link_classes); ?>"
                        href="<?php echo esc_url((string) $whatsapp_card['href']); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="<?php echo esc_attr('Abrir WhatsApp: ' . (string) $whatsapp_card['display']); ?>"
                        title="<?php echo esc_attr('Abrir WhatsApp: ' . (string) $whatsapp_card['display']); ?>"
                    >
                        <?php echo $whatsapp_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup ?>
                        <span><?php echo esc_html((string) $whatsapp_card['display']); ?></span>
                    </a>
                </p>
            <?php elseif ($whatsapp_has_error) : ?>
                <p class="aa-shell-record-whatsapp-error text-sm text-amber-800 m-0" role="status">
                    No se pudo cargar WhatsApp.
                </p>
            <?php endif; ?>
            <?php if ($phone_has_value) : ?>
                <p class="<?php echo esc_attr($phone_value_classes . (($whatsapp_has_value || $whatsapp_has_error) ? ' mt-2' : '')); ?>">
                    <span class="sr-only">Teléfono: </span><?php echo esc_html((string) $phone_card['display']); ?>
                </p>
            <?php elseif ($phone_has_error) : ?>
                <p class="aa-shell-record-phone-error text-sm text-amber-800 m-0<?php echo ($whatsapp_has_value || $whatsapp_has_error) ? ' mt-2' : ''; ?>" role="status">
                    No se pudo cargar el teléfono.
                </p>
            <?php endif; ?>
            <?php if ($has_details_text) : ?>
                <p class="aa-shell-record-details text-sm text-gray-700 whitespace-pre-wrap m-0<?php echo $contact_block_shown ? ' mt-2' : ''; ?>"><?php echo esc_html($card_details); ?></p>
            <?php endif; ?>
            <?php if (is_array($amount_card) && ($amount_card['kind'] ?? '') === 'value') : ?>
                <p class="<?php echo esc_attr($amount_value_classes . (($has_details_text || $contact_block_shown) ? ' mt-2' : '')); ?>">
                    <span class="sr-only">Importe: </span><span aria-hidden="true">$</span><?php echo esc_html((string) $amount_card['value']); ?>
                </p>
            <?php elseif (is_array($amount_card) && ($amount_card['kind'] ?? '') === 'error') : ?>
                <p class="aa-shell-record-amount-error text-sm text-amber-800 <?php echo ($has_details_text || $contact_block_shown) ? 'mt-2' : ''; ?> m-0" role="status">
                    No se pudo cargar el importe.
                </p>
            <?php endif; ?>
            <?php if ($has_gallery) : ?>
                <?php require __DIR__ . '/record-images-gallery.php'; ?>
            <?php elseif (is_array($images_card) && ($images_card['kind'] ?? '') === 'error') : ?>
                <p class="aa-shell-record-images-error text-sm text-amber-800 <?php echo ($has_details_text || $contact_block_shown || is_array($amount_card)) ? 'mt-2' : ''; ?> m-0" role="status">
                    No se pudieron cargar las imágenes.
                </p>
            <?php endif; ?>
            <?php if ($has_updated) : ?>
                <p class="aa-shell-record-updated text-xs text-gray-500 <?php echo ($has_details_text || $contact_block_shown || is_array($amount_card) || $has_gallery) ? 'mt-2' : ''; ?> m-0">
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
        <?php if ($whatsapp_has_value) : ?>
            <p class="mt-2 m-0">
                <a
                    class="<?php echo esc_attr($whatsapp_link_classes); ?>"
                    href="<?php echo esc_url((string) $whatsapp_card['href']); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php echo esc_attr('Abrir WhatsApp: ' . (string) $whatsapp_card['display']); ?>"
                    title="<?php echo esc_attr('Abrir WhatsApp: ' . (string) $whatsapp_card['display']); ?>"
                >
                    <?php echo $whatsapp_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup ?>
                    <span><?php echo esc_html((string) $whatsapp_card['display']); ?></span>
                </a>
            </p>
        <?php elseif ($whatsapp_has_error) : ?>
            <p class="aa-shell-record-whatsapp-error mt-2 text-sm text-amber-800 m-0" role="status">
                No se pudo cargar WhatsApp.
            </p>
        <?php endif; ?>
        <?php if ($phone_has_value) : ?>
            <p class="<?php echo esc_attr($phone_value_classes . ' mt-2'); ?>">
                <span class="sr-only">Teléfono: </span><?php echo esc_html((string) $phone_card['display']); ?>
            </p>
        <?php elseif ($phone_has_error) : ?>
            <p class="aa-shell-record-phone-error mt-2 text-sm text-amber-800 m-0" role="status">
                No se pudo cargar el teléfono.
            </p>
        <?php endif; ?>
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
