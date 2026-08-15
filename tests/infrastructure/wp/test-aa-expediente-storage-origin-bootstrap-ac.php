<?php
/**
 * AC — fallback de AA_EXPEDIENTE_STORAGE_ORIGIN en el bootstrap.
 *
 * Ejecutar: php tests/infrastructure/wp/test-aa-expediente-storage-origin-bootstrap-ac.php
 *
 * Las constantes PHP no se redefinen en el mismo proceso: cada caso corre
 * en un hijo (aa-expediente-storage-origin-probe.php).
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

function aa_storage_origin_probe(string $mode): array {
    global $plugin_root;

    $probe = $plugin_root . '/tests/infrastructure/wp/aa-expediente-storage-origin-probe.php';
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' ' . escapeshellarg($mode);
    $output = [];
    $exit = 0;
    exec($cmd . ' 2>/dev/null', $output, $exit);
    $raw = implode("\n", $output);
    $decoded = json_decode($raw, true);

    return [
        'exit' => $exit,
        'raw' => $raw,
        'data' => is_array($decoded) ? $decoded : [],
    ];
}

$expected_default = 'https://ndnhlywkkydqmvhvzbfh.supabase.co';

$default = aa_storage_origin_probe('default');
$d = $default['data'];
ac_assert('hijo default exit 0', $default['exit'] === 0, $default['raw']);
ac_assert('sin override la constante queda definida', !empty($d['defined']));
ac_assert(
    'sin override el valor es el origen público predeterminado',
    ($d['value'] ?? '') === $expected_default,
    (string) ($d['value'] ?? '')
);
ac_assert(
    'uploader disponible solo después de definir la constante',
    !empty($d['uploader_loaded']) && !empty($d['defined'])
);
ac_assert(
    'validador disponible solo después de definir la constante',
    !empty($d['validator_loaded']) && !empty($d['defined'])
);
ac_assert('uploader acepta el origen predeterminado', !empty($d['upload_ok']));
ac_assert('validador acepta el origen predeterminado', !empty($d['read_ok']));

$override = aa_storage_origin_probe('override');
$o = $override['data'];
ac_assert('hijo override exit 0', $override['exit'] === 0, $override['raw']);
ac_assert(
    'override externo válido se conserva',
    ($o['value'] ?? '') === 'https://override-valid.example.co',
    (string) ($o['value'] ?? '')
);
ac_assert(
    'override no es sustituido por el predeterminado',
    ($o['value'] ?? '') !== $expected_default
);

$invalid = aa_storage_origin_probe('invalid');
$i = $invalid['data'];
ac_assert('hijo invalid exit 0', $invalid['exit'] === 0, $invalid['raw']);
ac_assert(
    'override inválido permanece sin normalizar',
    ($i['value'] ?? '') === 'http://insecure.example',
    (string) ($i['value'] ?? '')
);
ac_assert(
    'uploader rechaza override inválido de forma cerrada',
    empty($i['upload_ok']) && ($i['upload_code'] ?? '') === 'storage_origin_invalid',
    (string) ($i['upload_code'] ?? '')
);
ac_assert(
    'validador rechaza override inválido de forma cerrada',
    empty($i['read_ok']) && ($i['read_code'] ?? '') === 'storage_origin_invalid',
    (string) ($i['read_code'] ?? '')
);

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
