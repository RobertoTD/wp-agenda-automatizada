<?php
/**
 * Card de contenedor canónico (shell).
 *
 * Expects: $card_title, $card_details (?string), $card_iso, $card_display,
 * optional $card_records_url (string).
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$card_records_url = isset($card_records_url) && is_string($card_records_url) ? $card_records_url : '';
?>
<li>
    <article class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 h-full">
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
    </article>
</li>
