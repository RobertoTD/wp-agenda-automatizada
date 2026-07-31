<?php
/**
 * DTO público único de adjunto de expediente (MC4c).
 *
 * Contrato compartido por listado, attach y sign-read:
 *   { id, width, height, byte_size, created_at }
 *
 * Nunca expone storage_path, upload_operation_id, installation_id,
 * MIME interno, tokens ni firmas.
 */

defined('ABSPATH') or die('No direct access');

final class ExpedienteAdjuntoPublicDto {

    /**
     * @param array<string,mixed>|null $adjunto Fila interna del repositorio.
     * @return array{id:int,width:int,height:int,byte_size:int,created_at:string}|null
     */
    public static function from(?array $adjunto): ?array {
        if (!is_array($adjunto) || empty($adjunto['id'])) {
            return null;
        }

        return [
            'id' => (int) $adjunto['id'],
            'width' => (int) ($adjunto['width'] ?? 0),
            'height' => (int) ($adjunto['height'] ?? 0),
            'byte_size' => (int) ($adjunto['byte_size'] ?? 0),
            'created_at' => (string) ($adjunto['created_at'] ?? ''),
        ];
    }
}
