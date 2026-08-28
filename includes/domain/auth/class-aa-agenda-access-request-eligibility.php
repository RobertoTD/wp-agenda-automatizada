<?php
/**
 * Elegibilidad local para solicitar magic-link de agenda (C3B).
 *
 * Managed provisioned + HMAC listo. No deriva desde URL, dominio, MU ni formulario.
 *
 * @package WPAgendaAutomatizada\Domain\Auth
 */

defined('ABSPATH') or die('No direct access');

final class AA_Agenda_Access_Request_Eligibility {

    /**
     * True solo cuando el blog está provisionado por plataforma y puede firmar HMAC.
     */
    public static function can_request(): bool {
        if (!class_exists('AA_Installation_Provisioning_Detector')
            || !AA_Installation_Provisioning_Detector::is_provisioned()
        ) {
            return false;
        }

        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return false;
        }

        $secret = trim((string) get_option('aa_client_secret', ''));

        return $secret !== '';
    }
}
