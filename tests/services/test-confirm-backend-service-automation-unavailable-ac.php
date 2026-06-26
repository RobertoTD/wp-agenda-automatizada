<?php
/**
 * AC — benefit_notices sintetizados cuando falta aa_client_secret (preflight local).
 *
 * Ejecutar:
 *   php tests/services/test-confirm-backend-service-automation-unavailable-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        /** @var string */
        private $code;

        /** @var string */
        private $message;

        public function __construct($code = '', $message = '') {
            $this->code    = (string) $code;
            $this->message = (string) $message;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/includes/services/confirm-backend-service.php';

$passed = 0;
$total  = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

$no_secret = new WP_Error(
    'no_secret',
    'Client secret no configurado. No se puede autenticar con el backend.'
);
$network_error = new WP_Error(
    'http_request_failed',
    'cURL error 7: Failed to connect to localhost port 3000'
);

ac(
    'no_secret es error de configuración local',
    aa_confirm_backend_is_local_config_wp_error($no_secret) === true
);
ac(
    'http_request_failed no es error de configuración local',
    aa_confirm_backend_is_local_config_wp_error($network_error) === false
);
ac(
    'valor no WP_Error no es configuración local',
    aa_confirm_backend_is_local_config_wp_error(null) === false
);

$local_result = aa_confirm_backend_build_local_config_failure_result($no_secret);

ac('local config mantiene success true', ($local_result['success'] ?? false) === true);
ac('local config mantiene local_confirmed', ($local_result['local_confirmed'] ?? false) === true);
ac('local config calendar_sync false', ($local_result['calendar_sync'] ?? true) === false);
ac('local config calendar_skipped true', ($local_result['calendar_skipped'] ?? false) === true);
ac(
    'local config incluye benefit_notices',
    isset($local_result['benefit_notices']) && is_array($local_result['benefit_notices']) && count($local_result['benefit_notices']) === 2
);

$notices = $local_result['benefit_notices'] ?? [];
ac(
    'calendar notice usa google_calendar_no_installation_id',
    ($notices[0]['code'] ?? '') === 'google_calendar_no_installation_id'
        && ($notices[0]['resource'] ?? '') === 'google_calendar_sync'
);
ac(
    'email notice usa no_installation_id',
    ($notices[1]['code'] ?? '') === 'no_installation_id'
        && ($notices[1]['resource'] ?? '') === 'email'
);
ac(
    'email skipped en payload',
    ($local_result['email']['skipped'] ?? false) === true
        && ($local_result['email']['code'] ?? '') === 'no_installation_id'
);

$ajax_payload = aa_build_confirm_cita_ajax_success_payload($local_result);

ac(
    'ajax payload propaga benefit_notices',
    isset($ajax_payload['benefit_notices']) && count($ajax_payload['benefit_notices']) === 2
);
ac(
    'ajax payload propaga calendar_sync false',
    ($ajax_payload['calendar_sync'] ?? true) === false
);
ac(
    'ajax payload propaga calendar_skipped',
    ($ajax_payload['calendar_skipped'] ?? false) === true
);

$network_result = [
    'success' => true,
    'message' => 'Cita confirmada en WordPress, pero no se pudo notificar al backend: cURL error',
    'local_confirmed' => true,
    'calendar_sync' => false,
];
$network_ajax = aa_build_confirm_cita_ajax_success_payload($network_result);

ac(
    'error de red sin benefit_notices en ajax payload',
    !isset($network_ajax['benefit_notices'])
);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
