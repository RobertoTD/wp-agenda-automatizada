<?php
/**
 * Slot horizontal para metadata tipada aportada por capabilities.
 *
 * Expects: $card_capability_metadata (list<array<string,mixed>>).
 * No declarar claves verticales ni renderizar HTML de providers aquí.
 */
defined('ABSPATH') or die('¡Sin acceso directo!');

$card_metadata_rows = [];
foreach ($card_capability_metadata as $metadata) {
    if (!is_array($metadata)
        || ($metadata['kind'] ?? '') !== 'datetime'
        || !isset($metadata['label'], $metadata['datetime_iso'], $metadata['datetime_display'])
        || !is_string($metadata['label'])
        || !is_string($metadata['datetime_iso'])
        || !is_string($metadata['datetime_display'])
    ) {
        continue;
    }
    $card_metadata_rows[] = $metadata;
}
?>
<?php if ($card_metadata_rows !== []) : ?>
    <div class="aa-shell-record-capability-metadata mt-2">
        <?php foreach ($card_metadata_rows as $metadata) : ?>
            <p class="aa-shell-record-capability-metadata-item text-xs text-gray-500 m-0">
                <span><?php echo esc_html($metadata['label']); ?>: </span><time datetime="<?php echo esc_attr($metadata['datetime_iso']); ?>"><?php echo esc_html($metadata['datetime_display']); ?></time>
            </p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
