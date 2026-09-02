<?php
/**
 * Canonical Shell Base URL Policy — URLs del módulo paralelo `canonical_shell`.
 *
 * Hermano de AA_Canonical_Shell_Url_Policy. No altera el builder de `module=canonical`.
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

    private const ALLOWED_QUERY_KEYS = [
        'action',
        'module',
        'family',
        'variant',
        'page',
        'shell_mode',
        'view',
        'container_id',
        'containers_page',
    ];

    /**
     * Construye una URL segura del shell canónico base (módulo paralelo).
     *
     * @param string      $family_key  Configuración externa (p. ej. finance).
     * @param string|null $variant_key Configuración externa (p. ej. general).
     * @param int|null    $page        Página >= 1; omitida si null o 1.
     */
    public static function build_url(
        string $family_key,
        ?string $variant_key = null,
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

        if ($variant_key !== null && $variant_key !== '') {
            if (!AA_Canonical_Key::is_valid($variant_key)) {
                throw new InvalidArgumentException('Clave de variante no válida para shell base.');
            }
            $args['variant'] = $variant_key;
        }

        if ($page !== null && $page > 1) {
            $args['page'] = (string) $page;
        }

        return add_query_arg($args, admin_url('admin-post.php'));
    }

    /**
     * URL de preview administrativo explícito (sin family/variant).
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
     */
    public static function build_records_url(
        string $family_key,
        string $variant_key,
        int $container_id,
        ?int $page = null,
        ?int $containers_page = null
    ): string {
        if (!AA_Canonical_Key::is_valid($family_key)) {
            throw new InvalidArgumentException('Clave de familia no válida para shell base.');
        }
        if (!AA_Canonical_Key::is_valid($variant_key)) {
            throw new InvalidArgumentException('Clave de variante no válida para shell base.');
        }
        if ($container_id < 1) {
            throw new InvalidArgumentException('container_id no válido para shell base.');
        }

        $args = [
            'action' => self::ACTION_IFRAME_CONTENT,
            'module' => self::MODULE_SHELL,
            'family' => $family_key,
            'variant' => $variant_key,
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
     * URL base del módulo sin identidad canónica (estado de desarrollo controlado).
     */
    public static function build_module_url(): string {
        return add_query_arg(
            [
                'action' => self::ACTION_IFRAME_CONTENT,
                'module' => self::MODULE_SHELL,
            ],
            admin_url('admin-post.php')
        );
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
        $has_variant = isset($query['variant']);
        $view = isset($query['view']) ? (string) $query['view'] : '';
        $has_container_id = isset($query['container_id']);
        $has_containers_page = isset($query['containers_page']);

        if ($shell_mode !== '') {
            if ($shell_mode !== self::SHELL_MODE_PREVIEW) {
                return false;
            }
            if ($has_family || $has_variant) {
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

        if ($has_variant) {
            $variant = (string) $query['variant'];
            if ($variant === '' || !AA_Canonical_Key::is_valid($variant)) {
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
