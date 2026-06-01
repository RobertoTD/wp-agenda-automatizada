<?php
/**
 * Learning Recommendations AJAX — lectura de recomendaciones para Guías/Aprendizaje.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/learning/GetLearningRecommendationsUseCase.php';

final class LearningRecommendationsAjax {

    public static function register(): void {
        add_action('wp_ajax_aa_get_learning_recommendations', [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer('aa_get_learning_recommendations_nonce', '_wpnonce');

        $result = (new GetLearningRecommendationsUseCase())->execute();
        wp_send_json_success($result);
    }
}
