<?php
/** Transporte de la acción reversible de `completed`. */
defined('ABSPATH') or die('No direct access');
final class CanonicalSetRecordCompletionAjax {
    public const ACTION = 'aa_set_canonical_record_completion';
    public const NONCE_ACTION = 'aa_set_canonical_record_completion';
    public static function register(): void { add_action('wp_ajax_' . self::ACTION, [__CLASS__, 'handle']); }
    public static function handle(): void {
        if (!is_user_logged_in()) { self::error('unauthorized', 'Debes iniciar sesión.', 401); }
        $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
        if (!is_string($nonce) || !wp_verify_nonce($nonce, self::NONCE_ACTION)) { self::error('invalid_nonce', 'Nonce de seguridad inválido o expirado.', 403); }
        $family = isset($_POST['family_key']) && is_string($_POST['family_key']) ? sanitize_key(wp_unslash($_POST['family_key'])) : '';
        $container = isset($_POST['container_id']) ? (int) $_POST['container_id'] : 0;
        $record = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;
        $completed = isset($_POST['completed']) && (string) $_POST['completed'] === '1';
        if ($family !== 'action' || $container < 1 || $record < 1) { self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400); }
        try {
            $authorized = CanonicalShellWriteAjaxSupport::authorize_identity($family);
            $config = new CanonicalCapabilityConfigRepository();
            $assignment = $config->find_container_capability($container, 'completed');
            if ($assignment === null || empty($assignment['is_active'])) { self::error('capability_inactive', 'Completar no está activo en esta lista.', 409); }
            $rel = new CanonicalRelationalRepository();
            $family_id = $rel->resolve_family_id($authorized['family']->key());
            if ($family_id === null || $rel->find_container($family_id, $container) === null) { self::error('container_not_found', 'La lista no existe.', 404); }
            if ($rel->find_record($container, $record) === null) { self::error('record_not_found', 'El registro no existe.', 404); }
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            (new CanonicalRecordCompletionRepository($wpdb))->set_completed($record, $completed);
            $now = gmdate('Y-m-d H:i:s');
            $records = AA_Canonical_Schema::records_table_name();
            $containers = AA_Canonical_Schema::containers_table_name();
            if ($wpdb->update($records, ['updated_at' => $now], ['id' => $record, 'container_id' => $container], ['%s'], ['%d','%d']) === false || $wpdb->update($containers, ['updated_at' => $now], ['id' => $container, 'family_id' => $family_id], ['%s'], ['%d','%d']) === false) { throw new \RuntimeException('touch_failed'); }
            $wpdb->query('COMMIT');
            wp_send_json_success(['redirect_url' => wp_get_referer() ?: AA_Canonical_Shell_Base_Url_Policy::build_records_url('action', $container)]);
        } catch (\Throwable $e) {
            global $wpdb; if (isset($wpdb)) { $wpdb->query('ROLLBACK'); }
            self::error('persistence_failed', 'No se pudo actualizar el estado.', 500);
        }
    }
    private static function error(string $code, string $message, int $status): void { wp_send_json_error(['code'=>$code,'message'=>$message], $status); }
}
