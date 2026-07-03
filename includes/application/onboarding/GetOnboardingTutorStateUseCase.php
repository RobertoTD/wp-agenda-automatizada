<?php
/**
 * Get Onboarding Tutor State Use Case — lectura del estado durable UX del tutor.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/OnboardingTutorStateRepository.php';

final class GetOnboardingTutorStateUseCase {

    /**
     * @return array<string,mixed>
     */
    public function execute(): array {
        return OnboardingTutorStateRepository::find();
    }
}
