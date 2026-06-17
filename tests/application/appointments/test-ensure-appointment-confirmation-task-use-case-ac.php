<?php
/**
 * AC MC2 — EnsureAppointmentConfirmationTaskUseCase.
 *
 * Ejecutar: php tests/application/appointments/test-ensure-appointment-confirmation-task-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

$plugin_root = dirname(__DIR__, 3);

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

$use_case_file = $plugin_root . '/includes/application/appointments/EnsureAppointmentConfirmationTaskUseCase.php';
$use_case_src = file_get_contents($use_case_file);

ac_assert('Use case file readable', $use_case_src !== false);
ac_assert('Use case defines EnsureAppointmentConfirmationTaskUseCase', strpos($use_case_src, 'class EnsureAppointmentConfirmationTaskUseCase') !== false);
ac_assert('Use case does not use CreateTaskUseCase', strpos($use_case_src, 'CreateTaskUseCase') === false);
ac_assert('Use case uses SeededTaskRepository', strpos($use_case_src, 'SeededTaskRepository') !== false);
ac_assert('Use case uses TaskActionRepository', strpos($use_case_src, 'TaskActionRepository') !== false);
ac_assert('Use case syncs appointment actions list defensively', strpos($use_case_src, 'SyncAppointmentActionsListUseCase') !== false);
ac_assert('Use case recovers task after upsert null', strpos($use_case_src, 'find_task_by_origin') !== false);
ac_assert('Use case rejects missing_reservation_id', strpos($use_case_src, 'missing_reservation_id') !== false);
ac_assert('Use case rejects reservation_not_confirmable', strpos($use_case_src, 'reservation_not_confirmable') !== false);
ac_assert('Use case rejects action_persistence_failed', strpos($use_case_src, 'action_persistence_failed') !== false);
ac_assert('Projector file has no TaskUseCaseSupport import', strpos(file_get_contents($plugin_root . '/includes/domain/appointments/class-aa-appointment-confirmation-task-projector.php'), 'TaskUseCaseSupport') === false);

require_once $plugin_root . '/includes/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once $plugin_root . '/includes/domain/appointments/class-aa-appointment-confirmation-task-projector.php';
require_once $plugin_root . '/includes/application/tasks/TaskUseCaseSupport.php';
require_once $plugin_root . '/includes/repositories/SeededTaskRepository.php';
require_once $plugin_root . '/includes/repositories/TaskActionRepository.php';
require_once $plugin_root . '/includes/infrastructure/appointments/class-aa-appointment-reservation-display-formatter.php';
require_once $plugin_root . '/includes/application/tasks/SyncAppointmentActionsListUseCase.php';
require_once $use_case_file;

$sample_reservation = [
    'id' => 101,
    'estado' => 'pending',
    'fecha' => '2026-06-20 10:30:00',
    'nombre' => 'Ana Test',
    'telefono' => '5550001',
    'correo' => 'ana@example.com',
    'servicio' => 'Corte',
    'duracion' => 60,
    'assignment_id' => null,
];

$race_simulated = false;
$race_use_case = new EnsureAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($sample_reservation): ?array {
        return $sample_reservation;
    },
    static function (): void {
    },
    null,
    static function (array $payload) use (&$race_simulated): ?array {
        if ($race_simulated) {
            return SeededTaskRepository::upsert_seeded_task($payload);
        }

        SeededTaskRepository::upsert_seeded_task($payload);
        $race_simulated = true;

        return null;
    }
);

$fail_action_once = true;
$repair_use_case = new EnsureAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($sample_reservation): ?array {
        return array_merge($sample_reservation, ['id' => $reservation_id]);
    },
    static function (): void {
        (new SyncAppointmentActionsListUseCase())->execute();
    },
    null,
    null,
    static function (int $task_id, array $payload) use (&$fail_action_once): ?array {
        if ($fail_action_once) {
            $fail_action_once = false;
            return null;
        }

        return TaskActionRepository::upsert($task_id, $payload);
    }
);

$list_missing_use_case = new EnsureAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($sample_reservation): ?array {
        return $sample_reservation;
    },
    static function (): void {
    },
    static function (): ?array {
        return null;
    }
);

$missing_id = (new EnsureAppointmentConfirmationTaskUseCase())->execute([]);
ac_assert(
    'missing_reservation_id error',
    empty($missing_id['success']) && ($missing_id['error']['code'] ?? '') === 'missing_reservation_id'
);

$not_found = (new EnsureAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id): ?array {
        return null;
    }
))->execute(['reservation_id' => 999]);
ac_assert(
    'reservation_not_found error',
    empty($not_found['success']) && ($not_found['error']['code'] ?? '') === 'reservation_not_found'
);

$not_confirmable = (new EnsureAppointmentConfirmationTaskUseCase(
    static function (int $reservation_id) use ($sample_reservation): ?array {
        return array_merge($sample_reservation, ['estado' => 'confirmed']);
    }
))->execute(['reservation_id' => 101]);
ac_assert(
    'reservation_not_confirmable error',
    empty($not_confirmable['success']) && ($not_confirmable['error']['code'] ?? '') === 'reservation_not_confirmable'
);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $plugin_root . '/includes/repositories/TaskRepository.php';
    require_once $plugin_root . '/includes/application/tasks/CreateTaskUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/CreateTaskListUseCase.php';
    require_once $plugin_root . '/includes/application/tasks/GetTaskBoardUseCase.php';
    require_once $plugin_root . '/includes/application/executable/TaskBoardToExecutableMapper.php';

    AA_Schema::install();
    (new SyncAppointmentActionsListUseCase())->execute();

    global $wpdb;
    $reservas_table = $wpdb->prefix . 'aa_reservas';
    $tasks_table = $wpdb->prefix . 'aa_tasks';
    $actions_table = $wpdb->prefix . 'aa_task_actions';
    $suffix = (string) time();

    $insert_reservation = static function (array $overrides = []) use ($wpdb, $reservas_table, $suffix): int {
        $payload = array_merge([
            'servicio' => 'Corte MC2 ' . $suffix,
            'fecha' => '2026-06-21 11:00:00',
            'duracion' => 60,
            'nombre' => 'Cliente MC2 ' . $suffix,
            'telefono' => '5559999',
            'correo' => 'mc2@example.com',
            'estado' => 'pending',
        ], $overrides);
        $wpdb->insert($reservas_table, $payload);

        return (int) $wpdb->insert_id;
    };

    $cleanup_task_by_origin = static function (string $origin_key) use ($wpdb, $tasks_table, $actions_table): void {
        $task = SeededTaskRepository::find_task_by_origin('agenda_app', $origin_key);

        if (!is_array($task)) {
            return;
        }

        $task_id = (int) ($task['id'] ?? 0);
        $wpdb->delete($actions_table, ['task_id' => $task_id], ['%d']);
        $wpdb->delete($tasks_table, ['id' => $task_id], ['%d']);
    };

    $reservation_id = $insert_reservation(['nombre' => 'Ana MC2 ' . $suffix, 'telefono' => '5551111']);
    $origin_key = AA_Appointment_Actions_Catalog::task_origin_key($reservation_id);
    $cleanup_task_by_origin($origin_key);

    $first = (new EnsureAppointmentConfirmationTaskUseCase())->execute(['reservation_id' => $reservation_id]);
    ac_assert('First ensure succeeds', !empty($first['success']));
    $task_id = (int) ($first['data']['task']['id'] ?? 0);
    $action_id = (int) ($first['data']['action']['id'] ?? 0);
    ac_assert('First ensure creates task', $task_id > 0);
    ac_assert('First ensure creates action', $action_id > 0);
    ac_assert(
        'Task origin_key matches reservation',
        ($first['data']['task']['origin_key'] ?? '') === $origin_key
    );
    ac_assert(
        'Task title includes client name',
        strpos((string) ($first['data']['task']['title'] ?? ''), 'Ana MC2') !== false
    );
    ac_assert(
        'Task notes include phone and service',
        strpos((string) ($first['data']['task']['notes'] ?? ''), '5551111') !== false
        && strpos((string) ($first['data']['task']['notes'] ?? ''), 'Corte MC2') !== false
    );
    ac_assert(
        'Task completion_type system',
        ($first['data']['task']['completion_type'] ?? '') === 'system'
    );
    ac_assert(
        'Action key appointment.confirm',
        ($first['data']['action']['action_key'] ?? '') === 'appointment.confirm'
    );

    $second = (new EnsureAppointmentConfirmationTaskUseCase())->execute(['reservation_id' => $reservation_id]);
    ac_assert('Second ensure succeeds', !empty($second['success']));
    ac_assert(
        'Second ensure preserves task id',
        (int) ($second['data']['task']['id'] ?? 0) === $task_id
    );

    $action_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$actions_table} WHERE task_id = %d AND action_key = %s",
            $task_id,
            'appointment.confirm'
        )
    );
    ac_assert('Action appointment.confirm is unique', $action_count === 1);

    $reservation_b = $insert_reservation(['nombre' => 'Bruno MC2 ' . $suffix]);
    $origin_b = AA_Appointment_Actions_Catalog::task_origin_key($reservation_b);
    $cleanup_task_by_origin($origin_b);
    $ensure_b = (new EnsureAppointmentConfirmationTaskUseCase())->execute(['reservation_id' => $reservation_b]);
    ac_assert('Second reservation creates distinct task', (int) ($ensure_b['data']['task']['id'] ?? 0) !== $task_id);

    $confirmed_id = $insert_reservation(['estado' => 'confirmed']);
    $confirmed_origin = AA_Appointment_Actions_Catalog::task_origin_key($confirmed_id);
    $cleanup_task_by_origin($confirmed_origin);
    $blocked = (new EnsureAppointmentConfirmationTaskUseCase())->execute(['reservation_id' => $confirmed_id]);
    ac_assert(
        'Non-pending reservation does not create task',
        empty($blocked['success']) && ($blocked['error']['code'] ?? '') === 'reservation_not_confirmable'
    );
    ac_assert(
        'Non-pending reservation leaves no task row',
        SeededTaskRepository::find_task_by_origin('agenda_app', $confirmed_origin) === null
    );

    $wpdb->update(
        $reservas_table,
        [
            'nombre' => 'Ana Actualizada ' . $suffix,
            'telefono' => '5552222',
            'servicio' => 'Barba',
            'fecha' => '2026-06-22 15:45:00',
        ],
        ['id' => $reservation_id],
        ['%s', '%s', '%s', '%s'],
        ['%d']
    );
    $updated = (new EnsureAppointmentConfirmationTaskUseCase())->execute(['reservation_id' => $reservation_id]);
    ac_assert('Update ensure succeeds', !empty($updated['success']));
    ac_assert(
        'Updated title reflects new client name',
        strpos((string) ($updated['data']['task']['title'] ?? ''), 'Ana Actualizada') !== false
    );
    ac_assert(
        'Updated notes reflect new phone and service',
        strpos((string) ($updated['data']['task']['notes'] ?? ''), '5552222') !== false
        && strpos((string) ($updated['data']['task']['notes'] ?? ''), 'Barba') !== false
    );
    ac_assert(
        'Updated ensure keeps same task id',
        (int) ($updated['data']['task']['id'] ?? 0) === $task_id
    );

    TaskRepository::mark_completed($task_id, '2026-06-17 12:00:00');
    $wpdb->update(
        $reservas_table,
        ['nombre' => 'No Debe Reabrir ' . $suffix],
        ['id' => $reservation_id],
        ['%s'],
        ['%d']
    );
    $done_again = (new EnsureAppointmentConfirmationTaskUseCase())->execute(['reservation_id' => $reservation_id]);
    ac_assert('Ensure on completed task still succeeds', !empty($done_again['success']));
    ac_assert(
        'Completed task stays done after ensure',
        ($done_again['data']['task']['status'] ?? '') === 'done'
    );
    ac_assert(
        'Completed task keeps completed_at',
        ($done_again['data']['task']['completed_at'] ?? '') !== ''
    );

    $wpdb->delete($actions_table, ['task_id' => $task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $task_id], ['%d']);
    $race_result = $race_use_case->execute(['reservation_id' => $reservation_id]);
    ac_assert('Race recovery ensure succeeds', !empty($race_result['success']));
    ac_assert('Race recovery returns task id', (int) ($race_result['data']['task']['id'] ?? 0) > 0);

    $cleanup_task_by_origin($origin_key);
    $fail_action_once = true;
    $first_repair = $repair_use_case->execute(['reservation_id' => $reservation_id]);
    ac_assert(
        'Partial persistence returns action_persistence_failed',
        empty($first_repair['success']) && ($first_repair['error']['code'] ?? '') === 'action_persistence_failed'
    );
    $orphan = SeededTaskRepository::find_task_by_origin('agenda_app', $origin_key);
    ac_assert('Partial persistence keeps created task', is_array($orphan));
    $second_repair = $repair_use_case->execute(['reservation_id' => $reservation_id]);
    ac_assert('Second ensure repairs missing action', !empty($second_repair['success']));
    ac_assert(
        'Repaired action has appointment.confirm',
        ($second_repair['data']['action']['action_key'] ?? '') === 'appointment.confirm'
    );

    $lists_table = $wpdb->prefix . 'aa_task_lists';
    $list = SeededTaskRepository::find_list_by_origin('agenda_app', 'appointment_actions');
    $list_row_id = (int) ($list['id'] ?? 0);
    $wpdb->delete($lists_table, ['id' => $list_row_id], ['%d']);
    $missing_list = (new EnsureAppointmentConfirmationTaskUseCase(
        null,
        static function (): void {
        }
    ))->execute(['reservation_id' => $reservation_id]);
    ac_assert(
        'Missing list returns appointment_actions_list_not_ready',
        empty($missing_list['success']) && ($missing_list['error']['code'] ?? '') === 'appointment_actions_list_not_ready'
    );
    (new SyncAppointmentActionsListUseCase())->execute();

    $wpdb->update($reservas_table, ['estado' => 'pending'], ['id' => $reservation_id], ['%s'], ['%d']);
    $wpdb->delete($actions_table, ['task_id' => $task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $task_id], ['%d']);
    $cleanup_task_by_origin($origin_key);
    $fresh = (new EnsureAppointmentConfirmationTaskUseCase())->execute(['reservation_id' => $reservation_id]);
    ac_assert('Fresh ensure after list restore succeeds', !empty($fresh['success']));
    $fresh_task_id = (int) ($fresh['data']['task']['id'] ?? 0);

    $board = (new GetTaskBoardUseCase())->execute();
    $mapped = TaskBoardToExecutableMapper::map($board);
    $executable_task = null;

    foreach ($mapped as $mapped_list) {
        if (($mapped_list['origin_key'] ?? '') !== 'appointment_actions') {
            continue;
        }

        foreach ($mapped_list['buckets'] ?? [] as $bucket) {
            foreach ($bucket['items'] ?? [] as $item) {
                if ((int) ($item['id'] ?? 0) === $fresh_task_id) {
                    $executable_task = $item;
                    break 3;
                }
            }
        }
    }

    ac_assert('Executable feed includes confirmation task', is_array($executable_task));
    ac_assert(
        'Executable primary action is Confirmar',
        ($executable_task['primary_action']['label'] ?? '') === 'Confirmar'
        && ($executable_task['primary_action']['handler'] ?? '') === 'appointment.confirm'
    );
    ac_assert(
        'Executable can_complete is false',
        ($executable_task['capabilities']['can_complete'] ?? true) === false
    );
    ac_assert(
        'Executable primary action is not generic Completar',
        ($executable_task['primary_action']['label'] ?? '') !== 'Completar'
    );
    ac_assert(
        'Executable task is not user editable',
        ($executable_task['capabilities']['can_edit'] ?? true) === false
        && ($executable_task['capabilities']['can_delete'] ?? true) === false
    );

    $seeded_list_id = (int) (SeededTaskRepository::find_list_by_origin('agenda_app', 'appointment_actions')['id'] ?? 0);
    $manual_blocked = (new CreateTaskUseCase())->execute([
        'list_id' => $seeded_list_id,
        'title' => 'Manual bloqueada',
    ]);
    ac_assert(
        'CreateTaskUseCase still blocks manual task in appointment_actions',
        empty($manual_blocked['success']) && ($manual_blocked['error']['code'] ?? '') === 'list_not_manual_destination'
    );

    $wpdb->delete($actions_table, ['task_id' => $fresh_task_id], ['%d']);
    $wpdb->delete($tasks_table, ['id' => $fresh_task_id], ['%d']);
    $cleanup_task_by_origin($origin_b);
    $wpdb->delete($reservas_table, ['id' => $reservation_id], ['%d']);
    $wpdb->delete($reservas_table, ['id' => $reservation_b], ['%d']);
    $wpdb->delete($reservas_table, ['id' => $confirmed_id], ['%d']);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para pruebas de BD.\n";

    $list_missing = $list_missing_use_case->execute(['reservation_id' => 101]);
    ac_assert(
        'Missing list without sync returns appointment_actions_list_not_ready',
        empty($list_missing['success']) && ($list_missing['error']['code'] ?? '') === 'appointment_actions_list_not_ready'
    );
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
