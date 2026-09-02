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

    private const ALLOWED_QUERY_KEYS = [
        'action',
        'module',
        'family',
        'variant',
    ];

    /**
     * Construye una URL segura del shell canónico base (módulo paralelo).
     *
     * @param string      $family_key  Configuración externa (p. ej. finance).
     * @param string|null $variant_key Configuración externa (p. ej. general).
     */
    public static function build_url(string $family_key, ?string $variant_key = null): string {
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

        if (isset($query['family'])) {
            $family = (string) $query['family'];
            if ($family === '' || !AA_Canonical_Key::is_valid($family)) {
                return false;
            }
        }

        if (isset($query['variant'])) {
            $variant = (string) $query['variant'];
            if ($variant === '' || !AA_Canonical_Key::is_valid($variant)) {
                return false;
            }
        }

        return true;
    }
}
