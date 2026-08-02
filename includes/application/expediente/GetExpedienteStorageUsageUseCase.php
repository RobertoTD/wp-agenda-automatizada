<?php
/**
 * Get Expediente Storage Usage Use Case (MC5d2).
 *
 * Devuelve used_bytes: suma de byte_size de los adjuntos de expediente
 * finalizados de la instalación actual (tabla del prefijo del blog).
 * Solo lectura; sin límites, cuotas ni enforcement. El alcance lo
 * determina autoritativamente el blog actual: no acepta ningún input.
 *
 * Si la suma local no puede calcularse, conserva el contrato informativo
 * histórico devolviendo used_bytes = 0 (este endpoint no aplica cuota).
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteAdjuntosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteAdjuntosRepository.php';
}

final class GetExpedienteStorageUsageUseCase {

    /**
     * @return array{ok:true,used_bytes:int}
     */
    public function execute(): array {
        $used_bytes = ExpedienteAdjuntosRepository::sum_byte_size_total();

        if ($used_bytes === null || $used_bytes < 0) {
            $used_bytes = 0;
        }

        return [
            'ok' => true,
            'used_bytes' => $used_bytes,
        ];
    }
}
