<?php
/**
 * Especificaciones puras de variantes de adjunto de expediente.
 *
 * Identidad canónica = original `{uuid}.jpg`. Las lecturas UI usan
 * `summary | gallery | display`. Sin I/O, sin WordPress, sin Storage.
 */

defined('ABSPATH') or die('No direct access');

final class ExpedienteAdjuntoVariants {

    public const MANIFEST_VERSION = 1;

    public const VARIANT_SUMMARY = 'summary';
    public const VARIANT_GALLERY = 'gallery';
    public const VARIANT_DISPLAY = 'display';

    /** @var list<string> */
    public const ALLOWED_VARIANTS = [
        self::VARIANT_SUMMARY,
        self::VARIANT_GALLERY,
        self::VARIANT_DISPLAY,
    ];

    public const ORIGINAL_MAX_BYTES = 1048576;

    public const SUMMARY_WIDTH = 160;
    public const SUMMARY_HEIGHT = 160;
    public const SUMMARY_QUALITY = 65;
    public const SUMMARY_RESIZE = 'cover';
    public const SUMMARY_MAX_BYTES = 32768;

    public const GALLERY_WIDTH = 384;
    public const GALLERY_HEIGHT = 384;
    public const GALLERY_QUALITY = 70;
    public const GALLERY_RESIZE = 'cover';
    public const GALLERY_MAX_BYTES = 98304;

    public const DISPLAY_WIDTH = 1280;
    public const DISPLAY_HEIGHT = 1280;
    public const DISPLAY_QUALITY = 75;
    public const DISPLAY_RESIZE = 'contain';
    public const DISPLAY_MAX_BYTES = 524288;
    public const DISPLAY_UPSCALE = false;

    /**
     * Original + summary + gallery + display (topes estrictos).
     */
    public const PHYSICAL_UPLOAD_MAX_BYTES = 1703936;

    private const ORIGINAL_PATH_RE =
        '#^installations/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/clients/(\d+)/records/(\d+)/([0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\.jpg$#i';

    /**
     * @return bool
     */
    public static function is_allowed_variant($variant): bool {
        return is_string($variant) && in_array($variant, self::ALLOWED_VARIANTS, true);
    }

    /**
     * @param mixed $variant
     * @return array{
     *   variant:string,
     *   width:int,
     *   height:int,
     *   quality:int,
     *   resize:string,
     *   max_bytes:int,
     *   upscale:bool
     * }|null
     */
    public static function spec($variant): ?array {
        if (!self::is_allowed_variant($variant)) {
            return null;
        }

        if ($variant === self::VARIANT_SUMMARY) {
            return [
                'variant' => self::VARIANT_SUMMARY,
                'width' => self::SUMMARY_WIDTH,
                'height' => self::SUMMARY_HEIGHT,
                'quality' => self::SUMMARY_QUALITY,
                'resize' => self::SUMMARY_RESIZE,
                'max_bytes' => self::SUMMARY_MAX_BYTES,
                'upscale' => false,
            ];
        }

        if ($variant === self::VARIANT_GALLERY) {
            return [
                'variant' => self::VARIANT_GALLERY,
                'width' => self::GALLERY_WIDTH,
                'height' => self::GALLERY_HEIGHT,
                'quality' => self::GALLERY_QUALITY,
                'resize' => self::GALLERY_RESIZE,
                'max_bytes' => self::GALLERY_MAX_BYTES,
                'upscale' => false,
            ];
        }

        return [
            'variant' => self::VARIANT_DISPLAY,
            'width' => self::DISPLAY_WIDTH,
            'height' => self::DISPLAY_HEIGHT,
            'quality' => self::DISPLAY_QUALITY,
            'resize' => self::DISPLAY_RESIZE,
            'max_bytes' => self::DISPLAY_MAX_BYTES,
            'upscale' => self::DISPLAY_UPSCALE,
        ];
    }

    /**
     * Parser canónico: acepta exclusivamente `{uuid}.jpg`.
     *
     * @param mixed $storage_path
     * @return array{
     *   installation_id:string,
     *   wp_client_id:int,
     *   wp_record_id:int,
     *   upload_operation_id:string,
     *   storage_path:string
     * }|null
     */
    public static function parse_original_path($storage_path): ?array {
        $path = is_string($storage_path) ? trim($storage_path) : '';
        if (
            $path === ''
            || strpos($path, '..') !== false
            || strpos($path, '//') !== false
            || $path[0] === '/'
        ) {
            return null;
        }

        if (!preg_match(self::ORIGINAL_PATH_RE, $path, $match)) {
            return null;
        }

        $installation_id = strtolower($match[1]);
        $operation_id = strtolower($match[4]);

        return [
            'installation_id' => $installation_id,
            'wp_client_id' => (int) $match[2],
            'wp_record_id' => (int) $match[3],
            'upload_operation_id' => $operation_id,
            'storage_path' => 'installations/' . $installation_id
                . '/clients/' . $match[2]
                . '/records/' . $match[3]
                . '/' . $operation_id . '.jpg',
        ];
    }

    /**
     * Deriva un path de variante solo desde un original canónico válido
     * y una variante de la allowlist.
     *
     * @param mixed $original_path
     * @param mixed $variant
     * @return string|null
     */
    public static function derive_path($original_path, $variant): ?string {
        if (!self::is_allowed_variant($variant)) {
            return null;
        }

        $parsed = self::parse_original_path($original_path);
        if ($parsed === null) {
            return null;
        }

        return 'installations/' . $parsed['installation_id']
            . '/clients/' . $parsed['wp_client_id']
            . '/records/' . $parsed['wp_record_id']
            . '/' . $parsed['upload_operation_id'] . '_' . $variant . '.jpg';
    }
}
