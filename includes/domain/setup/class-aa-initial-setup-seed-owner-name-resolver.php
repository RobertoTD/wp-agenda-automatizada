<?php
/**
 * Initial Setup Seed Owner Name Resolver — nombre del dueño para el personal inicial.
 *
 * Provisionado: deoia_owner_name. Cualquier otro caso: Personal de prueba.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__) . '/tenant/class-aa-installation-provisioning-detector.php';
require_once __DIR__ . '/class-aa-initial-setup-seed-definition.php';

final class AA_Initial_Setup_Seed_Owner_Name_Resolver {

    private const PROVISIONED_OWNER_NAME_OPTION = 'deoia_owner_name';

    public static function resolve(): string {
        $fallback = AA_Initial_Setup_Seed_Definition::STAFF_NAME;

        if (!AA_Installation_Provisioning_Detector::is_provisioned()) {
            return $fallback;
        }

        $raw = get_option(self::PROVISIONED_OWNER_NAME_OPTION, '');
        if (!is_string($raw)) {
            return $fallback;
        }

        $normalized = sanitize_text_field(trim($raw));

        return $normalized !== '' ? $normalized : $fallback;
    }
}
