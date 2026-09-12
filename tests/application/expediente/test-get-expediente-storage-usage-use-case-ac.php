<?php
/**
 * AC — GetExpedienteStorageUsageUseCase (MC5d2 / IMG-3a).
 *
 * used_bytes = confirmed_bytes (legacy + canon). Fallo → ok:false.
 *
 * Ejecutar: php tests/application/expediente/test-get-expediente-storage-usage-use-case-ac.php
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
require_once $plugin_root . '/includes/application/expediente/GetExpedienteStorageUsageUseCase.php';

final class FakeConfirmedUsage {
    /** @var int|null null → throw */
    public $confirmed = 0;

    public function confirmed_bytes(): int {
        if ($this->confirmed === null) {
            throw new AA_Installation_Storage_Usage_Failed('unavailable');
        }
        return (int) $this->confirmed;
    }
}

$fake = new FakeConfirmedUsage();
$uc = new GetExpedienteStorageUsageUseCase($fake);

$fake->confirmed = 0;
$res = $uc->execute();
ac_assert('sin consumo → used_bytes = 0', !empty($res['ok']) && ($res['used_bytes'] ?? null) === 0);
ac_assert('used_bytes es entero', is_int($res['used_bytes']));

$fake->confirmed = 874289;
$res2 = $uc->execute();
ac_assert('confirmed proxy', ($res2['used_bytes'] ?? -1) === 874289);
ac_assert('contrato exacto {ok, used_bytes}', array_keys($res2) === ['ok', 'used_bytes']);
$encoded = json_encode($res2);
ac_assert('sin paths/bucket/desglose', strpos($encoded, 'storage_path') === false
    && strpos($encoded, 'bucket') === false
    && strpos($encoded, 'upload_intent') === false);

$fake->confirmed = null;
$res6 = $uc->execute();
ac_assert('fallo → ok false', empty($res6['ok']) && ($res6['code'] ?? '') === 'storage_usage_unavailable');
ac_assert('fallo sin used_bytes falso', !array_key_exists('used_bytes', $res6));

$src = file_get_contents($plugin_root . '/includes/application/expediente/GetExpedienteStorageUsageUseCase.php');
ac_assert('use case usa confirmed_bytes', strpos($src, 'confirmed_bytes') !== false);
ac_assert('use case sin input externo', strpos($src, '$_POST') === false
    && strpos($src, 'installation_id') === false);
ac_assert('sin fingir cero en fallo', strpos($src, "used_bytes' => 0") === false
    || strpos($src, 'storage_usage_unavailable') !== false);

$helper_src = file_get_contents($plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage.php');
ac_assert('helper marca dependencia legacy temporal', strpos($helper_src, 'TEMPORARY') !== false
    && strpos($helper_src, 'ExpedienteAdjuntosRepository') !== false);

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
