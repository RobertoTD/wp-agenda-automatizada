<?php
/**
 * Executable Navigation URL Resolver.
 *
 * Adapter de Application para convertir navegación declarativa del contrato a URL runtime.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Executable
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ExecutableNavigationUrlResolver {

    /**
     * @param array<string,mixed> $navigation
     */
    public static function resolve(array $navigation): ?string {
        $module = isset($navigation['module']) ? (string) $navigation['module'] : '';

        if ($module === '') {
            return null;
        }

        $args = [
            'action' => 'aa_iframe_content',
            'module' => sanitize_key($module),
        ];

        if ($args['module'] === '') {
            return null;
        }

        $setup_focus = $navigation['setup_focus'] ?? null;

        if (is_string($setup_focus) && $setup_focus !== '') {
            $args['setup_focus'] = sanitize_key($setup_focus);
        }

        $url = add_query_arg($args, admin_url('admin-post.php'));
        $fragment = $navigation['fragment'] ?? null;

        if (is_string($fragment) && $fragment !== '') {
            $url .= '#' . ltrim($fragment, '#');
        }

        return $url;
    }
}
