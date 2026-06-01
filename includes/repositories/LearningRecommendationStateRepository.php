<?php
/**
 * Learning Recommendation State Repository — SQL puro para estados por instalación.
 *
 * Persiste acciones del usuario (completar, ignorar, overrides) sobre recomendaciones
 * identificadas por recommendation_key. No conoce catálogo ni reglas de visibilidad.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

if (!defined('ABSPATH')) {
    exit;
}

final class LearningRecommendationStateRepository {

    private const KEY_MAX_LENGTH = 100;

    /**
     * Columnas permitidas en upsert (whitelist).
     *
     * @var list<string>
     */
    private const UPSERT_COLUMNS = [
        'is_completed',
        'is_ignored',
        'list_override',
        'last_suggested_at',
        'completed_at',
        'ignored_at',
    ];

    /**
     * @return string
     */
    private static function table_name() {
        global $wpdb;

        return $wpdb->prefix . 'aa_learning_recommendation_state';
    }

    /**
     * Normaliza recommendation_key para almacenamiento seguro.
     *
     * @param string $recommendation_key
     * @return string|null Clave válida o null si vacía/inválida.
     */
    private static function normalize_key($recommendation_key) {
        $key = is_string($recommendation_key) ? trim($recommendation_key) : '';

        if ($key === '') {
            return null;
        }

        $key = sanitize_key($key);

        if ($key === '') {
            return null;
        }

        if (strlen($key) > self::KEY_MAX_LENGTH) {
            $key = substr($key, 0, self::KEY_MAX_LENGTH);
        }

        return $key;
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
            'id' => (int) $row->id,
            'recommendation_key' => (string) $row->recommendation_key,
            'is_completed' => (int) $row->is_completed,
            'is_ignored' => (int) $row->is_ignored,
            'list_override' => $row->list_override === null ? null : (int) $row->list_override,
            'last_suggested_at' => $row->last_suggested_at,
            'completed_at' => $row->completed_at,
            'ignored_at' => $row->ignored_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Todos los estados indexados por recommendation_key.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function get_all() {
        global $wpdb;

        $table = self::table_name();
        $rows = $wpdb->get_results("SELECT * FROM {$table}");

        if ($wpdb->last_error) {
            error_log('[LearningRecommendationStateRepository] get_all: ' . $wpdb->last_error);
            return [];
        }

        $indexed = [];

        foreach ($rows as $row) {
            $mapped = self::map_row($row);
            if ($mapped !== null) {
                $indexed[$mapped['recommendation_key']] = $mapped;
            }
        }

        return $indexed;
    }

    /**
     * @param string $recommendation_key
     * @return array<string,mixed>|null
     */
    public static function find_by_key($recommendation_key) {
        $key = self::normalize_key($recommendation_key);

        if ($key === null) {
            return null;
        }

        global $wpdb;

        $table = self::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE recommendation_key = %s LIMIT 1",
                $key
            )
        );

        if ($wpdb->last_error) {
            error_log('[LearningRecommendationStateRepository] find_by_key: ' . $wpdb->last_error);
            return null;
        }

        return self::map_row($row);
    }

    /**
     * Crea o actualiza el estado de una recomendación (UNIQUE recommendation_key).
     *
     * @param string               $recommendation_key
     * @param array<string,mixed>  $data Solo claves de UPSERT_COLUMNS.
     * @return array<string,mixed>|null Fila resultante o null en error/clave inválida.
     */
    public static function upsert($recommendation_key, array $data) {
        $key = self::normalize_key($recommendation_key);

        if ($key === null) {
            return null;
        }

        $payload = self::filter_upsert_data($data);
        $now = current_time('mysql');
        $existing = self::find_by_key($key);

        global $wpdb;

        $table = self::table_name();

        if ($existing === null) {
            $insert = array_merge(
                [
                    'recommendation_key' => $key,
                    'is_completed' => 0,
                    'is_ignored' => 0,
                    'list_override' => null,
                    'last_suggested_at' => null,
                    'completed_at' => null,
                    'ignored_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $payload
            );

            $formats = self::build_formats($insert);
            $result = $wpdb->insert($table, $insert, $formats);

            if ($result === false) {
                error_log('[LearningRecommendationStateRepository] upsert insert: ' . $wpdb->last_error);
                return null;
            }

            return self::find_by_key($key);
        }

        $update = array_merge($payload, ['updated_at' => $now]);
        $formats = self::build_formats($update);

        $result = $wpdb->update(
            $table,
            $update,
            ['recommendation_key' => $key],
            $formats,
            ['%s']
        );

        if ($result === false) {
            error_log('[LearningRecommendationStateRepository] upsert update: ' . $wpdb->last_error);
            return null;
        }

        return self::find_by_key($key);
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
        if (in_array($column, ['is_completed', 'is_ignored'], true)) {
            return !empty($value) ? 1 : 0;
        }

        if ($column === 'list_override') {
            if ($value === null || $value === '') {
                return null;
            }

            return (int) $value;
        }

        if (in_array($column, ['last_suggested_at', 'completed_at', 'ignored_at'], true)) {
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
            if (in_array($column, ['is_completed', 'is_ignored'], true)) {
                $formats[] = '%d';
                continue;
            }

            if ($column === 'list_override') {
                $formats[] = $row[$column] === null ? '%s' : '%d';
                continue;
            }

            $formats[] = '%s';
        }

        return $formats;
    }
}
