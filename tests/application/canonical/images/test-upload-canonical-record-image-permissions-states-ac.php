<?php
/**
 * AC IMG-3b bloque 1 — permisos, pertenencia y árbol de estados.
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-upload-canonical-record-image-permissions-states-ac.php
 */

$plugin_root = dirname(__DIR__, 4);

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
if (!defined('UPLOAD_ERR_OK')) {
    define('UPLOAD_ERR_OK', 0);
}
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        public function __construct($code = '', $message = '') {
            $this->code = $code;
            $this->message = $message;
        }
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

$uc_src = file_get_contents($plugin_root . '/includes/application/canonical/images/UploadCanonicalRecordImageUseCase.php');
$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/CanonicalAttachRecordImageAjax.php');
$policy_src = file_get_contents($plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php');
$lock_src = file_get_contents($plugin_root . '/includes/infrastructure/wp/class-aa-expediente-aggregate-lock.php');
$bootstrap_src = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$store_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-record-image-confirmation-store.php');

ac_assert('UC sin START TRANSACTION', !preg_match('/START TRANSACTION|COMMIT\b|ROLLBACK\b/', $uc_src));
ac_assert('AJAX action constante', strpos($ajax_src, "ACTION = 'aa_attach_canonical_record_image'") !== false);
ac_assert('AJAX usa authorize_identity', strpos($ajax_src, 'authorize_identity') !== false);
ac_assert('AJAX sin manage_options propio', strpos($ajax_src, 'manage_options') === false);
ac_assert('UC sin manage_options', strpos($uc_src, 'manage_options') === false);
ac_assert('Access Policy finance sin manage_options', strpos($policy_src, "family_key !== 'finance'") !== false);
ac_assert('bootstrap registra attach', strpos($bootstrap_src, 'CanonicalAttachRecordImageAjax::register') !== false);
ac_assert('lock scope canonical_container', strpos($lock_src, "SCOPE_CANONICAL_CONTAINER = 'canonical_container'") !== false);
ac_assert('orden locks contenedor→purge→cuota', strpos($uc_src, 'SCOPE_CANONICAL_CONTAINER') !== false
    && strpos($uc_src, 'has_blocking_purge') !== false
    && strpos($uc_src, 'SCOPE_STORAGE_QUOTA') !== false
    && strpos($uc_src, 'SCOPE_CANONICAL_CONTAINER') < strpos($uc_src, 'has_blocking_purge')
    && strpos($uc_src, 'has_blocking_purge') < strpos($uc_src, 'SCOPE_STORAGE_QUOTA'));
ac_assert('pertenencia antes de mutar ops', strpos($uc_src, 'operation_identity_conflict') !== false
    && strpos($uc_src, 'mark_owned_cleanup') !== false);
ac_assert('fresh exige ready+active', strpos($uc_src, 'capability_not_ready') !== false
    && strpos($uc_src, 'capability_inactive') !== false);
ac_assert('cleanup/incomplete no es fresh', strpos($uc_src, 'admission_not_resumable') !== false);
ac_assert('expired → cleanup + admission_expired', strpos($uc_src, 'admission_expired') !== false);
ac_assert('SQL read fail no es ausencia', strpos($uc_src, 'No se pudo leer la admisión') !== false);
ac_assert('DTO público sin credentials', strpos($ajax_src, 'upload_intent') === false
    && strpos($ajax_src, 'signed_url') === false);
ac_assert('store TX fuera de Application', strpos($store_src, 'START TRANSACTION') !== false
    && strpos($uc_src, 'confirm_after_remote_finalize') !== false);
ac_assert('reloj fresco pre-confirm', substr_count($uc_src, 'now_ms()') >= 3
    || (strpos($uc_src, 'now_ms()') !== false && strpos($uc_src, 'confirm_after_transfer') !== false));
ac_assert('images sigue not-ready en bootstrap catálogo', strpos(
    file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'),
    "'images'"
) !== false && preg_match("/'images'[\s\S]{0,120}false/", file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'
)) === 1);

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoJpegValidator.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadPersistenceFailed.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadSchemaNotReady.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadOperationConflict.php';
require_once $plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage_Failed.php';
require_once $plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalRecordImageConfirmationResult.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalRecordImageConfirmationPort.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalImageUploadTransfer.php';
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
require_once $plugin_root . '/includes/application/canonical/images/UploadCanonicalRecordImageUseCase.php';

$ops_stub = new class {
    public $cleanup_calls = 0;
    public function find_by_operation_id($op) {
        return [
            'upload_operation_id' => $op,
            'record_id' => 99,
            'status' => 'admitted',
            'upload_intent' => 'x',
            'upload_objects_json' => '{}',
            'backend_intent_exp_ms' => 9999999999999,
            'content_sha256' => str_repeat('a', 64),
            'mime_type' => 'image/jpeg',
            'byte_size' => 10,
            'width' => 1,
            'height' => 1,
            'storage_path' => 'p',
        ];
    }
    public function mark_cleanup_needed($op, $at) {
        $this->cleanup_calls++;
    }
    public function insert_admitted(array $row): void {}
    public function delete_by_operation_id($op): int { return 0; }
    public function sum_reserved_byte_size($a, $b, $c = null): int { return 0; }
};

$images_stub = new class {
    public function find_by_upload_operation_id($op) { return null; }
    public function find_by_id($id) { return null; }
    public function insert_confirmed(array $row): int { return 1; }
    public function sum_byte_size_total(): int { return 0; }
};

$purge_stub = new class {
    public function has_blocking_purge($r, $c): bool { return false; }
};

$relational_stub = new class {
    public function resolve_family_id($k) { return 1; }
    public function find_container($f, $c) { return ['id' => $c, 'family_id' => $f]; }
    public function find_record($c, $r) { return ['id' => $r, 'container_id' => $c]; }
};

$lock = new class extends AA_Expediente_Aggregate_Lock {
    public function acquire(string $scope_kind, int $scope_id, int $timeout_seconds = self::DEFAULT_TIMEOUT_SECONDS) {
        return new AA_Expediente_Aggregate_Lock_Lease('k', 1, $scope_kind, $scope_id);
    }
    public function assert_held($lease) { return true; }
    public function release($lease): bool { return true; }
};

$validator = new class {
    public function validate(array $file): array {
        return [
            'ok' => true,
            'tmp_name' => $file['tmp_name'] ?? '',
            'mime_type' => 'image/jpeg',
            'byte_size' => 10,
            'width' => 1,
            'height' => 1,
        ];
    }
};

$cap_registry = new AA_Canonical_Capability_Registry();
$cap_registry->register(new AA_Canonical_Capability_Definition(
    'images',
    AA_Canonical_Capability_Definition::SCOPE_RECORD,
    true
));
$cap_registry->freeze();

$tmp = tempnam(sys_get_temp_dir(), 'aaimg');
file_put_contents($tmp, 'not-really-jpeg-but-hashed');

$uc = new UploadCanonicalRecordImageUseCase(
    $validator,
    new class {
        public function generate($p) { return ['ok' => false]; }
        public function delete_generated($v): void {}
    },
    new class {
        public function authorize_canonical_upload(array $i): array { return ['ok' => false, 'code' => 'x']; }
        public function finalize(string $i): array { return ['ok' => false]; }
    },
    null,
    $lock,
    new AA_Installation_Storage_Usage(static function (): ?int { return 0; }, $images_stub, $ops_stub, static function (): int { return 1000; }),
    $images_stub,
    $ops_stub,
    $purge_stub,
    $relational_stub,
    new class {
        public function find_container_capability($c, $k) { return ['is_active' => 1]; }
    },
    $cap_registry,
    new class implements CanonicalRecordImageConfirmationPort {
        public function confirm_after_remote_finalize(array $payload): CanonicalRecordImageConfirmationResult {
            return CanonicalRecordImageConfirmationResult::failed('x');
        }
    },
    static function (): int { return 1000; }
);

$out = $uc->execute('finance', 1, 5, '550e8400-e29b-41d4-a716-446655440000', [
    'tmp_name' => $tmp,
    'error' => UPLOAD_ERR_OK,
]);
ac_assert('op ajena → conflict', ($out['ok'] ?? true) === false && ($out['code'] ?? '') === 'operation_identity_conflict');
ac_assert('op ajena sin cleanup', $ops_stub->cleanup_calls === 0);

@unlink($tmp);

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
