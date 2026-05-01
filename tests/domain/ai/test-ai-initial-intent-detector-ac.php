<?php
/**
 * Harness standalone de `AA_AI_Initial_Intent_Detector`.
 *
 *   php tests/domain/ai/test-ai-initial-intent-detector-ac.php
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Tests\Domain\AI
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/ai/class-aa-ai-initial-intent-detector.php';

$passed = 0;
$total  = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' — ' . $detail) . "\n";
}

function detects_create_client(string $message): bool {
    return AA_AI_Initial_Intent_Detector::is_clear_create_client_request($message);
}

$positive_cases = [
    'crea cliente Juan',
    'crea un cliente llamado Juan Pérez',
    'agrega cliente María López',
    'registra nuevo cliente Juan',
    'dar de alta cliente Ana',
    'nuevo cliente Pedro Gómez',
    'crear cliente con teléfono 5551234567',
    'agrega a María López como cliente',
];

foreach ($positive_cases as $message) {
    ac(
        'detecta create_client claro: ' . $message,
        detects_create_client($message) === true
    );
}

$negative_cases = [
    'agenda a Juan',
    'crea cita para Juan',
    'agrega a Juan a una cita',
    '¿a las 5 está libre?',
    'hay espacio mañana',
    'hola',
    'buenas tardes',
    'agrega a Juan',
    'cliente Juan',
    'busca al cliente Ana Martínez',
    'cómo crear cliente',
];

foreach ($negative_cases as $message) {
    ac(
        'rechaza no-create_client: ' . $message,
        detects_create_client($message) === false
    );
}

echo "\n{$passed}/{$total} acceptance checks passed.\n";
exit($passed === $total ? 0 : 1);
