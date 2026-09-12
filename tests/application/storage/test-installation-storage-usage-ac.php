<?php
/**
 * AC IMG-3a bloque 1 — AA_Installation_Storage_Usage (consumo compartido).
 *
 * Ejecutar: php tests/application/storage/test-installation-storage-usage-ac.php
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

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage_Failed.php';
require_once $plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage.php';

final class AaTestImagesSum {
    public $sum = 200;
    public $throw = false;
    public function sum_byte_size_total(): int {
        if ($this->throw) {
            throw new RuntimeException('sql');
        }
        return $this->sum;
    }
}

final class AaTestOpsSum {
    /** @var list<array{now_ms:int,now_utc:string,exclude:?string}> */
    public $calls = [];
    public $reserved = 50;
    public $throw = false;
    public function sum_reserved_byte_size(int $now_ms, string $now_utc, ?string $exclude_operation_id = null): int {
        $this->calls[] = [
            'now_ms' => $now_ms,
            'now_utc' => $now_utc,
            'exclude' => $exclude_operation_id,
        ];
        if ($this->throw) {
            throw new RuntimeException('sql');
        }
        return $this->reserved;
    }
}

$legacy = 1000;
$images = new AaTestImagesSum();
$ops = new AaTestOpsSum();
$now_ms = 1_700_000_000_000;
$usage = new AA_Installation_Storage_Usage(
    static function () use (&$legacy): ?int {
        return $legacy;
    },
    $images,
    $ops,
    static function () use ($now_ms): int {
        return $now_ms;
    }
);

ac_assert('confirmed = legacy + canon', $usage->confirmed_bytes() === 1200);
ac_assert('reserved vigente', $usage->reserved_bytes() === 50);
ac_assert('admission sin exclude', $usage->admission_used_bytes(null) === 1250);
$after_null = $ops->calls[count($ops->calls) - 1];
ac_assert(
    'exclude null en llamada',
    array_key_exists('exclude', $after_null) && $after_null['exclude'] === null,
    'got=' . var_export($after_null['exclude'] ?? 'missing', true)
);

$usage->admission_used_bytes('aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee');
$last = $ops->calls[count($ops->calls) - 1];
ac_assert('exclude id interno', ($last['exclude'] ?? '') === 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee');
ac_assert('mismo now_ms', ($last['now_ms'] ?? 0) === $now_ms);
ac_assert(
    'now_utc derivado del mismo ms',
    ($last['now_utc'] ?? '') === AA_Installation_Storage_Usage::utc_datetime_from_ms($now_ms)
);

$legacy = null;
try {
    $usage->confirmed_bytes();
    ac_assert('legacy null lanza', false);
} catch (AA_Installation_Storage_Usage_Failed $e) {
    ac_assert('legacy null lanza', true);
}
$legacy = 1000;

$images->throw = true;
try {
    $usage->confirmed_bytes();
    ac_assert('images sql lanza', false);
} catch (AA_Installation_Storage_Usage_Failed $e) {
    ac_assert('images sql lanza', true);
}
$images->throw = false;

$ops->throw = true;
try {
    $usage->admission_used_bytes(null);
    ac_assert('ops sql lanza', false);
} catch (AA_Installation_Storage_Usage_Failed $e) {
    ac_assert('ops sql lanza', true);
}
$ops->throw = false;

$src = file_get_contents($plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage.php');
ac_assert('sin mutar ops', strpos($src, 'mark_cleanup') === false && strpos($src, 'UPDATE') === false);
ac_assert('expires_at helper', strpos($src, 'expires_at_from_intent_exp_ms') !== false);

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
