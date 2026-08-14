<?php
/**
 * AC — AA_Expediente_Adjunto_Variant_Generator.
 *
 * Ejecutar: php tests/infrastructure/wp/test-aa-expediente-adjunto-variant-generator-ac.php
 */

$plugin_root = dirname(__DIR__, 3);

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }
    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}
if (!defined('IMAGETYPE_JPEG')) {
    define('IMAGETYPE_JPEG', 2);
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        /** @var string */
        private $code;
        /** @var string */
        private $message;

        public function __construct(string $code = '', string $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-expediente-adjunto-variant-generator.php';

/**
 * Editor GD que replica el contrato de WP_Image_Editor: crop cover centrado,
 * contain sin upscale, maybe_exif_rotate, set_quality y save JPEG.
 */
final class AA_Test_Gd_Image_Editor {
    /** @var \GdImage|resource|null */
    private $image;
    /** @var int */
    private $quality = 90;
    /** @var bool */
    public $rotate_called = false;
    /** @var string */
    public $source_path;

    public function __construct(string $source_path) {
        $this->source_path = $source_path;
        $loaded = @imagecreatefromjpeg($source_path);
        $this->image = $loaded !== false ? $loaded : null;
    }

    public function is_loaded(): bool {
        return $this->image !== null;
    }

    public function maybe_exif_rotate() {
        $this->rotate_called = true;
        return true;
    }

    public function set_quality($quality) {
        $q = (int) $quality;
        if ($q < 1 || $q > 100) {
            return new WP_Error('quality_failed', 'invalid quality');
        }
        $this->quality = $q;
        return true;
    }

    public function get_size(): array {
        return [
            'width' => imagesx($this->image),
            'height' => imagesy($this->image),
        ];
    }

    public function resize($max_w, $max_h, $crop = false) {
        $orig_w = imagesx($this->image);
        $orig_h = imagesy($this->image);
        $dims = aa_test_image_resize_dimensions($orig_w, $orig_h, (int) $max_w, (int) $max_h, (bool) $crop);
        if ($dims === null) {
            return new WP_Error('error_getting_dimensions', 'Could not calculate resized image dimensions');
        }

        [$dst_w, $dst_h, $src_x, $src_y, $src_w, $src_h] = $dims;
        $resized = imagecreatetruecolor($dst_w, $dst_h);
        if ($resized === false) {
            return new WP_Error('resize_failed', 'imagecreatetruecolor failed');
        }
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefilledrectangle($resized, 0, 0, $dst_w, $dst_h, $white);
        imagecopyresampled($resized, $this->image, 0, 0, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
        imagedestroy($this->image);
        $this->image = $resized;

        return true;
    }

    public function save($dest, $mime_type = null) {
        if ($mime_type !== 'image/jpeg') {
            return new WP_Error('save_failed', 'mime must be image/jpeg');
        }
        if (!imagejpeg($this->image, $dest, $this->quality)) {
            return new WP_Error('save_failed', 'imagejpeg failed');
        }
        $size = $this->get_size();

        return [
            'path' => $dest,
            'width' => $size['width'],
            'height' => $size['height'],
            'mime-type' => 'image/jpeg',
        ];
    }
}

/**
 * Dimensiones al estilo WP: crop cover centrado; contain sin agrandar.
 *
 * @return array{0:int,1:int,2:int,3:int,4:int,5:int}|null dst_w, dst_h, src_x, src_y, src_w, src_h
 */
function aa_test_image_resize_dimensions(int $orig_w, int $orig_h, int $dest_w, int $dest_h, bool $crop): ?array {
    if ($orig_w < 1 || $orig_h < 1 || $dest_w < 1 || $dest_h < 1) {
        return null;
    }

    if ($crop) {
        $new_w = min($dest_w, $orig_w);
        $new_h = min($dest_h, $orig_h);
        $size_ratio = max($new_w / $orig_w, $new_h / $orig_h);
        $crop_w = (int) round($new_w / $size_ratio);
        $crop_h = (int) round($new_h / $size_ratio);
        $src_x = (int) floor(($orig_w - $crop_w) / 2);
        $src_y = (int) floor(($orig_h - $crop_h) / 2);

        return [$new_w, $new_h, $src_x, $src_y, $crop_w, $crop_h];
    }

    if ($orig_w <= $dest_w && $orig_h <= $dest_h) {
        return null;
    }

    $ratio = max($orig_w / $dest_w, $orig_h / $dest_h);
    $new_w = (int) round($orig_w / $ratio);
    $new_h = (int) round($orig_h / $ratio);

    return [$new_w, $new_h, 0, 0, $orig_w, $orig_h];
}

function aa_make_jpeg_fixture(int $w, int $h, int $quality = 90): string {
    $img = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($img, 40, 120, 200);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);
    $fg = imagecolorallocate($img, 220, 80, 40);
    imagefilledrectangle($img, 0, 0, (int) floor($w / 3), $h, $fg);
    $path = tempnam(sys_get_temp_dir(), 'aa_src_');
    imagejpeg($img, $path, $quality);
    imagedestroy($img);
    return $path;
}

function aa_test_make_temp(string $variant): string {
    $path = tempnam(sys_get_temp_dir(), 'aa_var_' . $variant . '_');
    return $path;
}

function aa_test_get_editor(string $source_path) {
    $editor = new AA_Test_Gd_Image_Editor($source_path);
    if (!$editor->is_loaded()) {
        return new WP_Error('editor_unavailable', 'could not load jpeg');
    }
    return $editor;
}

function aa_collect_variant_paths(array $result): array {
    $paths = [];
    if (empty($result['ok']) || empty($result['variants']) || !is_array($result['variants'])) {
        return $paths;
    }
    foreach ($result['variants'] as $file) {
        if (is_array($file) && !empty($file['path'])) {
            $paths[] = (string) $file['path'];
        }
    }
    return $paths;
}

$src_gen = file_get_contents($plugin_root . '/includes/infrastructure/wp/class-aa-expediente-adjunto-variant-generator.php');
ac_assert('generador usa ExpedienteAdjuntoVariants', strpos($src_gen, 'ExpedienteAdjuntoVariants::spec') !== false);
ac_assert('una instancia de editor por variante', substr_count($src_gen, '($this->get_editor)($source_path)') >= 1);
ac_assert('llama maybe_exif_rotate', strpos($src_gen, 'maybe_exif_rotate') !== false);
ac_assert('guarda image/jpeg', strpos($src_gen, "save(\$dest, self::MIME_JPEG)") !== false || strpos($src_gen, "save(\$dest, 'image/jpeg')") !== false);
ac_assert('default wp_get_image_editor', strpos($src_gen, 'wp_get_image_editor') !== false);
ac_assert('no habla con supabase', strpos($src_gen, 'supabase') === false && strpos($src_gen, 'sign_read') === false);
ac_assert('no toca el use case de upload', strpos($src_gen, 'UploadExpedienteRegistroAdjuntoUseCase') === false);

$generator = new AA_Expediente_Adjunto_Variant_Generator('aa_test_get_editor', 'aa_test_make_temp');

// 1) Fuente horizontal grande.
$large = aa_make_jpeg_fixture(1600, 900);
$large_hash = md5_file($large);
$large_size = filesize($large);
$result_large = $generator->generate($large);

ac_assert('horizontal grande ok', !empty($result_large['ok']));
$summary = $result_large['variants']['summary'] ?? null;
$gallery = $result_large['variants']['gallery'] ?? null;
$display = $result_large['variants']['display'] ?? null;

ac_assert('summary 160x160 crop', is_array($summary) && $summary['width'] === 160 && $summary['height'] === 160);
ac_assert('gallery 384x384 crop', is_array($gallery) && $gallery['width'] === 384 && $gallery['height'] === 384);
ac_assert(
    'display contain 1280x720',
    is_array($display) && $display['width'] === 1280 && $display['height'] === 720
);
ac_assert(
    'display dentro de 1280 y proporción 16:9',
    is_array($display)
    && $display['width'] <= 1280
    && $display['height'] <= 1280
    && abs(($display['width'] / $display['height']) - (1600 / 900)) < 0.02
);

foreach (['summary' => $summary, 'gallery' => $gallery, 'display' => $display] as $name => $file) {
    $spec = ExpedienteAdjuntoVariants::spec($name);
    ac_assert(
        $name . ' jpeg con bytes y tope',
        is_array($file)
        && $file['mime_type'] === 'image/jpeg'
        && $file['byte_size'] > 0
        && $file['byte_size'] <= (int) $spec['max_bytes']
        && is_file($file['path'])
    );
    $info = @getimagesize($file['path']);
    ac_assert(
        $name . ' getimagesize jpeg',
        is_array($info) && (int) $info[2] === IMAGETYPE_JPEG
        && (int) $info[0] === (int) $file['width']
        && (int) $info[1] === (int) $file['height']
    );
}

ac_assert('fuente grande intacta tras éxito', is_file($large) && md5_file($large) === $large_hash && filesize($large) === $large_size);
$generator->delete_generated($result_large['variants'] ?? []);
@unlink($large);

// 2) Fuente pequeña: display no upscale.
$small = aa_make_jpeg_fixture(400, 300);
$small_hash = md5_file($small);
$result_small = $generator->generate($small);
$small_display = $result_small['variants']['display'] ?? null;
$small_summary = $result_small['variants']['summary'] ?? null;

ac_assert('fuente pequeña ok', !empty($result_small['ok']));
ac_assert(
    'display no hace upscale',
    is_array($small_display) && $small_display['width'] === 400 && $small_display['height'] === 300
);
ac_assert(
    'summary sigue en 160x160 sobre fuente 400x300',
    is_array($small_summary) && $small_summary['width'] === 160 && $small_summary['height'] === 160
);
ac_assert(
    'display pequeña respeta tope',
    is_array($small_display)
    && $small_display['mime_type'] === 'image/jpeg'
    && $small_display['byte_size'] <= ExpedienteAdjuntoVariants::DISPLAY_MAX_BYTES
);
ac_assert('fuente pequeña intacta tras éxito', is_file($small) && md5_file($small) === $small_hash);
$generator->delete_generated($result_small['variants'] ?? []);
@unlink($small);

// 4) Fallos cerrados + sin temporales residuales.
$before_tmp = glob(sys_get_temp_dir() . '/aa_var_*') ?: [];

$missing = $generator->generate('/no/existe/aa-missing.jpg');
ac_assert('fuente ausente fail-closed', empty($missing['ok']) && ($missing['code'] ?? '') === 'source_unreadable');

$not_jpeg = tempnam(sys_get_temp_dir(), 'aa_txt_');
file_put_contents($not_jpeg, 'not-a-jpeg');
$not_jpeg_hash = md5_file($not_jpeg);
$bad_mime = $generator->generate($not_jpeg);
ac_assert('no-jpeg fail-closed', empty($bad_mime['ok']) && ($bad_mime['code'] ?? '') === 'editor_unavailable');
ac_assert('fuente no-jpeg intacta tras fallo', is_file($not_jpeg) && md5_file($not_jpeg) === $not_jpeg_hash);
@unlink($not_jpeg);

$save_calls = 0;
$created_by_fail = [];
$failing_save = new AA_Expediente_Adjunto_Variant_Generator(
    static function (string $source_path) use (&$save_calls) {
        $editor = new AA_Test_Gd_Image_Editor($source_path);
        return new class($editor, $save_calls) {
            private $inner;
            private $save_calls;
            public function __construct($inner, &$save_calls) {
                $this->inner = $inner;
                $this->save_calls =& $save_calls;
            }
            public function maybe_exif_rotate() {
                return $this->inner->maybe_exif_rotate();
            }
            public function set_quality($q) {
                return $this->inner->set_quality($q);
            }
            public function get_size() {
                return $this->inner->get_size();
            }
            public function resize($w, $h, $crop = false) {
                return $this->inner->resize($w, $h, $crop);
            }
            public function save($dest, $mime = null) {
                $this->save_calls++;
                if ($this->save_calls >= 2) {
                    return new WP_Error('save_failed', 'forced');
                }
                return $this->inner->save($dest, $mime);
            }
        };
    },
    static function (string $variant) use (&$created_by_fail) {
        $path = aa_test_make_temp($variant);
        $created_by_fail[] = $path;
        return $path;
    }
);

$fail_src = aa_make_jpeg_fixture(800, 600);
$fail_hash = md5_file($fail_src);
$fail_save = $failing_save->generate($fail_src);
ac_assert('save fallido fail-closed', empty($fail_save['ok']) && ($fail_save['code'] ?? '') === 'save_failed');
$leftovers = 0;
foreach ($created_by_fail as $path) {
    if (is_file($path)) {
        $leftovers++;
    }
}
ac_assert('save fallido no deja temporales del generador', $leftovers === 0);
ac_assert('fuente intacta tras save fallido', is_file($fail_src) && md5_file($fail_src) === $fail_hash);
@unlink($fail_src);

$after_tmp = glob(sys_get_temp_dir() . '/aa_var_*') ?: [];
$new_tmps = array_diff($after_tmp, $before_tmp);
ac_assert('sin aa_var residuales de los fallos', count($new_tmps) === 0);

$rotate_fail = new AA_Expediente_Adjunto_Variant_Generator(
    static function (string $source_path) {
        return new class($source_path) {
            public function maybe_exif_rotate() {
                return new WP_Error('rotate_failed', 'forced');
            }
            public function set_quality($q) {
                return true;
            }
            public function resize($w, $h, $crop = false) {
                return true;
            }
            public function save($dest, $mime = null) {
                return ['path' => $dest];
            }
        };
    },
    'aa_test_make_temp'
);
$rot_src = aa_make_jpeg_fixture(320, 240);
$rot = $rotate_fail->generate($rot_src);
ac_assert('rotate fallido fail-closed', empty($rot['ok']) && ($rot['code'] ?? '') === 'rotate_failed');
@unlink($rot_src);

$src_plugin = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert(
    'bootstrap carga ExpedienteAdjuntoVariants',
    is_string($src_plugin) && strpos($src_plugin, 'includes/domain/expediente/ExpedienteAdjuntoVariants.php') !== false
);
$pos_variants = is_string($src_plugin)
    ? strpos($src_plugin, 'includes/domain/expediente/ExpedienteAdjuntoVariants.php')
    : false;
$pos_generator = is_string($src_plugin)
    ? strpos($src_plugin, 'includes/infrastructure/wp/class-aa-expediente-adjunto-variant-generator.php')
    : false;
ac_assert(
    'bootstrap carga el generador después de las specs',
    $pos_variants !== false && $pos_generator !== false && $pos_variants < $pos_generator
);

echo "\n";
if (count($failed) === 0) {
    echo "Passed {$passed}/{$total}\n";
    exit(0);
}
echo 'Failed ' . count($failed) . "/{$total}\n";
foreach ($failed as $label) {
    echo " - {$label}\n";
}
exit(1);
