<?php
/** HTTP transport for FH-2B canonical-shell mutations. */
defined('ABSPATH') or die('No direct access');

final class CanonicalCleanShellAjax {
    public const NONCE_ACTION = 'aa_canonical_clean_shell';
    private const ACTIONS = ['create_list', 'update_list', 'delete_list', 'create_record', 'update_record', 'delete_record', 'list_delete_preview'];

    public static function register(): void {
        foreach (self::ACTIONS as $action) add_action('wp_ajax_aa_canonical_clean_' . $action, [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        if (!current_user_can('manage_options')) self::error('forbidden', 'Permisos insuficientes.', 403);
        $nonce = self::scalar('nonce');
        if ($nonce === null || !wp_verify_nonce($nonce, self::NONCE_ACTION)) self::error('invalid_nonce', 'La sesión de seguridad expiró. Recarga la página.', 403);
        $operation = self::scalar('operation');
        if ($operation === null || !in_array($operation, self::ACTIONS, true)) self::error('invalid_operation', 'La operación no es válida.', 400);
        try {
            $core = self::core();
            if ($operation === 'list_delete_preview') {
                $list_id = self::positive_id('list_id');
                if ($core->list($list_id) === null) self::error('not_found', 'La lista ya no existe.', 404);
                wp_send_json_success(['record_count' => $core->record_count($list_id)]);
            }
            $result = self::mutate($core, $operation);
            if ($result->state() === CanonicalCoreMutationResult::CONFIRMED) wp_send_json_success(['resource' => $result->resource()]);
            $map = [CanonicalCoreMutationResult::NOT_FOUND => ['not_found', 'El recurso ya no existe.', 404], CanonicalCoreMutationResult::UNCERTAIN => ['uncertain', 'No fue posible confirmar el resultado. Recarga antes de reintentar.', 409], CanonicalCoreMutationResult::PERSISTENCE_FAILED => ['persistence_failed', 'No se pudo guardar el cambio.', 500]];
            $entry = $map[$result->state()] ?? ['persistence_failed', 'No se pudo guardar el cambio.', 500];
            self::error($entry[0], $entry[1], $entry[2]);
        } catch (InvalidArgumentException $e) {
            self::error('invalid_payload', 'Revisa el título y los datos enviados.', 400);
        }
    }

    private static function mutate(CanonicalCoreUseCase $core, string $operation): CanonicalCoreMutationResult {
        $list_id = in_array($operation, ['update_list', 'delete_list', 'create_record', 'update_record', 'delete_record'], true) ? self::positive_id('list_id') : 0;
        if ($operation === 'create_list') return $core->create_list(self::fields());
        if ($operation === 'update_list') return $core->update_list($list_id, self::fields());
        if ($operation === 'delete_list') return $core->delete_list($list_id);
        if ($operation === 'create_record') return $core->create_record($list_id, self::fields());
        $record_id = self::positive_id('record_id');
        return $operation === 'update_record' ? $core->update_record($list_id, $record_id, self::fields()) : $core->delete_record($list_id, $record_id);
    }
    private static function core(): CanonicalCoreUseCase {
        if (!class_exists('AA_Canonical_Base_Fields')) require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-base-fields.php';
        if (!class_exists('CanonicalCoreUseCase')) require_once dirname(__DIR__, 2) . '/application/canonical/core/CanonicalCoreUseCase.php';
        if (!class_exists('CanonicalCoreRepository')) require_once dirname(__DIR__, 2) . '/repositories/CanonicalCoreRepository.php';
        return new CanonicalCoreUseCase(new CanonicalCoreRepository());
    }
    private static function fields(): AA_Canonical_Base_Fields { return new AA_Canonical_Base_Fields(self::required_scalar('title'), self::scalar('details')); }
    private static function positive_id(string $key): int { $value = self::scalar($key); if ($value === null || !ctype_digit($value) || (int) $value < 1) throw new InvalidArgumentException('invalid id'); return (int) $value; }
    private static function required_scalar(string $key): string { $value = self::scalar($key); if ($value === null) throw new InvalidArgumentException('missing'); return $value; }
    private static function scalar(string $key): ?string { if (!isset($_POST[$key]) || !is_string($_POST[$key])) return null; return wp_unslash($_POST[$key]); }
    private static function error(string $code, string $message, int $status): void { wp_send_json_error(['code' => $code, 'message' => $message], $status); }
}
