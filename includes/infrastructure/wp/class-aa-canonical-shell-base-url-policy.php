<?php
/**
 * Canonical Shell Base URL Policy — URLs del módulo `canonical_shell`.
 *
 * Único builder de URLs del shell canónico tras LEGACY-X (module=canonical retirado).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\WP
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Key')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-key.php';
}

final class AA_Canonical_Shell_Base_Url_Policy {

    public const MODULE_SHELL = 'canonical_shell';
    public const ACTION_IFRAME_CONTENT = 'aa_iframe_content';
    public const SHELL_MODE_PREVIEW = 'preview';
    public const VIEW_RECORDS = 'records';
    public const LISTS_SCOPE_ALL = 'all';

    private const ALLOWED_QUERY_KEYS = [
        'action',
        'module',
        'family',
        'page',
        'shell_mode',
        'view',
        'container_id',
        'containers_page',
        'lists_scope',
    ];

    /**
     * Construye una URL segura del shell canónico base (módulo paralelo).
     *
     * @param string   $family_key Configuración externa (p. ej. finance).
     * @param int|null $page       Página >= 1; omitida si null o 1.
     */
    public static function build_url(
        string $family_key,
        ?int $page = null
    ): string {
        if (!AA_Canonical_Key::is_valid($family_key)) {
            throw new InvalidArgumentException('Clave de familia no válida para shell base.');
        }

        $args = [
            'action' => self::ACTION_IFRAME_CONTENT,
            'module' => self::MODULE_SHELL,
            'family' => $family_key,
        ];

        if ($page !== null && $page > 1) {
            $args['page'] = (string) $page;
        }

        return add_query_arg($args, admin_url('admin-post.php'));
    }

    /**
     * URL de preview administrativo explícito (sin family).
     */
    public static function build_preview_url(?int $page = null): string {
        $args = [
            'action' => self::ACTION_IFRAME_CONTENT,
            'module' => self::MODULE_SHELL,
            'shell_mode' => self::SHELL_MODE_PREVIEW,
        ];
        if ($page !== null && $page > 1) {
            $args['page'] = (string) $page;
        }

        return add_query_arg($args, admin_url('admin-post.php'));
    }

    /**
     * URL de registros de un contenedor (familia real).
     *
     * @param string|null $lists_scope `all` para origen «Todas las listas»; null = origen familiar.
     */
    public static function build_records_url(
        string $family_key,
        int $container_id,
        ?int $page = null,
        ?int $containers_page = null,
        ?string $lists_scope = null
    ): string {
        if (!AA_Canonical_Key::is_valid($family_key)) {
            throw new InvalidArgumentException('Clave de familia no válida para shell base.');
        }
        if ($container_id < 1) {
            throw new InvalidArgumentException('container_id no válido para shell base.');
        }

        $args = [
            'action' => self::ACTION_IFRAME_CONTENT,
            'module' => self::MODULE_SHELL,
            'family' => $family_key,
            'view' => self::VIEW_RECORDS,
            'container_id' => (string) $container_id,
        ];
        if ($page !== null && $page > 1) {
            $args['page'] = (string) $page;
        }
        if ($containers_page !== null && $containers_page > 1) {
            $args['containers_page'] = (string) $containers_page;
        }
        if ($lists_scope === self::LISTS_SCOPE_ALL) {
            $args['lists_scope'] = self::LISTS_SCOPE_ALL;
        }

        return add_query_arg($args, admin_url('admin-post.php'));
    }

    /**
     * URL de registros en preview administrativo.
     */
    public static function build_preview_records_url(
        int $container_id,
        ?int $page = null,
        ?int $containers_page = null
    ): string {
        if ($container_id < 1) {
            throw new InvalidArgumentException('container_id no válido para shell base.');
        }

        $args = [
            'action' => self::ACTION_IFRAME_CONTENT,
            'module' => self::MODULE_SHELL,
            'shell_mode' => self::SHELL_MODE_PREVIEW,
            'view' => self::VIEW_RECORDS,
            'container_id' => (string) $container_id,
        ];
        if ($page !== null && $page > 1) {
            $args['page'] = (string) $page;
        }
        if ($containers_page !== null && $containers_page > 1) {
            $args['containers_page'] = (string) $containers_page;
        }

        return add_query_arg($args, admin_url('admin-post.php'));
    }

    /**
     * URL del listado general «Todas las listas» (sin family).
     */
    public static function build_module_url(?int $page = null): string {
        $args = [
            'action' => self::ACTION_IFRAME_CONTENT,
            'module' => self::MODULE_SHELL,
        ];
        if ($page !== null && $page > 1) {
            $args['page'] = (string) $page;
        }

        return add_query_arg($args, admin_url('admin-post.php'));
    }

    /**
     * Redirect de listado de contenedores tras mutación (familia o alcance all).
     */
    public static function build_containers_return_url(
        ?string $lists_scope,
        ?string $family_key,
        ?int $page = null
    ): string {
        if ($lists_scope === self::LISTS_SCOPE_ALL) {
            return self::build_module_url($page);
        }
        if ($family_key === null || $family_key === '') {
            throw new InvalidArgumentException('family_key requerida para retorno familiar.');
        }

        return self::build_url($family_key, $page);
    }

    /**
     * @param mixed $value
     * @return string|null `all` o null si ausente/vacío; false-like invalid handled by caller via parse
     */
    public static function parse_present_lists_scope($value): ?string {
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return null;
        }
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if ($raw === self::LISTS_SCOPE_ALL) {
            return self::LISTS_SCOPE_ALL;
        }

        return null;
    }

    /**
     * Contexto de retorno de mutaciones (navegación). No autoriza ni comprueba pertenencia.
     *
     * @param array{lists_scope?:mixed,page?:mixed,containers_page?:mixed} $input
     * @return array{lists_scope:?string,page:?int,containers_page:?int}|null null si el input es inválido
     */
    public static function parse_mutation_return_context(array $input): ?array {
        $lists_scope = null;
        if (array_key_exists('lists_scope', $input)) {
            $parsed = self::parse_present_lists_scope($input['lists_scope']);
            if ($parsed === null) {
                return null;
            }
            $lists_scope = $parsed;
        }

        $page = null;
        if (array_key_exists('page', $input)) {
            $parsed_page = self::parse_present_page_value($input['page']);
            if ($parsed_page === null) {
                return null;
            }
            $page = $parsed_page;
        }

        $containers_page = null;
        if (array_key_exists('containers_page', $input)) {
            $parsed_containers_page = self::parse_present_page_value($input['containers_page']);
            if ($parsed_containers_page === null) {
                return null;
            }
            $containers_page = $parsed_containers_page;
        }

        return [
            'lists_scope' => $lists_scope,
            'page' => $page,
            'containers_page' => $containers_page,
        ];
    }

    /**
     * Interpreta el valor de `page` / `containers_page` cuando está presente.
     *
     * @param mixed $value
     * @return int|null Página normalizada (>=1) o null si la solicitud es inválida.
     */
    public static function parse_present_page_value($value): ?int {
        if (is_array($value) || is_object($value) || is_bool($value)) {
            return null;
        }
        if (is_int($value)) {
            return $value < 1 ? 1 : $value;
        }
        if (is_float($value)) {
            return null;
        }
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
            return null;
        }

        $page = (int) $raw;
        return $page < 1 ? 1 : $page;
    }

    /**
     * Interpreta `container_id` cuando está presente. No normaliza valores < 1.
     *
     * @param mixed $value
     * @return int|null Id positivo o null si inválido.
     */
    public static function parse_present_positive_id($value): ?int {
        if (is_array($value) || is_object($value) || is_bool($value) || is_float($value)) {
            return null;
        }
        if (is_int($value)) {
            return $value >= 1 ? $value : null;
        }
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
            return null;
        }

        $id = (int) $raw;
        return $id >= 1 ? $id : null;
    }

    /**
     * Valida si una URL apunta al módulo shell base con query allowlisted.
     */
    public static function is_allowlisted_shell_url(string $url): bool {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parsed = wp_parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }

        $expected = wp_parse_url(admin_url('admin-post.php'));
        if (is_array($expected)) {
            if (isset($parsed['host']) && isset($expected['host'])) {
                if (strtolower((string) $parsed['host']) !== strtolower((string) $expected['host'])) {
                    return false;
                }
            }
            if (isset($parsed['path']) && isset($expected['path'])) {
                if ((string) $parsed['path'] !== (string) $expected['path']) {
                    return false;
                }
            }
        }

        $query = [];
        if (!empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }

        $action = isset($query['action']) ? (string) $query['action'] : '';
        $module = isset($query['module']) ? (string) $query['module'] : '';

        if ($action !== self::ACTION_IFRAME_CONTENT || $module !== self::MODULE_SHELL) {
            return false;
        }

        foreach (array_keys($query) as $key) {
            if (!in_array($key, self::ALLOWED_QUERY_KEYS, true)) {
                return false;
            }
        }

        $shell_mode = isset($query['shell_mode']) ? (string) $query['shell_mode'] : '';
        $has_family = isset($query['family']);
        $view = isset($query['view']) ? (string) $query['view'] : '';
        $has_container_id = isset($query['container_id']);
        $has_containers_page = isset($query['containers_page']);
        $lists_scope = isset($query['lists_scope']) ? (string) $query['lists_scope'] : '';

        if ($lists_scope !== '') {
            if ($lists_scope !== self::LISTS_SCOPE_ALL) {
                return false;
            }
            if ($view !== self::VIEW_RECORDS) {
                return false;
            }
            if ($shell_mode !== '') {
                return false;
            }
            if (!$has_family) {
                return false;
            }
        }

        if ($shell_mode !== '') {
            if ($shell_mode !== self::SHELL_MODE_PREVIEW) {
                return false;
            }
            if ($has_family) {
                return false;
            }
        }

        if ($view !== '') {
            if ($view !== self::VIEW_RECORDS) {
                return false;
            }
            if (!$has_container_id || self::parse_present_positive_id($query['container_id']) === null) {
                return false;
            }
        } else {
            if ($has_container_id || $has_containers_page) {
                return false;
            }
        }

        if ($has_family) {
            $family = (string) $query['family'];
            if ($family === '' || !AA_Canonical_Key::is_valid($family)) {
                return false;
            }
        }

        if (isset($query['page'])) {
            if (self::parse_present_page_value($query['page']) === null) {
                return false;
            }
        }

        if ($has_containers_page) {
            if ($view !== self::VIEW_RECORDS) {
                return false;
            }
            if (self::parse_present_page_value($query['containers_page']) === null) {
                return false;
            }
        }

        return true;
    }
}
