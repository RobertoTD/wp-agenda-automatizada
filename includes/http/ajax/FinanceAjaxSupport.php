<?php
/**
 * Finance AJAX Support — Soporte compartido para el transporte HTTP/AJAX de Finanzas.
 *
 * Centraliza exclusivamente:
 * - Fuente única del nonce de Finanzas (aa_finance_nonce).
 * - Autorización mediante AA_Canonical_Access_Policy.
 * - Verificación del nonce de seguridad.
 * - Resolución segura del registry canónico.
 * - Mapeo unificado de códigos Application a códigos HTTP.
 * - Emisión uniforme de respuestas JSON de éxito y error.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Access_Policy')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-canonical-access-policy.php';
}
if (!class_exists('AA_Canonical_Core_Bootstrap')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
}

final class FinanceAjaxSupport {

    public const NONCE_ACTION = 'aa_finance_nonce';

    /**
     * Verifica la autorización de acceso y la validez del nonce para la familia Finanzas.
     * En caso de fallo, emite directamente la respuesta JSON de error y retorna false.
     *
     * @return bool True si está autorizado y el nonce es válido; false si se rechazó la petición.
     */
    public static function authorize(): bool {
        $access = AA_Canonical_Access_Policy::check_family_access('finance');
        if (!$access['authorized']) {
            wp_send_json_error([
                'code'    => $access['code'],
                'message' => $access['message'],
            ], $access['status']);
            return false;
        }

        if (!check_ajax_referer(self::NONCE_ACTION, '_wpnonce', false)) {
            wp_send_json_error([
                'code'    => 'bad_nonce',
                'message' => 'Nonce de seguridad inválido o expirado.',
            ], 403);
            return false;
        }

        return true;
    }

    /**
     * Obtiene de forma segura el registro canónico mediante el bootstrap.
     * Si el núcleo no está disponible (captura exclusiva de \LogicException), emite error HTTP 500 y retorna null.
     *
     * @return AA_Canonical_Registry|null Registro canónico o null si se rechazó la petición.
     */
    public static function resolve_registry(): ?AA_Canonical_Registry {
        try {
            return AA_Canonical_Core_Bootstrap::instance();
        } catch (\LogicException $e) {
            wp_send_json_error([
                'code'    => 'canonical_unavailable',
                'message' => 'El núcleo canónico no está disponible.',
            ], 500);
            return null;
        }
    }

    /**
     * Emite la respuesta JSON uniforme a partir del envelope estándar de Application.
     * En caso de éxito emite HTTP 200 con $result['data'].
     * En caso de error emite el código HTTP correspondiente con ['code' => ..., 'message' => ...].
     *
     * @param array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}} $result
     * @return void Termina el flujo mediante wp_send_json_success() o wp_send_json_error().
     */
    public static function respond(array $result): void {
        if (!empty($result['success'])) {
            wp_send_json_success($result['data'] ?? [], 200);
            return;
        }

        $error = $result['error'] ?? [];
        $code  = (string) ($error['code'] ?? 'unknown_error');
        $msg   = (string) ($error['message'] ?? 'No se pudo completar la acción.');

        wp_send_json_error([
            'code'    => $code,
            'message' => $msg,
        ], self::http_status_for_code($code));
    }

    /**
     * Traduce de forma unificada un código de error estructurado de Application a su código de estado HTTP.
     * Si el código es desconocido (inconsistencia de contrato), responde con HTTP 500 de forma fail-closed.
     *
     * @param string $code
     * @return int Código HTTP (400, 404 o 500).
     */
    public static function http_status_for_code(string $code): int {
        switch ($code) {
            // Recursos o variantes no encontrados (404)
            case 'not_found':
            case 'container_not_found':
            case 'record_not_found':
            case 'unknown_variant':
                return 404;

            // Fallos de persistencia o disponibilidad de registry (500)
            case 'persistence_failed':
            case 'canonical_unavailable':
                return 500;

            // Errores de validación de entrada (400)
            case 'missing_title':
            case 'invalid_title':
            case 'title_too_long':
            case 'invalid_details':
            case 'details_too_long':
            case 'invalid_id':
            case 'invalid_container_id':
            case 'invalid_record_id':
            case 'invalid_amount':
            case 'amount_too_many_decimals':
            case 'amount_out_of_range':
            case 'invalid_variant_key':
                return 400;

            // Inconsistencia interna o código no reconocido (fail-closed a 500)
            default:
                return 500;
        }
    }
}
