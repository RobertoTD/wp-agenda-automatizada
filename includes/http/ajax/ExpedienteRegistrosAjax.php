<?php
/**
 * Expediente Registros AJAX — list + create + update + delete (MC5c2).
 *
 * Capability: manage_options (aligned with clients / expedientes UI gate).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/ExpedienteRegistrosRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/ExpedienteAdjuntosRepository.php';
require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoPublicDto.php';
require_once dirname(__DIR__, 2) . '/application/expediente/DeleteExpedienteRegistroUseCase.php';
require_once dirname(__DIR__, 2) . '/application/expediente/CreateExpedienteRegistroForClientUseCase.php';

final class ExpedienteRegistrosAjax {

    public const ACTION_LIST = 'aa_list_expediente_registros';
    public const ACTION_CREATE = 'aa_create_expediente_registro';
    public const ACTION_UPDATE = 'aa_update_expediente_registro';
    public const ACTION_DELETE = 'aa_delete_expediente_registro';
    public const NONCE_ACTION = 'aa_expediente_registros_nonce';

    public const TITLE_MAX = 200;
    public const BODY_MAX = 10000;

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

        $client_id = self::read_client_id();
        if ($client_id < 1) {
            wp_send_json_error(['message' => 'Cliente no válido.'], 400);
        }

        if (ClientsRepository::find_by_id($client_id) === null) {
            wp_send_json_error(['message' => 'Cliente no encontrado.'], 404);
        }

        $records = ExpedienteRegistrosRepository::list_by_client_id($client_id);

        // MC5a: todos los adjuntos por registro en una sola consulta bulk (sin N+1).
        // `adjuntos` (id DESC) es la fuente de verdad; `adjunto` es alias
        // temporal de adjuntos[0] para compatibilidad con MC4c.
        $record_ids = array_map(static function (array $record): int {
            return (int) $record['id'];
        }, $records);

        $adjuntos_by_record = ExpedienteAdjuntosRepository::list_by_record_ids($record_ids, $client_id);

        foreach ($records as $index => $record) {
            $rows = $adjuntos_by_record[(int) $record['id']] ?? [];
            $dtos = [];
            foreach ($rows as $row) {
                $dto = ExpedienteAdjuntoPublicDto::from($row);
                if ($dto !== null) {
                    $dtos[] = $dto;
                }
            }
            $records[$index]['adjuntos'] = $dtos;
            $records[$index]['adjunto'] = $dtos[0] ?? null;
        }

        wp_send_json_success([
            'records' => $records,
        ]);
    }

    public static function handle_create(): void {
        if (!self::authorize()) {
            return;
        }

        $client_id = self::read_client_id();
        $title = isset($_POST['title'])
            ? sanitize_text_field(wp_unslash((string) $_POST['title']))
            : '';
        $body = isset($_POST['body'])
            ? sanitize_textarea_field(wp_unslash((string) $_POST['body']))
            : '';

        // Ignorar expediente_id / category_id / nombre / título padre / fechas / blog_id del POST.
        $result = (new CreateExpedienteRegistroForClientUseCase())->execute([
            'client_id' => $client_id,
            'title' => $title,
            'body' => $body,
        ]);

        if (empty($result['success'])) {
            $code = (string) ($result['error']['code'] ?? 'persistence_failed');
            $message = (string) ($result['error']['message'] ?? 'No se pudo guardar el registro.');
            $status = self::http_status_for_create_code($code);
            wp_send_json_error(['message' => $message, 'code' => $code], $status);
        }

        $record = is_array($result['data']['record'] ?? null) ? $result['data']['record'] : null;
        if ($record === null) {
            wp_send_json_error(['message' => 'No se pudo guardar el registro.', 'code' => 'persistence_failed'], 500);
        }

        $payload = ['record' => $record];
        if (isset($result['data']['expediente_id'])) {
            $payload['expediente_id'] = (int) $result['data']['expediente_id'];
        }

        wp_send_json_success($payload);
    }

    private static function http_status_for_create_code(string $code): int {
        switch ($code) {
            case 'not_found':
                return 404;
            case 'invalid_client':
            case 'missing_title':
            case 'missing_body':
            case 'title_too_long':
            case 'body_too_long':
                return 400;
            case 'category_not_found':
            case 'persistence_failed':
            case 'tx_retryable':
            default:
                return 500;
        }
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

    public static function handle_delete(): void {
        if (!self::authorize()) {
            return;
        }

        $client_id = self::read_client_id();
        $record_id = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;

        if ($client_id < 1 || $record_id < 1) {
            wp_send_json_error(['message' => 'Cliente o registro no válido.', 'code' => 'invalid_context'], 400);
        }

        $use_case = new DeleteExpedienteRegistroUseCase();
        $result = $use_case->execute([
            'client_id' => $client_id,
            'record_id' => $record_id,
        ]);

        if (empty($result['ok'])) {
            $code = (string) ($result['code'] ?? 'delete_failed');
            $message = (string) ($result['message'] ?? 'No se pudo eliminar el registro.');
            wp_send_json_error(['message' => $message, 'code' => $code], self::http_status_for_delete_code($code));
        }

        wp_send_json_success([
            'deleted' => true,
            'record_id' => (int) $result['record_id'],
        ]);
    }

    private static function http_status_for_delete_code(string $code): int {
        switch ($code) {
            case 'forbidden':
                return 403;
            case 'client_not_found':
            case 'record_not_found':
                return 404;
            case 'adjunto_inconsistent':
            case 'path_forbidden':
                return 409;
            case 'storage_delete_partial':
            case 'delete_failed':
            case 'expediente_attachments_unreachable':
                return 502;
            case 'local_delete_failed':
                return 500;
            default:
                return 400;
        }
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

        if (!self::require_expediente_shell_access()) {
            return false;
        }

        return true;
    }

    /**
     * Fail-closed SaaS gate for Expedientes: shell access must be full.
     * Shared with ExpedienteAdjuntosAjax. Does not change shell fail-open.
     */
    public static function require_expediente_shell_access(): bool {
        require_once dirname(__DIR__, 2) . '/application/legal/ResolveShellAccessUseCase.php';
        require_once dirname(__DIR__, 2) . '/domain/legal/class-aa-shell-access.php';

        $shell = (new ResolveShellAccessUseCase())->execute();
        if (($shell['access'] ?? '') !== AA_Shell_Access::ACCESS_FULL) {
            wp_send_json_error([
                'message' => 'Acceso denegado.',
                'code'    => 'expediente_access_denied',
            ], 403);
            return false;
        }

        return true;
    }

    private static function read_client_id(): int {
        return isset($_REQUEST['client_id']) ? absint($_REQUEST['client_id']) : 0;
    }
}
