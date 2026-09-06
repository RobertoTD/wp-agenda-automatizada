<?php
/**
 * AC Test — CanonicalMutationReceipt invariants (SB1-5A1).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-mutation-receipt-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';

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

function expect_receipt_invalid(callable $fn): bool {
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return strpos($e->getMessage(), '[invalid_mutation_receipt]') === 0;
    }
    return false;
}

$identity = new CanonicalReadIdentity('sample');
$R = CanonicalMutationReceipt::class;

$ops = [
    [$R::OPERATION_CREATE, $R::RESOURCE_CONTAINER, 10, null],
    [$R::OPERATION_UPDATE, $R::RESOURCE_CONTAINER, 10, null],
    [$R::OPERATION_DELETE, $R::RESOURCE_CONTAINER, 10, null],
    [$R::OPERATION_CREATE, $R::RESOURCE_RECORD, 20, 5],
    [$R::OPERATION_UPDATE, $R::RESOURCE_RECORD, 20, 5],
    [$R::OPERATION_DELETE, $R::RESOURCE_RECORD, 20, 5],
];

foreach ($ops as $i => $spec) {
    [$op, $type, $rid, $cid] = $spec;
    $receipt = CanonicalMutationReceipt::confirmed($identity, $op, $type, $rid, $cid);
    ac_assert('Confirmed op ' . ($i + 1), $receipt->outcome() === $R::OUTCOME_CONFIRMED
        && $receipt->operation() === $op
        && $receipt->resource_type() === $type
        && $receipt->resource_id() === $rid
        && $receipt->container_id() === $cid
        && $receipt->family_key() === 'sample'
        && $receipt->family_key() === 'alpha');
}

$uncertain_specs = [
    [$R::OPERATION_CREATE, $R::RESOURCE_CONTAINER, null, null],
    [$R::OPERATION_CREATE, $R::RESOURCE_CONTAINER, 11, null],
    [$R::OPERATION_UPDATE, $R::RESOURCE_CONTAINER, 10, null],
    [$R::OPERATION_DELETE, $R::RESOURCE_CONTAINER, 10, null],
    [$R::OPERATION_CREATE, $R::RESOURCE_RECORD, null, 5],
    [$R::OPERATION_CREATE, $R::RESOURCE_RECORD, 21, 5],
    [$R::OPERATION_UPDATE, $R::RESOURCE_RECORD, 20, 5],
    [$R::OPERATION_DELETE, $R::RESOURCE_RECORD, 20, 5],
];

foreach ($uncertain_specs as $i => $spec) {
    [$op, $type, $rid, $cid] = $spec;
    $receipt = CanonicalMutationReceipt::uncertain($identity, $op, $type, $rid, $cid);
    ac_assert('Uncertain spec ' . ($i + 1), $receipt->outcome() === $R::OUTCOME_UNCERTAIN);
}

ac_assert('Container confirmed container_id null', CanonicalMutationReceipt::confirmed(
    $identity, $R::OPERATION_CREATE, $R::RESOURCE_CONTAINER, 1, null
)->container_id() === null);

ac_assert('Record confirmed container_id set', CanonicalMutationReceipt::confirmed(
    $identity, $R::OPERATION_CREATE, $R::RESOURCE_RECORD, 1, 2
)->container_id() === 2);

// Invalid combinations
ac_assert('Reject container with container_id set', expect_receipt_invalid(static function () use ($identity, $R): void {
    CanonicalMutationReceipt::confirmed($identity, $R::OPERATION_CREATE, $R::RESOURCE_CONTAINER, 1, 5);
}));

ac_assert('Reject record without container_id', expect_receipt_invalid(static function () use ($identity, $R): void {
    CanonicalMutationReceipt::confirmed($identity, $R::OPERATION_CREATE, $R::RESOURCE_RECORD, 1, null);
}));

ac_assert('Reject resource_id 0', expect_receipt_invalid(static function () use ($identity, $R): void {
    CanonicalMutationReceipt::confirmed($identity, $R::OPERATION_CREATE, $R::RESOURCE_CONTAINER, 0, null);
}));

ac_assert('Reject negative resource_id', expect_receipt_invalid(static function () use ($identity, $R): void {
    CanonicalMutationReceipt::confirmed($identity, $R::OPERATION_UPDATE, $R::RESOURCE_CONTAINER, -1, null);
}));

ac_assert('Reject uncertain container update without resource_id', expect_receipt_invalid(static function () use ($identity, $R): void {
    CanonicalMutationReceipt::uncertain($identity, $R::OPERATION_UPDATE, $R::RESOURCE_CONTAINER, null, null);
}));

ac_assert('Reject uncertain record update without resource_id', expect_receipt_invalid(static function () use ($identity, $R): void {
    CanonicalMutationReceipt::uncertain($identity, $R::OPERATION_UPDATE, $R::RESOURCE_RECORD, null, 5);
}));

ac_assert('Reject record container_id 0', expect_receipt_invalid(static function () use ($identity, $R): void {
    CanonicalMutationReceipt::confirmed($identity, $R::OPERATION_DELETE, $R::RESOURCE_RECORD, 3, 0);
}));

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
