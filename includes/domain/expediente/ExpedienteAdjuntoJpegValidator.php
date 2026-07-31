<?php
/**
 * Validación autoritativa de JPEG temporal para adjuntos de expediente (MC4b).
 *
 * No confía en nombre, extensión ni metadatos declarados por el navegador.
 */

defined('ABSPATH') or die('No direct access');

final class ExpedienteAdjuntoJpegValidator {

    public const MAX_BYTES = 1048576;
    public const MAX_DIMENSION = 2048;
    public const MIME_JPEG = 'image/jpeg';

    /** @var callable(string):bool */
    private $is_uploaded_file;

    /**
     * @param callable(string):bool|null $is_uploaded_file Inyectable solo para tests; producción usa is_uploaded_file.
     */
    public function __construct(?callable $is_uploaded_file = null) {
        $this->is_uploaded_file = $is_uploaded_file ?: 'is_uploaded_file';
    }

    /**
     * @param array<string,mixed> $file Elemento de $_FILES['file']
     * @return array{
     *   ok:true,
     *   tmp_name:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int
     * }|array{ok:false,code:string,message:string}
     */
    public function validate(array $file): array {
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            return $this->fail('upload_error', 'No se pudo recibir el archivo.');
        }

        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp === '' || !is_string($tmp)) {
            return $this->fail('upload_missing', 'Archivo temporal no disponible.');
        }

        $checker = $this->is_uploaded_file;
        if (!$checker($tmp)) {
            return $this->fail('upload_not_uploaded', 'El archivo no proviene de una subida HTTP válida.');
        }

        if (!is_readable($tmp)) {
            return $this->fail('upload_unreadable', 'No se puede leer el archivo temporal.');
        }

        $size = @filesize($tmp);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            return $this->fail('invalid_size', 'El archivo supera el tamaño permitido o está vacío.');
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                if (is_string($detected)) {
                    $mime = strtolower(trim($detected));
                }
            }
        }

        if ($mime !== self::MIME_JPEG) {
            return $this->fail('invalid_mime', 'Solo se admiten imágenes JPEG.');
        }

        $image_info = null;
        if (function_exists('wp_getimagesize')) {
            $image_info = @wp_getimagesize($tmp);
        }
        if (!is_array($image_info) && function_exists('getimagesize')) {
            $image_info = @getimagesize($tmp);
        }

        if (!is_array($image_info) || empty($image_info[0]) || empty($image_info[1])) {
            return $this->fail('invalid_jpeg', 'La imagen JPEG no es válida o está truncada.');
        }

        $type = isset($image_info[2]) ? (int) $image_info[2] : 0;
        if (defined('IMAGETYPE_JPEG') && $type !== IMAGETYPE_JPEG) {
            return $this->fail('invalid_jpeg', 'La imagen JPEG no es válida o está truncada.');
        }

        $width = (int) $image_info[0];
        $height = (int) $image_info[1];
        if (
            $width < 1
            || $height < 1
            || $width > self::MAX_DIMENSION
            || $height > self::MAX_DIMENSION
        ) {
            return $this->fail('invalid_dimensions', 'Las dimensiones de la imagen no son válidas.');
        }

        return [
            'ok' => true,
            'tmp_name' => $tmp,
            'mime_type' => self::MIME_JPEG,
            'byte_size' => (int) $size,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return array{ok:false,code:string,message:string}
     */
    private function fail(string $code, string $message): array {
        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}
