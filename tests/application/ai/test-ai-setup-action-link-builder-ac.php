<?php
/**
 * Harness standalone de `AA_AI_Setup_Action_Link_Builder`.
 *
 *   php tests/application/ai/test-ai-setup-action-link-builder-ac.php
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

require_once __DIR__ . '/../../../includes/application/ai/AI_Setup_Action_Link_Builder.php';

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

$builder = new AA_AI_Setup_Action_Link_Builder();
$action = $builder->build_action_for_key('clients_create');

ac('clients_create devuelve action', is_array($action), json_encode($action, JSON_UNESCAPED_SLASHES));
ac('clients_create conserva key', ($action['key'] ?? null) === 'clients_create', json_encode($action, JSON_UNESCAPED_SLASHES));
ac('clients_create label Ir a Clientes', ($action['label'] ?? null) === 'Ir a Clientes', json_encode($action, JSON_UNESCAPED_UNICODE));
ac('URL contiene module=clients', isset($action['url']) && strpos($action['url'], 'module=clients') !== false, $action['url'] ?? '');
ac('URL contiene setup_focus=clients', isset($action['url']) && strpos($action['url'], 'setup_focus=clients') !== false, $action['url'] ?? '');
ac('URL apunta a hash aa-clients-grid', isset($action['url']) && substr($action['url'], -16) === '#aa-clients-grid', $action['url'] ?? '');

$actions = $builder->build_actions([
    ['action_key' => 'clients_create'],
]);

ac(
    'build_actions preserva shape listado',
    count($actions) === 1
        && ($actions[0]['key'] ?? null) === 'clients_create'
        && ($actions[0]['label'] ?? null) === 'Ir a Clientes'
        && isset($actions[0]['url']),
    json_encode($actions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo "\n{$passed}/{$total} acceptance checks passed.\n";
exit($passed === $total ? 0 : 1);
