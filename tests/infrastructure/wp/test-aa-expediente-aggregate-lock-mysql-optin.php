<?php
/**
 * Opt-in — Validación MySQL real de GET_LOCK / RELEASE_LOCK (Ciclo A).
 *
 * NO forma parte del suite por defecto. No usa datos de producto.
 *
 * Requisitos:
 *   AA_EXPEDIENTE_LOCK_MYSQL_DSN   p.ej. mysql:host=127.0.0.1;dbname=wordpress;charset=utf8mb4
 *   AA_EXPEDIENTE_LOCK_MYSQL_USER
 *   AA_EXPEDIENTE_LOCK_MYSQL_PASS  (puede vacía)
 *
 * Ejecutar:
 *   AA_EXPEDIENTE_LOCK_MYSQL_DSN='...' AA_EXPEDIENTE_LOCK_MYSQL_USER='...' \
 *   AA_EXPEDIENTE_LOCK_MYSQL_PASS='...' \
 *   php tests/infrastructure/wp/test-aa-expediente-aggregate-lock-mysql-optin.php
 *
 * Comprueba:
 * 1) GET_LOCK=1
 * 2) RELEASE_LOCK=1
 * 3) Segunda conexión se bloquea (timeout 1 → 0) con la misma key
 * 4) Scope distinto progresa
 * 5) Cierre de conexión libera el lock
 * 6) Tablas aa_expedientes / registros / adjuntos / categories usan InnoDB
 */

$dsn = getenv('AA_EXPEDIENTE_LOCK_MYSQL_DSN');
$user = getenv('AA_EXPEDIENTE_LOCK_MYSQL_USER');
$pass = getenv('AA_EXPEDIENTE_LOCK_MYSQL_PASS');

if ($dsn === false || $dsn === '' || $user === false || $user === '') {
    fwrite(STDERR, "OMITIDO: define AA_EXPEDIENTE_LOCK_MYSQL_DSN y AA_EXPEDIENTE_LOCK_MYSQL_USER (PASS opcional).\n");
    fwrite(STDERR, "Gate operativo MySQL real pendiente de validación manual fuera de producción.\n");
    exit(2);
}

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

try {
    $pdo1 = new PDO($dsn, $user, (string) $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
    ]);
    $pdo2 = new PDO($dsn, $user, (string) $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'OMITIDO: no se pudo conectar (' . $e->getMessage() . ").\n");
    exit(2);
}

$key_a = 'aaexp:' . substr(hash('sha256', 'ciclo-a-mysql-optin-a'), 0, 56);
$key_b = 'aaexp:' . substr(hash('sha256', 'ciclo-a-mysql-optin-b'), 0, 56);

$got = (int) $pdo1->query("SELECT GET_LOCK(" . $pdo1->quote($key_a) . ", 1)")->fetchColumn();
ac_assert('GET_LOCK = 1', $got === 1);

$busy = (int) $pdo2->query("SELECT GET_LOCK(" . $pdo2->quote($key_a) . ", 1)")->fetchColumn();
ac_assert('misma key → segunda conexión 0', $busy === 0);

$other = (int) $pdo2->query("SELECT GET_LOCK(" . $pdo2->quote($key_b) . ", 1)")->fetchColumn();
ac_assert('scope distinto progresa', $other === 1);
$pdo2->query("SELECT RELEASE_LOCK(" . $pdo2->quote($key_b) . ")");

$rel = (int) $pdo1->query("SELECT RELEASE_LOCK(" . $pdo1->quote($key_a) . ")")->fetchColumn();
ac_assert('RELEASE_LOCK = 1', $rel === 1);

$got2 = (int) $pdo1->query("SELECT GET_LOCK(" . $pdo1->quote($key_a) . ", 1)")->fetchColumn();
ac_assert('re-adquiere tras release', $got2 === 1);
$pdo1 = null; // cierre → libera
usleep(100000);
$after_close = (int) $pdo2->query("SELECT GET_LOCK(" . $pdo2->quote($key_a) . ", 1)")->fetchColumn();
ac_assert('cierre de conexión libera lock', $after_close === 1);
$pdo2->query("SELECT RELEASE_LOCK(" . $pdo2->quote($key_a) . ")");

$tables = [
    'aa_expedientes',
    'aa_expediente_registros',
    'aa_expediente_adjuntos',
    'aa_expediente_categories',
];
$prefix_row = $pdo2->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$found_any = false;
foreach ($tables as $logical) {
    $matches = array_values(array_filter($prefix_row, static function ($t) use ($logical) {
        return is_string($t) && substr($t, -strlen($logical)) === $logical;
    }));
    if ($matches === []) {
        continue;
    }
    $found_any = true;
    $table = $matches[0];
    $eng = $pdo2->query("SHOW TABLE STATUS LIKE " . $pdo2->quote($table))->fetch(PDO::FETCH_ASSOC);
    ac_assert(
        'InnoDB ' . $logical,
        is_array($eng) && strcasecmp((string) ($eng['Engine'] ?? ''), 'InnoDB') === 0,
        (string) ($eng['Engine'] ?? 'missing')
    );
}
if (!$found_any) {
    ac_assert('tablas expediente presentes (prefix local)', false, 'ninguna tabla aa_expediente* encontrada');
}

echo "\nResultado: {$passed}/{$total}" . (count($failed) ? (' FAIL') : ' OK') . "\n";
exit(count($failed) === 0 ? 0 : 1);
