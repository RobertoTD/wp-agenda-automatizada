<?php
/**
 * Finance Containers AJAX — Transporte HTTP/AJAX para contenedores financieros.
 *
 * Expone las cuatro operaciones canónicas de contenedores:
 * - aa_list_finance_containers   → ListFinanceContainersUseCase
 * - aa_create_finance_container → CreateFinanceContainerUseCase
 * - aa_get_finance_container    → GetFinanceContainerUseCase
 * - aa_delete_finance_container → DeleteFinanceContainerUseCase
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
if (!class_exists('CreateFinanceContainerUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/finance/CreateFinanceContainerUseCase.php';
}
if (!class_exists('GetFinanceContainerUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/finance/GetFinanceContainerUseCase.php';
}
if (!class_exists('ListFinanceContainersUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/finance/ListFinanceContainersUseCase.php';
}
if (!class_exists('DeleteFinanceContainerUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/finance/DeleteFinanceContainerUseCase.php';
}

final class FinanceContainersAjax {

    public const ACTION_LIST   = 'aa_list_finance_containers';
    public const ACTION_CREATE = 'aa_create_finance_container';
    public const ACTION_GET    = 'aa_get_finance_container';
    public const ACTION_DELETE = 'aa_delete_finance_container';
    public const NONCE_ACTION  = 'aa_finance_nonce';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_LIST, [__CLASS__, 'handle_list']);
        add_action('wp_ajax_' . self::ACTION_CREATE, [__CLASS__, 'handle_create']);
        add_action('wp_ajax_' . self::ACTION_GET, [__CLASS__, 'handle_get']);
        add_action('wp_ajax_' . self::ACTION_DELETE, [__CLASS__, 'handle_delete']);
    }

    public static function handle_list(): void {
        if (!self::authorize()) {
            return;
        }

        $registry = self::resolve_registry();
        if ($registry === null) {
            return;
        }

        $input = [];
        if (array_key_exists('variant_key', $_POST)) {
            $input['variant_key'] = wp_unslash($_POST['variant_key']);
        }
        if (array_key_exists('page', $_POST)) {
            $input['page'] = wp_unslash($_POST['page']);
        }

        $result = (new ListFinanceContainersUseCase($registry))->execute($input);
        self::respond($result);
    }

    public static function handle_create(): void {
        if (!self::authorize()) {
            return;
        }

        $registry = self::resolve_registry();
        if ($registry === null) {
            return;
        }

        $input = [];
        if (array_key_exists('title', $_POST)) {
            $input['title'] = wp_unslash($_POST['title']);
        }
        if (array_key_exists('details', $_POST)) {
            $input['details'] = wp_unslash($_POST['details']);
        }
        if (array_key_exists('variant_key', $_POST)) {
            $input['variant_key'] = wp_unslash($_POST['variant_key']);
        }

        $result = (new CreateFinanceContainerUseCase($registry))->execute($input);
        self::respond($result);
    }

    public static function handle_get(): void {
        if (!self::authorize()) {
            return;
        }

        $registry = self::resolve_registry();
        if ($registry === null) {
            return;
        }

        $input = [];
        if (array_key_exists('id', $_POST)) {
            $input['id'] = wp_unslash($_POST['id']);
        }
        if (array_key_exists('variant_key', $_POST)) {
            $input['variant_key'] = wp_unslash($_POST['variant_key']);
        }

        $result = (new GetFinanceContainerUseCase($registry))->execute($input);
        self::respond($result);
    }

    public static function handle_delete(): void {
        if (!self::authorize()) {
            return;
        }

        $registry = self::resolve_registry();
        if ($registry === null) {
            return;
        }

        $input = [];
        if (array_key_exists('id', $_POST)) {
            $input['id'] = wp_unslash($_POST['id']);
        }
        if (array_key_exists('variant_key', $_POST)) {
            $input['variant_key'] = wp_unslash($_POST['variant_key']);
        }

        $result = (new DeleteFinanceContainerUseCase($registry))->execute($input);
        self::respond($result);
    }

    private static function authorize(): bool {
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

    private static function resolve_registry(): ?AA_Canonical_Registry {
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
     * @param array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}} $result
     */
    private static function respond(array $result): void {
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

    private static function http_status_for_code(string $code): int {
        switch ($code) {
            case 'not_found':
            case 'unknown_variant':
                return 404;
            case 'persistence_failed':
            case 'canonical_unavailable':
                return 500;
            case 'missing_title':
            case 'invalid_title':
            case 'title_too_long':
            case 'invalid_details':
            case 'details_too_long':
            case 'invalid_id':
            case 'invalid_variant_key':
                return 400;
            default:
                return 400;
        }
    }
}
