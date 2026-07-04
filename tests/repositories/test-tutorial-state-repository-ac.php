<?php
/**
 * AC — TutorialStateRepository (site-scoped options).
 *
 * Ejecutar: php tests/repositories/test-tutorial-state-repository-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

require_once __DIR__ . '/../../includes/domain/tutorials/class-aa-tutorial-state-policy.php';
require_once __DIR__ . '/../../includes/repositories/TutorialStateRepository.php';

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

/** @var array<int,mixed> */
$storage = [];

TutorialStateRepository::set_storage_override_for_tests(
    static function (string $operation, int $blog_id, $payload = null) use (&$storage) {
        if ($operation === 'read') {
            return $storage[$blog_id] ?? false;
        }

        if ($operation === 'write') {
            $storage[$blog_id] = $payload;

            return true;
        }

        return false;
    }
);

$tutorial_id = AA_Tutorial_State_Policy::TUTORIAL_CREATE_TEST_APPOINTMENT;

$state = [
    'version' => 1,
    'tutorials' => [
        $tutorial_id => [
            'status' => 'in_progress',
            'current_step_id' => 'open_sidebar',
            'accepted_at' => '2026-07-03 12:00:00',
            'started_at' => '2026-07-03 12:00:00',
            'paused_at' => null,
            'completed_at' => null,
            'updated_at' => '2026-07-03 12:00:01',
        ],
    ],
];

ac_assert('save site 1', TutorialStateRepository::save($state));
$loaded_site_1 = TutorialStateRepository::find();
ac_assert(
    'roundtrip site 1 current_step_id',
    ($loaded_site_1['tutorials'][$tutorial_id]['current_step_id'] ?? '') === 'open_sidebar'
);

$GLOBALS['aa_test_current_blog_id'] = 2;
if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id() {
        return (int) ($GLOBALS['aa_test_current_blog_id'] ?? 1);
    }
}

ac_assert('empty site 2 before save', TutorialStateRepository::find()['tutorials'] === []);

$site_2_state = [
    'version' => 1,
    'tutorials' => [
        $tutorial_id => [
            'status' => 'in_progress',
            'current_step_id' => 'calendar_overview',
            'accepted_at' => '2026-07-04 09:30:00',
            'started_at' => '2026-07-04 09:30:00',
            'paused_at' => null,
            'completed_at' => null,
            'updated_at' => '2026-07-04 09:30:05',
        ],
    ],
];
ac_assert('save site 2', TutorialStateRepository::save($site_2_state));

$GLOBALS['aa_test_current_blog_id'] = 1;
$site_1_again = TutorialStateRepository::find();
ac_assert(
    'site 1 isolated from site 2',
    ($site_1_again['tutorials'][$tutorial_id]['current_step_id'] ?? '') === 'open_sidebar'
);

$GLOBALS['aa_test_current_blog_id'] = 2;
$site_2_again = TutorialStateRepository::find();
ac_assert(
    'site 2 keeps its own state',
    ($site_2_again['tutorials'][$tutorial_id]['current_step_id'] ?? '') === 'calendar_overview'
);

ac_assert(
    'repository uses site option key constant',
    TutorialStateRepository::OPTION_KEY === 'aa_tutorial_state_v1'
);

$repo_src = file_get_contents(__DIR__ . '/../../includes/repositories/TutorialStateRepository.php');
ac_assert('repository uses get_option path', strpos($repo_src, 'get_option') !== false);
ac_assert('repository does not use get_site_option', strpos($repo_src, 'get_site_option') === false);

echo "\nPassed {$passed}/{$total}\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
