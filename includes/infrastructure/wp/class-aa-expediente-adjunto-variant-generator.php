<?php
/**
 * Genera las tres variantes JPEG de un adjunto de expediente (summary, gallery, display).
 *
 * Infraestructura WP: delega el trabajo gráfico a WP_Image_Editor.
 * Specs: ExpedienteAdjuntoVariants. Sin Storage, sin Use Case, sin I/O remoto.
 *
 * El archivo fuente pertenece al llamante y nunca se elimina aquí.
 * Los temporales de variante son del llamante tras un éxito; si algo falla,
 * este generador los borra todos antes de devolver error.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}

final class AA_Expediente_Adjunto_Variant_Generator {

    public const MIME_JPEG = 'image/jpeg';

    /** @var callable(string):object */
    private $get_editor;

    /** @var callable(string):string */
    private $make_temp;

    /**
     * @param callable(string):object|null $get_editor Recibe el path fuente; debe devolver editor o WP_Error.
     * @param callable(string):string|null $make_temp Recibe el nombre de variante; debe crear un temporal vacío.
     */
    public function __construct(?callable $get_editor = null, ?callable $make_temp = null) {
        $this->get_editor = $get_editor ?: static function (string $source_path) {
            return wp_get_image_editor($source_path);
        };
        $this->make_temp = $make_temp ?: [$this, 'default_make_temp'];
    }

    /**
     * @param string $source_path JPEG local del llamante (no se borra).
     * @return array{
     *   ok:true,
     *   variants:array<string,array{path:string,width:int,height:int,mime_type:string,byte_size:int}>
     * }|array{ok:false,code:string,message:string}
     */
    public function generate(string $source_path): array {
        $created = [];

        try {
            $source_path = trim($source_path);
            if ($source_path === '' || !is_file($source_path) || !is_readable($source_path)) {
                return $this->fail('source_unreadable', 'No se pudo leer la imagen de origen.');
            }

            $variants = [];
            foreach (ExpedienteAdjuntoVariants::ALLOWED_VARIANTS as $variant) {
                $spec = ExpedienteAdjuntoVariants::spec($variant);
                if ($spec === null) {
                    return $this->fail('variant_spec_missing', 'Especificación de variante no disponible.');
                }

                $dest = $this->create_temp($variant);
                if ($dest === '') {
                    $this->unlink_all($created);
                    return $this->fail('temp_create_failed', 'No se pudo crear un archivo temporal.');
                }
                $created[] = $dest;

                $one = $this->generate_one($source_path, $spec, $dest);
                if (empty($one['ok'])) {
                    $this->unlink_all($created);
                    return $this->fail(
                        (string) ($one['code'] ?? 'variant_failed'),
                        (string) ($one['message'] ?? 'No se pudo generar la variante.')
                    );
                }

                $variants[$variant] = $one['file'];
            }

            return [
                'ok' => true,
                'variants' => $variants,
            ];
        } catch (Throwable $e) {
            $this->unlink_all($created);
            return $this->fail('variant_failed', 'No se pudo generar las variantes.');
        }
    }

    /**
     * Borra temporales de un resultado exitoso. Nunca toca el archivo fuente.
     *
     * @param array<string,array{path?:string}> $variants
     */
    public static function delete_generated(array $variants): void {
        foreach ($variants as $file) {
            if (!is_array($file)) {
                continue;
            }
            $path = isset($file['path']) ? (string) $file['path'] : '';
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param array{
     *   variant:string,
     *   width:int,
     *   height:int,
     *   quality:int,
     *   resize:string,
     *   max_bytes:int,
     *   upscale:bool
     * } $spec
     * @return array{ok:true,file:array{path:string,width:int,height:int,mime_type:string,byte_size:int}}|array{ok:false,code:string,message:string}
     */
    private function generate_one(string $source_path, array $spec, string $dest): array {
        $editor = ($this->get_editor)($source_path);
        if ($this->is_error($editor)) {
            return $this->fail(
                'editor_unavailable',
                'No se pudo abrir el editor de imágenes.'
            );
        }

        if (is_object($editor) && method_exists($editor, 'maybe_exif_rotate')) {
            $rotated = $editor->maybe_exif_rotate();
            if ($this->is_error($rotated)) {
                return $this->fail('rotate_failed', 'No se pudo orientar la imagen.');
            }
        }

        if (!is_object($editor) || !method_exists($editor, 'set_quality') || !method_exists($editor, 'resize') || !method_exists($editor, 'save')) {
            return $this->fail('editor_unavailable', 'El editor de imágenes no soporta las operaciones necesarias.');
        }

        $quality = $editor->set_quality((int) $spec['quality']);
        if ($this->is_error($quality)) {
            return $this->fail('quality_failed', 'No se pudo aplicar la calidad JPEG.');
        }

        $crop = ((string) $spec['resize'] === 'cover');
        $resized = $editor->resize((int) $spec['width'], (int) $spec['height'], $crop);
        if ($this->is_error($resized)) {
            if ($crop || !$this->already_fits($editor, $spec)) {
                return $this->fail('resize_failed', 'No se pudo redimensionar la imagen.');
            }
            // contain + sin upscale: la fuente ya cabe en la caja; se guarda tal cual.
        }

        $saved = $editor->save($dest, self::MIME_JPEG);
        if ($this->is_error($saved) || !is_array($saved)) {
            return $this->fail('save_failed', 'No se pudo guardar la variante JPEG.');
        }

        $path = isset($saved['path']) && is_string($saved['path']) && $saved['path'] !== ''
            ? $saved['path']
            : $dest;

        if (!is_file($path) || !is_readable($path)) {
            return $this->fail('save_failed', 'La variante JPEG no se escribió en disco.');
        }

        if ($path !== $dest && is_file($dest) && $dest !== $source_path) {
            @unlink($dest);
        }

        $info = @getimagesize($path);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            $this->unlink_output($path, $source_path);
            return $this->fail('variant_invalid_output', 'La variante JPEG no es válida.');
        }

        $type = isset($info[2]) ? (int) $info[2] : 0;
        if (defined('IMAGETYPE_JPEG') && $type !== IMAGETYPE_JPEG) {
            $this->unlink_output($path, $source_path);
            return $this->fail('variant_invalid_output', 'La variante no es JPEG.');
        }

        $mime = isset($info['mime']) ? strtolower((string) $info['mime']) : '';
        if ($mime !== '' && $mime !== self::MIME_JPEG) {
            $this->unlink_output($path, $source_path);
            return $this->fail('variant_invalid_output', 'La variante no es JPEG.');
        }

        $byte_size = @filesize($path);
        if ($byte_size === false || $byte_size < 1) {
            $this->unlink_output($path, $source_path);
            return $this->fail('variant_invalid_output', 'La variante JPEG está vacía.');
        }

        $max_bytes = (int) $spec['max_bytes'];
        if ($byte_size > $max_bytes) {
            $this->unlink_output($path, $source_path);
            return $this->fail('variant_bytes_exceeded', 'La variante supera el tamaño máximo permitido.');
        }

        $width = (int) $info[0];
        $height = (int) $info[1];

        return [
            'ok' => true,
            'file' => [
                'path' => $path,
                'width' => $width,
                'height' => $height,
                'mime_type' => self::MIME_JPEG,
                'byte_size' => (int) $byte_size,
            ],
        ];
    }

    /**
     * @param array{width:int,height:int,upscale:bool} $spec
     */
    private function already_fits(object $editor, array $spec): bool {
        if (!method_exists($editor, 'get_size')) {
            return false;
        }
        $size = $editor->get_size();
        if (!is_array($size)) {
            return false;
        }
        $w = (int) ($size['width'] ?? 0);
        $h = (int) ($size['height'] ?? 0);

        return $w > 0 && $h > 0
            && $w <= (int) $spec['width']
            && $h <= (int) $spec['height'];
    }

    private function create_temp(string $variant): string {
        $path = ($this->make_temp)($variant);
        if (!is_string($path) || $path === '') {
            return '';
        }

        return $path;
    }

    private function default_make_temp(string $variant): string {
        $safe = preg_replace('/[^a-z0-9]/', '', strtolower($variant));
        if (!is_string($safe) || $safe === '') {
            $safe = 'var';
        }

        if (function_exists('wp_tempnam')) {
            $path = wp_tempnam('aa-exp-' . $safe . '.jpg');
            return is_string($path) ? $path : '';
        }

        $path = tempnam(sys_get_temp_dir(), 'aaexp' . $safe);
        return is_string($path) ? $path : '';
    }

    /**
     * @param mixed $thing
     */
    private function is_error($thing): bool {
        if (function_exists('is_wp_error') && is_wp_error($thing)) {
            return true;
        }

        return is_object($thing) && is_a($thing, 'WP_Error');
    }

    private function unlink_output(string $path, string $source_path): void {
        if ($path !== '' && $path !== $source_path && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param list<string> $paths
     */
    private function unlink_all(array $paths): void {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
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
