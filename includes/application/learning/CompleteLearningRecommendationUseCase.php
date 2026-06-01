<?php
/**
 * Complete Learning Recommendation Use Case (solo recomendaciones manuales).
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/LearningRecommendationCatalogValidator.php';
require_once dirname(__DIR__, 2) . '/domain/learning/class-aa-learning-catalog.php';
require_once dirname(__DIR__, 2) . '/repositories/LearningRecommendationStateRepository.php';

final class CompleteLearningRecommendationUseCase {

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

        $definition = $resolved['definition'];

        if (($definition['completion_type'] ?? '') !== AA_Learning_Catalog::COMPLETION_MANUAL) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'not_manual_recommendation',
                    'message' => 'Esta recomendación se completa automáticamente al configurar tu agenda.',
                ],
            ];
        }

        $now = $this->resolve_now();
        $row = LearningRecommendationStateRepository::mark_completed($definition['key'], $now);

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

    private function resolve_now(): string {
        if (function_exists('aa_get_current_datetime')) {
            return aa_get_current_datetime();
        }

        return current_time('mysql');
    }
}
