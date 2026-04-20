<?php
/**
 * AI Module Bootstrap
 *
 * Punto de entrada del bounded context AI.
 *
 * Responsabilidad:
 * - Centralizar el wiring mínimo del backend AI.
 * - Evitar que el bootstrap principal conozca detalles internos del módulo.
 */

defined('ABSPATH') or die('No direct access');

/**
 * Bootstrap ancla del módulo AI.
 */
final class AA_AI_Module {
    /**
     * Registra la conexión mínima del backend AI.
     *
     * @return void
     */
    public static function register() {
        require_once AA_PLUGIN_PATH . 'includes/controllers/ai/admin-ai-chat-controller.php';
        require_once AA_PLUGIN_PATH . 'includes/controllers/ai/admin-ai-confirm-booking-controller.php';

        AA_Admin_AI_Chat_Controller::register();
        AA_Admin_AI_Confirm_Booking_Controller::register();
    }
}
