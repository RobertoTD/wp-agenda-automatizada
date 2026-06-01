<?php
/**
 * Reactivate Learning Recommendation Use Case
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/LearningRecommendationCatalogValidator.php';
require_once dirname(__DIR__, 2) . '/repositories/LearningRecommendationStateRepository.php';

final class ReactivateLearningRecommendationUseCase {

    /**
     * @param string $recommendation_key
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(string $recommendation_key): array {
        $resolved = LearningRecommendationCatalogValidator::resolve($recommendation_key);

        if (!$resolved['ok']) {
            return [
                'success' => false,
                'error' => [
                    'code' => $resolved['code'],
                    'message' => $resolved['message'],
                ],
            ];
        }

        $row = LearningRecommendationStateRepository::reactivate($resolved['definition']['key']);

        if ($row === null) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'persistence_failed',
                    'message' => 'No se pudo guardar el estado.',
                ],
            ];
        }

        return [
            'success' => true,
            'data' => ['recommendation_key' => $row['recommendation_key']],
        ];
    }
}
