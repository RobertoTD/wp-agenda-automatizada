<?php
/**
 * AC Paso 2+ — presentación images (summary cabecera + galería panel).
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-canonical-images-shell-paso2-ui-ac.php
 *   scripts/safe-node-test.sh tests/js/canonical-shell-images-field.test.js
 *   scripts/safe-node-test.sh tests/js/canonical-shell-images-gallery.test.js
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

echo "=== 1. Contratos UI estáticos ===\n";

$index = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php'
);
$form_js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js'
);
$images_js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-field.js'
);
$gallery_js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-gallery.js'
);
$card = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php'
);
$gallery_partial = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-images-gallery.php'
);
$defaults = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php'
);
$bootstrap = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');

ac_assert('Campo images SSR condicionado a offered', strpos($index, 'images_offered') !== false);
ac_assert('Boot attachImageAction/Nonce', strpos($index, 'attachImageAction') !== false
    && strpos($index, 'attachImageNonce') !== false);
ac_assert('Boot signReadAction/Nonce', strpos($index, 'signReadAction') !== false
    && strpos($index, 'signReadNonce') !== false);
ac_assert('Sin batch duplicado en index', strpos($index, 'records_page_images_by_record') === false
    && strpos($index, 'find_public_rows_by_record_ids_for_container') === false);
ac_assert('Advertencia delete lista menciona imágenes', strpos($index, 'sus imágenes asociadas') !== false
    && strpos($index, 'Esta eliminación es irremediable') !== false);
ac_assert('Visor modal shell', strpos($index, 'aa-shell-image-viewer-modal') !== false);
ac_assert('Carga gallery.js antes del form', strpos($index, 'canonical-shell-images-gallery.js') !== false
    && strpos($index, 'canonical-shell-images-gallery.js') < strpos($index, 'canonical-shell-record-form.js'));
ac_assert('Carga images-field.js antes del form', strpos($index, 'canonical-shell-images-field.js') !== false
    && strpos($index, 'canonical-shell-images-field.js') < strpos($index, 'canonical-shell-record-form.js'));
ac_assert('Form continueAfterRecordConfirmed', strpos($form_js, 'continueAfterRecordConfirmed') !== false);
ac_assert('Form afterRecordSaved hook', strpos($form_js, 'afterRecordSaved') !== false);
ac_assert('Form afterRetire galería', strpos($form_js, 'AACanonicalShellImagesGallery.afterRetire') !== false);
ac_assert('Images no escribe WriteBag (collect vacío)', preg_match(
    '/function collect\(\)\s*\{\s*\/\/ Images no van en WriteBag/s',
    $images_js
) === 1);
ac_assert('Gallery JS sin expediente-registros', strpos($gallery_js, 'expediente-registros') === false
    && strpos($gallery_js, 'aa-expediente-') === false);
ac_assert('Card summary cabecera compacta', strpos($card, 'aa-shell-record-image-summary--header') !== false);
ac_assert('Card incluye partial galería', strpos($card, 'record-images-gallery.php') !== false);
ac_assert('Partial galería principal display', strpos($gallery_partial, 'data-aa-read-version="display"') !== false);
ac_assert('Partial galería mini gallery', strpos($gallery_partial, 'data-aa-read-version="gallery"') !== false);
ac_assert('Partial sin lista textual Imagen #', strpos($gallery_partial, 'Imagen #') === false);
ac_assert('Presenter images registrado', strpos($bootstrap, 'class-aa-canonical-images-shell-presenter.php') !== false);
ac_assert('DEFAULTS_VERSION = 3', strpos($defaults, 'public const DEFAULTS_VERSION = 3;') !== false);

$resolve_pos = strpos($index, '$aa_shell_resolve_card_image_summary_url');
$uc_require_pos = strpos(
    $index,
    "require_once dirname(__DIR__, 4) . '/application/canonical/images/GetCanonicalRecordImageReadUrlUseCase.php'"
);
ac_assert(
    'SSR resolve carga GetCanonicalRecordImageReadUrlUseCase',
    $resolve_pos !== false
    && $uc_require_pos !== false
    && $uc_require_pos > $resolve_pos
    && strpos($index, 'new GetCanonicalRecordImageReadUrlUseCase()', $uc_require_pos) !== false
);

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}
if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        return $url;
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-amount-shell-presenter.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-images-shell-presenter.php';

echo "\n=== 2. Presenter: galería id DESC ===\n";

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
$gallery = AA_Canonical_Images_Shell_Presenter::card_view(['images' => $collection]);
ac_assert(
    'collection → gallery con última = 20',
    is_array($gallery)
    && ($gallery['kind'] ?? '') === 'gallery'
    && (int) ($gallery['image_id'] ?? 0) === 20
    && isset($gallery['image_ids'])
    && $gallery['image_ids'] === [20, 10]
);

$reread = AA_Canonical_Images_Shell_Presenter::card_view(['images' => $collection]);
ac_assert(
    'relectura independiente misma última',
    is_array($reread) && (int) ($reread['image_id'] ?? 0) === 20
);

echo "\n=== 3. Sign-read discreto summary ===\n";

$ok_uc = new class {
    public function execute($fk, $cid, $rid, $iid, $variant): array {
        return ['ok' => true, 'url' => 'https://signed.example/summary.jpg', 'expires_in' => 60];
    }
};
$url = AA_Canonical_Images_Shell_Presenter::resolve_summary_url($ok_uc, 'archive', 1, 2, 20);
ac_assert('sign ok → URL https', $url === 'https://signed.example/summary.jpg');
ac_assert('variant summary constante', AA_Canonical_Images_Shell_Presenter::SUMMARY_VARIANT === 'summary');
ac_assert('constantes gallery/display', AA_Canonical_Images_Shell_Presenter::GALLERY_VARIANT === 'gallery'
    && AA_Canonical_Images_Shell_Presenter::DISPLAY_VARIANT === 'display');

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

echo "\n=== 5. Card compacta: summary cabecera + galería; sin lista textual ===\n";

$collection_multi = CanonicalCapabilityRecordReadState::known_collection([
    ['id' => 7, 'width' => 1, 'height' => 1, 'byte_size' => 1, 'created_at' => '2026-01-03'],
    ['id' => 5, 'width' => 1, 'height' => 1, 'byte_size' => 1, 'created_at' => '2026-01-02'],
    ['id' => 3, 'width' => 1, 'height' => 1, 'byte_size' => 1, 'created_at' => '2026-01-01'],
])->to_array();

$card_title = 'Registro 192';
$card_details = 'woowow';
$card_iso = '2026-09-15T12:00:00+00:00';
$card_display = '15 sep 2026';
$card_record_id = 192;
$card_capabilities = ['images' => $collection_multi];
$show_edit_record = false;
$shell_record_presentation = 'compact';
$show_image_actions = true;
$card_image_summary_url = null;

ob_start();
require $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php';
$html_no_url = (string) ob_get_clean();
ac_assert(
    'Sin URL: sin summary cabecera',
    strpos($html_no_url, 'aa-shell-record-image-summary--header') === false
);
ac_assert(
    'Sin URL: galería presente (collection offered)',
    strpos($html_no_url, 'aa-shell-record-gallery') !== false
);
ac_assert(
    'Sin URL: sin lista textual Imagen #',
    strpos($html_no_url, 'Imagen #') === false
);
ac_assert(
    'Sin URL: tira + contador con 3 imágenes',
    strpos($html_no_url, 'aa-shell-record-gallery-strip') !== false
    && strpos($html_no_url, '1 de 3') !== false
);
ac_assert(
    'Sin URL: papelera delete presente',
    strpos($html_no_url, 'aa-shell-delete-image-btn') !== false
);

$card_image_summary_url = 'https://signed.example/summary.jpg';
ob_start();
require $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php';
$html_with_url = (string) ob_get_clean();
ac_assert(
    'Con URL: summary en cabecera del toggle',
    strpos($html_with_url, 'aa-shell-record-image-summary--header') !== false
    && strpos($html_with_url, 'https://signed.example/summary.jpg') !== false
    && strpos($html_with_url, 'aa-shell-record-toggle') !== false
    && preg_match(
        '/aa-shell-record-toggle[\s\S]*aa-shell-record-image-summary--header[\s\S]*aa-shell-record-title/s',
        $html_with_url
    ) === 1
);
ac_assert(
    'Con URL: galería en panel',
    preg_match(
        '/id="aa-shell-record-panel-\d+"[\s\S]*aa-shell-record-gallery/s',
        $html_with_url
    ) === 1
);

$collection_one = CanonicalCapabilityRecordReadState::known_collection([
    ['id' => 9, 'width' => 1, 'height' => 1, 'byte_size' => 1, 'created_at' => '2026-01-02'],
])->to_array();
$card_capabilities = ['images' => $collection_one];
$card_image_summary_url = 'https://signed.example/one.jpg';
ob_start();
require $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php';
$html_one = (string) ob_get_clean();
ac_assert(
    'Una imagen: sin tira ni contador',
    strpos($html_one, 'aa-shell-record-gallery-strip') === false
    && strpos($html_one, ' de ') === false
    && strpos($html_one, 'aa-shell-record-gallery-main') !== false
);

$card_capabilities = null;
$card_image_summary_url = 'https://signed.example/stale.jpg';
ob_start();
require $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php';
$html_inactive = (string) ob_get_clean();
ac_assert(
    'Sin capability offered: sin summary ni galería',
    strpos($html_inactive, 'aa-shell-record-image-summary') === false
    && strpos($html_inactive, 'aa-shell-record-gallery') === false
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
