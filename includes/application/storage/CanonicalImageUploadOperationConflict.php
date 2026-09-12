<?php
/**
 * Colisión de upload_operation_id al insertar una admisión (insert-only).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Storage
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalImageUploadOperationConflict extends \RuntimeException {
}
