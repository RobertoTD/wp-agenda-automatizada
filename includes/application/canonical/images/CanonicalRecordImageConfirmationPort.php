<?php
/**
 * Puerto de confirmación SQL de imagen canónica tras finalize remoto (IMG-3b).
 *
 * Application coordina; infraestructura ejecuta la TX. Sin SQL en el Use Case.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalRecordImageConfirmationPort {

    /**
     * @param array{
     *   family_id:int,
     *   container_id:int,
     *   record_id:int,
     *   upload_operation_id:string,
     *   storage_path:string,
     *   content_sha256:string,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int
     * } $payload
     */
    public function confirm_after_remote_finalize(array $payload): CanonicalRecordImageConfirmationResult;
}
