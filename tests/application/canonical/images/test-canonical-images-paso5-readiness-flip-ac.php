<?php
/**
 * AC Paso 5 — flip readiness images + DEFAULTS_VERSION=3.
 *
 * Tres baterías:
 *   1) Lifecycle/seeds (fuente + insert-if-missing / no overwrite)
 *   2) Selección y gates por lista
 *   3) Orquestación de registro con doubles (UI/presenter; sin Storage/HTTP real)
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-canonical-images-paso5-readiness-flip-ac.php
 *   AA_WP_ROOT=… php tests/application/canonical/images/test-canonical-images-paso5-readiness-flip-ac.php
 *   scripts/safe-node-test.sh tests/js/canonical-shell-record-form.test.js
 *   scripts/safe-node-test.sh tests/js/canonical-shell-images-field.test.js
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

echo "=== Batería 1. Lifecycle / seeds (fuente) ===\n";

$boot = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'
);
$life = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php'
);
$schema = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/wp/Schema.php'
);

ac_assert(
    'images is_ready=true',
    preg_match(
        "/new AA_Canonical_Capability_Definition\(\s*'images'\s*,\s*AA_Canonical_Capability_Definition::SCOPE_RECORD\s*,\s*true\s*\)/s",
        $boot
    ) === 1
);
ac_assert('DEFAULTS_VERSION=3', strpos($life, 'public const DEFAULTS_VERSION = 3;') !== false);
ac_assert('DB_VERSION permanece 31', strpos($schema, "public const DB_VERSION = '31';") !== false);
ac_assert('ensure usa insert_family_capability_if_missing', strpos($life, 'insert_family_capability_if_missing') !== false);
ac_assert('ensure salta !is_ready', strpos($life, '!$definition->is_ready()') !== false);

// Matriz desde fuente (sin cargar clases WP antes de wp-load).
$images_by_family = [];
if (preg_match_all(
    "/'family_key'\\s*=>\\s*'(archive|finance|catalog|contact)'\\s*,\\s*'capability_key'\\s*=>\\s*'images'\\s*,\\s*'is_default'\\s*=>\\s*(true|false)/s",
    $life,
    $m,
    PREG_SET_ORDER
)) {
    foreach ($m as $row) {
        $images_by_family[$row[1]] = ($row[2] === 'true');
    }
}
ac_assert('matriz 4 familias images', count($images_by_family) === 4);
ac_assert('archive default on', ($images_by_family['archive'] ?? false) === true);
ac_assert('finance default off', ($images_by_family['finance'] ?? true) === false);
ac_assert('catalog default off', ($images_by_family['catalog'] ?? true) === false);
ac_assert('contact default off', ($images_by_family['contact'] ?? true) === false);

echo "\n=== Batería 2. Selección / gates (contratos estáticos) ===\n";

$index = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php'
);
$form_js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js'
);
$images_js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-field.js'
);
$uc_src = (string) file_get_contents(
    $plugin_root . '/includes/application/canonical/images/UploadCanonicalRecordImageUseCase.php'
);
$contributor_src = (string) file_get_contents(
    $plugin_root . '/includes/application/canonical/capabilities/images/CanonicalImagesRecordsPageContributor.php'
);

ac_assert('UI filtra options por is_ready', strpos($index, '!$cap_def->is_ready()') !== false);
ac_assert('Label Imágenes cableado', strpos($index, "'Imágenes'") !== false);
ac_assert('Picker gated por images_offered', strpos($index, 'images_offered') !== false);
ac_assert('Form hook post-save', strpos($form_js, 'continueAfterRecordConfirmed') !== false
    && strpos($form_js, 'afterRecordSaved') !== false);
ac_assert('fresh exige assert_fresh_capability', strpos($uc_src, 'assert_fresh_capability') !== false);
ac_assert('contributor exige ready', strpos($contributor_src, 'is_ready()') !== false);
ac_assert('contributor exige is_active', strpos($contributor_src, "['is_active']") !== false
    || strpos($contributor_src, 'is_active') !== false);

echo "\n=== Batería 3. Orquestación registro / presenter (doubles) ===\n";

ac_assert('images module registered key', strpos($images_js, "key: 'images'") !== false);
ac_assert('images collect no-op WriteBag', preg_match(
    '/function collect\(\)\s*\{\s*\/\/ Images no van en WriteBag/s',
    $images_js
) === 1);
ac_assert('retry reutiliza pending operation', strpos($images_js, 'upload_operation_id') !== false
    && strpos($images_js, 'RECOVERABLE_CODES') !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
$has_wp = ($wp_load !== '' && is_readable($wp_load));

if ($has_wp) {
    require_once $wp_load;
} elseif (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-images-shell-presenter.php';

$collection = CanonicalCapabilityRecordReadState::known_collection([
    ['id' => 30, 'width' => 1, 'height' => 1, 'byte_size' => 1, 'created_at' => 'b'],
    ['id' => 10, 'width' => 1, 'height' => 1, 'byte_size' => 1, 'created_at' => 'a'],
])->to_array();
$thumb = AA_Canonical_Images_Shell_Presenter::card_view(['images' => $collection]);
ac_assert(
    'última summary = collection[0] (id 30)',
    is_array($thumb) && ($thumb['kind'] ?? '') === 'thumb' && (int) ($thumb['image_id'] ?? 0) === 30
);
$absent = AA_Canonical_Images_Shell_Presenter::card_view([
    'images' => CanonicalCapabilityRecordReadState::known_absent()->to_array(),
]);
ac_assert('sin imagen no galería vacía', $absent === null);

$sign_ok = new class {
    public function execute($a, $b, $c, $d, $e): array {
        return ['ok' => true, 'url' => 'https://signed.example/summary.jpg'];
    }
};
$sign_fail = new class {
    public function execute($a, $b, $c, $d, $e): array {
        return ['ok' => false, 'code' => 'capability_inactive', 'message' => '/secret/path'];
    }
};
ac_assert(
    'sign summary ok',
    AA_Canonical_Images_Shell_Presenter::resolve_summary_url($sign_ok, 'archive', 1, 2, 30)
        === 'https://signed.example/summary.jpg'
);
ac_assert(
    'sign fail discreto sin path',
    AA_Canonical_Images_Shell_Presenter::resolve_summary_url($sign_fail, 'archive', 1, 2, 30) === null
);
ac_assert(
    'variant summary',
    AA_Canonical_Images_Shell_Presenter::SUMMARY_VARIANT === 'summary'
);

if (!$has_wp) {
    echo "\n[INFO / SKIP] MySQL real no ejecutado (AA_WP_ROOT ausente). Baterías 1–3 estáticas PASS.\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-catalog-lifecycle.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilitySchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilitySelection.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilitySelectionPreparer.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityUnknown.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityNotReady.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityWriteRejected.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyNotProvisioned.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyUnknown.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilityEffect.php';
require_once $plugin_root . '/includes/infrastructure/canonical/capabilities/class-aa-canonical-apply-container-capability-selection-effect.php';

echo "\n=== Batería 1b. Lifecycle MySQL (insert-if-missing) ===\n";

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_p5_' . substr(md5(uniqid('p5', true)), 0, 8) . '_';
$prior_db = (string) get_option('aa_db_version', '0');

$cleanup = static function () use ($wpdb, $temp_prefix): void {
    if (strpos($temp_prefix, 'tmp_p5_') !== 0) {
        return;
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $like = $wpdb->esc_like($temp_prefix) . '%';
    $rows = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
    if (is_array($rows)) {
        foreach ($rows as $t) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $t) . '`');
        }
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
};

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();
    AA_Canonical_Schema::install();
    update_option('aa_db_version', '31');

    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);
    AA_Canonical_Capability_Registry_Bootstrap::reset_for_tests();
    $cap_registry = AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
    ac_assert('runtime images ready', $cap_registry->get('images')->is_ready() === true);

    $config = new CanonicalCapabilityConfigRepository($wpdb);

    // Lista previa sin images: se crea contenedor vacío de caps y se conserva.
    $finance_id = (int) $config->resolve_family_id('finance');
    $archive_id = (int) $config->resolve_family_id('archive');
    $now = gmdate('Y-m-d H:i:s');
    $wpdb->insert(
        AA_Canonical_Schema::containers_table_name(),
        [
            'family_id' => $finance_id,
            'public_id' => wp_generate_uuid4(),
            'title' => 'Lista previa',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s']
    );
    $prior_list_id = (int) $wpdb->insert_id;
    $prior_caps_before = $config->list_container_capabilities($prior_list_id);

    foreach (AA_Canonical_Capability_Defaults_Lifecycle::declared_seeds() as $seed) {
        $def = $cap_registry->get($seed['capability_key']);
        if (!$def->is_ready()) {
            continue;
        }
        $fid = $config->resolve_family_id($seed['family_key']);
        if ($fid === null) {
            continue;
        }
        $config->insert_family_capability_if_missing((int) $fid, $seed['capability_key'], (bool) $seed['is_default']);
    }

    $img_arch = $config->find_family_capability($archive_id, 'images');
    $img_fin = $config->find_family_capability($finance_id, 'images');
    ac_assert('seed archive on', is_array($img_arch) && $img_arch['is_default'] === true);
    ac_assert('seed finance off', is_array($img_fin) && $img_fin['is_default'] === false);

    $config->upsert_family_capability($finance_id, 'images', true);
    $skipped = $config->insert_family_capability_if_missing($finance_id, 'images', false);
    $guarded = $config->find_family_capability($finance_id, 'images');
    ac_assert('re-ensure no sobrescribe is_default guardado', $skipped === false && is_array($guarded) && $guarded['is_default'] === true);
    $config->upsert_family_capability($finance_id, 'images', false);

    $prior_caps_after = $config->list_container_capabilities($prior_list_id);
    ac_assert(
        'lista existente sin activación retroactiva',
        count($prior_caps_before) === count($prior_caps_after)
            && $config->find_container_capability($prior_list_id, 'images') === null
    );

    echo "\n=== Batería 2b. Gates lista MySQL ===\n";

    $preparer = new CanonicalContainerCapabilitySelectionPreparer($config, $cap_registry);

    $create_ok = false;
    try {
        $effect = $preparer->build_create_effect(
            'archive',
            CanonicalContainerCapabilitySelection::present(['images'], ['images'])
        );
        $create_ok = ($effect !== null);
    } catch (\Throwable $e) {
        $create_ok = false;
    }
    ac_assert('create selection images aceptada (producto ready)', $create_ok);

    $wpdb->insert(
        AA_Canonical_Schema::containers_table_name(),
        [
            'family_id' => $archive_id,
            'public_id' => wp_generate_uuid4(),
            'title' => 'Lista archive images',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s']
    );
    $list_id = (int) $wpdb->insert_id;

    $config->upsert_container_capability($list_id, 'images', true);
    $active_row = $config->find_container_capability($list_id, 'images');
    ac_assert('lista activa images', is_array($active_row) && !empty($active_row['is_active']));

    $config->upsert_container_capability($list_id, 'images', false);
    $inactive_row = $config->find_container_capability($list_id, 'images');
    ac_assert('desactivar conserva fila inactive', is_array($inactive_row) && empty($inactive_row['is_active']));

    $config->upsert_container_capability($list_id, 'images', true);
    $reactivated = $config->find_container_capability($list_id, 'images');
    ac_assert('reactivar images', is_array($reactivated) && !empty($reactivated['is_active']));

    $config->insert_family_capability_if_missing($finance_id, 'amount', true);
    $wpdb->insert(
        AA_Canonical_Schema::containers_table_name(),
        [
            'family_id' => $finance_id,
            'public_id' => wp_generate_uuid4(),
            'title' => 'Finance both',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s']
    );
    $fin_list = (int) $wpdb->insert_id;
    $config->upsert_container_capability($fin_list, 'amount', true);
    $config->upsert_container_capability($fin_list, 'images', true);
    ac_assert(
        'amount + images coexisten',
        !empty($config->find_container_capability($fin_list, 'amount')['is_active'])
        && !empty($config->find_container_capability($fin_list, 'images')['is_active'])
    );
} catch (\Throwable $e) {
    ac_assert('Excepción MySQL: ' . $e->getMessage(), false);
} finally {
    $cleanup();
    $wpdb->prefix = $prior_prefix;
    update_option('aa_db_version', $prior_db);
    AA_Canonical_Capability_Registry_Bootstrap::reset_for_tests();
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
