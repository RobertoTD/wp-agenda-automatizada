<?php
/**
 * Especificaciones puras de variantes de adjunto de expediente.
 *
 * Identidad = original `{uuid}.jpg`. Lecturas UI: summary | gallery | display.
 * Paths duales (P1 / N1):
 * - client_v1: installations/{uuid}/clients/{client_id}/records/{record_id}/{op}.jpg
 * - expediente_v2: installations/{uuid}/expedientes/{expediente_id}/records/{record_id}/{op}.jpg
 *
 * Sin I/O, sin WordPress, sin Storage. Writes productivos siguen en v1 (P1).
 */

defined('ABSPATH') or die('No direct access');

final class ExpedienteAdjuntoVariants {

    public const MANIFEST_VERSION = 1;

    public const CONTRACT_CLIENT_V1 = 'client_v1';
    public const CONTRACT_EXPEDIENTE_V2 = 'expediente_v2';
    /** Path canónico de registros (IMG-2 / IMG-3b). Sin client/expediente. */
    public const CONTRACT_CANONICAL_V1 = 'canonical_v1';

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

    private const INSTALLATION_UUID =
        '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    private const OP_UUID_V4 =
        '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const CLIENT_V1_ORIGINAL_RE =
        '#^installations/(' . self::INSTALLATION_UUID . ')/clients/(\d+)/records/(\d+)/(' . self::OP_UUID_V4 . ')\.jpg$#i';

    private const EXPEDIENTE_V2_ORIGINAL_RE =
        '#^installations/(' . self::INSTALLATION_UUID . ')/expedientes/(\d+)/records/(\d+)/(' . self::OP_UUID_V4 . ')\.jpg$#i';

    private const CANONICAL_V1_ORIGINAL_RE =
        '#^installations/(' . self::INSTALLATION_UUID . ')/canonical/records/(\d+)/(' . self::OP_UUID_V4 . ')\.jpg$#i';

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
     * Builder legacy client_v1 (writes productivos actuales).
     *
     * @return string|null
     */
    public static function build_client_original_path(
        string $installation_id,
        int $client_id,
        int $record_id,
        string $operation_id
    ): ?string {
        $installation_id = strtolower(trim($installation_id));
        $operation_id = strtolower(trim($operation_id));
        if (
            $installation_id === ''
            || $client_id < 1
            || $record_id < 1
            || $operation_id === ''
            || !preg_match('#^' . self::INSTALLATION_UUID . '$#i', $installation_id)
            || !preg_match('#^' . self::OP_UUID_V4 . '$#i', $operation_id)
        ) {
            return null;
        }

        return 'installations/' . $installation_id
            . '/clients/' . $client_id
            . '/records/' . $record_id
            . '/' . $operation_id . '.jpg';
    }

    /**
     * Builder canónico expediente_v2. Solo para tests/P2+; P1 no tiene callers productivos.
     *
     * @return string|null
     */
    public static function build_expediente_record_original_path(
        string $installation_id,
        int $expediente_id,
        int $record_id,
        string $operation_id
    ): ?string {
        $installation_id = strtolower(trim($installation_id));
        $operation_id = strtolower(trim($operation_id));
        if (
            $installation_id === ''
            || $expediente_id < 1
            || $record_id < 1
            || $operation_id === ''
            || !preg_match('#^' . self::INSTALLATION_UUID . '$#i', $installation_id)
            || !preg_match('#^' . self::OP_UUID_V4 . '$#i', $operation_id)
        ) {
            return null;
        }

        return 'installations/' . $installation_id
            . '/expedientes/' . $expediente_id
            . '/records/' . $record_id
            . '/' . $operation_id . '.jpg';
    }

    /**
     * Parser dual: original `.jpg` client_v1 o expediente_v2.
     *
     * Campos canónicos + aliases legacy (`wp_client_id`, `wp_record_id`,
     * `upload_operation_id`) para callers v1 existentes.
     *
     * @param mixed $storage_path
     * @return array{
     *   contract:string,
     *   installation_id:string,
     *   client_id:?int,
     *   expediente_id:?int,
     *   record_id:int,
     *   operation_id:string,
     *   storage_path:string,
     *   wp_client_id:?int,
     *   wp_record_id:int,
     *   upload_operation_id:string
     * }|null
     */
    public static function parse_original_path($storage_path): ?array {
        $path = is_string($storage_path) ? trim($storage_path) : '';
        if (
            $path === ''
            || strpos($path, '..') !== false
            || strpos($path, '//') !== false
            || $path[0] === '/'
            || strpos($path, '?') !== false
            || strpos($path, '#') !== false
            || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)
        ) {
            return null;
        }

        if (preg_match(self::CLIENT_V1_ORIGINAL_RE, $path, $match)) {
            $installation_id = strtolower($match[1]);
            $client_id = (int) $match[2];
            $record_id = (int) $match[3];
            $operation_id = strtolower($match[4]);
            if ($client_id < 1 || $record_id < 1) {
                return null;
            }

            $normalized = 'installations/' . $installation_id
                . '/clients/' . $client_id
                . '/records/' . $record_id
                . '/' . $operation_id . '.jpg';

            return [
                'contract' => self::CONTRACT_CLIENT_V1,
                'installation_id' => $installation_id,
                'client_id' => $client_id,
                'expediente_id' => null,
                'record_id' => $record_id,
                'operation_id' => $operation_id,
                'storage_path' => $normalized,
                // Aliases legacy (callers v1 / transfer / delete).
                'wp_client_id' => $client_id,
                'wp_record_id' => $record_id,
                'upload_operation_id' => $operation_id,
            ];
        }

        if (preg_match(self::EXPEDIENTE_V2_ORIGINAL_RE, $path, $match)) {
            $installation_id = strtolower($match[1]);
            $expediente_id = (int) $match[2];
            $record_id = (int) $match[3];
            $operation_id = strtolower($match[4]);
            if ($expediente_id < 1 || $record_id < 1) {
                return null;
            }

            $normalized = 'installations/' . $installation_id
                . '/expedientes/' . $expediente_id
                . '/records/' . $record_id
                . '/' . $operation_id . '.jpg';

            return [
                'contract' => self::CONTRACT_EXPEDIENTE_V2,
                'installation_id' => $installation_id,
                'client_id' => null,
                'expediente_id' => $expediente_id,
                'record_id' => $record_id,
                'operation_id' => $operation_id,
                'storage_path' => $normalized,
                'wp_client_id' => null,
                'wp_record_id' => $record_id,
                'upload_operation_id' => $operation_id,
            ];
        }

        if (preg_match(self::CANONICAL_V1_ORIGINAL_RE, $path, $match)) {
            $installation_id = strtolower($match[1]);
            $record_id = (int) $match[2];
            $operation_id = strtolower($match[3]);
            if ($record_id < 1) {
                return null;
            }

            $normalized = 'installations/' . $installation_id
                . '/canonical/records/' . $record_id
                . '/' . $operation_id . '.jpg';

            return [
                'contract' => self::CONTRACT_CANONICAL_V1,
                'installation_id' => $installation_id,
                'client_id' => null,
                'expediente_id' => null,
                'record_id' => $record_id,
                'operation_id' => $operation_id,
                'storage_path' => $normalized,
                'wp_client_id' => null,
                'wp_record_id' => $record_id,
                'upload_operation_id' => $operation_id,
            ];
        }

        return null;
    }

    /**
     * Deriva un path de variante desde un original v1 o v2 válido.
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

        if ($parsed['contract'] === self::CONTRACT_CLIENT_V1) {
            return 'installations/' . $parsed['installation_id']
                . '/clients/' . $parsed['client_id']
                . '/records/' . $parsed['record_id']
                . '/' . $parsed['operation_id'] . '_' . $variant . '.jpg';
        }

        if ($parsed['contract'] === self::CONTRACT_CANONICAL_V1) {
            return 'installations/' . $parsed['installation_id']
                . '/canonical/records/' . $parsed['record_id']
                . '/' . $parsed['operation_id'] . '_' . $variant . '.jpg';
        }

        return 'installations/' . $parsed['installation_id']
            . '/expedientes/' . $parsed['expediente_id']
            . '/records/' . $parsed['record_id']
            . '/' . $parsed['operation_id'] . '_' . $variant . '.jpg';
    }
}
