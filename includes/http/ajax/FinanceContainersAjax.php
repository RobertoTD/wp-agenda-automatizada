<?php
/**
 * Finance Containers AJAX — Transporte HTTP/AJAX para contenedores financieros.
 *
 * Expone las cuatro operaciones canónicas de contenedores:
 * - aa_list_finance_containers   → ListFinanceContainersUseCase
 * - aa_create_finance_container → CreateFinanceContainerUseCase
 * - aa_get_finance_container    → GetFinanceContainerUseCase
 * - aa_delete_finance_container → DeleteFinanceContainerUseCase
 * - aa_update_finance_container → UpdateFinanceContainerUseCase
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('FinanceAjaxSupport')) {
    require_once __DIR__ . '/FinanceAjaxSupport.php';
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
if (!class_exists('UpdateFinanceContainerUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/finance/UpdateFinanceContainerUseCase.php';
}

final class FinanceContainersAjax {

    public const ACTION_LIST   = 'aa_list_finance_containers';
    public const ACTION_CREATE = 'aa_create_finance_container';
    public const ACTION_GET    = 'aa_get_finance_container';
    public const ACTION_DELETE = 'aa_delete_finance_container';
    public const ACTION_UPDATE = 'aa_update_finance_container';
    public const NONCE_ACTION  = FinanceAjaxSupport::NONCE_ACTION;

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_LIST, [__CLASS__, 'handle_list']);
        add_action('wp_ajax_' . self::ACTION_CREATE, [__CLASS__, 'handle_create']);
        add_action('wp_ajax_' . self::ACTION_GET, [__CLASS__, 'handle_get']);
        add_action('wp_ajax_' . self::ACTION_DELETE, [__CLASS__, 'handle_delete']);
        add_action('wp_ajax_' . self::ACTION_UPDATE, [__CLASS__, 'handle_update']);
    }

    public static function handle_list(): void {
        if (!FinanceAjaxSupport::authorize()) {
            return;
        }

        $registry = FinanceAjaxSupport::resolve_registry();
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
        FinanceAjaxSupport::respond($result);
    }

    public static function handle_create(): void {
        if (!FinanceAjaxSupport::authorize()) {
            return;
        }

        $registry = FinanceAjaxSupport::resolve_registry();
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
        FinanceAjaxSupport::respond($result);
    }

    public static function handle_get(): void {
        if (!FinanceAjaxSupport::authorize()) {
            return;
        }

        $registry = FinanceAjaxSupport::resolve_registry();
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
        FinanceAjaxSupport::respond($result);
    }

    public static function handle_delete(): void {
        if (!FinanceAjaxSupport::authorize()) {
            return;
        }

        $registry = FinanceAjaxSupport::resolve_registry();
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
        FinanceAjaxSupport::respond($result);
    }

    public static function handle_update(): void {
        if (!FinanceAjaxSupport::authorize()) {
            return;
        }

        $registry = FinanceAjaxSupport::resolve_registry();
        if ($registry === null) {
            return;
        }

        $input = [];
        if (array_key_exists('id', $_POST)) {
            $input['id'] = wp_unslash($_POST['id']);
        }
        if (array_key_exists('title', $_POST)) {
            $input['title'] = wp_unslash($_POST['title']);
        }
        if (array_key_exists('details', $_POST)) {
            $input['details'] = wp_unslash($_POST['details']);
        }
        if (array_key_exists('variant_key', $_POST)) {
            $input['variant_key'] = wp_unslash($_POST['variant_key']);
        }

        $result = (new UpdateFinanceContainerUseCase($registry))->execute($input);
        FinanceAjaxSupport::respond($result);
    }
}
