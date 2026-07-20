<?php
/**
 * C8A3 — Training lesson HTML sanitizer AC.
 *
 * Ejecutar: php tests/application/training/test-aa-training-lesson-html-sanitizer-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);

/**
 * Minimal wp_kses stand-in for directed AC (mirrors allowlist + protocol stripping).
 *
 * @param string               $content
 * @param array<string, mixed> $allowed_html
 */
function wp_kses($content, $allowed_html) {
    $content = (string) $content;

    $content = preg_replace('#<(script|style|iframe|img)\b[^>]*>.*?</\1>#is', '', $content) ?? $content;
    $content = preg_replace('#<(script|style|iframe|img)\b[^>]*/?>#is', '', $content) ?? $content;
    $content = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content) ?? $content;
    $content = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content) ?? $content;
    $content = preg_replace(
        '/href\s*=\s*(["\'])\s*(javascript|data|vbscript):[^"\']*\1/i',
        'href=$1#$1',
        $content
    ) ?? $content;

    $allowed_tags = array_keys($allowed_html);
    $allowed_list = '';
    foreach ($allowed_tags as $tag) {
        $allowed_list .= '<' . $tag . '>';
    }

    $content = strip_tags($content, $allowed_list);

    // Drop attributes not in allowlist for <a>.
    $content = preg_replace_callback(
        '#<a\b([^>]*)>#i',
        static function ($m) use ($allowed_html) {
            $attrs = $m[1];
            $kept  = [];
            $a_allowed = isset($allowed_html['a']) && is_array($allowed_html['a'])
                ? $allowed_html['a']
                : [];
            foreach (['href', 'rel', 'target'] as $name) {
                if (empty($a_allowed[$name])) {
                    continue;
                }
                if (preg_match('/\b' . $name . '\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/i', $attrs, $am)) {
                    $val = $am[2] !== '' ? $am[2] : ($am[3] !== '' ? $am[3] : $am[1]);
                    $kept[] = $name . '="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"';
                }
            }
            return '<a' . ($kept ? ' ' . implode(' ', $kept) : '') . '>';
        },
        $content
    ) ?? $content;

    return $content;
}

require_once $plugin_root . '/includes/application/training/AA_Training_Lesson_Html_Sanitizer.php';

$total  = 0;
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

$allowed = AA_Training_Lesson_Html_Sanitizer::allowed_html();
ac_assert('allowlist includes p/br/strong/em/ul/ol/li/h2/h3/a/code/pre/blockquote',
    isset($allowed['p'], $allowed['br'], $allowed['strong'], $allowed['em'], $allowed['ul'], $allowed['ol'], $allowed['li'], $allowed['h2'], $allowed['h3'], $allowed['a'], $allowed['code'], $allowed['pre'], $allowed['blockquote'])
);
ac_assert('a only allows href/rel/target',
    isset($allowed['a']['href'], $allowed['a']['rel'], $allowed['a']['target'])
    && count($allowed['a']) === 3
);
ac_assert('no script/style/iframe/img in allowlist',
    !isset($allowed['script'], $allowed['style'], $allowed['iframe'], $allowed['img'])
);

$safe = AA_Training_Lesson_Html_Sanitizer::sanitize_html(
    '<p>Hola <strong>mundo</strong></p><ul><li>uno</li></ul><h2>Título</h2><a href="https://deoia.com" rel="noopener" target="_blank">link</a>'
);
ac_assert('keeps allowed tags',
    strpos($safe, '<p>') !== false
    && strpos($safe, '<strong>') !== false
    && strpos($safe, '<ul>') !== false
    && strpos($safe, '<h2>') !== false
    && strpos($safe, '<a ') !== false
);

$dirty = AA_Training_Lesson_Html_Sanitizer::sanitize_html(
    '<p onclick="alert(1)" style="color:red">x</p><script>evil()</script><iframe src="x"></iframe><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">bad</a>'
);
ac_assert('strips script/iframe/img',
    strpos($dirty, '<script') === false
    && strpos($dirty, '<iframe') === false
    && strpos($dirty, '<img') === false
);
ac_assert('strips inline handlers and styles',
    stripos($dirty, 'onclick') === false
    && stripos($dirty, 'style=') === false
    && stripos($dirty, 'onerror') === false
);
ac_assert('neutralizes javascript: href',
    stripos($dirty, 'javascript:') === false
);

$payload = AA_Training_Lesson_Html_Sanitizer::sanitize_lesson_data([
    'course_key' => 'fundamentos-deoia',
    'lesson'     => ['key' => 'bienvenida', 'title' => 'Bienvenida'],
    'blocks'     => [
        [
            'type' => 'rich_text',
            'html' => '<p>ok</p><script>x</script><img src=x>',
        ],
        [
            'type'         => 'exercise',
            'title'        => 'Ejercicio',
            'instructions' => 'Haz esto',
        ],
        [
            'type' => 'video',
            'url'  => 'https://evil.example',
        ],
    ],
]);

ac_assert('sanitize_lesson_data cleans rich_text only',
    isset($payload['blocks'][0]['html'])
    && strpos($payload['blocks'][0]['html'], '<script') === false
    && strpos($payload['blocks'][0]['html'], '<img') === false
    && strpos($payload['blocks'][0]['html'], '<p>') !== false
);
ac_assert('exercise block preserved as plain fields',
    ($payload['blocks'][1]['type'] ?? '') === 'exercise'
    && ($payload['blocks'][1]['title'] ?? '') === 'Ejercicio'
);
ac_assert('unknown blocks passed through unchanged for JS to ignore',
    ($payload['blocks'][2]['type'] ?? '') === 'video'
);

// Use case wires sanitizer on success.
if (!function_exists('get_option')) {
    function get_option($k, $d = false) {
        return $k === 'aa_client_secret' ? 'secret' : $d;
    }
}

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-training-backend-client.php';
require_once $plugin_root . '/includes/application/training/TrainingContentUseCase.php';

final class Fake_Training_Client_For_Sanitize extends AA_Training_Backend_Client {
    public function get_lesson($lesson_key): array {
        return [
            'ok'     => true,
            'result' => [
                'course_key' => 'fundamentos-deoia',
                'lesson'     => ['key' => $lesson_key, 'title' => 'T', 'position' => 1, 'availability' => 'available'],
                'blocks'     => [
                    ['type' => 'rich_text', 'html' => '<p>hola</p><script>bad()</script>'],
                ],
            ],
        ];
    }

    public function is_valid_lesson_key($value): bool {
        return is_string($value) && $value !== '';
    }
}

$uc = new TrainingContentUseCase(new Fake_Training_Client_For_Sanitize());
$result = $uc->get_lesson('bienvenida');
ac_assert('use case success sanitizes html',
    !empty($result['success'])
    && isset($result['data']['blocks'][0]['html'])
    && strpos($result['data']['blocks'][0]['html'], '<script') === false
    && strpos($result['data']['blocks'][0]['html'], '<p>') !== false
);

$use_case_src = (string) file_get_contents($plugin_root . '/includes/application/training/TrainingContentUseCase.php');
ac_assert('use case requires sanitizer',
    strpos($use_case_src, 'AA_Training_Lesson_Html_Sanitizer') !== false
);

echo "\nPassed {$passed}/{$total}\n";
if ($failed) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
