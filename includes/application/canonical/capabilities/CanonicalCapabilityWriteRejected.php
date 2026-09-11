<?php
/**
 * Rechazo de escritura de capacidad (input inválido u otra guarda de aplicación).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityWriteRejected extends \RuntimeException {

    /** @var string */
    private $error_code;

    /** @var int */
    private $http_status;

    public function __construct(string $error_code, string $message, int $http_status) {
        $this->error_code = $error_code;
        $this->http_status = $http_status;
        parent::__construct($message);
    }

    public function error_code(): string {
        return $this->error_code;
    }

    public function http_status(): int {
        return $this->http_status;
    }
}
