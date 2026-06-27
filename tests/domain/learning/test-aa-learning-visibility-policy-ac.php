<?php
/**
 * AC para AA_Learning_Catalog y AA_Learning_Visibility_Policy.
 *
 * Ejecutar: php tests/domain/learning/test-aa-learning-visibility-policy-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/learning/class-aa-learning-catalog.php';
require_once __DIR__ . '/../../../includes/domain/learning/class-aa-learning-visibility-policy.php';

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

/**
 * @param array<string,mixed>      $definition
 * @param array<string,mixed>|null $state
 * @param array<string,mixed>      $facts
 * @param string                   $now
 * @param array<string,mixed>      $options
 * @return array<string,mixed>
 */
function learning_eval(
    array $definition,
    ?array $state = null,
    array $facts = [],
    string $now = '2026-06-01 12:00:00',
    array $options = []
): array {
    return (new AA_Learning_Visibility_Policy())->evaluate($definition, $state, $facts, $now, $options);
}

$policy = new AA_Learning_Visibility_Policy();
$catalog = AA_Learning_Catalog::definitions();
$now = '2026-06-01 12:00:00';

ac_assert('Catalog has 7 definitions', count($catalog) === 7);
ac_assert(
    'Catalog active_definition_keys has 7 entries',
    count(AA_Learning_Catalog::active_definition_keys()) === 7
);
ac_assert('Catalog excludes learn_basic_flow', !isset($catalog['learn_basic_flow']));
ac_assert('Catalog excludes review_agenda', !isset($catalog['review_agenda']));
ac_assert('Catalog contains connect_google_calendar', isset($catalog['connect_google_calendar']));
ac_assert(
    'Catalog auto entry has completion_fact',
    ($catalog['configure_services']['completion_type'] ?? '') === AA_Learning_Catalog::COMPLETION_AUTO
    && ($catalog['configure_services']['completion_fact'] ?? '') === 'has_active_service'
);
ac_assert(
    'Catalog manual entry has no completion_fact',
    ($catalog['install_pwa']['completion_type'] ?? '') === AA_Learning_Catalog::COMPLETION_MANUAL
    && ($catalog['install_pwa']['completion_fact'] ?? null) === null
);

$r_install_visible = learning_eval($catalog['install_pwa']);
ac_assert(
    'install_pwa evaluate preserves explicit action',
    array_key_exists('action', $r_install_visible)
    && is_array($r_install_visible['action'])
    && ($r_install_visible['action']['type'] ?? '') === 'handler'
    && ($r_install_visible['action']['handler'] ?? '') === 'pwa.install'
    && ($r_install_visible['action']['label'] ?? '') === 'Instalar'
);

$r_legacy_visible = learning_eval($catalog['configure_services']);
ac_assert(
    'legacy evaluate does not add explicit action key',
    !array_key_exists('action', $r_legacy_visible)
);

// AC1: Activa sin estado usa default_list.
$r = learning_eval($catalog['configure_services']);
ac_assert('AC1 visible with empty state', $r['visible'] === true);
ac_assert('AC1 default_list is primary', $r['effective_list'] === AA_Learning_Visibility_Policy::LIST_PRIMARY);

// AC2: Mayor importance aparece antes en la misma lista.
$definitions = [
    'high' => [
        'key' => 'high',
        'title' => 'High',
        'description' => '',
        'importance' => 50,
        'default_list' => 1,
        'active' => true,
        'completion_type' => AA_Learning_Catalog::COMPLETION_MANUAL,
        'completion_fact' => null,
        'navigation' => [],
    ],
    'low' => [
        'key' => 'low',
        'title' => 'Low',
        'description' => '',
        'importance' => 5,
        'default_list' => 1,
        'active' => true,
        'completion_type' => AA_Learning_Catalog::COMPLETION_MANUAL,
        'completion_fact' => null,
        'navigation' => [],
    ],
];
$grouped = $policy->evaluate_all($definitions, [], [], $now);
ac_assert(
    'AC2 higher importance sorts first',
    ($grouped['list_1'][0]['key'] ?? '') === 'high'
    && ($grouped['list_1'][1]['key'] ?? '') === 'low'
);

// AC3: is_completed manual oculta.
$r = learning_eval($catalog['install_pwa'], ['is_completed' => 1]);
ac_assert('AC3 manual completed hidden', $r['visible'] === false && $r['is_completed'] === true);

// AC4: Auto-completada por facts oculta.
$r = learning_eval($catalog['configure_services'], null, ['has_active_service' => true]);
ac_assert(
    'AC4 auto completed hidden',
    $r['visible'] === false
    && $r['is_completed'] === true
    && $r['is_auto_completed'] === true
);

// AC5: is_ignored pasa a lista 2.
$r = learning_eval($catalog['connect_google_calendar'], ['is_ignored' => 1]);
ac_assert(
    'AC5 ignored moves to list 2',
    $r['visible'] === true
    && $r['is_ignored'] === true
    && $r['effective_list'] === AA_Learning_Visibility_Policy::LIST_SECONDARY
);

// AC5b: is_dismissed reciente oculta temporalmente la recomendación.
$r_dismissed = learning_eval($catalog['install_pwa'], [
    'is_dismissed' => 1,
    'dismissed_at' => '2026-06-01 06:00:00',
]);
ac_assert(
    'AC5b recent dismissed hides recommendation',
    $r_dismissed['visible'] === false
    && $r_dismissed['is_dismissed'] === true
    && $r_dismissed['is_dismiss_active'] === true
);

// AC5c: is_dismissed antiguo vuelve visible en lista 2.
$r_expired_dismiss = learning_eval($catalog['install_pwa'], [
    'is_dismissed' => 1,
    'dismissed_at' => '2026-05-30 11:00:00',
]);
ac_assert(
    'AC5c expired dismissed returns to list 2',
    $r_expired_dismiss['visible'] === true
    && $r_expired_dismiss['is_dismissed'] === true
    && $r_expired_dismiss['is_dismiss_active'] === false
    && $r_expired_dismiss['effective_list'] === AA_Learning_Visibility_Policy::LIST_SECONDARY
);

$r_empty_dismiss_date = learning_eval($catalog['install_pwa'], [
    'is_dismissed' => 1,
    'dismissed_at' => '',
]);
ac_assert('AC5d dismissed empty date hides defensively', $r_empty_dismiss_date['visible'] === false);

$r_invalid_dismiss_date = learning_eval($catalog['install_pwa'], [
    'is_dismissed' => 1,
    'dismissed_at' => 'not-a-date',
]);
ac_assert('AC5e dismissed invalid date hides defensively', $r_invalid_dismiss_date['visible'] === false);

$r_expired_auto_completed = learning_eval(
    $catalog['configure_services'],
    [
        'is_dismissed' => 1,
        'dismissed_at' => '2026-05-30 11:00:00',
    ],
    ['has_active_service' => true]
);
ac_assert('AC5f expired dismissed auto-completed stays hidden', $r_expired_auto_completed['visible'] === false);

$r_expired_manual_completed = learning_eval($catalog['install_pwa'], [
    'is_dismissed' => 1,
    'dismissed_at' => '2026-05-30 11:00:00',
    'is_completed' => 1,
]);
ac_assert('AC5g expired dismissed manually completed stays hidden', $r_expired_manual_completed['visible'] === false);

$r_deferred_visible = learning_eval($catalog['install_pwa'], ['is_ignored' => 1]);
ac_assert(
    'AC5h ignored native list 2 stays visible',
    $r_deferred_visible['visible'] === true
    && $r_deferred_visible['effective_list'] === AA_Learning_Visibility_Policy::LIST_SECONDARY
);

// AC6: list_override manda sobre default_list.
$r = learning_eval(
    $catalog['configure_areas'],
    ['list_override' => 2]
);
ac_assert(
    'AC6 list_override wins',
    $r['effective_list'] === AA_Learning_Visibility_Policy::LIST_SECONDARY
);

$r_override_primary = learning_eval(
    $catalog['install_pwa'],
    ['list_override' => 1, 'is_ignored' => 1]
);
ac_assert(
    'AC6 list_override wins over ignored',
    $r_override_primary['effective_list'] === AA_Learning_Visibility_Policy::LIST_PRIMARY
);

// AC7: last_suggested_at antiguo pasa a lista 2.
$r = learning_eval(
    $catalog['connect_google_calendar'],
    ['last_suggested_at' => '2026-05-01 10:00:00'],
    [],
    $now,
    ['aging_days' => 14]
);
ac_assert(
    'AC7 aging moves to list 2',
    $r['effective_list'] === AA_Learning_Visibility_Policy::LIST_SECONDARY
);

$r_recent = learning_eval(
    $catalog['connect_google_calendar'],
    ['last_suggested_at' => '2026-05-25 10:00:00'],
    [],
    $now,
    ['aging_days' => 14]
);
ac_assert(
    'AC7 recent suggestion keeps default list',
    $r_recent['effective_list'] === AA_Learning_Visibility_Policy::LIST_PRIMARY
);

// AC10: aging_days por item.
$aging_def = $catalog['configure_services'];
$aging_def['aging_days'] = 3;
$r_item_aging = learning_eval(
    $aging_def,
    ['last_suggested_at' => '2026-05-28 12:00:00'],
    [],
    $now
);
ac_assert(
    'AC10 item aging_days=3 moves to list 2 after 4 days',
    $r_item_aging['effective_list'] === AA_Learning_Visibility_Policy::LIST_SECONDARY
);

$r_default_aging = learning_eval(
    $catalog['configure_services'],
    ['last_suggested_at' => '2026-05-28 12:00:00'],
    [],
    $now
);
ac_assert(
    'AC10 default aging keeps list 1 after 4 days',
    $r_default_aging['effective_list'] === AA_Learning_Visibility_Policy::LIST_PRIMARY
);

$invalid_aging_def = $catalog['configure_services'];
$invalid_aging_def['aging_days'] = 0;
$r_invalid_aging = learning_eval(
    $invalid_aging_def,
    ['last_suggested_at' => '2026-05-28 12:00:00'],
    [],
    $now
);
ac_assert(
    'AC10 invalid aging_days falls back to default',
    $r_invalid_aging['effective_list'] === AA_Learning_Visibility_Policy::LIST_PRIMARY
);

// AC11: dismiss_hours por item.
$dismiss_def = $catalog['install_pwa'];
$dismiss_def['dismiss_hours'] = 1;
$r_item_dismiss = learning_eval($dismiss_def, [
    'is_dismissed' => 1,
    'dismissed_at' => '2026-06-01 10:00:00',
], [], $now);
ac_assert(
    'AC11 item dismiss_hours=1 returns to list 2 after 2 hours',
    $r_item_dismiss['visible'] === true
    && $r_item_dismiss['is_dismiss_active'] === false
    && $r_item_dismiss['effective_list'] === AA_Learning_Visibility_Policy::LIST_SECONDARY
);

$r_default_dismiss = learning_eval($catalog['install_pwa'], [
    'is_dismissed' => 1,
    'dismissed_at' => '2026-06-01 10:00:00',
], [], $now);
ac_assert(
    'AC11 default dismiss_hours keeps hidden after 2 hours',
    $r_default_dismiss['visible'] === false
    && $r_default_dismiss['is_dismiss_active'] === true
);

$invalid_dismiss_def = $catalog['install_pwa'];
$invalid_dismiss_def['dismiss_hours'] = -5;
$r_invalid_dismiss = learning_eval($invalid_dismiss_def, [
    'is_dismissed' => 1,
    'dismissed_at' => '2026-06-01 10:00:00',
], [], $now);
ac_assert(
    'AC11 invalid dismiss_hours falls back to default',
    $r_invalid_dismiss['visible'] === false
    && $r_invalid_dismiss['is_dismiss_active'] === true
);

// AC12: item rule gana sobre options global.
$item_over_options = $catalog['configure_services'];
$item_over_options['aging_days'] = 3;
$r_item_wins = learning_eval(
    $item_over_options,
    ['last_suggested_at' => '2026-05-28 12:00:00'],
    [],
    $now,
    ['aging_days' => 14]
);
ac_assert(
    'AC12 catalog aging_days wins over options',
    $r_item_wins['effective_list'] === AA_Learning_Visibility_Policy::LIST_SECONDARY
);

// AC8: Inactiva no aparece en evaluate_all.
$inactive = $catalog['configure_services'];
$inactive['active'] = false;
$grouped_inactive = $policy->evaluate_all(
    ['inactive_test' => $inactive],
    [],
    [],
    $now
);
ac_assert('AC8 inactive not in visible output', count($grouped_inactive['all_visible']) === 0);

$r_inactive = learning_eval($inactive);
ac_assert('AC8 inactive evaluate visible false', $r_inactive['visible'] === false);

// AC9: Mayor importance ordena antes que menor.
$neg_defs = [
    'positive' => [
        'key' => 'positive',
        'title' => 'P',
        'description' => '',
        'importance' => 10,
        'default_list' => 1,
        'active' => true,
        'completion_type' => AA_Learning_Catalog::COMPLETION_MANUAL,
        'completion_fact' => null,
        'navigation' => [],
    ],
    'negative' => [
        'key' => 'negative',
        'title' => 'N',
        'description' => '',
        'importance' => -20,
        'default_list' => 1,
        'active' => true,
        'completion_type' => AA_Learning_Catalog::COMPLETION_MANUAL,
        'completion_fact' => null,
        'navigation' => [],
    ],
];
$neg_grouped = $policy->evaluate_all($neg_defs, [], [], $now);
ac_assert(
    'AC9 higher importance first',
    ($neg_grouped['list_1'][0]['key'] ?? '') === 'positive'
);

// Tie-break por key ASC.
$tie_defs = [
    'b_key' => [
        'key' => 'b_key',
        'title' => 'B',
        'description' => '',
        'importance' => 0,
        'default_list' => 1,
        'active' => true,
        'completion_type' => AA_Learning_Catalog::COMPLETION_MANUAL,
        'completion_fact' => null,
        'navigation' => [],
    ],
    'a_key' => [
        'key' => 'a_key',
        'title' => 'A',
        'description' => '',
        'importance' => 0,
        'default_list' => 1,
        'active' => true,
        'completion_type' => AA_Learning_Catalog::COMPLETION_MANUAL,
        'completion_fact' => null,
        'navigation' => [],
    ],
];
$tie_grouped = $policy->evaluate_all($tie_defs, [], [], $now);
ac_assert(
    'Tie-break key ASC',
    ($tie_grouped['list_1'][0]['key'] ?? '') === 'a_key'
);

// Catálogo real: google conectado oculta recomendación.
$all_pending = $policy->evaluate_all($catalog, [], [], $now);
ac_assert(
    'Full catalog pending includes configure_services',
    count(array_filter(
        $all_pending['all_visible'],
        static function (array $item): bool {
            return ($item['key'] ?? '') === 'configure_services';
        }
    )) === 1
);

$all_done_google = $policy->evaluate_all($catalog, [], ['google_connected' => true], $now);
$google_visible = array_filter(
    $all_done_google['all_visible'],
    static function (array $item): bool {
        return ($item['key'] ?? '') === 'connect_google_calendar';
    }
);
ac_assert('Google connected hides catalog item', count($google_visible) === 0);

ac_assert('Catalog SEED_VERSION is 4', AA_Learning_Catalog::SEED_VERSION === '4');
ac_assert(
    'Catalog connect_google_calendar has highest importance',
    (int) ($catalog['connect_google_calendar']['importance'] ?? 0) === 100
);
ac_assert(
    'Catalog install_pwa has lowest importance',
    (int) ($catalog['install_pwa']['importance'] ?? 0) === 10
);

$list_1_keys = array_map(
    static function (array $item): string {
        return (string) ($item['key'] ?? '');
    },
    $all_pending['list_1'] ?? []
);
ac_assert(
    'Catalog list_1 preserves activation flow order under DESC',
    $list_1_keys === [
        'connect_google_calendar',
        'complete_business_data',
        'configure_services',
        'configure_areas',
        'configure_staff',
        'create_first_client',
    ]
);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
