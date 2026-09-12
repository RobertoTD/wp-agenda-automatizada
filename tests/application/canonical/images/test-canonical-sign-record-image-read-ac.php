<?php
/**
 * AC IMG-4 bloque 2 — acceso y firma canónica de lectura.
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-canonical-sign-record-image-read-ac.php
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
if (!defined('AA_EXPEDIENTE_STORAGE_ORIGIN')) {
    define('AA_EXPEDIENTE_STORAGE_ORIGIN', 'https://storage.example.test');
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';
require_once $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php';
require_once $plugin_root . '/includes/application/canonical/images/GetCanonicalRecordImageReadUrlUseCase.php';

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/CanonicalSignRecordImageReadAjax.php');
$uc_src = file_get_contents($plugin_root . '/includes/application/canonical/images/GetCanonicalRecordImageReadUrlUseCase.php');
$policy_src = file_get_contents($plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php');
$boot_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');

ac_assert('action/nonce', strpos($ajax_src, "ACTION = 'aa_sign_canonical_record_image_read'") !== false
    && strpos($ajax_src, "NONCE_ACTION = 'aa_sign_canonical_record_image_read'") !== false);
ac_assert('AJAX authorize_identity', strpos($ajax_src, 'authorize_identity') !== false);
ac_assert('AJAX sin manage_options', strpos($ajax_src, 'manage_options') === false);
ac_assert('UC sin CanonicalShellWriteAjaxSupport', strpos($uc_src, 'CanonicalShellWriteAjaxSupport') === false);
ac_assert('UC path desde fila', strpos($uc_src, "image['storage_path']") !== false);
ac_assert('UC no lee storage_path POST', strpos($uc_src, '$_POST') === false && strpos($uc_src, 'storage_path') !== false);
ac_assert('UC exige ready+active', strpos($uc_src, 'capability_not_ready') !== false
    && strpos($uc_src, 'capability_inactive') !== false);
ac_assert('UC sin cuota/beneficio', strpos($uc_src, 'admission_used') === false
    && strpos($uc_src, 'storage_quota') === false
    && strpos($uc_src, 'used_bytes') === false);
ac_assert('UC valida URL firmada', strpos($uc_src, 'url_validator') !== false);
ac_assert('desactivar no cleanup', strpos($uc_src, 'mark_cleanup') === false && strpos($uc_src, 'DELETE') === false);
ac_assert('Access Policy finance', strpos($policy_src, "family_key !== 'finance'") !== false);
ac_assert('bootstrap registra sign-read', strpos($boot_src, 'CanonicalSignRecordImageReadAjax::register') !== false);
ac_assert('variantes allowlist en UC', strpos($uc_src, 'is_allowed_variant') !== false);
ac_assert('canonical path derive', ExpedienteAdjuntoVariants::derive_path(
    'installations/11111111-1111-4111-8111-111111111111/canonical/records/7/550e8400-e29b-41d4-a716-446655440000.jpg',
    'summary'
) === 'installations/11111111-1111-4111-8111-111111111111/canonical/records/7/550e8400-e29b-41d4-a716-446655440000_summary.jpg');

$path = 'installations/11111111-1111-4111-8111-111111111111/canonical/records/7/550e8400-e29b-41d4-a716-446655440000.jpg';
$good_url = 'https://storage.example.test/storage/v1/object/sign/expediente-adjuntos/'
    . 'installations/11111111-1111-4111-8111-111111111111/canonical/records/7/550e8400-e29b-41d4-a716-446655440000_gallery.jpg'
    . '?token=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.signature';
$validator = new AA_Expediente_Attachment_Read_Url_Validator();
$ok = $validator->validate($good_url, $path, 'gallery');
ac_assert('validator acepta canonical', !empty($ok['ok']));

$cap_registry = new AA_Canonical_Capability_Registry();
$cap_registry->register(new AA_Canonical_Capability_Definition(
    'images',
    AA_Canonical_Capability_Definition::SCOPE_RECORD,
    true
));
$cap_registry->freeze();

$images = new class {
    public $row;
    public function find_by_id(int $id) {
        return $this->row;
    }
};
$images->row = [
    'id' => 9,
    'record_id' => 7,
    'storage_path' => $path,
];

$relational = new class {
    public function resolve_family_id($k) { return 1; }
    public function find_container($f, $c) { return ['id' => $c]; }
    public function find_record($c, $r) { return ['id' => $r, 'container_id' => $c]; }
};

$config_active = new class {
    public function find_container_capability($c, $k) { return ['is_active' => 1]; }
};
$config_inactive = new class {
    public function find_container_capability($c, $k) { return ['is_active' => 0]; }
};

$backend_ok = new class($good_url) {
    private $url;
    public function __construct($url) { $this->url = $url; }
    public function sign_read($path, $variant): array {
        return [
            'ok' => true,
            'result' => [
                'url' => $this->url,
                'expires_in' => 600,
                'variant' => $variant,
            ],
        ];
    }
};

$uc = new GetCanonicalRecordImageReadUrlUseCase(
    $backend_ok,
    $validator,
    $images,
    $relational,
    $config_active,
    $cap_registry
);
$out = $uc->execute('finance', 1, 7, 9, 'gallery');
ac_assert('firma ok', !empty($out['ok']) && ($out['expires_in'] ?? 0) === 600 && ($out['variant'] ?? '') === 'gallery');
ac_assert('expires_in segundos', ($out['expires_in'] ?? 0) === 600);

$uc_inactive = new GetCanonicalRecordImageReadUrlUseCase(
    $backend_ok,
    $validator,
    $images,
    $relational,
    $config_inactive,
    $cap_registry
);
$blocked = $uc_inactive->execute('finance', 1, 7, 9, 'gallery');
ac_assert('inactive bloquea firma', ($blocked['ok'] ?? true) === false && ($blocked['code'] ?? '') === 'capability_inactive');

$nr = new AA_Canonical_Capability_Registry();
$nr->register(new AA_Canonical_Capability_Definition(
    'images',
    AA_Canonical_Capability_Definition::SCOPE_RECORD,
    false
));
$nr->freeze();
$uc_nr = new GetCanonicalRecordImageReadUrlUseCase(
    $backend_ok,
    $validator,
    $images,
    $relational,
    $config_active,
    $nr
);
$not_ready = $uc_nr->execute('finance', 1, 7, 9, 'gallery');
ac_assert('not-ready bloquea firma', ($not_ready['ok'] ?? true) === false && ($not_ready['code'] ?? '') === 'capability_not_ready');

$images_foreign = new class {
    public function find_by_id(int $id) {
        return [
            'id' => $id,
            'record_id' => 99,
            'storage_path' => 'installations/11111111-1111-4111-8111-111111111111/canonical/records/99/550e8400-e29b-41d4-a716-446655440099.jpg',
        ];
    }
};
$uc_foreign = new GetCanonicalRecordImageReadUrlUseCase(
    $backend_ok,
    $validator,
    $images_foreign,
    $relational,
    $config_active,
    $cap_registry
);
$foreign = $uc_foreign->execute('finance', 1, 7, 9, 'gallery');
ac_assert('imagen ajena 404', ($foreign['ok'] ?? true) === false && ($foreign['code'] ?? '') === 'image_not_found');

$bad_variant = $uc->execute('finance', 1, 7, 9, 'original');
ac_assert('variante original rechazada', ($bad_variant['ok'] ?? true) === false && ($bad_variant['code'] ?? '') === 'variant_invalid');

$backend_bad_url = new class {
    public function sign_read($path, $variant): array {
        return [
            'ok' => true,
            'result' => [
                'url' => 'http://evil.example/storage/v1/object/sign/expediente-adjuntos/x.jpg?token=abc',
                'expires_in' => 600,
                'variant' => $variant,
            ],
        ];
    }
};
$uc_bad = new GetCanonicalRecordImageReadUrlUseCase(
    $backend_bad_url,
    $validator,
    $images,
    $relational,
    $config_active,
    $cap_registry
);
$rejected = $uc_bad->execute('finance', 1, 7, 9, 'gallery');
ac_assert('URL inválida rechazada', ($rejected['ok'] ?? true) === false);

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
