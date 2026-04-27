<?php
/**
 * AC1–AC21 para AA_AI_Datetime_Resolver (Paso 1.8).
 *
 * Ejecutar: php tests/domain/booking/test-ai-datetime-resolver-ac.php
 *
 * Fija `now` a 2026-04-20 15:00 America/Mexico_City vía reflexión
 * (sin cargar WordPress).
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        if ($name === 'aa_timezone') {
            return 'America/Mexico_City';
        }
        return $default;
    }
}

require_once __DIR__ . '/../../../includes/services/ai/chat/class-aa-ai-datetime-resolver.php';

$tz  = new DateTimeZone('America/Mexico_City');
$now = new DateTimeImmutable('2026-04-20 15:00:00', $tz);

$resolver = new AA_AI_Datetime_Resolver();
$ref      = new ReflectionClass($resolver);
foreach (['now', 'tz', 'tz_name'] as $prop) {
    $p = $ref->getProperty($prop);
    $p->setAccessible(true);
    if ($prop === 'now') {
        $p->setValue($resolver, $now);
    } elseif ($prop === 'tz') {
        $p->setValue($resolver, $tz);
    } else {
        $p->setValue($resolver, 'America/Mexico_City');
    }
}

$total  = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo "[ OK ] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $failed[] = $label;
        echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

// AC1
$r = $resolver->resolve('hoy 20/04/2026', '18:30');
ac_assert(
    'AC1',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-04-20',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC2
$r = $resolver->resolve('mañana 21 de abril', '10:00');
ac_assert(
    'AC2',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-04-21',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC3
$r = $resolver->resolve('mañana 22/04/2026', '10:00');
ac_assert('AC3', $r['status'] === 'invalid_date', $r['status']);

// AC4
$r = $resolver->resolve('20/04/2026', '18:30');
ac_assert(
    'AC4',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-04-20',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC5
$r = $resolver->resolve('21-04-2026', '10:00');
ac_assert(
    'AC5',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-04-21',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC6
$r = $resolver->resolve('15 de abril 2027', '10:00');
ac_assert(
    'AC6',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2027-04-15',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC7
$r = $resolver->resolve('15 abril 2027', '10:00');
ac_assert(
    'AC7',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2027-04-15',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC8
$r = $resolver->resolve('30/02/2026', '10:00');
ac_assert('AC8', $r['status'] === 'invalid_date', $r['status']);

// AC9
$r = $resolver->resolve('15/04/1800', '10:00');
ac_assert('AC9', $r['status'] === 'invalid_date', $r['status']);

// AC10
$r = $resolver->resolve('hoy', '18:30');
ac_assert(
    'AC10',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-04-20',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC11
$r = $resolver->resolve('20/04', '18:30');
ac_assert(
    'AC11',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-04-20',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC12
$r = $resolver->resolve('ayer 19/04/2026', '10:00');
ac_assert(
    'AC12',
    $r['status'] === 'invalid_or_past',
    $r['status'] . ' ' . json_encode($r['normalized'] ?? [])
);

// AC13 — próximo lunes desde 2026-04-20 (lunes) → 2026-04-27
$r = $resolver->resolve('lunes', '09:00');
ac_assert(
    'AC13',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-04-27',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC14
$r = $resolver->resolve('', '');
ac_assert('AC14', $r['status'] === 'partial', $r['status']);

// AC15
$r = $resolver->resolve('pasado mañana 22/04/2026', '10:00');
ac_assert(
    'AC15',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-04-22',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC16
$r = $resolver->resolve('el 10 de mayo del 2026', '10:00');
ac_assert(
    'AC16',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-05-10',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC17
$r = $resolver->resolve('el 10 de mayo de 2026', '10:00');
ac_assert(
    'AC17',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-05-10',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC18
$r = $resolver->resolve('10 de mayo del 2026', '10:00');
ac_assert(
    'AC18',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-05-10',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC19
$r = $resolver->resolve('10 de mayo de 2026', '10:00');
ac_assert(
    'AC19',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-05-10',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC20
$r = $resolver->resolve('10 mayo 2026', '10:00');
ac_assert(
    'AC20',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-05-10',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

// AC21
$r = $resolver->resolve('10 de mayo', '10:00');
ac_assert(
    'AC21',
    $r['status'] === 'resolved' && ($r['normalized']['local_date'] ?? null) === '2026-05-10',
    json_encode($r['normalized'] ?? [], JSON_UNESCAPED_UNICODE)
);

echo "\nResultado: {$passed}/{$total}\n";
exit(empty($failed) ? 0 : 1);
