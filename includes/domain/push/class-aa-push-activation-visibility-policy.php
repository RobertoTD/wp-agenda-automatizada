<?php
/**
 * Push Activation Visibility Policy — proyección de la tarea global enable_push.
 *
 * Dominio puro: sin WordPress ni SQL.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Push_Activation_Visibility_Policy {

    public const TASK_ORIGIN_KEY = 'enable_push';

    public static function is_push_activation_task(string $origin_key): bool {
        return trim($origin_key) === self::TASK_ORIGIN_KEY;
    }

    public static function is_legacy_push_activation_task(string $origin_key): bool {
        return strpos(trim($origin_key), self::TASK_ORIGIN_KEY . ':') === 0;
    }

    public static function should_hide_for_context(
        string $origin_key,
        bool $app_subscription_active,
        bool $push_ready
    ): bool {
        if (self::is_legacy_push_activation_task($origin_key)) {
            return true;
        }

        if (!self::is_push_activation_task($origin_key)) {
            return false;
        }

        return !$app_subscription_active || $push_ready;
    }
}
