<?php
/**
 * AC — Schema aa_expediente_adjuntos (MC4a2) + DB_VERSION 16.
 *
 * Ejecutar: php tests/repositories/test-expediente-adjuntos-schema-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';

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

$schema_src = file_get_contents($schema_file);
ac_assert('Schema readable', is_string($schema_src) && $schema_src !== '');
ac_assert('DB_VERSION is 17', strpos($schema_src, "DB_VERSION = '17'") !== false);
ac_assert('CREATE TABLE aa_expediente_adjuntos', strpos($schema_src, "aa_expediente_adjuntos") !== false);
ac_assert('storage_path varchar(191)', strpos($schema_src, 'storage_path varchar(191) NOT NULL') !== false);
ac_assert('upload_operation_id char(36)', strpos($schema_src, 'upload_operation_id char(36) NOT NULL') !== false);
ac_assert('unique operation ensure_index', strpos($schema_src, 'uq_aa_exp_adj_operation') !== false);
ac_assert('unique storage_path ensure_index', strpos($schema_src, 'uq_aa_exp_adj_storage_path') !== false);
ac_assert('KEY record_id_id', strpos($schema_src, 'KEY record_id_id (record_id, id)') !== false);
ac_assert('KEY client_record', strpos($schema_src, 'KEY client_record (client_id, record_id)') !== false);
ac_assert('sin FOREIGN KEY adjuntos', strpos($schema_src, 'aa_expediente_adjuntos') !== false && !preg_match('/aa_expediente_adjuntos[\s\S]{0,800}FOREIGN KEY/', $schema_src));
// Bloque adjuntos no declara columna status
$adj_start = strpos($schema_src, 'aa_expediente_adjuntos');
$adj_end = $adj_start !== false ? strpos($schema_src, ') $charset;";', $adj_start) : false;
$adj_block = ($adj_start !== false && $adj_end !== false) ? substr($schema_src, $adj_start, $adj_end - $adj_start) : '';
ac_assert('bloque adjuntos sin columna status', $adj_block !== '' && strpos($adj_block, 'status') === false);
ac_assert('bloque adjuntos sin expediente_id', $adj_block !== '' && strpos($adj_block, 'expediente_id') === false);
ac_assert('usa prefix aa_expediente_adjuntos', strpos($schema_src, "\$wpdb->prefix . 'aa_expediente_adjuntos'") !== false);

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
