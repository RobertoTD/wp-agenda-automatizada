<?php
/**
 * Initial Setup Seed Owner Email Resolver — correo del dueño para el cliente de prueba.
 *
 * Provisionado: deoia_owner_email. Standalone: admin_email. Fallback: ''.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__) . '/tenant/class-aa-installation-provisioning-detector.php';

final class AA_Initial_Setup_Seed_Owner_Email_Resolver {

    private const PROVISIONED_OWNER_EMAIL_OPTION = 'deoia_owner_email';

    private const ADMIN_EMAIL_OPTION = 'admin_email';

    public static function resolve(): string {
        if (AA_Installation_Provisioning_Detector::is_provisioned()) {
            $provisioned_email = sanitize_email(
                (string) get_option(self::PROVISIONED_OWNER_EMAIL_OPTION, '')
            );

            if ($provisioned_email !== '') {
                return $provisioned_email;
            }
        }

        $admin_email = sanitize_email((string) get_option(self::ADMIN_EMAIL_OPTION, ''));

        return $admin_email !== '' ? $admin_email : '';
    }
}
