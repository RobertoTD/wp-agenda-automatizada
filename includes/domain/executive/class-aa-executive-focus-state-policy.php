<?php
/**
 * Executive Focus State Policy — foco manual y streak de dismiss fuera de sprint (MC5).
 *
 * Dominio puro: sin WordPress ni SQL.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Executive_Focus_State_Policy {

    public const DURATION_SECONDS = 3600;

    public const STATE_VERSION = 1;

    public const MAX_DISMISS_STREAK = 2;

    public const DISMISS_STREAK_TRIGGER = 3;

    /**
     * @return array<string,mixed>
     */
    public static function empty_state(): array {
        return [
            'version' => self::STATE_VERSION,
            'manual_focus_list_id' => null,
            'previous_focus_list_id' => null,
            'dismiss_streak_without_sprint' => 0,
            'manual_focus_expires_at' => null,
        ];
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

        $manual_focus_list_id = (int) ($raw['manual_focus_list_id'] ?? 0);
        $previous_focus_list_id = (int) ($raw['previous_focus_list_id'] ?? 0);
        $dismiss_streak = (int) ($raw['dismiss_streak_without_sprint'] ?? 0);
        $manual_focus_expires_at = isset($raw['manual_focus_expires_at'])
            ? (int) $raw['manual_focus_expires_at']
            : null;

        if ($dismiss_streak < 0) {
            $dismiss_streak = 0;
        }

        if ($dismiss_streak > self::MAX_DISMISS_STREAK) {
            $dismiss_streak = self::MAX_DISMISS_STREAK;
        }

        if ($manual_focus_expires_at !== null && $manual_focus_expires_at < 0) {
            $manual_focus_expires_at = null;
        }

        return [
            'version' => self::STATE_VERSION,
            'manual_focus_list_id' => $manual_focus_list_id > 0 ? $manual_focus_list_id : null,
            'previous_focus_list_id' => $previous_focus_list_id > 0 ? $previous_focus_list_id : null,
            'dismiss_streak_without_sprint' => $dismiss_streak,
            'manual_focus_expires_at' => $manual_focus_expires_at > 0 ? $manual_focus_expires_at : null,
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    public static function is_manual_focus_active(array $state, int $now_ts): bool {
        $sanitized = self::sanitize($state);
        $manual_focus_list_id = (int) ($sanitized['manual_focus_list_id'] ?? 0);

        if ($manual_focus_list_id < 1) {
            return false;
        }

        $expires_at = (int) ($sanitized['manual_focus_expires_at'] ?? 0);

        if ($expires_at > 0 && $now_ts >= $expires_at) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $state
     */
    public static function get_manual_focus_list_id(array $state, int $now_ts): ?int {
        if (!self::is_manual_focus_active($state, $now_ts)) {
            return null;
        }

        $sanitized = self::sanitize($state);

        return (int) ($sanitized['manual_focus_list_id'] ?? 0) ?: null;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function set_manual_focus(
        array $state,
        int $manual_focus_list_id,
        ?int $previous_focus_list_id,
        int $now_ts
    ): array {
        if ($manual_focus_list_id < 1) {
            return self::empty_state();
        }

        $sanitized = self::sanitize($state);
        $sanitized['manual_focus_list_id'] = $manual_focus_list_id;
        $sanitized['previous_focus_list_id'] = $previous_focus_list_id !== null && $previous_focus_list_id > 0
            ? $previous_focus_list_id
            : null;
        $sanitized['manual_focus_expires_at'] = $now_ts + self::DURATION_SECONDS;

        return $sanitized;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function clear_manual_focus(array $state): array {
        $sanitized = self::sanitize($state);
        $sanitized['manual_focus_list_id'] = null;
        $sanitized['manual_focus_expires_at'] = null;

        return $sanitized;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function set_previous_focus_list_id(array $state, ?int $previous_focus_list_id): array {
        $sanitized = self::sanitize($state);
        $sanitized['previous_focus_list_id'] = $previous_focus_list_id !== null && $previous_focus_list_id > 0
            ? $previous_focus_list_id
            : null;

        return $sanitized;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function reset_dismiss_streak(array $state): array {
        $sanitized = self::sanitize($state);
        $sanitized['dismiss_streak_without_sprint'] = 0;

        return $sanitized;
    }

    /**
     * @param array<string,mixed> $state
     * @return array{state:array<string,mixed>,triggered:bool,new_streak:int}
     */
    public static function increment_dismiss_streak(array $state): array {
        $sanitized = self::sanitize($state);
        $new_streak = (int) ($sanitized['dismiss_streak_without_sprint'] ?? 0) + 1;

        if ($new_streak >= self::DISMISS_STREAK_TRIGGER) {
            $sanitized['dismiss_streak_without_sprint'] = 0;

            return [
                'state' => $sanitized,
                'triggered' => true,
                'new_streak' => 0,
            ];
        }

        $sanitized['dismiss_streak_without_sprint'] = $new_streak;

        return [
            'state' => $sanitized,
            'triggered' => false,
            'new_streak' => $new_streak,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function clear_expired_manual_focus(array $state, int $now_ts): array {
        if (!self::is_manual_focus_active($state, $now_ts)) {
            return self::clear_manual_focus($state);
        }

        return self::sanitize($state);
    }
}
