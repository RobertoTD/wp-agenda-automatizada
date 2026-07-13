<?php
/**
 * Push Activation Visibility Policy — proyección de tareas enable_push:*.
 *
 * Dominio puro: sin WordPress, SQL ni entitlement.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Push_Activation_Visibility_Policy {

    private const ORIGIN_KEY_PATTERN = '/^enable_push:[a-f0-9]{32}:[a-f0-9]{16}$/';

    public static function is_valid_enable_push_origin_key(string $origin_key): bool {
        return (bool) preg_match(self::ORIGIN_KEY_PATTERN, trim($origin_key));
    }

    /**
     * True when a valid enable_push task must be hidden because the agenda is unlinked.
     *
     * Callers pass the unlinked fact; this policy only validates origin_key format.
     */
    public static function should_hide_when_agenda_unlinked(string $origin_key): bool {
        return self::is_valid_enable_push_origin_key($origin_key);
    }
}
