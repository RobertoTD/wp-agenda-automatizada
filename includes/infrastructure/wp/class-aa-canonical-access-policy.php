<?php
/**
 * Canonical Access Policy — Política compartida y neutral de autorización para familias canónicas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\WP
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Access_Policy {

    public const CODE_AUTHORIZED   = 'authorized';
    public const CODE_UNAUTHORIZED = 'unauthorized';
    public const CODE_FORBIDDEN    = 'forbidden';

    /**
     * Evalúa el acceso de un usuario a una familia canónica en el contexto activo de WordPress.
     *
     * Reglas:
     * 1. Usuario debe estar autenticado (is_user_logged_in()).
     * 2. En Multisite, debe ser miembro explícito del blog activo (is_user_member_of_blog()).
     *    Los superadministradores que no sean miembros no reciben bypass.
     * 3. La familia 'finance' no requiere manage_options.
     * 4. Familias canónicas distintas de 'finance' requieren manage_options.
     *
     * @param string $family_key Clave de la familia canónica (ej. 'finance').
     * @return array{
     *     authorized: bool,
     *     code: string,
     *     status: int,
     *     message: string
     * }
     */
    public static function check_family_access(string $family_key): array {
        if (!is_user_logged_in()) {
            return [
                'authorized' => false,
                'code'       => self::CODE_UNAUTHORIZED,
                'status'     => 401,
                'message'    => 'Usuario no autenticado.',
            ];
        }

        if (is_multisite() && function_exists('is_user_member_of_blog') && !is_user_member_of_blog()) {
            return [
                'authorized' => false,
                'code'       => self::CODE_FORBIDDEN,
                'status'     => 403,
                'message'    => 'Acceso denegado: el usuario no pertenece a este sitio.',
            ];
        }

        if ($family_key !== 'finance') {
            if (!current_user_can('manage_options')) {
                return [
                    'authorized' => false,
                    'code'       => self::CODE_FORBIDDEN,
                    'status'     => 403,
                    'message'    => 'Permisos insuficientes para este módulo.',
                ];
            }
        }

        return [
            'authorized' => true,
            'code'       => self::CODE_AUTHORIZED,
            'status'     => 200,
            'message'    => 'Acceso autorizado.',
        ];
    }
}
