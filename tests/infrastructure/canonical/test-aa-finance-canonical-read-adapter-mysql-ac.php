<?php
/**
 * AC Test — Integración MySQL Finance canonical read adapter (SB1-4B).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-finance-canonical-read-adapter-mysql-ac.php
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
    echo "[INFO / SKIP] Integración MySQL real no ejecutada (AA_WP_ROOT no definido o inaccesible).\n";
    exit(0);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/FinanceSchema.php';
require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/repositories/FinanceRecordRepository.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/infrastructure/canonical/finance/class-aa-finance-canonical-read-adapter.php';

global $wpdb;

echo "=== Integración MySQL — Finance Canonical Read Adapter ===\n";
echo "Host: " . DB_HOST . " | Base: " . DB_NAME . "\n";

$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_finread_' . substr(md5(uniqid('fr', true)), 0, 8) . '_';

$cleanup = function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_finread_') !== 0) {
        return;
    }
    $r_table = $p . AA_Finance_Schema::TABLE_RECORDS;
    $c_table = $p . AA_Finance_Schema::TABLE_CONTAINERS;
    $wpdb->query("DROP TABLE IF EXISTS `{$r_table}`");
    $wpdb->query("DROP TABLE IF EXISTS `{$c_table}`");
};

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);
    AA_Finance_Schema::install();
    AA_Finance_Schema::verify();

    $c_table = $wpdb->prefix . AA_Finance_Schema::TABLE_CONTAINERS;
    $r_table = $wpdb->prefix . AA_Finance_Schema::TABLE_RECORDS;

    $older = FinanceContainerRepository::create('general', 'Contenedor antiguo', 'Detalle viejo');
    $newer = FinanceContainerRepository::create('general', 'Contenedor reciente', null);
    ac_assert('Seed containers via repository', is_array($older) && is_array($newer));

    $r1 = FinanceRecordRepository::create($older['id'], 'Registro A', 'Detalle A', '150.50');
    $r2 = FinanceRecordRepository::create($older['id'], 'Registro B', null, '-10.00');
    ac_assert('Seed records with amount via repository', is_array($r1) && is_array($r2));

    $wpdb->update(
        $c_table,
        ['updated_at' => '2020-01-01 10:00:00'],
        ['id' => $older['id']],
        ['%s'],
        ['%d']
    );
    $wpdb->update(
        $c_table,
        ['updated_at' => '2026-08-01 15:00:00'],
        ['id' => $newer['id']],
        ['%s'],
        ['%d']
    );

    $adapter = new AA_Finance_Canonical_Read_Adapter($wpdb);
    $per_page = CanonicalReadGateway::PAGE_SIZE;

    $page = $adapter->list_containers('general', 1, $per_page);
    ac_assert('Adapter lists seeded containers', $page->total() === 2);
    ac_assert('Order by updated_at DESC', $page->items()[0]->id() === (int) $newer['id']);

    $newer_item = null;
    foreach ($page->items() as $item) {
        if ($item->id() === (int) $newer['id']) {
            $newer_item = $item;
            break;
        }
    }
    ac_assert('Details null preserved', $newer_item !== null && $newer_item->details() === null);
    ac_assert('Amount not in canonical container', !array_key_exists('amount', $page->items()[1]->to_canonical_array()));

    $local_ts = '2026-08-01 15:00:00';
    $expected_utc = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $local_ts, wp_timezone())
        ->setTimezone(new \DateTimeZone('UTC'))
        ->format('Y-m-d\TH:i:s\Z');
    ac_assert('Timestamp converted via wp_timezone to UTC Z', $newer_item !== null && $newer_item->updated_at_canonical() === $expected_utc);

    $records = $adapter->list_records('general', (int) $older['id'], 1, $per_page);
    ac_assert('Records for container resolved', $records->total() === 2);
    ac_assert('Record amount not projected', !array_key_exists('amount', $records->items()[0]->to_canonical_array()));

    $parent = $adapter->get_container('general', (int) $older['id']);
    ac_assert('get_container matches seeded title', $parent->title() === 'Contenedor antiguo');

    $nav_missing = false;
    try {
        $adapter->get_container('general', 999999);
    } catch (CanonicalContainerNotFound $e) {
        $nav_missing = true;
    }
    ac_assert('Missing container not found', $nav_missing);

    $empty_newer = $adapter->list_records('general', (int) $newer['id'], 1, $per_page);
    ac_assert('Empty container records page', $empty_newer->total() === 0);

    $amount_row = $wpdb->get_row($wpdb->prepare("SELECT amount FROM `{$r_table}` WHERE id = %d", $r1['id']));
    ac_assert('Source row still has amount in DB', is_object($amount_row) && $amount_row->amount === '150.50');

} finally {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);
    $wpdb->prefix = $original_prefix;
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
