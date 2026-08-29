<?php
/**
 * Canonical Shell URL Policy — Construcción y validación de URLs canónicas.
 *
 * Capa de infraestructura / WordPress para el módulo de transporte `canonical`.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\WP
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Key')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-key.php';
}

final class AA_Canonical_Shell_Url_Policy {

    public const MODULE_CANONICAL = 'canonical';
    public const ACTION_IFRAME_CONTENT = 'aa_iframe_content';

    private const ALLOWED_QUERY_KEYS = [
        'action',
        'module',
        'family',
        'variant',
    ];

    /**
     * Construye una URL de shell canónica segura y allowlisted.
     */
    public static function build_url(string $family_key, ?string $variant_key = null): string {
        $args = [
            'action' => self::ACTION_IFRAME_CONTENT,
            'module' => self::MODULE_CANONICAL,
            'family' => $family_key,
        ];

        if ($variant_key !== null && $variant_key !== '') {
            $args['variant'] = $variant_key;
        }

        return add_query_arg($args, admin_url('admin-post.php'));
    }

    /**
     * Valida si una URL es una URL canónica del mismo sitio estrictamente allowlisted.
     */
    public static function is_allowlisted_canonical_url(string $url): bool {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parsed = wp_parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }

        // Si incluye scheme o host, deben coincidir con admin_url()
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

        if ($action !== self::ACTION_IFRAME_CONTENT || $module !== self::MODULE_CANONICAL) {
            return false;
        }

        // No permitir parámetros no autorizados (ej. view, client_id, etc.)
        foreach (array_keys($query) as $key) {
            if (!in_array($key, self::ALLOWED_QUERY_KEYS, true)) {
                return false;
            }
        }

        // Validar family
        $family = isset($query['family']) ? (string) $query['family'] : '';
        if ($family === '' || !AA_Canonical_Key::is_valid($family)) {
            return false;
        }

        // Validar variant si está presente
        if (isset($query['variant'])) {
            $variant = (string) $query['variant'];
            if ($variant === '' || !AA_Canonical_Key::is_valid($variant)) {
                return false;
            }
        }

        return true;
    }
}
