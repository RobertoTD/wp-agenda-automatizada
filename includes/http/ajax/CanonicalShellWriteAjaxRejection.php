<?php
/**
 * Canonical Shell Write Ajax Rejection — rechazo tipado del soporte de transporte (SB1-5C1).
 *
 * El soporte no emite JSON ni termina la petición: lanza este rechazo con la terna exacta
 * (código, mensaje de usuario, HTTP) y cada endpoint la traduce con su propio error().
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalShellWriteAjaxRejection extends \RuntimeException {

    /** @var string */
    private $error_code;

    /** @var string */
    private $error_message;

    /** @var int */
    private $http_status;

    public function __construct(string $error_code, string $error_message, int $http_status) {
        parent::__construct('[' . $error_code . '] ' . $error_message);
        $this->error_code = $error_code;
        $this->error_message = $error_message;
        $this->http_status = $http_status;
    }

    public function error_code(): string {
        return $this->error_code;
    }

    public function error_message(): string {
        return $this->error_message;
    }

    public function http_status(): int {
        return $this->http_status;
    }
}
