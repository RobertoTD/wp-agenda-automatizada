<?php
/**
 * Sanitizes Training lesson rich_text HTML before it reaches the browser (C8A3).
 */

defined('ABSPATH') or die('No direct access');

final class AA_Training_Lesson_Html_Sanitizer {

    /**
     * Allowlist for wp_kses on rich_text.html blocks.
     *
     * @return array<string, array<string, bool>>
     */
    public static function allowed_html(): array {
        return [
            'p'          => [],
            'br'         => [],
            'strong'     => [],
            'em'         => [],
            'ul'         => [],
            'ol'         => [],
            'li'         => [],
            'h2'         => [],
            'h3'         => [],
            'a'          => [
                'href'   => true,
                'rel'    => true,
                'target' => true,
            ],
            'code'       => [],
            'pre'        => [],
            'blockquote' => [],
        ];
    }

    /**
     * @param string $html
     */
    public static function sanitize_html($html): string {
        if (!is_string($html) || $html === '') {
            return '';
        }

        if (!function_exists('wp_kses')) {
            return '';
        }

        return (string) wp_kses($html, self::allowed_html());
    }

    /**
     * Sanitizes rich_text.html in a successful lesson payload. Other fields unchanged.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function sanitize_lesson_data(array $data): array {
        if (!isset($data['blocks']) || !is_array($data['blocks'])) {
            return $data;
        }

        $sanitized_blocks = [];
        foreach ($data['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = isset($block['type']) && is_string($block['type']) ? $block['type'] : '';

            if ($type === 'rich_text') {
                $html = isset($block['html']) && is_string($block['html']) ? $block['html'] : '';
                $sanitized_blocks[] = [
                    'type' => 'rich_text',
                    'html' => self::sanitize_html($html),
                ];
                continue;
            }

            $sanitized_blocks[] = $block;
        }

        $data['blocks'] = $sanitized_blocks;

        return $data;
    }
}
