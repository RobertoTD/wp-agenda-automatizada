<?php
/**
 * Resuelve el slug de instalación para copy visible en admin (p. ej. header).
 *
 * Solo devuelve valor cuando el subsite fue provisionado por DEOIA Platform MU
 * y existe un slug explícito persistido. No deriva slug desde URL/path ni usa
 * nombres de negocio.
 *
 * @package WPAgendaAutomatizada\Domain\Tenant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AA_Installation_Display_Slug')) {
    final class AA_Installation_Display_Slug {

        private const SLUG_OPTION = 'deoia_platform_slug';

        private const PROVISIONED_AT_OPTION = 'deoia_platform_provisioned_at';

        private const SUBSCRIPTION_REQUEST_OPTION = 'deoia_subscription_request_id';

        /**
         * @return string|null Slug de instalación para UI, o null si no aplica.
         */
        public static function resolve(): ?string {
            if (!self::has_provisioning_signal()) {
                return null;
            }

            $slug = trim((string) get_option(self::SLUG_OPTION, ''));
            if ($slug === '') {
                return null;
            }

            return $slug;
        }

        private static function has_provisioning_signal(): bool {
            $provisioned_at = trim((string) get_option(self::PROVISIONED_AT_OPTION, ''));
            if ($provisioned_at !== '') {
                return true;
            }

            $request_id = trim((string) get_option(self::SUBSCRIPTION_REQUEST_OPTION, ''));

            return $request_id !== '';
        }
    }
}
