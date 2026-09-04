<?php
/**
 * Canonical Family Enabled AJAX — activación individual de familias canónicas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalFamilyEnabledAjax {

    public const ACTION = 'aa_update_canonical_family_enabled';
    public const NONCE_ACTION = 'aa_canonical_family_enabled';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION, [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        if (!is_user_logged_in()) {
            self::error('forbidden', 'Debes iniciar sesión.', 403);
        }

        if (!current_user_can('manage_options')) {
            self::error('forbidden', 'Permisos insuficientes.', 403);
        }

        $nonce_raw = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
        if (!is_string($nonce_raw) || !wp_verify_nonce($nonce_raw, self::NONCE_ACTION)) {
            self::error('invalid_nonce', 'Nonce de seguridad inválido o expirado.', 403);
        }

        $family_key_raw = isset($_POST['family_key']) ? wp_unslash($_POST['family_key']) : null;
        if (is_array($family_key_raw) || !is_string($family_key_raw) || $family_key_raw === '') {
            self::error('unknown_family', 'Familia no reconocida.', 400);
        }
        $family_key = sanitize_key($family_key_raw);

        $enabled_raw = isset($_POST['enabled']) ? wp_unslash($_POST['enabled']) : null;
        if (is_array($enabled_raw) || is_object($enabled_raw) || is_bool($enabled_raw) || is_float($enabled_raw)) {
            self::error('invalid_enabled_value', 'El valor enabled debe ser 0 o 1.', 400);
        }
        if (!is_string($enabled_raw) && !is_int($enabled_raw)) {
            self::error('invalid_enabled_value', 'El valor enabled debe ser 0 o 1.', 400);
        }
        $enabled_str = (string) $enabled_raw;
        if ($enabled_str !== '0' && $enabled_str !== '1') {
            self::error('invalid_enabled_value', 'El valor enabled debe ser 0 o 1.', 400);
        }
        $enabled = $enabled_str === '1';

        try {
            $registry = AA_Canonical_Core_Bootstrap::instance();
        } catch (\Throwable $e) {
            self::error('persistence_failed', 'El núcleo canónico no está disponible.', 500);
        }

        try {
            $command = new SetCanonicalFamilyEnabledCommand($family_key, $enabled);
        } catch (\InvalidArgumentException $e) {
            self::error('unknown_family', 'Familia no reconocida.', 400);
        }

        $port = new AA_Canonical_Family_Enablement_Store();
        $use_case = new SetCanonicalFamilyEnabledUseCase($port, $registry);

        try {
            $result = $use_case->execute($command);
        } catch (CanonicalFamilyUnknown $e) {
            self::error('unknown_family', 'Familia no reconocida.', 400);
        } catch (CanonicalFamilyNotProvisioned $e) {
            self::error('family_not_provisioned', 'Esta familia aún no está provisionada.', 409);
        } catch (CanonicalFamilyEnablementSchemaNotReady $e) {
            self::error('schema_not_ready', 'El esquema canónico no está listo.', 503);
        } catch (CanonicalFamilyEnablementPersistenceFailed $e) {
            self::error('persistence_failed', 'No se pudo guardar el estado.', 500);
        } catch (\Throwable $e) {
            self::error('persistence_failed', 'No se pudo guardar el estado.', 500);
        }

        $nav = [];
        try {
            $snapshot = (new ReadCanonicalFamilyEnablementUseCase($port))->execute($registry);
            $nav = AA_Canonical_Family_Enablement_Nav::build($registry, $snapshot);
        } catch (\Throwable $e) {
            // Mutación ya persistida: devolver nav vacío en lugar de falsear el éxito.
            $nav = [];
        }

        wp_send_json_success([
            'family_key' => $result->family_key(),
            'is_enabled' => $result->is_enabled(),
            'changed' => $result->changed(),
            'nav' => $nav,
        ]);
    }

    /**
     * @return never
     */
    private static function error(string $code, string $message, int $status): void {
        wp_send_json_error([
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
