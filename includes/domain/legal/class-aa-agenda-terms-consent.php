<?php
/**
 * Agenda legal-gate terms checkbox copy (must stay in sync with backend
 * services/legal/termsConsentConstants.js).
 */

defined('ABSPATH') or die('No direct access');

final class AA_Agenda_Terms_Consent {

    public const TEXT = 'He leído y acepto los Términos y Condiciones de Uso de DEOIA Citas, incluido el Anexo A de Encargo de Tratamiento; declaro que actúo por cuenta propia o con facultades suficientes para obligar al negocio; y confirmo haber leído el Aviso de privacidad de DEOIA aplicable.';

    public const HUMAN_URL = 'https://deoia.com/terminos/';

    /**
     * Whether version matches YYYY-MM-DD.N (positive N, no leading zeros, real calendar date).
     */
    public static function version_is_valid(string $version): bool {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})\.([1-9]\d*)$/', $version, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
