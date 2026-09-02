<?php
/**
 * Card de contenedor canónico (shell).
 *
 * Expects: $card_title, $card_details (?string), $card_iso, $card_display.
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>
<li>
    <article class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 h-full">
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
    </article>
</li>
