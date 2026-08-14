<?php
/**
 * AC — ExpedienteAdjuntoVariants (specs, allowlist, derivación pura).
 *
 * Ejecutar: php tests/domain/expediente/test-expediente-adjunto-variants-ac.php
 */

$plugin_root = dirname(__DIR__, 3);

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }
    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';

$iid = '11111111-2222-4333-8444-555555555555';
$op = '550e8400-e29b-41d4-a716-446655440000';
$original = 'installations/' . $iid . '/clients/9/records/42/' . $op . '.jpg';

ac_assert('MANIFEST_VERSION 1', ExpedienteAdjuntoVariants::MANIFEST_VERSION === 1);
ac_assert(
    'allowlist estricta de 3',
    ExpedienteAdjuntoVariants::ALLOWED_VARIANTS === ['summary', 'gallery', 'display']
);
ac_assert('is_allowed_variant summary', ExpedienteAdjuntoVariants::is_allowed_variant('summary'));
ac_assert('is_allowed_variant gallery', ExpedienteAdjuntoVariants::is_allowed_variant('gallery'));
ac_assert('is_allowed_variant display', ExpedienteAdjuntoVariants::is_allowed_variant('display'));
ac_assert('rechaza original como variant', !ExpedienteAdjuntoVariants::is_allowed_variant('original'));
ac_assert('rechaza thumb legado', !ExpedienteAdjuntoVariants::is_allowed_variant('thumb'));
ac_assert('rechaza variant vacía', !ExpedienteAdjuntoVariants::is_allowed_variant(''));
ac_assert('rechaza variant no-string', !ExpedienteAdjuntoVariants::is_allowed_variant(null));

$summary = ExpedienteAdjuntoVariants::spec('summary');
ac_assert(
    'spec summary 160 cover q65 32KiB',
    is_array($summary)
    && $summary['width'] === 160
    && $summary['height'] === 160
    && $summary['resize'] === 'cover'
    && $summary['quality'] === 65
    && $summary['max_bytes'] === 32768
    && $summary['upscale'] === false
);

$gallery = ExpedienteAdjuntoVariants::spec('gallery');
ac_assert(
    'spec gallery 384 cover q70 96KiB',
    is_array($gallery)
    && $gallery['width'] === 384
    && $gallery['height'] === 384
    && $gallery['resize'] === 'cover'
    && $gallery['quality'] === 70
    && $gallery['max_bytes'] === 98304
    && $gallery['upscale'] === false
);

$display = ExpedienteAdjuntoVariants::spec('display');
ac_assert(
    'spec display 1280 contain q75 512KiB sin upscale',
    is_array($display)
    && $display['width'] === 1280
    && $display['height'] === 1280
    && $display['resize'] === 'contain'
    && $display['quality'] === 75
    && $display['max_bytes'] === 524288
    && $display['upscale'] === false
);

ac_assert('spec variant inválida → null', ExpedienteAdjuntoVariants::spec('thumb') === null);
ac_assert('spec original → null', ExpedienteAdjuntoVariants::spec('original') === null);

$physical = ExpedienteAdjuntoVariants::ORIGINAL_MAX_BYTES
    + ExpedienteAdjuntoVariants::SUMMARY_MAX_BYTES
    + ExpedienteAdjuntoVariants::GALLERY_MAX_BYTES
    + ExpedienteAdjuntoVariants::DISPLAY_MAX_BYTES;
ac_assert('PHYSICAL_UPLOAD_MAX_BYTES = suma de topes', ExpedienteAdjuntoVariants::PHYSICAL_UPLOAD_MAX_BYTES === 1703936);
ac_assert('PHYSICAL_UPLOAD_MAX_BYTES coherente con constantes', ExpedienteAdjuntoVariants::PHYSICAL_UPLOAD_MAX_BYTES === $physical);

$parsed = ExpedienteAdjuntoVariants::parse_original_path($original);
ac_assert(
    'parse original válido',
    is_array($parsed)
    && $parsed['installation_id'] === $iid
    && $parsed['wp_client_id'] === 9
    && $parsed['wp_record_id'] === 42
    && $parsed['upload_operation_id'] === $op
    && $parsed['storage_path'] === $original
);

$upper = 'installations/' . strtoupper($iid) . '/clients/9/records/42/' . strtoupper($op) . '.jpg';
$parsed_upper = ExpedienteAdjuntoVariants::parse_original_path($upper);
ac_assert(
    'parse normaliza uuid a minúsculas',
    is_array($parsed_upper)
    && $parsed_upper['installation_id'] === $iid
    && $parsed_upper['upload_operation_id'] === $op
    && $parsed_upper['storage_path'] === $original
);

ac_assert(
    'derive summary',
    ExpedienteAdjuntoVariants::derive_path($original, 'summary')
    === 'installations/' . $iid . '/clients/9/records/42/' . $op . '_summary.jpg'
);
ac_assert(
    'derive gallery',
    ExpedienteAdjuntoVariants::derive_path($original, 'gallery')
    === 'installations/' . $iid . '/clients/9/records/42/' . $op . '_gallery.jpg'
);
ac_assert(
    'derive display',
    ExpedienteAdjuntoVariants::derive_path($original, 'display')
    === 'installations/' . $iid . '/clients/9/records/42/' . $op . '_display.jpg'
);

$summary_path = ExpedienteAdjuntoVariants::derive_path($original, 'summary');
$gallery_path = ExpedienteAdjuntoVariants::derive_path($original, 'gallery');
$display_path = ExpedienteAdjuntoVariants::derive_path($original, 'display');

ac_assert('path derivado summary rechazado como original', ExpedienteAdjuntoVariants::parse_original_path($summary_path) === null);
ac_assert('path derivado gallery rechazado como original', ExpedienteAdjuntoVariants::parse_original_path($gallery_path) === null);
ac_assert('path derivado display rechazado como original', ExpedienteAdjuntoVariants::parse_original_path($display_path) === null);

ac_assert('derive sobre summary → null', ExpedienteAdjuntoVariants::derive_path($summary_path, 'display') === null);
ac_assert('derive variant inválida → null', ExpedienteAdjuntoVariants::derive_path($original, 'thumb') === null);
ac_assert('derive original como variant → null', ExpedienteAdjuntoVariants::derive_path($original, 'original') === null);

ac_assert('parse path vacío → null', ExpedienteAdjuntoVariants::parse_original_path('') === null);
ac_assert('parse no-string → null', ExpedienteAdjuntoVariants::parse_original_path(null) === null);
ac_assert('parse traversal → null', ExpedienteAdjuntoVariants::parse_original_path('installations/../x.jpg') === null);
ac_assert('parse slash inicial → null', ExpedienteAdjuntoVariants::parse_original_path('/' . $original) === null);
ac_assert(
    'parse instalación inválida → null',
    ExpedienteAdjuntoVariants::parse_original_path(
        'installations/not-a-uuid/clients/9/records/42/' . $op . '.jpg'
    ) === null
);
ac_assert(
    'parse png → null',
    ExpedienteAdjuntoVariants::parse_original_path(
        'installations/' . $iid . '/clients/9/records/42/' . $op . '.png'
    ) === null
);
ac_assert(
    'parse operation no-v4 → null',
    ExpedienteAdjuntoVariants::parse_original_path(
        'installations/' . $iid . '/clients/9/records/42/11111111-2222-3333-4444-555555555555.jpg'
    ) === null
);
ac_assert(
    'derive instalación inválida → null',
    ExpedienteAdjuntoVariants::derive_path(
        'installations/not-a-uuid/clients/9/records/42/' . $op . '.jpg',
        'summary'
    ) === null
);

$src = file_get_contents($plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php');
ac_assert('sin I/O de storage', is_string($src) && strpos($src, 'supabase') === false && strpos($src, 'wp_remote') === false);
ac_assert('sin wp_get_image_editor', is_string($src) && strpos($src, 'wp_get_image_editor') === false);

echo "\n";
if (count($failed) === 0) {
    echo "Passed {$passed}/{$total}\n";
    exit(0);
}
echo 'Failed ' . count($failed) . "/{$total}\n";
foreach ($failed as $label) {
    echo " - {$label}\n";
}
exit(1);
