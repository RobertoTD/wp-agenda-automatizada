<?php
/**
 * Get Expediente Storage Usage Use Case (MC5d2 / IMG-3a).
 *
 * Devuelve used_bytes = confirmed_bytes (legacy + canónicos confirmados).
 * No incluye reservas. Solo lectura; sin límites ni enforcement.
 * El alcance lo determina el blog actual: no acepta input del navegador.
 *
 * Fallo de suma → ok:false + storage_usage_unavailable (no fingir cero).
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Installation_Storage_Usage')) {
    require_once dirname(__DIR__) . '/storage/AA_Installation_Storage_Usage.php';
}
if (!class_exists('AA_Installation_Storage_Usage_Failed')) {
    require_once dirname(__DIR__) . '/storage/AA_Installation_Storage_Usage_Failed.php';
}

final class GetExpedienteStorageUsageUseCase {

    /** @var AA_Installation_Storage_Usage|object */
    private $storage_usage;

    /**
     * @param AA_Installation_Storage_Usage|object|null $storage_usage
     */
    public function __construct($storage_usage = null) {
        $this->storage_usage = is_object($storage_usage)
            ? $storage_usage
            : AA_Installation_Storage_Usage::create_default();
    }

    /**
     * @return array{ok:true,used_bytes:int}|array{ok:false,code:string,message:string}
     */
    public function execute(): array {
        try {
            $used_bytes = $this->storage_usage->confirmed_bytes();
        } catch (AA_Installation_Storage_Usage_Failed $e) {
            return [
                'ok' => false,
                'code' => 'storage_usage_unavailable',
                'message' => 'No se pudo verificar el espacio utilizado.',
            ];
        }

        if (!is_int($used_bytes) || $used_bytes < 0) {
            return [
                'ok' => false,
                'code' => 'storage_usage_unavailable',
                'message' => 'No se pudo verificar el espacio utilizado.',
            ];
        }

        return [
            'ok' => true,
            'used_bytes' => $used_bytes,
        ];
    }
}
