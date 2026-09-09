<?php
/**
 * Card de registro canónico (shell).
 *
 * Expects: $card_title, $card_details (?string), $card_iso, $card_display.
 * Optional actions: $show_edit_record (bool), $card_record_id (int).
 * Optional presentation: $shell_record_presentation ('card'|'compact').
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

$has_details_text = is_string($card_details) && $card_details !== '';
$has_updated = ($card_iso !== '' && $card_display !== '');
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
                <span class="aa-shell-record-title"><?php echo esc_html($card_title); ?></span>
            </button>
            <?php if ($edit_payload_attr !== '') : ?>
                <div class="aa-shell-record-options absolute inset-y-0 right-0 w-12">
                    <button
                        type="button"
                        class="aa-shell-record-options-trigger aa-options-trigger-flat w-full h-full"
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
            <?php if ($has_updated) : ?>
                <p class="aa-shell-record-updated text-xs text-gray-500 <?php echo $has_details_text ? 'mt-2' : ''; ?> m-0">
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
