<?php
/**
 * Dismiss Learning Recommendation Use Case — ocultar recomendación en lista 2 ("Ignorar").
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/LearningRecommendationCatalogValidator.php';
require_once __DIR__ . '/LearningRecommendationEvaluator.php';
require_once dirname(__DIR__, 2) . '/domain/learning/class-aa-learning-visibility-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/LearningRecommendationStateRepository.php';

final class DismissLearningRecommendationUseCase {

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

        $key = $resolved['definition']['key'];
        $evaluated = LearningRecommendationEvaluator::evaluate_key($key);

        if ($evaluated === null) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'unknown_recommendation',
                    'message' => 'Recomendación no encontrada.',
                ],
            ];
        }

        if (empty($evaluated['visible'])) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'recommendation_not_active',
                    'message' => 'Esta recomendación ya no está activa.',
                ],
            ];
        }

        if ((int) ($evaluated['effective_list'] ?? 0) !== AA_Learning_Visibility_Policy::LIST_SECONDARY) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'not_in_secondary_list',
                    'message' => 'Solo puedes ignorar recomendaciones en Otras sugerencias.',
                ],
            ];
        }

        $now = LearningRecommendationEvaluator::resolve_now();
        $row = LearningRecommendationStateRepository::mark_dismissed($key, $now);

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
