<?php
/**
 * Expediente Registros by Expediente AJAX — create + list por expediente_id.
 *
 * Transporte HTTP autenticado. Create → CreateExpedienteRegistroUseCase.
 * List → ListExpedienteRegistrosWithPublicAdjuntosUseCase (DTO público de
 * adjuntos; sin caller UI todavía). No amplía el contrato legacy por client_id.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CreateExpedienteRegistroUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/expediente/CreateExpedienteRegistroUseCase.php';
}
if (!class_exists('ListExpedienteRegistrosWithPublicAdjuntosUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/expediente/ListExpedienteRegistrosWithPublicAdjuntosUseCase.php';
}
if (!class_exists('ExpedienteRegistrosAjax')) {
    require_once dirname(__DIR__, 2) . '/http/ajax/ExpedienteRegistrosAjax.php';
}

final class ExpedienteRegistrosByExpedienteAjax {

    public const ACTION_CREATE = 'aa_create_expediente_registro_for_expediente';
    public const ACTION_LIST = 'aa_list_expediente_registros_for_expediente';
    public const NONCE_ACTION = 'aa_expediente_registros_by_expediente_nonce';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_CREATE, [__CLASS__, 'handle_create']);
        add_action('wp_ajax_' . self::ACTION_LIST, [__CLASS__, 'handle_list']);
    }

    public static function handle_create(): void {
        if (!self::authorize()) {
            return;
        }

        $result = (new CreateExpedienteRegistroUseCase())->execute([
            'expediente_id' => self::read_expediente_id(),
            'title' => self::read_sanitized_text('title'),
            'body' => self::read_sanitized_textarea('body'),
        ]);

        self::respond_create($result);
    }

    /**
     * Lectura AJAX paginada con metadatos públicos de adjuntos (B2b).
     * Sin caller visual todavía.
     */
    public static function handle_list(): void {
        if (!self::authorize()) {
            return;
        }

        $result = (new ListExpedienteRegistrosWithPublicAdjuntosUseCase())->execute([
            'expediente_id' => self::read_expediente_id(),
            'page' => self::read_page(),
        ]);

        if (empty($result['success'])) {
            $error = $result['error'] ?? [];
            $code = (string) ($error['code'] ?? 'unknown_error');
            wp_send_json_error([
                'message' => (string) ($error['message'] ?? 'No se pudo completar la acción.'),
                'code' => $code,
            ], self::http_status_for_code($code));
            return;
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        wp_send_json_success($data, 200);
    }

    private static function authorize(): bool {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
            return false;
        }

        if (!check_ajax_referer(self::NONCE_ACTION, '_wpnonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido.', 'code' => 'bad_nonce'], 403);
            return false;
        }

        return ExpedienteRegistrosAjax::require_expediente_shell_access();
    }

    /**
     * @return mixed int|string|null (sin absint; no-escalares → null)
     */
    private static function read_expediente_id() {
        if (!isset($_POST['expediente_id'])) {
            return null;
        }

        $raw = wp_unslash($_POST['expediente_id']);

        if (is_int($raw) || is_string($raw)) {
            return $raw;
        }

        return null;
    }

    /**
     * Página para el UC de listado (sin normalizador propio).
     * No-escalares → 1 (mismo efecto que el UC textual ante tipos inválidos).
     *
     * @return int|string
     */
    private static function read_page() {
        if (!isset($_POST['page'])) {
            return 1;
        }

        $raw = wp_unslash($_POST['page']);
        if (is_int($raw) || is_string($raw)) {
            return $raw;
        }

        return 1;
    }

    /**
     * @return string|null
     */
    private static function read_sanitized_text(string $key): ?string {
        if (!isset($_POST[$key])) {
            return null;
        }

        $raw = wp_unslash($_POST[$key]);
        if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
            return null;
        }

        return sanitize_text_field((string) $raw);
    }

    /**
     * @return string|null
     */
    private static function read_sanitized_textarea(string $key): ?string {
        if (!isset($_POST[$key])) {
            return null;
        }

        $raw = wp_unslash($_POST[$key]);
        if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
            return null;
        }

        return sanitize_textarea_field((string) $raw);
    }

    /**
     * @param array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}} $result
     */
    private static function respond_create(array $result): void {
        if (!empty($result['success'])) {
            $data = $result['data'] ?? [];
            $record = is_array($data['record'] ?? null) ? $data['record'] : null;
            if ($record === null) {
                wp_send_json_error([
                    'message' => 'Respuesta de creación incompleta.',
                    'code' => 'persistence_failed',
                ], 500);
                return;
            }

            unset($record['client_id'], $record['expediente_id'], $record['blog_id']);

            wp_send_json_success([
                'record' => $record,
            ]);
            return;
        }

        $error = $result['error'] ?? [];
        $code = (string) ($error['code'] ?? 'unknown_error');
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No se pudo completar la acción.'),
            'code' => $code,
        ], self::http_status_for_code($code));
    }

    private static function http_status_for_code(string $code): int {
        switch ($code) {
            case 'not_found':
                return 404;
            case 'lookup_failed':
            case 'persistence_failed':
                return 500;
            case 'invalid_id':
            case 'missing_title':
            case 'title_too_long':
            case 'missing_body':
            case 'body_too_long':
                return 400;
            default:
                return 400;
        }
    }
}
