<?php
/**
 * Expedientes AJAX — listado, alta, update de título y delete de contenedor.
 *
 * Transporte HTTP: autentica, normaliza entrada, delega a Use Cases y
 * serializa. Sin reglas de título/descripción/categoría.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ListExpedientesUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/expediente/ListExpedientesUseCase.php';
}
if (!class_exists('CreateExpedienteUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/expediente/CreateExpedienteUseCase.php';
}
if (!class_exists('UpdateExpedienteUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/expediente/UpdateExpedienteUseCase.php';
}
if (!class_exists('DeleteExpedienteUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/expediente/DeleteExpedienteUseCase.php';
}
if (!class_exists('ExpedienteRegistrosAjax')) {
    require_once dirname(__DIR__, 2) . '/http/ajax/ExpedienteRegistrosAjax.php';
}

final class ExpedientesAjax {

    public const ACTION_LIST = 'aa_list_expedientes';
    public const ACTION_CREATE = 'aa_create_expediente';
    public const ACTION_UPDATE = 'aa_update_expediente';
    public const ACTION_DELETE = 'aa_delete_expediente';
    public const NONCE_ACTION = 'aa_expedientes_nonce';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_LIST, [__CLASS__, 'handle_list']);
        add_action('wp_ajax_' . self::ACTION_CREATE, [__CLASS__, 'handle_create']);
        add_action('wp_ajax_' . self::ACTION_UPDATE, [__CLASS__, 'handle_update']);
        add_action('wp_ajax_' . self::ACTION_DELETE, [__CLASS__, 'handle_delete']);
    }

    public static function handle_list(): void {
        if (!self::authorize()) {
            return;
        }

        $result = (new ListExpedientesUseCase())->execute([
            'query' => self::post_string('query') ?? '',
            'page' => self::post_scalar('page'),
        ]);

        self::respond_use_case($result);
    }

    public static function handle_create(): void {
        if (!self::authorize()) {
            return;
        }

        $input = [
            'title' => self::post_string('title'),
        ];

        if (array_key_exists('description', $_POST)) {
            $input['description'] = self::post_textarea('description');
        }

        if (array_key_exists('category_slug', $_POST)) {
            $input['category_slug'] = self::post_string('category_slug');
        }

        $result = (new CreateExpedienteUseCase())->execute($input);

        self::respond_use_case($result);
    }

    /**
     * Update canónico del título por expediente_id.
     * Nonce soft (die=false). Sin client_id/category_id/description.
     */
    public static function handle_update(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.', 'code' => 'forbidden'], 403);
            return;
        }

        $nonce_ok = check_ajax_referer(self::NONCE_ACTION, '_wpnonce', false);
        if ($nonce_ok === false) {
            wp_send_json_error(['message' => 'Sesión no válida.', 'code' => 'invalid_nonce'], 403);
            return;
        }

        if (!ExpedienteRegistrosAjax::require_expediente_shell_access()) {
            return;
        }

        $result = (new UpdateExpedienteUseCase())->execute([
            'expediente_id' => self::post_scalar('expediente_id'),
            'title' => self::post_string('title'),
        ]);

        if (!empty($result['success'])) {
            $data = is_array($result['data'] ?? null) ? $result['data'] : [];
            $expediente = is_array($data['expediente'] ?? null) ? $data['expediente'] : [];
            $id = (int) ($expediente['id'] ?? 0);
            $title = $expediente['title'] ?? null;
            if ($id < 1 || !is_string($title)) {
                wp_send_json_error([
                    'message' => 'Respuesta de actualización incompleta.',
                    'code' => 'persistence_failed',
                ], 500);
                return;
            }

            wp_send_json_success([
                'expediente' => [
                    'id' => $id,
                    'title' => $title,
                ],
            ]);
            return;
        }

        $error = $result['error'] ?? [];
        $code = (string) ($error['code'] ?? 'unknown_error');
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No se pudo actualizar el expediente.'),
            'code' => $code,
        ], self::http_status_for_update_code($code));
    }

    /**
     * Ciclo B: delete canónico del contenedor por expediente_id.
     * Nonce soft (die=false). Sin client_id.
     */
    public static function handle_delete(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.', 'code' => 'forbidden'], 403);
            return;
        }

        $nonce_ok = check_ajax_referer(self::NONCE_ACTION, '_wpnonce', false);
        if ($nonce_ok === false) {
            wp_send_json_error(['message' => 'Sesión no válida.', 'code' => 'invalid_nonce'], 403);
            return;
        }

        if (!ExpedienteRegistrosAjax::require_expediente_shell_access()) {
            return;
        }

        $result = (new DeleteExpedienteUseCase())->execute([
            'expediente_id' => self::post_scalar('expediente_id'),
        ]);

        if (!empty($result['success'])) {
            $data = is_array($result['data'] ?? null) ? $result['data'] : [];
            $expediente_id = (int) ($data['expediente_id'] ?? 0);
            if (empty($data['deleted']) || $expediente_id < 1) {
                wp_send_json_error([
                    'message' => 'Respuesta de eliminación incompleta.',
                    'code' => 'persistence_failed',
                ], 500);
                return;
            }

            wp_send_json_success([
                'deleted' => true,
                'expediente_id' => $expediente_id,
            ]);
            return;
        }

        $error = $result['error'] ?? [];
        $code = (string) ($error['code'] ?? 'unknown_error');
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No se pudo eliminar el expediente.'),
            'code' => $code,
        ], self::http_status_for_delete_code($code));
    }

    private static function authorize(): bool {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
            return false;
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');

        return ExpedienteRegistrosAjax::require_expediente_shell_access();
    }

    /**
     * @param array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}} $result
     */
    private static function respond_use_case(array $result): void {
        if (!empty($result['success'])) {
            wp_send_json_success($result['data'] ?? []);
        }

        $error = $result['error'] ?? [];
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No se pudo completar la acción.'),
            'code' => (string) ($error['code'] ?? 'unknown_error'),
        ], 400);
    }

    private static function http_status_for_update_code(string $code): int {
        switch ($code) {
            case 'not_found':
                return 404;
            case 'lookup_failed':
            case 'persistence_failed':
                return 500;
            case 'invalid_id':
            case 'missing_title':
            case 'title_too_long':
                return 400;
            default:
                return 400;
        }
    }

    private static function http_status_for_delete_code(string $code): int {
        switch ($code) {
            case 'not_found':
                return 404;
            case 'resource_busy':
            case 'concurrent_change':
            case 'aggregate_inconsistent':
                return 409;
            case 'coordination_failed':
            case 'coordination_lost':
            case 'lookup_failed':
            case 'persistence_failed':
                return 500;
            case 'storage_delete_failed':
            case 'storage_delete_partial':
            case 'delete_failed':
            case 'expediente_attachments_unreachable':
            case 'expediente_attachments_invalid_response':
                return 502;
            case 'invalid_id':
                return 400;
            default:
                return 400;
        }
    }

    /**
     * @return string|null
     */
    private static function post_string(string $key): ?string {
        if (!isset($_POST[$key])) {
            return null;
        }

        return sanitize_text_field(wp_unslash((string) $_POST[$key]));
    }

    /**
     * @return string|null
     */
    private static function post_textarea(string $key): ?string {
        if (!isset($_POST[$key])) {
            return null;
        }

        return sanitize_textarea_field(wp_unslash((string) $_POST[$key]));
    }

    /**
     * @return mixed
     */
    private static function post_scalar(string $key) {
        if (!isset($_POST[$key])) {
            return null;
        }

        return wp_unslash($_POST[$key]);
    }
}
