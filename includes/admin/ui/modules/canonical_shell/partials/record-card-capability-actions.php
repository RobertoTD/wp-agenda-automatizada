<?php
/** Slot común de acciones de card aportadas por capabilities. */
defined('ABSPATH') or die('¡Sin acceso directo!');

$card_capability_actions = isset($card_capability_actions) && is_array($card_capability_actions)
    ? $card_capability_actions
    : [];
$card_action_slot_classes = isset($card_action_slot_classes) && is_string($card_action_slot_classes)
    ? $card_action_slot_classes
    : 'aa-shell-record-capability-actions mt-3 pt-2 border-t border-gray-100';

if ($card_capability_actions === []) {
    return;
}
?>
<div class="<?php echo esc_attr($card_action_slot_classes); ?>">
    <?php foreach ($card_capability_actions as $card_action) : ?>
        <?php
        if (!is_array($card_action)) { continue; }
        $capability_key = isset($card_action['capability_key']) ? (string) $card_action['capability_key'] : '';
        $label = isset($card_action['label']) ? (string) $card_action['label'] : '';
        $payload = isset($card_action['payload']) && is_array($card_action['payload']) ? $card_action['payload'] : [];
        if ($capability_key === '' || $label === '') { continue; }
        $payload_json = wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (!is_string($payload_json) || $payload_json === '') { continue; }
        ?>
        <button
            type="button"
            class="aa-shell-capability-action-btn inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            data-aa-capability-action="<?php echo esc_attr($capability_key); ?>"
            data-aa-capability-action-payload="<?php echo esc_attr($payload_json); ?>"
        ><?php echo esc_html($label); ?></button>
    <?php endforeach; ?>
</div>
