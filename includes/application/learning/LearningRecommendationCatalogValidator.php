<?php
/**
 * Valida recommendation_key contra el catálogo versionado.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/learning/class-aa-learning-catalog.php';

final class LearningRecommendationCatalogValidator {

    /**
     * @param string $recommendation_key
     * @return array{ok:true,definition:array<string,mixed>}|array{ok:false,code:string,message:string}
     */
    public static function resolve(string $recommendation_key): array {
        $key = is_string($recommendation_key) ? sanitize_key(trim($recommendation_key)) : '';

        if ($key === '') {
            return [
                'ok' => false,
                'code' => 'invalid_recommendation_key',
                'message' => 'Clave de recomendación inválida.',
            ];
        }

        $definition = AA_Learning_Catalog::get($key);

        if ($definition === null || empty($definition['active'])) {
            return [
                'ok' => false,
                'code' => 'unknown_recommendation',
                'message' => 'Recomendación no encontrada.',
            ];
        }

        return [
            'ok' => true,
            'definition' => $definition,
        ];
    }
}
