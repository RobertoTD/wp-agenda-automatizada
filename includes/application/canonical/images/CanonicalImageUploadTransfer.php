<?php
/**
 * Transferencia canónica de imagen de registro (PUT + finalize).
 *
 * No autoriza, no persiste admisión ni imagen. Application aporta el plan
 * (intent + objetos con signed_url para pendientes) y los archivos locales.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 3) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}

final class CanonicalImageUploadTransfer {

    /** @var list<string> */
    public const OBJECT_KEYS = ['original', 'summary', 'gallery', 'display'];

    /** @var list<string> */
    public const UPLOAD_ORDER = ['summary', 'gallery', 'display', 'original'];

    /** @var object */
    private $backend;

    /** @var object */
    private $uploader;

    /**
     * @param object $backend  finalize(string): array
     * @param object $uploader put_jpeg(string, string, string): array
     */
    public function __construct(object $backend, object $uploader) {
        $this->backend = $backend;
        $this->uploader = $uploader;
    }

    /**
     * @param array{
     *   upload_intent:string,
     *   storage_path:string,
     *   objects:array<string,array{status:string,signed_url?:string}>,
     *   local_files:array{original:string,summary:string,gallery:string,display:string},
     *   expected_sizes:array{original:int,summary:int,gallery:int,display:int}
     * } $plan
     * @return array{ok:true,storage_path:string,finalize:array<string,mixed>}
     *     |array{ok:false,code:string,message:string}
     */
    public function put_and_finalize(array $plan): array {
        $intent = (string) ($plan['upload_intent'] ?? '');
        $storage_path = (string) ($plan['storage_path'] ?? '');
        $objects = isset($plan['objects']) && is_array($plan['objects']) ? $plan['objects'] : [];
        $local_files = isset($plan['local_files']) && is_array($plan['local_files']) ? $plan['local_files'] : [];
        $expected_sizes = isset($plan['expected_sizes']) && is_array($plan['expected_sizes']) ? $plan['expected_sizes'] : [];

        if ($intent === '' || $storage_path === '') {
            return $this->fail('transfer_failed', 'Plan de transferencia incompleto.');
        }

        foreach (self::OBJECT_KEYS as $key) {
            if (!isset($objects[$key]) || !is_array($objects[$key])) {
                return $this->fail('transfer_failed', 'Plan de objetos incompleto.');
            }
            if (!isset($local_files[$key]) || !is_string($local_files[$key]) || $local_files[$key] === '') {
                return $this->fail('transfer_failed', 'Archivos locales incompletos.');
            }
            if (!isset($expected_sizes[$key]) || !is_int($expected_sizes[$key]) || $expected_sizes[$key] < 1) {
                return $this->fail('transfer_failed', 'Tamaños esperados incompletos.');
            }
        }

        foreach (self::UPLOAD_ORDER as $key) {
            $entry = $objects[$key];
            $status = (string) ($entry['status'] ?? '');
            if ($status === 'already_uploaded') {
                continue;
            }

            if ($status !== 'pending_upload') {
                return $this->fail('transfer_failed', 'Estado de objeto no válido.');
            }

            $signed_url = isset($entry['signed_url']) ? (string) $entry['signed_url'] : '';
            if ($signed_url === '') {
                return $this->fail('transfer_failed', 'Permiso de subida ausente.');
            }

            $binary = @file_get_contents($local_files[$key]);
            if ($binary === false || strlen($binary) !== $expected_sizes[$key]) {
                return $this->fail('read_failed', 'No se pudo leer el archivo temporal.');
            }

            $object_path = $this->derive_object_path($storage_path, $key);
            if ($object_path === null) {
                return $this->fail('path_invalid', 'Path de objeto no válido.');
            }

            $put = $this->uploader->put_jpeg($signed_url, $binary, $object_path);
            unset($signed_url, $binary);

            if (!is_array($put) || empty($put['ok'])) {
                $code = is_array($put) ? (string) ($put['code'] ?? 'upload_failed') : 'upload_failed';
                return $this->fail(
                    $code !== '' ? $code : 'upload_failed',
                    'No se pudo subir la imagen.'
                );
            }
        }

        $finalize = $this->backend->finalize($intent);
        if (!is_array($finalize) || empty($finalize['ok'])) {
            $code = is_array($finalize) ? (string) ($finalize['code'] ?? 'finalize_mismatch') : 'finalize_mismatch';
            return $this->fail(
                $code !== '' ? $code : 'finalize_mismatch',
                'No se pudo confirmar la subida de la imagen.'
            );
        }

        $fin_result = $finalize['result'] ?? null;
        if (!is_array($fin_result)) {
            return $this->fail(
                'finalize_mismatch',
                'La confirmación no coincide con los datos esperados.'
            );
        }

        return [
            'ok' => true,
            'storage_path' => $storage_path,
            'finalize' => $fin_result,
        ];
    }

    private function derive_object_path(string $original_path, string $key): ?string {
        if ($key === 'original') {
            return $original_path;
        }

        if (!ExpedienteAdjuntoVariants::is_allowed_variant($key)) {
            return null;
        }

        if (substr($original_path, -4) !== '.jpg') {
            return null;
        }

        return substr($original_path, 0, -4) . '_' . $key . '.jpg';
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
