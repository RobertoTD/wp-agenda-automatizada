<?php
/**
 * Harness: footer_actions en create_booking cuando cliente es no_match.
 *
 *   php tests/application/ai/test-ai-create-booking-client-no-match-footer-ac.php
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Tests\Application\AI
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(array $args, string $url): string {
        $separator = strpos($url, '?') === false ? '?' : '&';

        return $url . $separator . http_build_query($args, '', '&');
    }
}

require_once __DIR__ . '/../../../includes/services/ai/chat/class-aa-ai-create-booking-intent-handler.php';

$passed = 0;
$total  = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' - ' . $detail) . "\n";
}

$handler = new AA_AI_Create_Booking_Intent_Handler();
$method  = new ReflectionMethod($handler, 'attach_footer_actions_when_client_no_match');
$method->setAccessible(true);

$base_reply = [
    'text'       => 'Seguimos con tu cita. ese cliente no existe; elige uno o créalo manualmente',
    'cta'        => 'collect_input',
    'highlights' => [],
    'choices'    => [],
    'draft_echo' => [
        'client'   => null,
        'service'  => 'Corte',
        'staff'    => null,
        'zone'     => null,
        'datetime' => '2026-05-02 17:00',
    ],
];

$draft_no_match = [
    'required_literal' => [
        [
            'field'  => 'client',
            'reason' => 'no_match',
            'hint'   => 'ese cliente no existe; elige uno o créalo manualmente',
        ],
    ],
];

$draft_missing_client = [
    'required_literal' => [
        [
            'field'  => 'client',
            'reason' => 'missing',
            'hint'   => 'indica el nombre del cliente',
        ],
    ],
];

$out_match = $method->invoke($handler, $base_reply, $draft_no_match);
ac(
    'añade footer_actions con Ir a Clientes',
    isset($out_match['footer_actions'][0]['label'], $out_match['footer_actions'][0]['url'])
        && $out_match['footer_actions'][0]['label'] === 'Ir a Clientes'
        && strpos($out_match['footer_actions'][0]['url'], 'module=clients') !== false
        && strpos($out_match['footer_actions'][0]['url'], 'setup_focus=clients') !== false
        && strpos($out_match['footer_actions'][0]['url'], 'aa-clients-grid') !== false,
    'shape footer_actions'
);

$out_missing = $method->invoke($handler, $base_reply, $draft_missing_client);
ac(
    'no footer_actions si reason es missing',
    !isset($out_missing['footer_actions']),
    'unexpected footer_actions'
);

echo "\n{$passed}/{$total}\n";
exit($passed === $total ? 0 : 1);
