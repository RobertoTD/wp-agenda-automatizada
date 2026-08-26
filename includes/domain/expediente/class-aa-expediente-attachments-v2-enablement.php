<?php
/**
 * Enablement server-side para writer expediente_v2 (P3).
 *
 * Constante: AA_EXPEDIENTE_ATTACHMENTS_V2_ENABLED
 * Ausente / false → desactivado. Solo true/1 explícito → activo.
 * Nunca leer desde HTTP. Override inyectable solo en tests.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Expediente_Attachments_V2_Enablement {

    /** @var bool|null */
    private static $override_for_tests = null;

    /**
     * @internal Acceptance tests only. null = usar constante.
     */
    public static function set_for_tests(?bool $enabled): void {
        self::$override_for_tests = $enabled;
    }

    public static function is_enabled(): bool {
        if (self::$override_for_tests !== null) {
            return self::$override_for_tests === true;
        }

        if (!defined('AA_EXPEDIENTE_ATTACHMENTS_V2_ENABLED')) {
            return false;
        }

        $value = constant('AA_EXPEDIENTE_ATTACHMENTS_V2_ENABLED');

        return $value === true || $value === 1 || $value === '1';
    }
}
