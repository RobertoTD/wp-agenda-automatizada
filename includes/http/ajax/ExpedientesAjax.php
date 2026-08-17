<?php
/**
 * Expedientes AJAX — listado paginado y alta de expedientes padre.
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
if (!class_exists('ExpedienteRegistrosAjax')) {
    require_once dirname(__DIR__, 2) . '/http/ajax/ExpedienteRegistrosAjax.php';
}

final class ExpedientesAjax {

    public const ACTION_LIST = 'aa_list_expedientes';
    public const ACTION_CREATE = 'aa_create_expediente';
    public const NONCE_ACTION = 'aa_expedientes_nonce';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_LIST, [__CLASS__, 'handle_list']);
        add_action('wp_ajax_' . self::ACTION_CREATE, [__CLASS__, 'handle_create']);
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
