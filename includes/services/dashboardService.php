<?php
/**
 * Dashboard Service
 *
 * Backend endpoints for the Dashboard module.
 * Handles aggregated queries that don't belong to existing controllers.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Services
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// ─── Shared helpers ────────────────────────────────────────────

/**
 * Resolve reservation estados for a given metric type.
 *
 * @param string $metric_type  One of: effective, confirmed, attended, pending, cancelled, no_show
 * @return array|null  Array of estado strings, or null if metric_type is invalid
 */
function aa_dashboard_resolve_estados($metric_type) {
    $map = [
        'effective'  => ['confirmed', 'asistió', 'asistio'],
        'confirmed'  => ['confirmed'],
        'attended'   => ['asistió', 'asistio'],
        'pending'    => ['pending'],
        'cancelled'  => ['cancelled'],
        'no_show'    => ['no asistió', 'no asistio'],
    ];
    return isset($map[$metric_type]) ? $map[$metric_type] : null;
}

/**
 * Count reservations within a date range filtered by estados.
 *
 * @param string $start_date  YYYY-MM-DD
 * @param string $end_date    YYYY-MM-DD
 * @param array  $estados     Array of estado strings to include
 * @return int
 */
function aa_dashboard_count_reservas($start_date, $end_date, $estados) {
    global $wpdb;

    $table = $wpdb->prefix . 'aa_reservas';
    $range_start = $start_date . ' 00:00:00';
    $range_end   = $end_date   . ' 23:59:59';

    $placeholders = implode(',', array_fill(0, count($estados), '%s'));

    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE fecha BETWEEN %s AND %s
           AND estado IN ({$placeholders})",
        array_merge([$range_start, $range_end], $estados)
    );

    return (int) $wpdb->get_var($query);
}

/**
 * Resolve current and previous date ranges for a period preset.
 * All ranges exclude today and use business timezone.
 *
 * @param string $preset  One of: 7d, 30d
 * @return array|null  [ 'current' => [start, end], 'previous' => [start, end] ] or null
 */
function aa_dashboard_resolve_ranges($preset) {
    $tz_string = get_option('aa_timezone', 'America/Mexico_City');
    $today = new DateTime('now', new DateTimeZone($tz_string));
    $today->setTime(0, 0, 0);

    $presets = [
        '7d'  => 7,
        '30d' => 30,
    ];

    if (!isset($presets[$preset])) {
        return null;
    }

    $days = $presets[$preset];

    $current_end = clone $today;
    $current_end->modify('-1 day');

    $current_start = clone $current_end;
    $current_start->modify('-' . ($days - 1) . ' days');

    $previous_end = clone $current_start;
    $previous_end->modify('-1 day');

    $previous_start = clone $previous_end;
    $previous_start->modify('-' . ($days - 1) . ' days');

    return [
        'current'  => [$current_start->format('Y-m-d'), $current_end->format('Y-m-d')],
        'previous' => [$previous_start->format('Y-m-d'), $previous_end->format('Y-m-d')],
    ];
}

/**
 * Shared permission check for dashboard endpoints.
 */
function aa_dashboard_check_permissions() {
    if (!current_user_can('aa_view_panel') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }
}

// ─── Endpoint: Revenue ─────────────────────────────────────────

add_action('wp_ajax_aa_get_dashboard_revenue', 'aa_get_dashboard_revenue');

/**
 * Get revenue summary for a date range.
 *
 * POST params:
 *   start_date (YYYY-MM-DD) — required
 *   end_date   (YYYY-MM-DD) — required
 *
 * Only counts reservations with billable status (confirmed, asistió).
 *
 * @return void JSON { total: float, count: int }
 */
function aa_get_dashboard_revenue() {
    check_ajax_referer('aa_dashboard_revenue');
    aa_dashboard_check_permissions();

    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date   = isset($_POST['end_date'])   ? sanitize_text_field($_POST['end_date'])   : '';

    $date_regex = '/^\d{4}-\d{2}-\d{2}$/';
    if (!preg_match($date_regex, $start_date) || !preg_match($date_regex, $end_date)) {
        wp_send_json_error(['message' => 'Formato de fecha inválido. Use YYYY-MM-DD.']);
    }

    if ($start_date > $end_date) {
        wp_send_json_error(['message' => 'start_date debe ser menor o igual a end_date.']);
    }

    global $wpdb;

    $table_reservas = $wpdb->prefix . 'aa_reservas';
    $table_services = $wpdb->prefix . 'aa_services';

    $range_start = $start_date . ' 00:00:00';
    $range_end   = $end_date   . ' 23:59:59';

    $billable_states = aa_dashboard_resolve_estados('effective');

    $placeholders = implode(',', array_fill(0, count($billable_states), '%s'));

    $query = $wpdb->prepare(
        "SELECT
            COALESCE(SUM(s.price), 0) AS total,
            COUNT(*) AS count
         FROM {$table_reservas} r
         LEFT JOIN {$table_services} s ON s.id = CAST(r.servicio AS UNSIGNED)
         WHERE r.fecha BETWEEN %s AND %s
           AND r.estado IN ({$placeholders})",
        array_merge([$range_start, $range_end], $billable_states)
    );

    $row = $wpdb->get_row($query);

    wp_send_json_success([
        'total' => $row ? floatval($row->total) : 0,
        'count' => $row ? intval($row->count) : 0,
    ]);
}

// ─── Endpoint: Comparison Summary ──────────────────────────────

add_action('wp_ajax_aa_get_dashboard_comparison_summary', 'aa_get_dashboard_comparison_summary');

/**
 * Get comparison summary between two consecutive date ranges.
 *
 * POST params:
 *   metric_type   — effective | confirmed | attended | pending | cancelled | no_show
 *   period_preset — 7d | 30d
 *
 * @return void JSON { current_count, previous_count, pct_change, trend, metric_type, period_preset }
 */
function aa_get_dashboard_comparison_summary() {
    check_ajax_referer('aa_dashboard_comparison');
    aa_dashboard_check_permissions();

    $metric_type   = isset($_POST['metric_type'])   ? sanitize_key($_POST['metric_type'])   : '';
    $period_preset = isset($_POST['period_preset']) ? sanitize_key($_POST['period_preset']) : '';

    $estados = aa_dashboard_resolve_estados($metric_type);
    if ($estados === null) {
        wp_send_json_error(['message' => 'metric_type inválido.']);
    }

    $ranges = aa_dashboard_resolve_ranges($period_preset);
    if ($ranges === null) {
        wp_send_json_error(['message' => 'period_preset inválido.']);
    }

    $current_count  = aa_dashboard_count_reservas($ranges['current'][0], $ranges['current'][1], $estados);
    $previous_count = aa_dashboard_count_reservas($ranges['previous'][0], $ranges['previous'][1], $estados);

    $pct_change = 0;
    $trend = 'neutral';

    if ($previous_count > 0) {
        $pct_change = round((($current_count - $previous_count) / $previous_count) * 100);
    } elseif ($current_count > 0) {
        $pct_change = 100;
    }

    if ($current_count > $previous_count) {
        $trend = 'up';
    } elseif ($current_count < $previous_count) {
        $trend = 'down';
    }

    wp_send_json_success([
        'current_count'  => $current_count,
        'previous_count' => $previous_count,
        'pct_change'     => $pct_change,
        'trend'          => $trend,
        'metric_type'    => $metric_type,
        'period_preset'  => $period_preset,
    ]);
}

// ─── Endpoint: Alerts Summary ──────────────────────────────────

add_action('wp_ajax_aa_get_dashboard_alerts_summary', 'aa_get_dashboard_alerts_summary');

/**
 * Get alerts summary for the dashboard.
 *
 * Returns counts of pending reservations in two buckets:
 * - Today remaining: pending citas from NOW until end of today
 * - Next 15 days: pending citas from tomorrow until +15 days
 *
 * @return void JSON { pending_today_remaining: int, pending_next_15_days: int }
 */
function aa_get_dashboard_alerts_summary() {
    check_ajax_referer('aa_dashboard_alerts');
    aa_dashboard_check_permissions();

    global $wpdb;

    $table = $wpdb->prefix . 'aa_reservas';
    $tz_string = get_option('aa_timezone', 'America/Mexico_City');
    $tz = new DateTimeZone($tz_string);
    $now = new DateTime('now', $tz);

    $now_sql = $now->format('Y-m-d H:i:s');

    $today_end = clone $now;
    $today_end->setTime(23, 59, 59);
    $today_end_sql = $today_end->format('Y-m-d H:i:s');

    $tomorrow_start = clone $now;
    $tomorrow_start->modify('+1 day');
    $tomorrow_start->setTime(0, 0, 0);
    $tomorrow_start_sql = $tomorrow_start->format('Y-m-d H:i:s');

    $future_end = clone $now;
    $future_end->modify('+15 days');
    $future_end->setTime(23, 59, 59);
    $future_end_sql = $future_end->format('Y-m-d H:i:s');

    $pending_today = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE estado = 'pending'
           AND fecha BETWEEN %s AND %s",
        $now_sql, $today_end_sql
    ));

    $pending_next_15 = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE estado = 'pending'
           AND fecha BETWEEN %s AND %s",
        $tomorrow_start_sql, $future_end_sql
    ));

    wp_send_json_success([
        'pending_today_remaining' => $pending_today,
        'pending_next_15_days'    => $pending_next_15,
    ]);
}
