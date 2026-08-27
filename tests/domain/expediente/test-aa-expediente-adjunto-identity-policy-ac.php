<?php
/**
 * AC — AA_Expediente_Adjunto_Identity_Policy (P2).
 *
 * Ejecutar: php tests/domain/expediente/test-aa-expediente-adjunto-identity-policy-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo "[ OK ] {$label}\n";
        return;
    }
    $failed[] = $label;
    echo "[FAIL] {$label}\n";
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-adjunto-identity-policy.php';

$iid = '11111111-2222-4333-8444-555555555555';
$op = '660e8400-e29b-41d4-a716-446655440000';
$v2 = ExpedienteAdjuntoVariants::build_expediente_record_original_path($iid, 7, 14, $op);
$v1 = ExpedienteAdjuntoVariants::build_client_original_path($iid, 55, 14, $op);

$parent_g = ['id' => 7, 'client_id' => null];
$record_g = ['id' => 14, 'expediente_id' => 7, 'client_id' => null];
$att_g = [
    'id' => 1,
    'record_id' => 14,
    'client_id' => null,
    'upload_operation_id' => $op,
    'storage_path' => $v2,
];

$ok = AA_Expediente_Adjunto_Identity_Policy::validate($parent_g, $record_g, $att_g);
ac_assert('general v2 válido', !empty($ok['ok']) && ($ok['parsed']['contract'] ?? '') === 'expediente_v2');

$bad_v1 = $att_g;
$bad_v1['storage_path'] = $v1;
ac_assert(
    'general v1 inválido',
    empty(AA_Expediente_Adjunto_Identity_Policy::validate($parent_g, $record_g, $bad_v1)['ok'])
);

$snap = $att_g;
$snap['client_id'] = 55;
ac_assert(
    'general con snapshot cliente inválido',
    empty(AA_Expediente_Adjunto_Identity_Policy::validate($parent_g, $record_g, $snap)['ok'])
);

$parent_r = ['id' => 7, 'client_id' => 55];
$record_r = ['id' => 14, 'expediente_id' => 7, 'client_id' => 55];
$att_v1 = [
    'id' => 2,
    'record_id' => 14,
    'client_id' => 55,
    'upload_operation_id' => $op,
    'storage_path' => $v1,
];
$att_v2 = [
    'id' => 3,
    'record_id' => 14,
    'client_id' => 55,
    'upload_operation_id' => $op,
    'storage_path' => $v2,
];

ac_assert(
    'relacionado v1 válido',
    !empty(AA_Expediente_Adjunto_Identity_Policy::validate($parent_r, $record_r, $att_v1)['ok'])
);
ac_assert(
    'relacionado v2 válido',
    !empty(AA_Expediente_Adjunto_Identity_Policy::validate($parent_r, $record_r, $att_v2)['ok'])
);

$mix_ok_v1 = AA_Expediente_Adjunto_Identity_Policy::validate($parent_r, $record_r, $att_v1);
$mix_ok_v2 = AA_Expediente_Adjunto_Identity_Policy::validate($parent_r, $record_r, $att_v2);
ac_assert('relacionado mixto (ambos contratos) válido', !empty($mix_ok_v1['ok']) && !empty($mix_ok_v2['ok']));

$bad_client = $att_v1;
$bad_client['client_id'] = 99;
ac_assert(
    'cliente contradictorio',
    empty(AA_Expediente_Adjunto_Identity_Policy::validate($parent_r, $record_r, $bad_client)['ok'])
);

$bad_rec = $record_r;
$bad_rec['expediente_id'] = 8;
ac_assert(
    'expediente contradictorio',
    empty(AA_Expediente_Adjunto_Identity_Policy::validate($parent_r, $bad_rec, $att_v1)['ok'])
);

$bad_att_rec = $att_v1;
$bad_att_rec['record_id'] = 99;
ac_assert(
    'record contradictorio',
    empty(AA_Expediente_Adjunto_Identity_Policy::validate($parent_r, $record_r, $bad_att_rec)['ok'])
);

$bad_op = $att_v1;
$bad_op['upload_operation_id'] = '770e8400-e29b-41d4-a716-446655440000';
ac_assert(
    'operation contradictoria',
    empty(AA_Expediente_Adjunto_Identity_Policy::validate($parent_r, $record_r, $bad_op)['ok'])
);

$bad_path = $att_v1;
$bad_path['storage_path'] = 'not/a/path.jpg';
ac_assert(
    'path inválido',
    empty(AA_Expediente_Adjunto_Identity_Policy::validate($parent_r, $record_r, $bad_path)['ok'])
);

$zero = $att_v1;
$zero['client_id'] = 0;
ac_assert(
    'client_id = 0 inválido',
    empty(AA_Expediente_Adjunto_Identity_Policy::validate($parent_r, $record_r, $zero)['ok'])
);

$orphan = AA_Expediente_Adjunto_Identity_Policy::validate_legacy_orphan(
    55,
    ['id' => 14, 'client_id' => 55, 'expediente_id' => null],
    $att_v1
);
ac_assert('legacy orphan v1 válido', !empty($orphan['ok']));

$orphan_v2 = AA_Expediente_Adjunto_Identity_Policy::validate_legacy_orphan(
    55,
    ['id' => 14, 'client_id' => 55, 'expediente_id' => null],
    $att_v2
);
ac_assert('legacy orphan v2 inválido', empty($orphan_v2['ok']));

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
