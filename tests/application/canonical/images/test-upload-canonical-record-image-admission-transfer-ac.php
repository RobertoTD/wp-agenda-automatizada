<?php
/**
 * AC IMG-3b bloque 2 — admisión, manifiesto y transferencia.
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-upload-canonical-record-image-admission-transfer-ac.php
 */

$plugin_root = dirname(__DIR__, 4);

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
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options);
    }
}

$uc_src = file_get_contents($plugin_root . '/includes/application/canonical/images/UploadCanonicalRecordImageUseCase.php');
$transfer_src = file_get_contents($plugin_root . '/includes/application/canonical/images/CanonicalImageUploadTransfer.php');

ac_assert('persist admisión antes de put_and_finalize en fresh',
    strpos($uc_src, 'insert_admitted') !== false
    && strpos($uc_src, 'put_and_finalize') !== false
    && strpos($uc_src, 'insert_admitted') < strpos($uc_src, "run_fresh") + 8000
);
ac_assert('manifiesto urls+variant_byte_sizes', strpos($uc_src, "'variant_byte_sizes'") !== false
    && strpos($uc_src, "'urls'") !== false);
ac_assert('resume exclude propia reserva', strpos($uc_src, 'admission_used_bytes($operation_id)') !== false);
ac_assert('fresh sin exclude', strpos($uc_src, 'admission_used_bytes(null)') !== false);
ac_assert('variant_manifest_mismatch definido', strpos($uc_src, 'variant_manifest_mismatch') !== false);
$resume_pos = strpos($uc_src, 'function run_resume');
$mismatch_pos = strpos($uc_src, 'variant_manifest_mismatch');
$put_in_resume = $resume_pos !== false ? strpos($uc_src, 'put_and_finalize', $resume_pos) : false;
ac_assert('variant_manifest_mismatch antes de PUT en resume', $resume_pos !== false && $mismatch_pos !== false && $put_in_resume !== false
    && $mismatch_pos > $resume_pos && $mismatch_pos < $put_in_resume);
ac_assert('resume exige mismo intent', strpos($uc_src, 'El intent remoto no coincide') !== false);
ac_assert('resume no acepta signed_url nuevas del authorize', strpos($uc_src, "(string) \$urls[\$key]") !== false);
ac_assert('transfer omite already_uploaded', strpos($transfer_src, "already_uploaded") !== false);
ac_assert('transfer orden summary→…→original', strpos($transfer_src, "'summary', 'gallery', 'display', 'original'") !== false
    || strpos($transfer_src, '"summary", "gallery", "display", "original"') !== false);
ac_assert('colisión ops reconcilia árbol', strpos($uc_src, 'CanonicalImageUploadOperationConflict') !== false);

require_once $plugin_root . '/includes/application/canonical/images/CanonicalImageUploadTransfer.php';

$puts = [];
$transfer = new CanonicalImageUploadTransfer(
    new class {
        public function finalize(string $intent): array {
            return [
                'ok' => true,
                'result' => [
                    'storage_path' => 'p.jpg',
                    'upload_operation_id' => 'op',
                    'installation_id' => 'i',
                    'mime_type' => 'image/jpeg',
                    'byte_size' => 4,
                    'width' => 1,
                    'height' => 1,
                ],
            ];
        }
    },
    new class($puts) {
        private $puts;
        public function __construct(&$puts) { $this->puts = &$puts; }
        public function put_jpeg($url, $binary, $path): array {
            $this->puts[] = ['url' => $url, 'path' => $path, 'len' => strlen($binary)];
            return ['ok' => true];
        }
    }
);

$tmp_o = tempnam(sys_get_temp_dir(), 'o');
$tmp_s = tempnam(sys_get_temp_dir(), 's');
$tmp_g = tempnam(sys_get_temp_dir(), 'g');
$tmp_d = tempnam(sys_get_temp_dir(), 'd');
file_put_contents($tmp_o, 'aaaa');
file_put_contents($tmp_s, 'bb');
file_put_contents($tmp_g, 'ccc');
file_put_contents($tmp_d, 'dddd');

$out = $transfer->put_and_finalize([
    'upload_intent' => 'intent',
    'storage_path' => 'installations/x/canonical/records/1/op.jpg',
    'objects' => [
        'original' => ['status' => 'pending_upload', 'signed_url' => 'https://u/original'],
        'summary' => ['status' => 'already_uploaded'],
        'gallery' => ['status' => 'pending_upload', 'signed_url' => 'https://u/gallery'],
        'display' => ['status' => 'already_uploaded'],
    ],
    'local_files' => [
        'original' => $tmp_o,
        'summary' => $tmp_s,
        'gallery' => $tmp_g,
        'display' => $tmp_d,
    ],
    'expected_sizes' => [
        'original' => 4,
        'summary' => 2,
        'gallery' => 3,
        'display' => 4,
    ],
]);

ac_assert('transfer parcial ok', !empty($out['ok']));
ac_assert('solo PUT pendientes', count($puts) === 2);
ac_assert('orden gallery luego original', ($puts[0]['url'] ?? '') === 'https://u/gallery'
    && ($puts[1]['url'] ?? '') === 'https://u/original');

@unlink($tmp_o);
@unlink($tmp_s);
@unlink($tmp_g);
@unlink($tmp_d);

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
