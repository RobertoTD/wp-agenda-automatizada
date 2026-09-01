<?php
/**
 * Finance Records AJAX — Transporte HTTP/AJAX para registros financieros.
 *
 * Expone las cuatro operaciones canónicas de registros:
 * - aa_list_finance_records   → ListFinanceRecordsUseCase
 * - aa_create_finance_record → CreateFinanceRecordUseCase
 * - aa_get_finance_record    → GetFinanceRecordUseCase
 * - aa_delete_finance_record → DeleteFinanceRecordUseCase
 * - aa_update_finance_record → UpdateFinanceRecordUseCase
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('FinanceAjaxSupport')) {
    require_once __DIR__ . '/FinanceAjaxSupport.php';
}
if (!class_exists('CreateFinanceRecordUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/finance/CreateFinanceRecordUseCase.php';
}
if (!class_exists('GetFinanceRecordUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/finance/GetFinanceRecordUseCase.php';
}
if (!class_exists('ListFinanceRecordsUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/finance/ListFinanceRecordsUseCase.php';
}
if (!class_exists('DeleteFinanceRecordUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/finance/DeleteFinanceRecordUseCase.php';
}
if (!class_exists('UpdateFinanceRecordUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/finance/UpdateFinanceRecordUseCase.php';
}

final class FinanceRecordsAjax {

    public const ACTION_LIST   = 'aa_list_finance_records';
    public const ACTION_CREATE = 'aa_create_finance_record';
    public const ACTION_GET    = 'aa_get_finance_record';
    public const ACTION_DELETE = 'aa_delete_finance_record';
    public const ACTION_UPDATE = 'aa_update_finance_record';
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
        if (array_key_exists('container_id', $_POST)) {
            $input['container_id'] = wp_unslash($_POST['container_id']);
        }
        if (array_key_exists('variant_key', $_POST)) {
            $input['variant_key'] = wp_unslash($_POST['variant_key']);
        }
        if (array_key_exists('page', $_POST)) {
            $input['page'] = wp_unslash($_POST['page']);
        }

        $result = (new ListFinanceRecordsUseCase($registry))->execute($input);
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
        if (array_key_exists('container_id', $_POST)) {
            $input['container_id'] = wp_unslash($_POST['container_id']);
        }
        if (array_key_exists('title', $_POST)) {
            $input['title'] = wp_unslash($_POST['title']);
        }
        if (array_key_exists('details', $_POST)) {
            $input['details'] = wp_unslash($_POST['details']);
        }
        if (array_key_exists('amount', $_POST)) {
            $input['amount'] = wp_unslash($_POST['amount']);
        }
        if (array_key_exists('variant_key', $_POST)) {
            $input['variant_key'] = wp_unslash($_POST['variant_key']);
        }

        $result = (new CreateFinanceRecordUseCase($registry))->execute($input);
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
        if (array_key_exists('container_id', $_POST)) {
            $input['container_id'] = wp_unslash($_POST['container_id']);
        }
        if (array_key_exists('record_id', $_POST)) {
            $input['record_id'] = wp_unslash($_POST['record_id']);
        }
        if (array_key_exists('variant_key', $_POST)) {
            $input['variant_key'] = wp_unslash($_POST['variant_key']);
        }

        $result = (new GetFinanceRecordUseCase($registry))->execute($input);
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
        if (array_key_exists('container_id', $_POST)) {
            $input['container_id'] = wp_unslash($_POST['container_id']);
        }
        if (array_key_exists('record_id', $_POST)) {
            $input['record_id'] = wp_unslash($_POST['record_id']);
        }
        if (array_key_exists('variant_key', $_POST)) {
            $input['variant_key'] = wp_unslash($_POST['variant_key']);
        }

        $result = (new DeleteFinanceRecordUseCase($registry))->execute($input);
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
        if (array_key_exists('container_id', $_POST)) {
            $input['container_id'] = wp_unslash($_POST['container_id']);
        }
        if (array_key_exists('record_id', $_POST)) {
            $input['record_id'] = wp_unslash($_POST['record_id']);
        }
        if (array_key_exists('title', $_POST)) {
            $input['title'] = wp_unslash($_POST['title']);
        }
        if (array_key_exists('details', $_POST)) {
            $input['details'] = wp_unslash($_POST['details']);
        }
        if (array_key_exists('amount', $_POST)) {
            $input['amount'] = wp_unslash($_POST['amount']);
        }
        if (array_key_exists('variant_key', $_POST)) {
            $input['variant_key'] = wp_unslash($_POST['variant_key']);
        }

        $result = (new UpdateFinanceRecordUseCase($registry))->execute($input);
        FinanceAjaxSupport::respond($result);
    }
}
