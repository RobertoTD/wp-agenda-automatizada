<?php
/**
 * AC Test — AA_Canonical_Record + Instant (SB1-3A).
 *
 * Ejecutar: php tests/domain/canonical/test-aa-canonical-record-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-record.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';

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

$record = new AA_Canonical_Record(7, 3, 'Titulo', null, '2026-05-01T10:00:00Z');
ac_assert('Accepts null details', $record->details() === null);
ac_assert('Serializes updated_at as Z', $record->updated_at_canonical() === '2026-05-01T10:00:00Z');
$arr = $record->to_canonical_array();
ac_assert('Canonical array keys', array_keys($arr) === ['id', 'container_id', 'title', 'details', 'updated_at']);
ac_assert('No variant_key in projection', !array_key_exists('variant_key', $arr));

$rejects = [
    ['id' => 0, 'cid' => 1, 'title' => 't', 'details' => null, 'at' => '2026-05-01T10:00:00Z', 'label' => 'Rejects non-positive id'],
    ['id' => 1, 'cid' => 0, 'title' => 't', 'details' => null, 'at' => '2026-05-01T10:00:00Z', 'label' => 'Rejects non-positive container_id'],
    ['id' => 1, 'cid' => 1, 'title' => '  ', 'details' => null, 'at' => '2026-05-01T10:00:00Z', 'label' => 'Rejects empty title'],
    ['id' => 1, 'cid' => 1, 'title' => 't', 'details' => null, 'at' => '2026-05-01 10:00:00', 'label' => 'Rejects MySQL naive'],
    ['id' => 1, 'cid' => 1, 'title' => 't', 'details' => null, 'at' => '2026-05-01T10:00:00+00:00', 'label' => 'Rejects +00:00'],
];
foreach ($rejects as $case) {
    $ok = false;
    try {
        new AA_Canonical_Record($case['id'], $case['cid'], $case['title'], $case['details'], $case['at']);
    } catch (InvalidArgumentException $e) {
        $ok = true;
    }
    ac_assert($case['label'], $ok === true);
}

$instant = AA_Canonical_Instant::from('2026-05-01T10:00:00Z');
ac_assert('Instant canonical string', $instant->to_canonical_string() === '2026-05-01T10:00:00Z');

$container_src = file_get_contents($plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php');
$record_src = file_get_contents($plugin_root . '/includes/domain/canonical/class-aa-canonical-record.php');
ac_assert('Container uses Instant', strpos($container_src, 'AA_Canonical_Instant') !== false);
ac_assert('Record uses Instant', strpos($record_src, 'AA_Canonical_Instant') !== false);
ac_assert('Record does not call Container normalize', strpos($record_src, 'AA_Canonical_Container::') === false);
ac_assert('Record has no Finance vocabulary', stripos($record_src, 'amount') === false && strpos($record_src, 'finance') === false);

// Shared normalize API preserved on Container
$dt = AA_Canonical_Container::normalize_updated_at('2026-05-01T10:00:00Z');
ac_assert('Container normalize_updated_at still public', $dt instanceof DateTimeImmutable && $dt->format('Y-m-d\TH:i:s\Z') === '2026-05-01T10:00:00Z');

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
