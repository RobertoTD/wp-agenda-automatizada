<?php
/**
 * AC — GetExpedienteStorageUsageUseCase (MC5d2).
 *
 * used_bytes = bytes contabilizados mediante metadata local finalizada
 * (SUM de byte_size en la tabla del blog actual), NO auditoría física en
 * vivo de Supabase. Una fila conservada por fallo parcial reintentable
 * (MC5c1/MC5c2) sigue contando hasta que el reintento la elimina.
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

/**
 * Doble del repositorio: simula la tabla de adjuntos del blog actual como
 * lista de filas {byte_size}. El use case solo consume sum_byte_size_total.
 */
final class ExpedienteAdjuntosRepository {
    /** @var list<array{byte_size:int}> */
    public static $rows = [];

    public static function sum_byte_size_total(): int {
        $sum = 0;
        foreach (self::$rows as $row) {
            $sum += (int) $row['byte_size'];
        }
        return $sum > 0 ? $sum : 0;
    }
}

require_once $plugin_root . '/includes/application/expediente/GetExpedienteStorageUsageUseCase.php';

$uc = new GetExpedienteStorageUsageUseCase();

// ── Tabla sin adjuntos → 0 ──
ExpedienteAdjuntosRepository::$rows = [];
$res = $uc->execute();
ac_assert('sin adjuntos → used_bytes = 0', !empty($res['ok']) && ($res['used_bytes'] ?? null) === 0);
ac_assert('used_bytes es entero', is_int($res['used_bytes']));

// ── Suma de varios adjuntos, registros y clientes del mismo blog ──
// (clientes 3 y 7, registros 10, 11 y 20: todos cuentan)
ExpedienteAdjuntosRepository::$rows = [
    ['byte_size' => 100000],  // cliente 3, registro 10
    ['byte_size' => 250000],  // cliente 3, registro 11
    ['byte_size' => 524288],  // cliente 7, registro 20
    ['byte_size' => 1],       // cliente 7, registro 20
];
$res2 = $uc->execute();
ac_assert('suma varios clientes/registros', ($res2['used_bytes'] ?? -1) === 874289, 'got=' . var_export($res2['used_bytes'] ?? null, true));

// ── Contrato cerrado: solo ok + used_bytes, sin metadata interna ──
ac_assert('contrato exacto {ok, used_bytes}', array_keys($res2) === ['ok', 'used_bytes']);
$encoded = json_encode($res2);
ac_assert('sin paths/bucket/desglose', strpos($encoded, 'storage_path') === false
    && strpos($encoded, 'bucket') === false
    && strpos($encoded, 'installations/') === false
    && strpos($encoded, 'adjuntos') === false
    && strpos($encoded, 'limit') === false);

// ── Entero no negativo incluso ante repo anómalo ──
ExpedienteAdjuntosRepository::$rows = [['byte_size' => -500]];
$res3 = $uc->execute();
ac_assert('nunca negativo', is_int($res3['used_bytes']) && $res3['used_bytes'] >= 0);

// ── Fila conservada por fallo parcial (Storage ya borrado, fila local
//    pendiente de reintento MC5c1/MC5c2) sigue contando ──
ExpedienteAdjuntosRepository::$rows = [
    ['byte_size' => 300000],  // adjunto vigente
    ['byte_size' => 200000],  // objeto ya ausente en Storage; fila retenida por local_delete_failed
];
$res4 = $uc->execute();
ac_assert('fila retenida por fallo parcial cuenta', ($res4['used_bytes'] ?? -1) === 500000);

// Reintento exitoso elimina la fila → la suma disminuye automáticamente.
ExpedienteAdjuntosRepository::$rows = [
    ['byte_size' => 300000],
];
$res5 = $uc->execute();
ac_assert('eliminación de la fila reduce la suma', ($res5['used_bytes'] ?? -1) === 300000);

// ── Estructural: sin cuota/enforcement y sin scope del navegador ──
$src = file_get_contents($plugin_root . '/includes/application/expediente/GetExpedienteStorageUsageUseCase.php');
ac_assert('use case sin input externo', strpos($src, '$_POST') === false
    && strpos($src, '$_REQUEST') === false
    && strpos($src, '$_GET') === false
    && strpos($src, 'installation_id') === false
    && strpos($src, 'client_id') === false);
ac_assert('sin limit_bytes/available/enforcement', strpos($src, 'limit_bytes') === false
    && strpos($src, 'available_bytes') === false
    && strpos($src, '12582912') === false
    && stripos($src, 'quota') === false);
ac_assert('solo lectura: no escribe ni borra', strpos($src, 'insert') === false
    && strpos($src, 'delete') === false
    && strpos($src, 'update') === false);

$repo_src = file_get_contents($plugin_root . '/includes/repositories/ExpedienteAdjuntosRepository.php');
ac_assert('repo usa COALESCE(SUM(byte_size), 0)', strpos($repo_src, 'COALESCE(SUM(byte_size), 0)') !== false);
ac_assert('repo suma sin scope externo (tabla del prefijo)', preg_match(
    '/function sum_byte_size_total\(\): int \{(?:(?!function ).)*?\}/s',
    $repo_src,
    $m
) === 1 && strpos($m[0], 'WHERE') === false && strpos($m[0], 'table_name()') !== false);

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
