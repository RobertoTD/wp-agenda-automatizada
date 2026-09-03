<?php
/**
 * AC Test — Canonical mutation commands (SB1-5A1).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-mutation-commands-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalUpdateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalDeleteRecordCommand.php';

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

function expect_invalid(callable $fn): bool {
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return strpos($e->getMessage(), '[invalid_mutation_input]') === 0;
    }
    return false;
}

$c = new CanonicalCreateContainerCommand('  Título  ', null);
ac_assert('Create container trims title', $c->title() === 'Título' && $c->details() === null);

ac_assert('Create container rejects empty title', expect_invalid(static function (): void {
    new CanonicalCreateContainerCommand('   ', null);
}));

$u = new CanonicalUpdateContainerCommand(5, 'Editado', 'Detalle');
ac_assert('Update container ids', $u->container_id() === 5);

ac_assert('Update container rejects id 0', expect_invalid(static function (): void {
    new CanonicalUpdateContainerCommand(0, 'X', null);
}));

$d = new CanonicalDeleteContainerCommand(3);
ac_assert('Delete container id', $d->container_id() === 3);

$r = new CanonicalCreateRecordCommand(2, 'Registro', null);
ac_assert('Create record fields', $r->container_id() === 2 && $r->title() === 'Registro');

ac_assert('Create record rejects container 0', expect_invalid(static function (): void {
    new CanonicalCreateRecordCommand(0, 'X', null);
}));

$ru = new CanonicalUpdateRecordCommand(2, 9, 'R edit', 'd');
ac_assert('Update record ids', $ru->container_id() === 2 && $ru->record_id() === 9);

ac_assert('Update record rejects record 0', expect_invalid(static function (): void {
    new CanonicalUpdateRecordCommand(1, 0, 'X', null);
}));

$rd = new CanonicalDeleteRecordCommand(4, 7);
ac_assert('Delete record ids', $rd->container_id() === 4 && $rd->record_id() === 7);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
