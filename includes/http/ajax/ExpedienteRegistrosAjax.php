<?php
/**
 * Expediente Registros AJAX — list + create + update for expediente chronology.
 *
 * Capability: manage_options (aligned with clients / expedientes UI gate).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/ExpedienteRegistrosRepository.php';

final class ExpedienteRegistrosAjax {

    public const ACTION_LIST = 'aa_list_expediente_registros';
    public const ACTION_CREATE = 'aa_create_expediente_registro';
    public const ACTION_UPDATE = 'aa_update_expediente_registro';
    public const NONCE_ACTION = 'aa_expediente_registros_nonce';

    public const TITLE_MAX = 200;
    public const BODY_MAX = 10000;

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_LIST, [__CLASS__, 'handle_list']);
        add_action('wp_ajax_' . self::ACTION_CREATE, [__CLASS__, 'handle_create']);
        add_action('wp_ajax_' . self::ACTION_UPDATE, [__CLASS__, 'handle_update']);
    }

    public static function handle_list(): void {
        if (!self::authorize()) {
            return;
        }

        $client_id = self::read_client_id();
        if ($client_id < 1) {
            wp_send_json_error(['message' => 'Cliente no válido.'], 400);
        }

        if (ClientsRepository::find_by_id($client_id) === null) {
            wp_send_json_error(['message' => 'Cliente no encontrado.'], 404);
        }

        $records = ExpedienteRegistrosRepository::list_by_client_id($client_id);

        wp_send_json_success([
            'records' => $records,
        ]);
    }

    public static function handle_create(): void {
        if (!self::authorize()) {
            return;
        }

        $client_id = self::read_client_id();
        if ($client_id < 1) {
            wp_send_json_error(['message' => 'Cliente no válido.'], 400);
        }

        if (ClientsRepository::find_by_id($client_id) === null) {
            wp_send_json_error(['message' => 'Cliente no encontrado.'], 404);
        }

        $fields = self::read_title_body_or_error();
        if ($fields === null) {
            return;
        }

        // Ignorar recorded_at / created_at / id / blog_id enviados por el cliente.
        $now = current_time('mysql');

        $record = ExpedienteRegistrosRepository::insert([
            'client_id' => $client_id,
            'title' => $fields['title'],
            'body' => $fields['body'],
            'recorded_at' => $now,
            'created_at' => $now,
        ]);

        if (is_wp_error($record)) {
            wp_send_json_error(['message' => $record->get_error_message()], 500);
        }

        wp_send_json_success([
            'record' => $record,
        ]);
    }

    public static function handle_update(): void {
        if (!self::authorize()) {
            return;
        }

        $client_id = self::read_client_id();
        $record_id = isset($_REQUEST['record_id']) ? absint($_REQUEST['record_id']) : 0;

        if ($client_id < 1) {
            wp_send_json_error(['message' => 'Cliente no válido.'], 400);
        }

        if ($record_id < 1) {
            wp_send_json_error(['message' => 'Registro no válido.'], 400);
        }

        if (ClientsRepository::find_by_id($client_id) === null) {
            wp_send_json_error(['message' => 'Cliente no encontrado.'], 404);
        }

        $fields = self::read_title_body_or_error();
        if ($fields === null) {
            return;
        }

        // Ignorar fechas / blog_id / ids alternativos del navegador.
        $now = current_time('mysql');

        $updated = ExpedienteRegistrosRepository::update_title_body(
            $record_id,
            $client_id,
            $fields['title'],
            $fields['body'],
            $now
        );

        if (is_wp_error($updated)) {
            wp_send_json_error(['message' => $updated->get_error_message()], 500);
        }

        $record = ExpedienteRegistrosRepository::find_by_id_for_client($record_id, $client_id);
        if ($record === null) {
            wp_send_json_error(['message' => 'Registro no encontrado.'], 404);
        }

        wp_send_json_success([
            'record' => $record,
        ]);
    }

    /**
     * @return array{title:string,body:string}|null
     */
    private static function read_title_body_or_error(): ?array {
        $title = isset($_POST['title'])
            ? sanitize_text_field(wp_unslash((string) $_POST['title']))
            : '';
        $body = isset($_POST['body'])
            ? sanitize_textarea_field(wp_unslash((string) $_POST['body']))
            : '';

        $title = trim($title);
        $body = trim($body);

        if ($title === '') {
            wp_send_json_error(['message' => 'El título es obligatorio.'], 400);
            return null;
        }

        if (mb_strlen($title) > self::TITLE_MAX) {
            wp_send_json_error(['message' => 'El título es demasiado largo.'], 400);
            return null;
        }

        if ($body === '') {
            wp_send_json_error(['message' => 'El texto es obligatorio.'], 400);
            return null;
        }

        if (mb_strlen($body) > self::BODY_MAX) {
            wp_send_json_error(['message' => 'El texto es demasiado largo.'], 400);
            return null;
        }

        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    private static function authorize(): bool {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
            return false;
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');

        return true;
    }

    private static function read_client_id(): int {
        return isset($_REQUEST['client_id']) ? absint($_REQUEST['client_id']) : 0;
    }
}
