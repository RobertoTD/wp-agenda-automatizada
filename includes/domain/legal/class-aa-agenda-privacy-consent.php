<?php
/**
 * Agenda legal-gate privacy checkbox copy (must stay in sync with backend
 * services/legal/privacyConsentConstants.js AGENDA_PRIVACY_CONSENT_TEXT).
 */

defined('ABSPATH') or die('No direct access');

final class AA_Agenda_Privacy_Consent {

    public const TEXT = 'Manifiesto que he leído el Aviso de Privacidad Integral y consiento el tratamiento de mis datos personales para administrar mi cuenta y agenda, autenticar mi acceso, prestar y proteger el servicio y cumplir las demás finalidades necesarias descritas en el Aviso.';

    /**
     * Whether version matches YYYY-MM-DD.N (positive N, no leading zeros, real calendar date).
     * Same contract as AA_Agenda_Terms_Consent::version_is_valid.
     */
    public static function version_is_valid(string $version): bool {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})\.([1-9]\d*)$/', $version, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
