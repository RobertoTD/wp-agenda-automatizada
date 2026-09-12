<?php
/**
 * Get Canonical Record Image Read URL — firma de lectura autorizada (IMG-4).
 *
 * Path solo desde la fila persistida. Sin cuota ni beneficio de subida.
 * Desactivar images bloquea nuevas firmas; no revoca URLs ya emitidas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 3) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}
if (!class_exists('AA_Expediente_Attachment_Read_Url_Validator')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php';
}
if (!class_exists('CanonicalRecordImagesRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRecordImagesRepository.php';
}
if (!class_exists('CanonicalRelationalRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRelationalRepository.php';
}
if (!class_exists('CanonicalCapabilityConfigRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalCapabilityConfigRepository.php';
}
if (!class_exists('AA_Canonical_Capability_Registry_Bootstrap')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__, 2) . '/storage/CanonicalImageUploadPersistenceFailed.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__, 2) . '/storage/CanonicalImageUploadSchemaNotReady.php';
}

final class GetCanonicalRecordImageReadUrlUseCase {

    /** @var object */
    private $backend;

    /** @var AA_Expediente_Attachment_Read_Url_Validator */
    private $url_validator;

    /** @var CanonicalRecordImagesRepository */
    private $images;

    /** @var CanonicalRelationalRepository */
    private $relational;

    /** @var CanonicalCapabilityConfigRepository */
    private $capability_config;

    /** @var AA_Canonical_Capability_Registry */
    private $capability_registry;

    /**
     * @param object|null $backend
     */
    public function __construct(
        $backend = null,
        ?AA_Expediente_Attachment_Read_Url_Validator $url_validator = null,
        $images = null,
        $relational = null,
        $capability_config = null,
        $capability_registry = null
    ) {
        $this->backend = $backend ?: new AA_Expediente_Attachments_Backend_Client();
        $this->url_validator = $url_validator ?: new AA_Expediente_Attachment_Read_Url_Validator();
        $this->images = $images ?: new CanonicalRecordImagesRepository();
        $this->relational = $relational ?: new CanonicalRelationalRepository();
        $this->capability_config = $capability_config ?: new CanonicalCapabilityConfigRepository();
        $this->capability_registry = $capability_registry instanceof AA_Canonical_Capability_Registry
            ? $capability_registry
            : AA_Canonical_Capability_Registry_Bootstrap::instance();
    }

    /**
     * @return array{ok:true,url:string,expires_in:int,variant:string}
     *     |array{ok:false,code:string,message:string,http_status:int}
     */
    public function execute(
        string $family_key,
        int $container_id,
        int $record_id,
        int $image_id,
        string $variant
    ): array {
        if ($container_id < 1 || $record_id < 1 || $image_id < 1) {
            return $this->fail('invalid_payload', 'Identificadores no válidos.', 400);
        }

        if (!ExpedienteAdjuntoVariants::is_allowed_variant($variant)) {
            return $this->fail('variant_invalid', 'Variante de lectura no válida.', 400);
        }

        try {
            $def = $this->capability_registry->get('images');
        } catch (\Throwable $e) {
            return $this->fail('capability_unknown', 'La capacidad images no está registrada.', 409);
        }

        if (!$def->is_ready()) {
            return $this->fail('capability_not_ready', 'La capacidad images aún no está lista.', 409);
        }

        try {
            $assignment = $this->capability_config->find_container_capability($container_id, 'images');
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            return $this->fail('schema_not_ready', 'El esquema canónico no está listo.', 503);
        } catch (\Throwable $e) {
            return $this->fail('persistence_failed', 'No se pudo leer la configuración de capacidades.', 500);
        }

        if ($assignment === null || empty($assignment['is_active'])) {
            return $this->fail(
                'capability_inactive',
                'La capacidad images no está activa en esta lista.',
                409
            );
        }

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

        try {
            $image = $this->images->find_by_id($image_id);
        } catch (CanonicalImageUploadSchemaNotReady $e) {
            return $this->fail('schema_not_ready', 'El esquema canónico no está listo.', 503);
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            return $this->fail('persistence_failed', 'No se pudo leer la imagen.', 500);
        }

        if ($image === null || (int) ($image['record_id'] ?? 0) !== $record_id) {
            return $this->fail('image_not_found', 'Imagen no encontrada.', 404);
        }

        $storage_path = (string) ($image['storage_path'] ?? '');
        if ($storage_path === '') {
            return $this->fail('image_not_found', 'Imagen no encontrada.', 404);
        }

        $signed = $this->backend->sign_read($storage_path, $variant);
        if (!is_array($signed) || empty($signed['ok'])) {
            $code = is_array($signed) ? (string) ($signed['code'] ?? 'sign_read_failed') : 'sign_read_failed';
            $status = 502;
            if ($code === 'object_missing') {
                $status = 404;
            } elseif ($code === 'variant_invalid') {
                $status = 400;
            }

            return $this->fail(
                $code !== '' ? $code : 'sign_read_failed',
                'No se pudo obtener la imagen.',
                $status
            );
        }

        /** @var array<string, mixed> $result */
        $result = $signed['result'] ?? [];
        $url = (string) ($result['url'] ?? '');
        $expires_in = (int) ($result['expires_in'] ?? 0);
        $got_variant = $result['variant'] ?? null;

        if ($url === '' || $expires_in < 1 || !is_string($got_variant) || $got_variant !== $variant) {
            return $this->fail('sign_read_invalid', 'Respuesta de firma incompleta.', 502);
        }

        $validated = $this->url_validator->validate($url, $storage_path, $variant);
        if (empty($validated['ok'])) {
            return $this->fail(
                (string) ($validated['code'] ?? 'signed_url_invalid'),
                'La URL firmada no es válida.',
                502
            );
        }

        return [
            'ok' => true,
            'url' => (string) $validated['url'],
            'expires_in' => $expires_in,
            'variant' => $variant,
        ];
    }

    /**
     * @return array{ok:false,code:string,message:string,http_status:int}
     */
    private function fail(string $code, string $message, int $http_status): array {
        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'http_status' => $http_status,
        ];
    }
}
