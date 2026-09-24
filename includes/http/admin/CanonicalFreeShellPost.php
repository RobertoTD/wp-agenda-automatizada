<?php
defined('ABSPATH') or die('No direct access');

final class CanonicalFreeShellPost {
    public static function register(): void { foreach (['save_list', 'delete_list', 'save_record', 'delete_record'] as $op) add_action('admin_post_aa_canonical_free_' . $op, [__CLASS__, $op]); }
    private static function allow(): void { if (!current_user_can('manage_options')) wp_die('Permisos insuficientes.', 'Error', ['response' => 403]); check_admin_referer('aa_canonical_free'); }
    private static function core(): CanonicalCoreUseCase {
        if (!class_exists('AA_Canonical_Base_Fields')) require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-base-fields.php';
        if (!class_exists('CanonicalCoreUseCase')) require_once dirname(__DIR__, 2) . '/application/canonical/core/CanonicalCoreUseCase.php';
        if (!class_exists('CanonicalCoreRepository')) require_once dirname(__DIR__, 2) . '/repositories/CanonicalCoreRepository.php';
        return new CanonicalCoreUseCase(new CanonicalCoreRepository());
    }
    private static function return_url(?int $container = null): string { $args = ['action' => 'aa_iframe_content', 'module' => 'canonical_shell']; if ($container) { $args['view'] = 'records'; $args['container_id'] = $container; } return add_query_arg($args, admin_url('admin-post.php')); }
    private static function fields(): AA_Canonical_Base_Fields { return new AA_Canonical_Base_Fields(isset($_POST['title']) ? wp_unslash((string) $_POST['title']) : '', isset($_POST['details']) ? wp_unslash((string) $_POST['details']) : null); }
    private static function id(string $key): int { return isset($_POST[$key]) ? (int) $_POST[$key] : 0; }
    private static function assert_confirmed(CanonicalCoreMutationResult $result): void {
        if ($result->state() === CanonicalCoreMutationResult::CONFIRMED) return;
        $messages = [CanonicalCoreMutationResult::NOT_FOUND => 'El recurso ya no existe.', CanonicalCoreMutationResult::UNCERTAIN => 'No se pudo confirmar el resultado. Recarga antes de reintentar.', CanonicalCoreMutationResult::PERSISTENCE_FAILED => 'No se pudo guardar el cambio.'];
        wp_die($messages[$result->state()] ?? 'Operación inválida.', 'Error', ['response' => $result->state() === CanonicalCoreMutationResult::NOT_FOUND ? 404 : 500]);
    }
    private static function run(callable $operation): void { try { $operation(); } catch (\InvalidArgumentException $e) { wp_die('Datos inválidos.', 'Error', ['response' => 400]); } }
    public static function save_list(): void { self::allow(); self::run(function () { $id = self::id('id'); $result = $id > 0 ? self::core()->update_list($id, self::fields()) : self::core()->create_list(self::fields()); self::assert_confirmed($result); wp_safe_redirect(self::return_url()); exit; }); }
    public static function delete_list(): void { self::allow(); self::run(function () { self::assert_confirmed(self::core()->delete_list(self::id('id'))); wp_safe_redirect(self::return_url()); exit; }); }
    public static function save_record(): void { self::allow(); self::run(function () { $list_id = self::id('container_id'); $record_id = self::id('id'); $result = $record_id > 0 ? self::core()->update_record($list_id, $record_id, self::fields()) : self::core()->create_record($list_id, self::fields()); self::assert_confirmed($result); wp_safe_redirect(self::return_url($list_id)); exit; }); }
    public static function delete_record(): void { self::allow(); self::run(function () { $list_id = self::id('container_id'); self::assert_confirmed(self::core()->delete_record($list_id, self::id('id'))); wp_safe_redirect(self::return_url($list_id)); exit; }); }
}
