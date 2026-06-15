<?php
/**
 * Executive Sprint Policy — reglas de sprint ejecutivo (MC4).
 *
 * Dominio puro: sin WordPress ni SQL.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__) . '/executable/class-aa-executable-contract.php';

final class AA_Executive_Sprint_Policy {

    public const DURATION_SECONDS = 3600;

    public const STATE_VERSION = 1;

    /**
     * @return array<string,mixed>
     */
    public static function empty_state(): array {
        return [];
    }

    /**
     * @param mixed $raw
     * @return array<string,mixed>
     */
    public static function sanitize($raw): array {
        if (!is_array($raw)) {
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);

                return self::sanitize(is_array($decoded) ? $decoded : []);
            }

            return self::empty_state();
        }

        $version = (int) ($raw['version'] ?? 0);

        if ($version !== self::STATE_VERSION) {
            return self::empty_state();
        }

        $active_focus_list_id = (int) ($raw['active_focus_list_id'] ?? 0);

        if ($active_focus_list_id < 1) {
            return self::empty_state();
        }

        $sprint_started_at = (int) ($raw['sprint_started_at'] ?? 0);
        $last_executive_action_at = (int) ($raw['last_executive_action_at'] ?? 0);
        $sprint_expires_at = (int) ($raw['sprint_expires_at'] ?? 0);

        if ($sprint_started_at < 0 || $last_executive_action_at < 0 || $sprint_expires_at < 0) {
            return self::empty_state();
        }

        if ($sprint_expires_at < $sprint_started_at) {
            return self::empty_state();
        }

        return [
            'version' => self::STATE_VERSION,
            'active_focus_list_id' => $active_focus_list_id,
            'sprint_started_at' => $sprint_started_at,
            'last_executive_action_at' => $last_executive_action_at,
            'sprint_expires_at' => $sprint_expires_at,
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    public static function is_active(array $state, int $now_ts): bool {
        $sanitized = self::sanitize($state);

        if ($sanitized === []) {
            return false;
        }

        return $now_ts < (int) ($sanitized['sprint_expires_at'] ?? 0);
    }

    /**
     * @param array<string,mixed> $state
     */
    public static function is_expired(array $state, int $now_ts): bool {
        $sanitized = self::sanitize($state);

        if ($sanitized === []) {
            return false;
        }

        return $now_ts >= (int) ($sanitized['sprint_expires_at'] ?? 0);
    }

    /**
     * @param array<string,mixed> $state
     */
    public static function get_active_focus_list_id(array $state, int $now_ts): ?int {
        if (!self::is_active($state, $now_ts)) {
            return null;
        }

        $sanitized = self::sanitize($state);

        return (int) ($sanitized['active_focus_list_id'] ?? 0) ?: null;
    }

    /**
     * @param array<string,mixed> $action
     */
    public static function should_renew_for_executive_action(array $action): bool {
        $type = strtolower(trim((string) ($action['type'] ?? '')));
        $key = strtolower(trim((string) ($action['key'] ?? '')));

        if ($type === AA_Executable_Contract::ACTION_INTENT && $key === 'dismiss') {
            return false;
        }

        if ($type === AA_Executable_Contract::ACTION_STATUS && $key === 'complete') {
            return true;
        }

        if ($type === AA_Executable_Contract::ACTION_NAVIGATE) {
            return true;
        }

        if ($type === AA_Executable_Contract::ACTION_HANDLER) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function renew(array $state, int $focus_list_id, int $now_ts): array {
        if ($focus_list_id < 1) {
            return self::empty_state();
        }

        $sanitized = self::sanitize($state);
        $had_active = self::is_active($sanitized, $now_ts);
        $sprint_started_at = $had_active
            ? (int) ($sanitized['sprint_started_at'] ?? $now_ts)
            : $now_ts;

        return [
            'version' => self::STATE_VERSION,
            'active_focus_list_id' => $focus_list_id,
            'sprint_started_at' => $sprint_started_at,
            'last_executive_action_at' => $now_ts,
            'sprint_expires_at' => $now_ts + self::DURATION_SECONDS,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function update_active_focus_without_renew(array $state, int $focus_list_id): array {
        $sanitized = self::sanitize($state);

        if ($sanitized === [] || $focus_list_id < 1) {
            return self::empty_state();
        }

        $sanitized['active_focus_list_id'] = $focus_list_id;

        return $sanitized;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function release(array $state): array {
        return self::empty_state();
    }

    /**
     * Marca sprint vencido para debug (MC5) sin borrar estado.
     *
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function expire_for_debug(array $state, int $now_ts): array {
        $sanitized = self::sanitize($state);

        if ($sanitized === []) {
            return self::empty_state();
        }

        $sanitized['sprint_expires_at'] = max(0, $now_ts - 1);

        return $sanitized;
    }
}
