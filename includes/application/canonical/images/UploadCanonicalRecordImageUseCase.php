<?php
/**
 * Upload Canonical Record Image — fresh / resume / transfer / finalize / confirm (IMG-3b).
 *
 * Reloj inyectable consultado en vigencia, reservas y pre-confirmación (sin congelar
 * un único instante para toda la petición). Application no ejecuta control SQL de TX.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteAdjuntoJpegValidator')) {
    require_once dirname(__DIR__, 3) . '/domain/expediente/ExpedienteAdjuntoJpegValidator.php';
}
if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 3) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}
if (!class_exists('AA_Expediente_Aggregate_Lock')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
}
if (!class_exists('AA_Installation_Storage_Usage')) {
    require_once dirname(__DIR__, 3) . '/application/storage/AA_Installation_Storage_Usage.php';
}
if (!class_exists('CanonicalImageUploadTransfer')) {
    require_once __DIR__ . '/CanonicalImageUploadTransfer.php';
}
if (!interface_exists('CanonicalRecordImageConfirmationPort')) {
    require_once __DIR__ . '/CanonicalRecordImageConfirmationPort.php';
}
if (!class_exists('CanonicalRecordImageConfirmationResult')) {
    require_once __DIR__ . '/CanonicalRecordImageConfirmationResult.php';
}
if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/wp/CanonicalSchema.php';
}
if (!class_exists('CanonicalRecordImagesRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRecordImagesRepository.php';
}
if (!class_exists('CanonicalImageUploadOperationsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalImageUploadOperationsRepository.php';
}
if (!class_exists('CanonicalPurgeRunsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalPurgeRunsRepository.php';
}
if (!class_exists('CanonicalRelationalRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRelationalRepository.php';
}
if (!class_exists('CanonicalCapabilityConfigRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalCapabilityConfigRepository.php';
}
if (!class_exists('AA_Expediente_Adjunto_Variant_Generator')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/wp/class-aa-expediente-adjunto-variant-generator.php';
}
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}
if (!class_exists('AA_Expediente_Attachment_Signed_Uploader')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/backend/class-aa-expediente-attachment-signed-uploader.php';
}
if (!class_exists('AA_Canonical_Record_Image_Confirmation_Store')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/canonical/images/class-aa-canonical-record-image-confirmation-store.php';
}
if (!class_exists('AA_Canonical_Capability_Registry_Bootstrap')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
}
if (!class_exists('CanonicalImageUploadOperationConflict')) {
    require_once dirname(__DIR__, 3) . '/application/storage/CanonicalImageUploadOperationConflict.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__, 3) . '/application/storage/CanonicalImageUploadPersistenceFailed.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__, 3) . '/application/storage/CanonicalImageUploadSchemaNotReady.php';
}
if (!class_exists('AA_Installation_Storage_Usage_Failed')) {
    require_once dirname(__DIR__, 3) . '/application/storage/AA_Installation_Storage_Usage_Failed.php';
}

if (!class_exists('CanonicalRecordImagePublicDto')) {
    require_once __DIR__ . '/CanonicalRecordImagePublicDto.php';
}

final class UploadCanonicalRecordImageUseCase {

    private const UUID_V4_RE = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /** @var object */
    private $validator;

    /** @var object */
    private $generator;

    /** @var object */
    private $backend;

    /** @var CanonicalImageUploadTransfer */
    private $transfer;

    /** @var AA_Expediente_Aggregate_Lock */
    private $lock;

    /** @var AA_Installation_Storage_Usage */
    private $storage_usage;

    /** @var CanonicalRecordImagesRepository */
    private $images;

    /** @var CanonicalImageUploadOperationsRepository */
    private $operations;

    /** @var CanonicalPurgeRunsRepository */
    private $purge_runs;

    /** @var CanonicalRelationalRepository */
    private $relational;

    /** @var CanonicalCapabilityConfigRepository */
    private $capability_config;

    /** @var AA_Canonical_Capability_Registry */
    private $capability_registry;

    /** @var CanonicalRecordImageConfirmationPort */
    private $confirmation;

    /** @var callable():int */
    private $clock_ms;

    /**
     * @param callable():int|null $clock_ms
     */
    public function __construct(
        $validator = null,
        $generator = null,
        $backend = null,
        ?CanonicalImageUploadTransfer $transfer = null,
        ?AA_Expediente_Aggregate_Lock $lock = null,
        ?AA_Installation_Storage_Usage $storage_usage = null,
        $images = null,
        $operations = null,
        $purge_runs = null,
        $relational = null,
        $capability_config = null,
        $capability_registry = null,
        ?CanonicalRecordImageConfirmationPort $confirmation = null,
        ?callable $clock_ms = null
    ) {
        $this->validator = $validator ?: new ExpedienteAdjuntoJpegValidator();
        $this->generator = $generator ?: new AA_Expediente_Adjunto_Variant_Generator();
        $this->backend = $backend ?: new AA_Expediente_Attachments_Backend_Client();
        $this->lock = $lock ?: AA_Expediente_Aggregate_Lock::create_default();
        $this->clock_ms = $clock_ms ?: static function (): int {
            return (int) floor(microtime(true) * 1000);
        };
        $this->storage_usage = $storage_usage ?: new AA_Installation_Storage_Usage(
            null,
            null,
            null,
            $this->clock_ms
        );
        $this->images = $images ?: new CanonicalRecordImagesRepository();
        $this->operations = $operations ?: new CanonicalImageUploadOperationsRepository();
        $this->purge_runs = $purge_runs ?: new CanonicalPurgeRunsRepository();
        $this->relational = $relational ?: new CanonicalRelationalRepository();
        $this->capability_config = $capability_config ?: new CanonicalCapabilityConfigRepository();
        $this->capability_registry = $capability_registry instanceof AA_Canonical_Capability_Registry
            ? $capability_registry
            : (class_exists('AA_Canonical_Capability_Registry_Bootstrap')
                ? AA_Canonical_Capability_Registry_Bootstrap::instance()
                : null);
        if ($this->capability_registry === null) {
            throw new \LogicException('[not_bootstrapped] Capability registry required for canonical image upload.');
        }
        $this->confirmation = $confirmation ?: new AA_Canonical_Record_Image_Confirmation_Store(
            $this->images instanceof CanonicalRecordImagesRepository ? $this->images : null,
            $this->operations instanceof CanonicalImageUploadOperationsRepository ? $this->operations : null,
            null,
            $this->clock_ms
        );
        $uploader = new AA_Expediente_Attachment_Signed_Uploader();
        $this->transfer = $transfer ?: new CanonicalImageUploadTransfer($this->backend, $uploader);
    }

    /**
     * @param array<string,mixed> $file
     * @return array{ok:true,image:array{id:int,width:int,height:int,byte_size:int,created_at:string}}
     *     |array{ok:false,code:string,message:string,http_status?:int}
     */
    public function execute(
        string $family_key,
        int $container_id,
        int $record_id,
        string $upload_operation_id,
        array $file
    ): array {
        $operation_id = strtolower(trim($upload_operation_id));
        if ($operation_id === '' || !preg_match(self::UUID_V4_RE, $operation_id)) {
            return $this->fail('invalid_operation_id', 'Identificador de operación no válido.', 400);
        }

        if ($container_id < 1 || $record_id < 1) {
            return $this->fail('invalid_payload', 'Identificadores no válidos.', 400);
        }

        $validated = $this->validator->validate($file);
        if (empty($validated['ok'])) {
            return $this->fail(
                (string) ($validated['code'] ?? 'invalid_file'),
                (string) ($validated['message'] ?? 'Archivo no válido.'),
                400
            );
        }

        $tmp = (string) $validated['tmp_name'];
        $mime = (string) $validated['mime_type'];
        $byte_size = (int) $validated['byte_size'];
        $width = (int) $validated['width'];
        $height = (int) $validated['height'];

        $sha = hash_file('sha256', $tmp);
        if (!is_string($sha) || strlen($sha) !== 64) {
            return $this->fail('invalid_content_sha256', 'No se pudo calcular el fingerprint de la imagen.', 400);
        }
        $sha = strtolower($sha);

        try {
            $family_id = $this->relational->resolve_family_id($family_key);
        } catch (\Throwable $e) {
            return $this->fail('persistence_failed', 'No se pudo resolver la familia.', 500);
        }

        if ($family_id === null || $family_id < 1) {
            return $this->fail('family_not_provisioned', 'La familia no está provisionada.', 409);
        }

        try {
            $container = $this->relational->find_container($family_id, $container_id);
            if ($container === null) {
                return $this->fail('container_not_found', 'El contenedor no existe.', 404);
            }
            $record = $this->relational->find_record($container_id, $record_id);
            if ($record === null) {
                return $this->fail('record_not_found', 'El registro no existe.', 404);
            }
        } catch (\Throwable $e) {
            return $this->fail('persistence_failed', 'No se pudo verificar la pertenencia.', 500);
        }

        $container_lease = $this->lock->acquire(
            AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER,
            $container_id,
            AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
        );
        if (is_wp_error($container_lease)) {
            return $this->fail_from_lock($container_lease);
        }

        $quota_lease = null;
        try {
            try {
                if ($this->purge_runs->has_blocking_purge($record_id, $container_id)) {
                    return $this->fail('purge_in_progress', 'Hay una eliminación en curso sobre este recurso.', 409);
                }
            } catch (CanonicalImageUploadSchemaNotReady $e) {
                return $this->fail('schema_not_ready', 'El esquema canónico no está listo.', 503);
            } catch (CanonicalImageUploadPersistenceFailed $e) {
                return $this->fail('persistence_failed', 'No se pudo consultar el estado de purge.', 500);
            }

            $held = $this->lock->assert_held($container_lease);
            if (is_wp_error($held)) {
                return $this->fail_from_lock($held);
            }

            $quota_lease = $this->lock->acquire(
                AA_Expediente_Aggregate_Lock::SCOPE_STORAGE_QUOTA,
                AA_Expediente_Aggregate_Lock::STORAGE_QUOTA_SCOPE_ID,
                AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
            );
            if (is_wp_error($quota_lease)) {
                return $this->fail_from_lock($quota_lease);
            }

            $held_quota = $this->lock->assert_held($quota_lease);
            if (is_wp_error($held_quota)) {
                return $this->fail_from_lock($held_quota);
            }

            return $this->resolve_under_locks(
                $family_key,
                $family_id,
                $container_id,
                $record_id,
                $operation_id,
                $tmp,
                $mime,
                $byte_size,
                $width,
                $height,
                $sha,
                $container_lease,
                $quota_lease
            );
        } finally {
            if ($quota_lease !== null && !is_wp_error($quota_lease)) {
                $this->lock->release($quota_lease);
            }
            $this->lock->release($container_lease);
        }
    }

    /**
     * @param AA_Expediente_Aggregate_Lock_Lease $container_lease
     * @param AA_Expediente_Aggregate_Lock_Lease $quota_lease
     * @return array{ok:true,image:array{id:int,width:int,height:int,byte_size:int,created_at:string}}
     *     |array{ok:false,code:string,message:string,http_status?:int}
     */
    private function resolve_under_locks(
        string $family_key,
        int $family_id,
        int $container_id,
        int $record_id,
        string $operation_id,
        string $tmp,
        string $mime,
        int $byte_size,
        int $width,
        int $height,
        string $sha,
        $container_lease,
        $quota_lease
    ): array {
        try {
            $image = $this->images->find_by_upload_operation_id($operation_id);
        } catch (CanonicalImageUploadSchemaNotReady $e) {
            return $this->fail('schema_not_ready', 'El esquema canónico no está listo.', 503);
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            return $this->fail('persistence_failed', 'No se pudo leer la imagen confirmada.', 500);
        }

        if ($image !== null) {
            if ($this->image_matches($image, $record_id, $sha, $mime, $byte_size, $width, $height)) {
                return $this->success_from_row($image);
            }

            return $this->fail('image_identity_conflict', 'La operación ya confirma otra imagen.', 409);
        }

        try {
            $ops = $this->operations->find_by_operation_id($operation_id);
        } catch (CanonicalImageUploadSchemaNotReady $e) {
            return $this->fail('schema_not_ready', 'El esquema canónico no está listo.', 503);
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            return $this->fail('persistence_failed', 'No se pudo leer la admisión.', 500);
        }

        if ($ops === null) {
            return $this->run_fresh(
                $family_key,
                $family_id,
                $container_id,
                $record_id,
                $operation_id,
                $tmp,
                $mime,
                $byte_size,
                $width,
                $height,
                $sha,
                $container_lease,
                $quota_lease
            );
        }

        if ((int) ($ops['record_id'] ?? 0) !== $record_id) {
            return $this->fail('operation_identity_conflict', 'La operación pertenece a otro registro.', 409);
        }

        $status = (string) ($ops['status'] ?? '');
        $intent = isset($ops['upload_intent']) ? trim((string) $ops['upload_intent']) : '';
        $objects_json = isset($ops['upload_objects_json']) ? trim((string) $ops['upload_objects_json']) : '';
        $complete = ($intent !== '' && $objects_json !== ''
            && isset($ops['backend_intent_exp_ms'])
            && $ops['backend_intent_exp_ms'] !== null
            && $ops['backend_intent_exp_ms'] !== '');

        if ($status === AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_CLEANUP_NEEDED || !$complete) {
            return $this->fail('admission_not_resumable', 'La admisión no es resumible.', 409);
        }

        $now_ms = $this->now_ms();
        if ((int) $ops['backend_intent_exp_ms'] <= $now_ms) {
            $this->mark_owned_cleanup($operation_id, $record_id);
            return $this->fail('admission_expired', 'La admisión ha caducado.', 409);
        }

        if ($status !== AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED) {
            return $this->fail('admission_not_resumable', 'La admisión no es resumible.', 409);
        }

        return $this->run_resume(
            $family_id,
            $container_id,
            $record_id,
            $operation_id,
            $ops,
            $tmp,
            $mime,
            $byte_size,
            $width,
            $height,
            $sha,
            $container_lease,
            $quota_lease
        );
    }

    /**
     * @param AA_Expediente_Aggregate_Lock_Lease $container_lease
     * @param AA_Expediente_Aggregate_Lock_Lease $quota_lease
     * @return array{ok:true,image:array{id:int,width:int,height:int,byte_size:int,created_at:string}}
     *     |array{ok:false,code:string,message:string,http_status?:int}
     */
    private function run_fresh(
        string $family_key,
        int $family_id,
        int $container_id,
        int $record_id,
        string $operation_id,
        string $tmp,
        string $mime,
        int $byte_size,
        int $width,
        int $height,
        string $sha,
        $container_lease,
        $quota_lease
    ): array {
        $cap = $this->assert_fresh_capability($family_key, $container_id);
        if ($cap !== null) {
            return $cap;
        }

        $variants = [];
        try {
            $generated = $this->generator->generate($tmp);
            if (!is_array($generated) || empty($generated['ok'])) {
                return $this->fail('variant_generation_failed', 'No se pudo generar las variantes.', 500);
            }
            $raw = isset($generated['variants']) && is_array($generated['variants']) ? $generated['variants'] : [];
            $variants = $raw;
            $checked = $this->checked_variants($raw);
            if ($checked === null) {
                return $this->fail('variant_generation_failed', 'No se pudo generar las variantes.', 500);
            }

            try {
                $used_bytes = $this->storage_usage->admission_used_bytes(null);
            } catch (AA_Installation_Storage_Usage_Failed $e) {
                return $this->fail('storage_usage_unavailable', 'No se pudo verificar el espacio disponible.', 500);
            }

            $held = $this->assert_locks($container_lease, $quota_lease);
            if ($held !== null) {
                return $held;
            }

            $authorize = $this->backend->authorize_canonical_upload([
                'upload_operation_id' => $operation_id,
                'wp_record_id' => $record_id,
                'mime_type' => $mime,
                'byte_size' => $byte_size,
                'width' => $width,
                'height' => $height,
                'content_sha256' => $sha,
                'used_bytes' => $used_bytes,
                'variants_manifest_version' => ExpedienteAdjuntoVariants::MANIFEST_VERSION,
                'variant_byte_sizes' => [
                    'summary' => $checked['summary']['byte_size'],
                    'gallery' => $checked['gallery']['byte_size'],
                    'display' => $checked['display']['byte_size'],
                ],
            ]);

            if (!is_array($authorize) || empty($authorize['ok'])) {
                return $this->map_backend_failure($authorize, 'authorize_invalid', 'No se pudo autorizar la subida.');
            }

            /** @var array<string,mixed> $auth */
            $auth = $authorize['result'];
            $plan_objects = $this->require_fresh_objects($auth['objects'] ?? null);
            if ($plan_objects === null) {
                return $this->fail('authorize_invalid', 'Respuesta de autorización incompleta.', 502);
            }

            $upload_intent = (string) ($auth['upload_intent'] ?? '');
            $storage_path = (string) ($auth['storage_path'] ?? '');
            $exp_ms = isset($auth['admission_expires_at_ms']) ? (int) $auth['admission_expires_at_ms'] : 0;
            if ($upload_intent === '' || $storage_path === '' || $exp_ms < 1) {
                return $this->fail('authorize_invalid', 'Respuesta de autorización incompleta.', 502);
            }

            $manifest = [
                'urls' => [
                    'original' => $plan_objects['original']['signed_url'],
                    'summary' => $plan_objects['summary']['signed_url'],
                    'gallery' => $plan_objects['gallery']['signed_url'],
                    'display' => $plan_objects['display']['signed_url'],
                ],
                'variant_byte_sizes' => [
                    'summary' => $checked['summary']['byte_size'],
                    'gallery' => $checked['gallery']['byte_size'],
                    'display' => $checked['display']['byte_size'],
                ],
            ];

            $now_ms = $this->now_ms();
            $now_utc = AA_Installation_Storage_Usage::utc_datetime_from_ms($now_ms);
            $expires_at = AA_Installation_Storage_Usage::expires_at_from_intent_exp_ms($exp_ms);

            try {
                $this->operations->insert_admitted([
                    'upload_operation_id' => $operation_id,
                    'record_id' => $record_id,
                    'storage_path' => $storage_path,
                    'content_sha256' => $sha,
                    'mime_type' => $mime,
                    'byte_size' => $byte_size,
                    'width' => $width,
                    'height' => $height,
                    'status' => AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED,
                    'expires_at' => $expires_at,
                    'backend_intent_exp_ms' => $exp_ms,
                    'upload_intent' => $upload_intent,
                    'upload_objects_json' => wp_json_encode($manifest),
                    'created_at' => $now_utc,
                    'updated_at' => $now_utc,
                ]);
            } catch (CanonicalImageUploadOperationConflict $e) {
                return $this->resolve_under_locks(
                    $family_key,
                    $family_id,
                    $container_id,
                    $record_id,
                    $operation_id,
                    $tmp,
                    $mime,
                    $byte_size,
                    $width,
                    $height,
                    $sha,
                    $container_lease,
                    $quota_lease
                );
            } catch (CanonicalImageUploadSchemaNotReady $e) {
                return $this->fail('schema_not_ready', 'El esquema canónico no está listo.', 503);
            } catch (CanonicalImageUploadPersistenceFailed $e) {
                return $this->fail('persistence_failed', 'No se pudo persistir la admisión.', 500);
            }

            $held_after_auth = $this->assert_locks($container_lease, $quota_lease);
            if ($held_after_auth !== null) {
                return $held_after_auth;
            }

            $transferred = $this->transfer->put_and_finalize([
                'upload_intent' => $upload_intent,
                'storage_path' => $storage_path,
                'objects' => $plan_objects,
                'local_files' => [
                    'original' => $tmp,
                    'summary' => $checked['summary']['path'],
                    'gallery' => $checked['gallery']['path'],
                    'display' => $checked['display']['path'],
                ],
                'expected_sizes' => [
                    'original' => $byte_size,
                    'summary' => $checked['summary']['byte_size'],
                    'gallery' => $checked['gallery']['byte_size'],
                    'display' => $checked['display']['byte_size'],
                ],
            ]);

            if (empty($transferred['ok'])) {
                return $this->fail(
                    (string) ($transferred['code'] ?? 'transfer_failed'),
                    (string) ($transferred['message'] ?? 'Fallo de transferencia.'),
                    502
                );
            }

            return $this->confirm_after_transfer(
                $family_id,
                $container_id,
                $record_id,
                $operation_id,
                $storage_path,
                $sha,
                $mime,
                $byte_size,
                $width,
                $height,
                $container_lease,
                $quota_lease
            );
        } finally {
            $this->generator->delete_generated($variants);
        }
    }

    /**
     * @param array<string,mixed> $ops
     * @param AA_Expediente_Aggregate_Lock_Lease $container_lease
     * @param AA_Expediente_Aggregate_Lock_Lease $quota_lease
     * @return array{ok:true,image:array{id:int,width:int,height:int,byte_size:int,created_at:string}}
     *     |array{ok:false,code:string,message:string,http_status?:int}
     */
    private function run_resume(
        int $family_id,
        int $container_id,
        int $record_id,
        string $operation_id,
        array $ops,
        string $tmp,
        string $mime,
        int $byte_size,
        int $width,
        int $height,
        string $sha,
        $container_lease,
        $quota_lease
    ): array {
        if (
            strtolower((string) ($ops['content_sha256'] ?? '')) !== $sha
            || (string) ($ops['mime_type'] ?? '') !== $mime
            || (int) ($ops['byte_size'] ?? 0) !== $byte_size
            || (int) ($ops['width'] ?? 0) !== $width
            || (int) ($ops['height'] ?? 0) !== $height
        ) {
            return $this->fail('image_identity_conflict', 'Los metadatos no coinciden con la admisión.', 409);
        }

        $persisted_intent = trim((string) $ops['upload_intent']);
        $manifest = json_decode((string) $ops['upload_objects_json'], true);
        if (!is_array($manifest) || !isset($manifest['urls'], $manifest['variant_byte_sizes'])) {
            return $this->fail('admission_not_resumable', 'Manifiesto de admisión inválido.', 409);
        }

        $urls = $manifest['urls'];
        $variant_sizes = $manifest['variant_byte_sizes'];
        if (!is_array($urls) || !is_array($variant_sizes)) {
            return $this->fail('admission_not_resumable', 'Manifiesto de admisión inválido.', 409);
        }

        foreach (['original', 'summary', 'gallery', 'display'] as $key) {
            if (!isset($urls[$key]) || !is_string($urls[$key]) || $urls[$key] === '') {
                return $this->fail('admission_not_resumable', 'Manifiesto de admisión inválido.', 409);
            }
        }
        foreach (['summary', 'gallery', 'display'] as $key) {
            if (!isset($variant_sizes[$key]) || !is_int($variant_sizes[$key]) || $variant_sizes[$key] < 1) {
                if (isset($variant_sizes[$key]) && is_numeric($variant_sizes[$key])) {
                    $variant_sizes[$key] = (int) $variant_sizes[$key];
                } else {
                    return $this->fail('admission_not_resumable', 'Manifiesto de admisión inválido.', 409);
                }
            }
        }

        $variants = [];
        try {
            $generated = $this->generator->generate($tmp);
            if (!is_array($generated) || empty($generated['ok'])) {
                return $this->fail('variant_generation_failed', 'No se pudo generar las variantes.', 500);
            }
            $raw = isset($generated['variants']) && is_array($generated['variants']) ? $generated['variants'] : [];
            $variants = $raw;
            $checked = $this->checked_variants($raw);
            if ($checked === null) {
                return $this->fail('variant_generation_failed', 'No se pudo generar las variantes.', 500);
            }

            try {
                $used_bytes = $this->storage_usage->admission_used_bytes($operation_id);
            } catch (AA_Installation_Storage_Usage_Failed $e) {
                return $this->fail('storage_usage_unavailable', 'No se pudo verificar el espacio disponible.', 500);
            }

            $held = $this->assert_locks($container_lease, $quota_lease);
            if ($held !== null) {
                return $held;
            }

            $authorize = $this->backend->authorize_canonical_upload([
                'upload_operation_id' => $operation_id,
                'wp_record_id' => $record_id,
                'mime_type' => $mime,
                'byte_size' => $byte_size,
                'width' => $width,
                'height' => $height,
                'content_sha256' => $sha,
                'used_bytes' => $used_bytes,
                'variants_manifest_version' => ExpedienteAdjuntoVariants::MANIFEST_VERSION,
                'variant_byte_sizes' => [
                    'summary' => (int) $variant_sizes['summary'],
                    'gallery' => (int) $variant_sizes['gallery'],
                    'display' => (int) $variant_sizes['display'],
                ],
                'prior_upload_intent' => $persisted_intent,
            ]);

            if (!is_array($authorize) || empty($authorize['ok'])) {
                return $this->map_backend_failure($authorize, 'authorize_invalid', 'No se pudo reanudar la subida.');
            }

            /** @var array<string,mixed> $auth */
            $auth = $authorize['result'];
            if ((string) ($auth['upload_intent'] ?? '') !== $persisted_intent) {
                return $this->fail('authorize_invalid', 'El intent remoto no coincide con la admisión.', 409);
            }

            $remote_exp = isset($auth['admission_expires_at_ms']) ? (int) $auth['admission_expires_at_ms'] : 0;
            if ($remote_exp !== (int) $ops['backend_intent_exp_ms']) {
                return $this->fail('authorize_invalid', 'El vencimiento remoto no coincide con la admisión.', 409);
            }

            $remote_objects = isset($auth['objects']) && is_array($auth['objects']) ? $auth['objects'] : [];
            $plan_objects = [];
            foreach (CanonicalImageUploadTransfer::OBJECT_KEYS as $key) {
                if (!isset($remote_objects[$key]) || !is_array($remote_objects[$key])) {
                    return $this->fail('authorize_invalid', 'Estados de objetos incompletos.', 502);
                }
                $status = (string) ($remote_objects[$key]['status'] ?? '');
                if ($status === 'already_uploaded') {
                    $plan_objects[$key] = ['status' => 'already_uploaded'];
                    continue;
                }
                if ($status !== 'pending_upload') {
                    return $this->fail('authorize_invalid', 'Estado de objeto no válido.', 502);
                }
                if ($key !== 'original' && (int) $checked[$key]['byte_size'] !== (int) $variant_sizes[$key]) {
                    return $this->fail(
                        'variant_manifest_mismatch',
                        'Las variantes regeneradas no coinciden con el manifiesto admitido.',
                        409
                    );
                }
                $plan_objects[$key] = [
                    'status' => 'pending_upload',
                    'signed_url' => (string) $urls[$key],
                ];
            }

            $held_before_put = $this->assert_locks($container_lease, $quota_lease);
            if ($held_before_put !== null) {
                return $held_before_put;
            }

            $transferred = $this->transfer->put_and_finalize([
                'upload_intent' => $persisted_intent,
                'storage_path' => (string) $ops['storage_path'],
                'objects' => $plan_objects,
                'local_files' => [
                    'original' => $tmp,
                    'summary' => $checked['summary']['path'],
                    'gallery' => $checked['gallery']['path'],
                    'display' => $checked['display']['path'],
                ],
                'expected_sizes' => [
                    'original' => $byte_size,
                    'summary' => $checked['summary']['byte_size'],
                    'gallery' => $checked['gallery']['byte_size'],
                    'display' => $checked['display']['byte_size'],
                ],
            ]);

            if (empty($transferred['ok'])) {
                return $this->fail(
                    (string) ($transferred['code'] ?? 'transfer_failed'),
                    (string) ($transferred['message'] ?? 'Fallo de transferencia.'),
                    502
                );
            }

            return $this->confirm_after_transfer(
                $family_id,
                $container_id,
                $record_id,
                $operation_id,
                (string) $ops['storage_path'],
                $sha,
                $mime,
                $byte_size,
                $width,
                $height,
                $container_lease,
                $quota_lease
            );
        } finally {
            $this->generator->delete_generated($variants);
        }
    }

    /**
     * @param AA_Expediente_Aggregate_Lock_Lease $container_lease
     * @param AA_Expediente_Aggregate_Lock_Lease $quota_lease
     * @return array{ok:true,image:array{id:int,width:int,height:int,byte_size:int,created_at:string}}
     *     |array{ok:false,code:string,message:string,http_status?:int}
     */
    private function confirm_after_transfer(
        int $family_id,
        int $container_id,
        int $record_id,
        string $operation_id,
        string $storage_path,
        string $sha,
        string $mime,
        int $byte_size,
        int $width,
        int $height,
        $container_lease,
        $quota_lease
    ): array {
        $held = $this->assert_locks($container_lease, $quota_lease);
        if ($held !== null) {
            return $held;
        }

        $now_ms = $this->now_ms();
        try {
            $ops = $this->operations->find_by_operation_id($operation_id);
        } catch (\Throwable $e) {
            return $this->fail('persistence_failed', 'No se pudo revalidar la admisión.', 500);
        }

        if ($ops === null) {
            try {
                $existing = $this->images->find_by_upload_operation_id($operation_id);
            } catch (\Throwable $e) {
                return $this->fail('uncertain', 'No fue posible confirmar si la imagen quedó registrada.', 409);
            }
            if ($existing !== null && $this->image_matches($existing, $record_id, $sha, $mime, $byte_size, $width, $height)) {
                return $this->success_from_row($existing);
            }

            return $this->fail('uncertain', 'No fue posible confirmar si la imagen quedó registrada.', 409);
        }

        if ((int) ($ops['record_id'] ?? 0) !== $record_id) {
            return $this->fail('operation_identity_conflict', 'La operación pertenece a otro registro.', 409);
        }

        if (
            (string) ($ops['status'] ?? '') !== AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED
            || !isset($ops['backend_intent_exp_ms'])
            || (int) $ops['backend_intent_exp_ms'] <= $now_ms
        ) {
            $this->mark_owned_cleanup($operation_id, $record_id);
            return $this->fail(
                'admission_expired_after_finalize',
                'La admisión caducó tras el finalize remoto; no se confirmó la imagen.',
                409
            );
        }

        $result = $this->confirmation->confirm_after_remote_finalize([
            'family_id' => $family_id,
            'container_id' => $container_id,
            'record_id' => $record_id,
            'upload_operation_id' => $operation_id,
            'storage_path' => $storage_path,
            'content_sha256' => $sha,
            'mime_type' => $mime,
            'byte_size' => $byte_size,
            'width' => $width,
            'height' => $height,
        ]);

        if ($result->outcome() === CanonicalRecordImageConfirmationResult::OUTCOME_CONFIRMED) {
            $dto = $result->dto();
            if ($dto === null) {
                return $this->fail('persistence_failed', 'Confirmación sin DTO.', 500);
            }

            return [
                'ok' => true,
                'image' => $dto,
            ];
        }

        if ($result->outcome() === CanonicalRecordImageConfirmationResult::OUTCOME_UNCERTAIN) {
            return $this->fail(
                'uncertain',
                'No fue posible confirmar si la imagen quedó registrada. Revisa antes de reintentar.',
                409
            );
        }

        $code = (string) ($result->failure_code() ?? 'persistence_failed');
        if ($code === 'admission_expired') {
            $this->mark_owned_cleanup($operation_id, $record_id);
            return $this->fail(
                'admission_expired_after_finalize',
                'La admisión caducó tras el finalize remoto; no se confirmó la imagen.',
                409
            );
        }
        if ($code === 'purge_in_progress') {
            return $this->fail('purge_in_progress', 'Hay una eliminación en curso sobre este recurso.', 409);
        }

        $status = ($code === 'image_identity_conflict' || $code === 'operation_identity_conflict') ? 409 : 500;

        return $this->fail(
            $code === 'persistence_failed' ? 'persistence_failed' : $code,
            'No se pudo confirmar la imagen en base de datos.',
            $status
        );
    }

    /**
     * @return array{ok:false,code:string,message:string,http_status:int}|null
     */
    private function assert_fresh_capability(string $family_key, int $container_id): ?array {
        try {
            $definition = $this->capability_registry->get('images');
        } catch (\Throwable $e) {
            return $this->fail('capability_unknown', 'La capacidad images no está registrada.', 409);
        }

        if (!$definition->is_ready()) {
            return $this->fail('capability_not_ready', 'La capacidad images aún no está lista.', 409);
        }

        try {
            $row = $this->capability_config->find_container_capability($container_id, 'images');
        } catch (\Throwable $e) {
            return $this->fail('persistence_failed', 'No se pudo leer la configuración de capacidades.', 500);
        }

        if ($row === null || empty($row['is_active'])) {
            return $this->fail('capability_inactive', 'La capacidad images no está activa en esta lista.', 409);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,array{path:string,byte_size:int}>|null
     */
    private function checked_variants(array $raw): ?array {
        $checked = [];
        foreach (ExpedienteAdjuntoVariants::ALLOWED_VARIANTS as $name) {
            if (!isset($raw[$name]) || !is_array($raw[$name])) {
                return null;
            }
            $path = isset($raw[$name]['path']) ? (string) $raw[$name]['path'] : '';
            $byte_size = $raw[$name]['byte_size'] ?? null;
            if ($path === '' || !is_int($byte_size) || $byte_size < 1) {
                return null;
            }
            $checked[$name] = [
                'path' => $path,
                'byte_size' => $byte_size,
            ];
        }

        return $checked;
    }

    /**
     * @param mixed $objects
     * @return array<string,array{status:string,signed_url:string}>|null
     */
    private function require_fresh_objects($objects): ?array {
        if (!is_array($objects)) {
            return null;
        }
        $out = [];
        foreach (CanonicalImageUploadTransfer::OBJECT_KEYS as $key) {
            if (!isset($objects[$key]) || !is_array($objects[$key])) {
                return null;
            }
            if ((string) ($objects[$key]['status'] ?? '') !== 'pending_upload') {
                return null;
            }
            $url = isset($objects[$key]['signed_url']) ? (string) $objects[$key]['signed_url'] : '';
            if ($url === '') {
                return null;
            }
            $out[$key] = [
                'status' => 'pending_upload',
                'signed_url' => $url,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $image
     */
    private function image_matches(
        array $image,
        int $record_id,
        string $sha,
        string $mime,
        int $byte_size,
        int $width,
        int $height
    ): bool {
        return (int) ($image['record_id'] ?? 0) === $record_id
            && strtolower((string) ($image['content_sha256'] ?? '')) === $sha
            && (string) ($image['mime_type'] ?? '') === $mime
            && (int) ($image['byte_size'] ?? 0) === $byte_size
            && (int) ($image['width'] ?? 0) === $width
            && (int) ($image['height'] ?? 0) === $height;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{ok:true,image:array{id:int,width:int,height:int,byte_size:int,created_at:string}}
     */
    private function success_from_row(array $row): array {
        return [
            'ok' => true,
            'image' => CanonicalRecordImagePublicDto::from_row($row),
        ];
    }

    private function mark_owned_cleanup(string $operation_id, int $record_id): void {
        try {
            $row = $this->operations->find_by_operation_id($operation_id);
            if ($row === null || (int) ($row['record_id'] ?? 0) !== $record_id) {
                return;
            }
            $now_utc = AA_Installation_Storage_Usage::utc_datetime_from_ms($this->now_ms());
            $this->operations->mark_cleanup_needed($operation_id, $now_utc);
        } catch (\Throwable $e) {
            // Fail-closed en el caller; no mutar en silencio identidades ajenas.
        }
    }

    /**
     * @return array{ok:false,code:string,message:string,http_status:int}|null
     */
    private function assert_locks($container_lease, $quota_lease): ?array {
        $held = $this->lock->assert_held($container_lease);
        if (is_wp_error($held)) {
            return $this->fail_from_lock($held);
        }
        $held_q = $this->lock->assert_held($quota_lease);
        if (is_wp_error($held_q)) {
            return $this->fail_from_lock($held_q);
        }

        return null;
    }

    /**
     * @param mixed $authorize
     * @return array{ok:false,code:string,message:string,http_status:int}
     */
    private function map_backend_failure($authorize, string $fallback_code, string $fallback_message): array {
        $code = is_array($authorize) ? (string) ($authorize['code'] ?? $fallback_code) : $fallback_code;
        $message = $fallback_message;
        if ($code === 'storage_quota_exceeded') {
            $message = 'No queda espacio de almacenamiento.';
        } elseif ($code === 'storage_not_included') {
            $message = 'El plan actual no incluye almacenamiento.';
        }

        $status = 502;
        if (in_array($code, ['storage_quota_exceeded', 'storage_not_included'], true)) {
            $status = 409;
        }

        return $this->fail($code !== '' ? $code : $fallback_code, $message, $status);
    }

    /**
     * @param \WP_Error $error
     * @return array{ok:false,code:string,message:string,http_status:int}
     */
    private function fail_from_lock($error): array {
        $code = $error->get_error_code();
        $message = $error->get_error_message();
        $status = ($code === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY) ? 409 : 500;

        return $this->fail(is_string($code) ? $code : 'coordination_failed', (string) $message, $status);
    }

    private function now_ms(): int {
        return (int) call_user_func($this->clock_ms);
    }

    /**
     * @return array{ok:false,code:string,message:string,http_status:int}
     */
    private function fail(string $code, string $message, int $http_status = 400): array {
        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'http_status' => $http_status,
        ];
    }
}
