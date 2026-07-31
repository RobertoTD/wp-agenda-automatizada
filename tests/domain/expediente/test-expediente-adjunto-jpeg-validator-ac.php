<?php
/**
 * AC — ExpedienteAdjuntoJpegValidator (MC4b).
 *
 * Ejecutar: php tests/domain/expediente/test-expediente-adjunto-jpeg-validator-ac.php
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
if (!defined('UPLOAD_ERR_OK')) {
    define('UPLOAD_ERR_OK', 0);
}
if (!defined('UPLOAD_ERR_NO_FILE')) {
    define('UPLOAD_ERR_NO_FILE', 4);
}
if (!defined('FILEINFO_MIME_TYPE')) {
    define('FILEINFO_MIME_TYPE', 16);
}
if (!defined('IMAGETYPE_JPEG')) {
    define('IMAGETYPE_JPEG', 2);
}

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoJpegValidator.php';

$src = file_get_contents($plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoJpegValidator.php');
ac_assert('validator file exists', is_string($src) && $src !== '');
ac_assert('MAX_BYTES 1MiB', strpos($src, '1048576') !== false);
ac_assert('MAX_DIMENSION 2048', strpos($src, '2048') !== false);
ac_assert('usa is_uploaded_file por defecto', strpos($src, "'is_uploaded_file'") !== false || strpos($src, '"is_uploaded_file"') !== false);
ac_assert('finfo MIME', strpos($src, 'finfo_file') !== false);
ac_assert('getimagesize', strpos($src, 'getimagesize') !== false);

function aa_make_jpeg_fixture(int $w, int $h, int $quality = 90): string {
    $img = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($img, 200, 100, 50);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);
    $path = tempnam(sys_get_temp_dir(), 'aa_jpg_');
    imagejpeg($img, $path, $quality);
    imagedestroy($img);
    return $path;
}

$ok_path = aa_make_jpeg_fixture(64, 48);
$validator = new ExpedienteAdjuntoJpegValidator(static function ($tmp) use ($ok_path) {
    return $tmp === $ok_path;
});

$result = $validator->validate([
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => $ok_path,
    'name' => 'foto.heic',
    'type' => 'image/heic',
    'size' => filesize($ok_path),
]);
ac_assert('acepta JPEG real ignorando name/type', !empty($result['ok']) && ($result['mime_type'] ?? '') === 'image/jpeg');
ac_assert('reporta dims', !empty($result['ok']) && (int) $result['width'] === 64 && (int) $result['height'] === 48);
@unlink($ok_path);

$not_uploaded = aa_make_jpeg_fixture(32, 32);
$strict = new ExpedienteAdjuntoJpegValidator(); // producción: is_uploaded_file real
$rej = $strict->validate([
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => $not_uploaded,
    'name' => 'a.jpg',
    'type' => 'image/jpeg',
    'size' => filesize($not_uploaded),
]);
ac_assert('producción rechaza no-upload', empty($rej['ok']) && ($rej['code'] ?? '') === 'upload_not_uploaded');
@unlink($not_uploaded);

$png_path = tempnam(sys_get_temp_dir(), 'aa_png_');
$png = imagecreatetruecolor(20, 20);
imagepng($png, $png_path);
imagedestroy($png);
$v2 = new ExpedienteAdjuntoJpegValidator(static function ($tmp) use ($png_path) {
    return $tmp === $png_path;
});
$png_res = $v2->validate([
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => $png_path,
    'name' => 'x.jpg',
    'type' => 'image/jpeg',
    'size' => filesize($png_path),
]);
ac_assert('rechaza PNG disfrazado', empty($png_res['ok']));
@unlink($png_path);

$over_path = aa_make_jpeg_fixture(32, 32);
// Inflar archivo más allá del límite sin ser JPEG válido grande: append bytes rompe JPEG —
// en su lugar crear archivo grande con cabecera JPEG mínima truncada + padding.
$big = aa_make_jpeg_fixture(32, 32);
$fh = fopen($big, 'ab');
fwrite($fh, str_repeat('A', 1048576 + 10));
fclose($fh);
$v3 = new ExpedienteAdjuntoJpegValidator(static function ($tmp) use ($big) {
    return $tmp === $big;
});
$big_res = $v3->validate([
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => $big,
    'name' => 'big.jpg',
    'type' => 'image/jpeg',
    'size' => filesize($big),
]);
ac_assert('rechaza oversize', empty($big_res['ok']) && ($big_res['code'] ?? '') === 'invalid_size');
@unlink($big);
@unlink($over_path);

$wide = aa_make_jpeg_fixture(2049, 10);
$v4 = new ExpedienteAdjuntoJpegValidator(static function ($tmp) use ($wide) {
    return $tmp === $wide;
});
$wide_res = $v4->validate([
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => $wide,
    'name' => 'wide.jpg',
    'type' => 'image/jpeg',
    'size' => filesize($wide),
]);
ac_assert('rechaza dimensión >2048', empty($wide_res['ok']) && ($wide_res['code'] ?? '') === 'invalid_dimensions');
@unlink($wide);

$trunc = tempnam(sys_get_temp_dir(), 'aa_tr_');
file_put_contents($trunc, "\xFF\xD8\xFF\xE0" . str_repeat("\0", 20));
$v5 = new ExpedienteAdjuntoJpegValidator(static function ($tmp) use ($trunc) {
    return $tmp === $trunc;
});
$trunc_res = $v5->validate([
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => $trunc,
    'name' => 't.jpg',
    'type' => 'image/jpeg',
    'size' => filesize($trunc),
]);
ac_assert('rechaza JPEG truncado', empty($trunc_res['ok']));
@unlink($trunc);

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
