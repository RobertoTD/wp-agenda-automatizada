<?php
/**
 * Task State Repository — SQL puro para señales operativas de tareas (defer/dismiss).
 *
 * Una fila por task_id. No conoce reglas de proyección ni visible_actions.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TaskStateRepository {

    /**
     * Columnas permitidas en upsert (whitelist).
     *
     * @var list<string>
     */
    private const UPSERT_COLUMNS = [
        'last_deferred_at',
        'defer_until',
        'defer_count',
        'last_dismissed_at',
        'dismiss_until',
        'dismiss_count',
    ];

    /**
     * @return string
     */
    private static function table_name() {
        global $wpdb;

        return $wpdb->prefix . 'aa_task_state';
    }

    /**
     * @param object|null $row
     * @return array<string,mixed>|null
     */
    private static function map_row($row) {
        if (!$row) {
            return null;
        }

        return [
            'task_id' => (int) $row->task_id,
            'last_deferred_at' => $row->last_deferred_at,
            'defer_until' => $row->defer_until,
            'defer_count' => (int) $row->defer_count,
            'last_dismissed_at' => $row->last_dismissed_at,
            'dismiss_until' => $row->dismiss_until,
            'dismiss_count' => (int) $row->dismiss_count,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * @param int $task_id
     * @return array<string,mixed>|null
     */
    public static function find_by_task_id($task_id) {
        $normalized_task_id = (int) $task_id;

        if ($normalized_task_id < 1) {
            return null;
        }

        global $wpdb;

        $table = self::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE task_id = %d LIMIT 1",
                $normalized_task_id
            )
        );

        if ($wpdb->last_error) {
            error_log('[TaskStateRepository] find_by_task_id: ' . $wpdb->last_error);
            return null;
        }

        return self::map_row($row);
    }

    /**
     * Carga estados de señales para múltiples tareas (solo lectura).
     *
     * @param list<int|string> $task_ids
     * @return array<int,array<string,mixed>> task_id => state row
     */
    public static function find_by_task_ids(array $task_ids): array {
        $normalized_ids = [];

        foreach ($task_ids as $task_id) {
            $normalized = (int) $task_id;

            if ($normalized < 1) {
                continue;
            }

            $normalized_ids[$normalized] = $normalized;
        }

        if ($normalized_ids === []) {
            return [];
        }

        global $wpdb;

        $table = self::table_name();
        $ids = array_values($normalized_ids);
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $query = "SELECT * FROM {$table} WHERE task_id IN ({$placeholders})";
        $rows = $wpdb->get_results($wpdb->prepare($query, ...$ids));

        if ($wpdb->last_error) {
            error_log('[TaskStateRepository] find_by_task_ids: ' . $wpdb->last_error);
            return [];
        }

        $mapped = [];

        foreach ($rows as $row) {
            $state = self::map_row($row);

            if ($state === null) {
                continue;
            }

            $mapped[(int) $state['task_id']] = $state;
        }

        return $mapped;
    }

    /**
     * Crea o actualiza el estado de señales de una tarea (PK task_id).
     *
     * @param int                  $task_id
     * @param array<string,mixed>  $data Solo claves de UPSERT_COLUMNS.
     * @return array<string,mixed>|null
     */
    public static function upsert($task_id, array $data) {
        $normalized_task_id = (int) $task_id;

        if ($normalized_task_id < 1) {
            return null;
        }

        $payload = self::filter_upsert_data($data);
        $now = current_time('mysql');
        $existing = self::find_by_task_id($normalized_task_id);

        global $wpdb;

        $table = self::table_name();

        if ($existing === null) {
            $insert = array_merge(
                [
                    'task_id' => $normalized_task_id,
                    'last_deferred_at' => null,
                    'defer_until' => null,
                    'defer_count' => 0,
                    'last_dismissed_at' => null,
                    'dismiss_until' => null,
                    'dismiss_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $payload
            );

            $formats = self::build_formats($insert);
            $result = $wpdb->insert($table, $insert, $formats);

            if ($result === false) {
                error_log('[TaskStateRepository] upsert insert: ' . $wpdb->last_error);
                return null;
            }

            return self::find_by_task_id($normalized_task_id);
        }

        $update = array_merge($payload, ['updated_at' => $now]);
        $formats = self::build_formats($update);

        $result = $wpdb->update(
            $table,
            $update,
            ['task_id' => $normalized_task_id],
            $formats,
            ['%d']
        );

        if ($result === false) {
            error_log('[TaskStateRepository] upsert update: ' . $wpdb->last_error);
            return null;
        }

        return self::find_by_task_id($normalized_task_id);
    }

    /**
     * Registra señal defer (Ahora no): last_deferred_at + increment defer_count.
     *
     * @param int    $task_id
     * @param string $now Y-m-d H:i:s
     * @return array<string,mixed>|null
     */
    public static function record_defer($task_id, $now) {
        $normalized_task_id = (int) $task_id;

        if ($normalized_task_id < 1) {
            return null;
        }

        $existing = self::find_by_task_id($normalized_task_id);
        $next_count = $existing === null
            ? 1
            : ((int) ($existing['defer_count'] ?? 0)) + 1;

        return self::upsert($normalized_task_id, [
            'last_deferred_at' => $now,
            'defer_count' => $next_count,
            'defer_until' => null,
        ]);
    }

    /**
     * Registra señal dismiss (Ignorar): last_dismissed_at + increment dismiss_count.
     *
     * @param int    $task_id
     * @param string $now Y-m-d H:i:s
     * @return array<string,mixed>|null
     */
    public static function record_dismiss($task_id, $now) {
        $normalized_task_id = (int) $task_id;

        if ($normalized_task_id < 1) {
            return null;
        }

        $existing = self::find_by_task_id($normalized_task_id);
        $next_count = $existing === null
            ? 1
            : ((int) ($existing['dismiss_count'] ?? 0)) + 1;

        return self::upsert($normalized_task_id, [
            'last_dismissed_at' => $now,
            'dismiss_count' => $next_count,
            'dismiss_until' => null,
        ]);
    }

    /**
     * Cierra el efecto activo de ocultamiento por dismiss sin borrar historial.
     *
     * @param int    $task_id
     * @param string $now Y-m-d H:i:s
     * @return array<string,mixed>|null
     */
    public static function clear_dismiss_hiding_effect($task_id, $now) {
        $normalized_task_id = (int) $task_id;

        if ($normalized_task_id < 1) {
            return null;
        }

        $existing = self::find_by_task_id($normalized_task_id);

        if ($existing === null) {
            return null;
        }

        $dismiss_count = (int) ($existing['dismiss_count'] ?? 0);
        $last_dismissed_at = $existing['last_dismissed_at'] ?? null;

        if ($dismiss_count < 1 || $last_dismissed_at === null || $last_dismissed_at === '') {
            return null;
        }

        return self::upsert($normalized_task_id, [
            'dismiss_until' => $now,
        ]);
    }

    /**
     * @param list<int|string> $task_ids
     * @param string           $now Y-m-d H:i:s
     * @return array<int,array<string,mixed>>
     */
    public static function clear_dismiss_hiding_effect_for_task_ids(array $task_ids, $now) {
        $updated = [];

        foreach ($task_ids as $task_id) {
            $normalized_task_id = (int) $task_id;

            if ($normalized_task_id < 1) {
                continue;
            }

            $row = self::clear_dismiss_hiding_effect($normalized_task_id, $now);

            if ($row !== null) {
                $updated[$normalized_task_id] = $row;
            }
        }

        return $updated;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function filter_upsert_data(array $data) {
        $filtered = [];

        foreach (self::UPSERT_COLUMNS as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $filtered[$column] = self::normalize_column_value($column, $data[$column]);
        }

        return $filtered;
    }

    /**
     * @param string $column
     * @param mixed  $value
     * @return mixed
     */
    private static function normalize_column_value($column, $value) {
        if (in_array($column, ['defer_count', 'dismiss_count'], true)) {
            return max(0, (int) $value);
        }

        if (in_array($column, ['last_deferred_at', 'defer_until', 'last_dismissed_at', 'dismiss_until'], true)) {
            if ($value === null || $value === '') {
                return null;
            }

            return is_string($value) ? $value : null;
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     * @return list<string>
     */
    private static function build_formats(array $row) {
        $formats = [];

        foreach (array_keys($row) as $column) {
            if (in_array($column, ['task_id', 'defer_count', 'dismiss_count'], true)) {
                $formats[] = '%d';
                continue;
            }

            $formats[] = '%s';
        }

        return $formats;
    }
}
