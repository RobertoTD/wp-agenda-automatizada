<?php
/**
 * Detecta si el sitio actual fue provisionado por DEOIA Platform MU.
 *
 * Usa solo metadata local fuerte; no deriva estado desde URL, dominio ni nombres.
 *
 * @package WPAgendaAutomatizada\Domain\Tenant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AA_Installation_Provisioning_Detector')) {
    final class AA_Installation_Provisioning_Detector {

        private const PROVISIONED_AT_OPTION = 'deoia_platform_provisioned_at';

        private const SLUG_OPTION = 'deoia_platform_slug';

        private const SUBSCRIPTION_REQUEST_OPTION = 'deoia_subscription_request_id';

        /**
         * True solo cuando existen señales fuertes de provisioning DEOIA.
         */
        public static function is_provisioned(): bool {
            if (self::has_provisioned_at_signal()) {
                return true;
            }

            return self::has_slug_and_request_id_signals();
        }

        private static function has_provisioned_at_signal(): bool {
            return trim((string) get_option(self::PROVISIONED_AT_OPTION, '')) !== '';
        }

        private static function has_slug_and_request_id_signals(): bool {
            $slug = trim((string) get_option(self::SLUG_OPTION, ''));
            if ($slug === '') {
                return false;
            }

            $request_id = trim((string) get_option(self::SUBSCRIPTION_REQUEST_OPTION, ''));

            return $request_id !== '';
        }
    }
}
