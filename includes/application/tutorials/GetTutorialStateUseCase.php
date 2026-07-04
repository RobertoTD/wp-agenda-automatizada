<?php
/**
 * Get Tutorial State Use Case — lectura del estado durable de tutoriales.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/TutorialStateRepository.php';

final class GetTutorialStateUseCase {

    /**
     * @return array<string,mixed>
     */
    public function execute(): array {
        return TutorialStateRepository::find();
    }
}
