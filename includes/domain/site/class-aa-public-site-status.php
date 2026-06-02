<?php
/**
 * Resuelve el estado local del sitio web público DEOIA.
 *
 * Fuente de verdad: option `deoia_public_site_status`.
 * Valores válidos: active | maintenance. Default seguro: active.
 *
 * @package WPAgendaAutomatizada\Domain\Site
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AA_Public_Site_Status')) {
    final class AA_Public_Site_Status {

        public const OPTION = 'deoia_public_site_status';

        public const STATUS_ACTIVE = 'active';

        public const STATUS_MAINTENANCE = 'maintenance';

        /**
         * @return string Uno de STATUS_ACTIVE o STATUS_MAINTENANCE.
         */
        public static function current(): string {
            $raw = get_option(self::OPTION, self::STATUS_ACTIVE);

            return self::normalize($raw);
        }

        public static function is_maintenance(): bool {
            return self::current() === self::STATUS_MAINTENANCE;
        }

        /**
         * Normaliza un valor crudo a un estado permitido.
         *
         * @param mixed $value
         */
        public static function normalize($value): string {
            $normalized = is_scalar($value) ? trim((string) $value) : '';

            if ($normalized === self::STATUS_MAINTENANCE) {
                return self::STATUS_MAINTENANCE;
            }

            return self::STATUS_ACTIVE;
        }
    }
}
