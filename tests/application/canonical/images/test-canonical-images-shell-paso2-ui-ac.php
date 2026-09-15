<?php
/**
 * AC Paso 2 — presentación mínima images (presenter + contratos UI estáticos).
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-canonical-images-shell-paso2-ui-ac.php
 *   scripts/safe-node-test.sh tests/js/canonical-shell-images-field.test.js
 *   scripts/safe-node-test.sh tests/js/canonical-shell-record-form.test.js
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

echo "=== 1. Contratos UI estáticos Paso 2 ===\n";

$index = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php'
);
$form_js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js'
);
$images_js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-field.js'
);
$card = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php'
);
$defaults = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php'
);
$bootstrap = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');

ac_assert('Campo images SSR condicionado a offered', strpos($index, 'images_offered') !== false);
ac_assert('Boot attachImageAction/Nonce', strpos($index, 'attachImageAction') !== false
    && strpos($index, 'attachImageNonce') !== false);
ac_assert('Carga images-field.js antes del form', strpos($index, 'canonical-shell-images-field.js') !== false
    && strpos($index, 'canonical-shell-images-field.js') < strpos($index, 'canonical-shell-record-form.js'));
ac_assert('Form continueAfterRecordConfirmed', strpos($form_js, 'continueAfterRecordConfirmed') !== false);
ac_assert('Form afterRecordSaved hook', strpos($form_js, 'afterRecordSaved') !== false);
ac_assert('Images no escribe WriteBag (collect vacío)', preg_match(
    '/function collect\(\)\s*\{\s*\/\/ Images no van en WriteBag/s',
    $images_js
) === 1);
ac_assert('Card miniatura summary', strpos($card, 'aa-shell-record-image-summary') !== false);
ac_assert('Presenter images registrado', strpos($bootstrap, 'class-aa-canonical-images-shell-presenter.php') !== false);
ac_assert('DEFAULTS_VERSION = 3', strpos($defaults, 'public const DEFAULTS_VERSION = 3;') !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-images-shell-presenter.php';

echo "\n=== 2. Presenter: última = id DESC (primera del collection) ===\n";


$absent = AA_Canonical_Images_Shell_Presenter::card_view([
    'images' => CanonicalCapabilityRecordReadState::known_absent()->to_array(),
]);
ac_assert('known_absent no renderiza galería', $absent === null);

$failed_read = AA_Canonical_Images_Shell_Presenter::card_view([
    'images' => CanonicalCapabilityRecordReadState::read_failed()->to_array(),
]);
ac_assert('read_failed → kind error', is_array($failed_read) && ($failed_read['kind'] ?? '') === 'error');

$collection = CanonicalCapabilityRecordReadState::known_collection([
    ['id' => 20, 'width' => 1, 'height' => 1, 'byte_size' => 1, 'created_at' => '2026-01-02'],
    ['id' => 10, 'width' => 1, 'height' => 1, 'byte_size' => 1, 'created_at' => '2026-01-01'],
])->to_array();
$thumb = AA_Canonical_Images_Shell_Presenter::card_view(['images' => $collection]);
ac_assert(
    'collection toma primera (id 20 = última persistida)',
    is_array($thumb) && ($thumb['kind'] ?? '') === 'thumb' && (int) ($thumb['image_id'] ?? 0) === 20
);

$reread = AA_Canonical_Images_Shell_Presenter::card_view(['images' => $collection]);
ac_assert(
    'relectura independiente misma última',
    is_array($reread) && (int) ($reread['image_id'] ?? 0) === 20
);

echo "\n=== 3. Sign-read discreto (sin filtrar paths) ===\n";

$ok_uc = new class {
    public function execute($fk, $cid, $rid, $iid, $variant): array {
        return ['ok' => true, 'url' => 'https://signed.example/summary.jpg', 'expires_in' => 60];
    }
};
$url = AA_Canonical_Images_Shell_Presenter::resolve_summary_url($ok_uc, 'archive', 1, 2, 20);
ac_assert('sign ok → URL https', $url === 'https://signed.example/summary.jpg');
ac_assert('variant summary constante', AA_Canonical_Images_Shell_Presenter::SUMMARY_VARIANT === 'summary');

$fail_uc = new class {
    public function execute($fk, $cid, $rid, $iid, $variant): array {
        return ['ok' => false, 'code' => 'capability_not_ready', 'message' => 'internal /storage/path/secret'];
    }
};
$no_url = AA_Canonical_Images_Shell_Presenter::resolve_summary_url($fail_uc, 'archive', 1, 2, 20);
ac_assert('sign fail → null (sin path)', $no_url === null);

$path_uc = new class {
    public function execute($fk, $cid, $rid, $iid, $variant): array {
        return ['ok' => true, 'url' => '/internal/storage/path.jpg'];
    }
};
$no_path = AA_Canonical_Images_Shell_Presenter::resolve_summary_url($path_uc, 'archive', 1, 2, 20);
ac_assert('URL no-http rechazada', $no_path === null);

$edit = AA_Canonical_Images_Shell_Presenter::edit_payload_fragment(['images' => $collection]);
ac_assert(
    'edit payload no expone collection',
    is_array($edit) && ($edit['status'] ?? '') === 'known_absent' && !isset($edit['items'])
);

echo "\n=== 4. is_ready de producción true tras Paso 5 ===\n";

$registry_boot = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'
);
ac_assert(
    'bootstrap images is_ready true en fuente',
    preg_match(
        "/new AA_Canonical_Capability_Definition\(\s*'images',\s*AA_Canonical_Capability_Definition::SCOPE_RECORD,\s*true\s*\)/s",
        $registry_boot
    ) === 1
);

$db_schema = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/wp/Schema.php'
);
ac_assert('DB_VERSION = 31 sin cambio', strpos($db_schema, "public const DB_VERSION = '31';") !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
