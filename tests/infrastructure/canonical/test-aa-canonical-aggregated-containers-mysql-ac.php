<?php
/**
 * AC Test — Lectura agregada multi-familia MySQL (Todas las listas).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-aggregated-containers-mysql-ac.php
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

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL real no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit(0);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalAggregatedContainerItem.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalAggregatedContainersPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalAggregatedContainersPort.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellAggregatedReadResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellAllContainersUseCase.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-aggregated-containers-adapter.php';

global $wpdb;
$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_agg_' . substr(md5(uniqid('ag', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_agg_') !== 0) {
        return;
    }
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_canonical_records`');
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_canonical_containers`');
    $wpdb->query('DROP TABLE IF EXISTS `' . $p . 'aa_canonical_families`');
};

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);
    AA_Canonical_Schema::install();

    $families = $temp_prefix . 'aa_canonical_families';
    $containers = $temp_prefix . 'aa_canonical_containers';
    $now = gmdate('Y-m-d H:i:s');

    $wpdb->insert($families, [
        'family_key' => 'finance',
        'is_enabled' => 1,
        'seed_version' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $finance_id = (int) $wpdb->insert_id;
    $wpdb->insert($families, [
        'family_key' => 'archive',
        'is_enabled' => 1,
        'seed_version' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $archive_id = (int) $wpdb->insert_id;
    $wpdb->insert($families, [
        'family_key' => 'disabled_fam',
        'is_enabled' => 0,
        'seed_version' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $disabled_id = (int) $wpdb->insert_id;

    for ($i = 1; $i <= 10; $i++) {
        $ts = sprintf('2026-01-%02d 10:00:00', $i);
        $wpdb->insert($containers, [
            'public_id' => sprintf('00000000-0000-4000-8000-%012d', $i),
            'family_id' => $finance_id,
            'title' => 'F-' . $i,
            'details' => null,
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
    }
    for ($i = 1; $i <= 8; $i++) {
        $ts = sprintf('2026-02-%02d 10:00:00', $i);
        $wpdb->insert($containers, [
            'public_id' => sprintf('00000000-0000-4000-8000-%012d', 100 + $i),
            'family_id' => $archive_id,
            'title' => 'A-' . $i,
            'details' => null,
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
    }
    $wpdb->insert($containers, [
        'public_id' => '00000000-0000-4000-8000-000000000999',
        'family_id' => $disabled_id,
        'title' => 'Hidden',
        'details' => null,
        'created_at' => '2026-03-01 10:00:00',
        'updated_at' => '2026-03-01 10:00:00',
    ]);

    $finance = new AA_Canonical_Family_Definition('finance', 'Finanzas');
    $archive = new AA_Canonical_Family_Definition('archive', 'Archivo');
    $repo = new CanonicalRelationalRepository($wpdb);
    $port = new AA_Canonical_Aggregated_Containers_Adapter($repo);
    $uc = new ReadCanonicalShellAllContainersUseCase($port);

    $page1 = $uc->execute([$finance, $archive], 1);
    ac_assert('Page 1 resolved', $page1->state() === CanonicalShellAggregatedReadResult::STATE_RESOLVED_PAGE);
    ac_assert('Global total is 18', $page1->page()->total() === 18);
    ac_assert('Page 1 has 15 items', count($page1->page()->items()) === 15);
    ac_assert('Page 1 has next', $page1->page()->has_next() === true);
    $first = $page1->page()->items()[0];
    ac_assert('Newest first is archive A-8', $first->container()->title() === 'A-8'
        && $first->family_key() === 'archive');

    $page2 = $uc->execute([$finance, $archive], 2);
    ac_assert('Page 2 has remaining 3', count($page2->page()->items()) === 3);
    ac_assert('Page 2 has previous', $page2->page()->has_previous() === true);
    $seen_hidden = false;
    foreach (array_merge($page1->page()->items(), $page2->page()->items()) as $it) {
        if ($it->container()->title() === 'Hidden' || $it->family_key() === 'disabled_fam') {
            $seen_hidden = true;
        }
    }
    ac_assert('Disabled family containers excluded when not in allowed set', $seen_hidden === false);

    $empty = $uc->execute([], 1);
    ac_assert('Empty allowed set is empty page', $empty->state() === CanonicalShellAggregatedReadResult::STATE_EMPTY
        && $empty->page()->total() === 0);

    $only_finance = $uc->execute([$finance], 1);
    ac_assert('Single family total 10', $only_finance->page()->total() === 10);
} catch (Throwable $e) {
    ac_assert('No unexpected exception', false, $e->getMessage());
} finally {
    $cleanup($temp_prefix);
    $wpdb->prefix = $original_prefix;
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}
echo "OK\n";
exit(0);
