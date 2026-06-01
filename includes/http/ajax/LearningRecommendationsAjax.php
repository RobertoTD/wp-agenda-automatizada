<?php
/**
 * Learning Recommendations AJAX — lectura y acciones de Guías/Aprendizaje.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/learning/GetLearningRecommendationsUseCase.php';
require_once dirname(__DIR__, 2) . '/application/learning/IgnoreLearningRecommendationUseCase.php';
require_once dirname(__DIR__, 2) . '/application/learning/DismissLearningRecommendationUseCase.php';
require_once dirname(__DIR__, 2) . '/application/learning/CompleteLearningRecommendationUseCase.php';
require_once dirname(__DIR__, 2) . '/application/learning/ReactivateLearningRecommendationUseCase.php';

final class LearningRecommendationsAjax {

    private const NONCE_ACTION = 'aa_get_learning_recommendations_nonce';

    public static function register(): void {
        add_action('wp_ajax_aa_get_learning_recommendations', [__CLASS__, 'handle_get']);
        add_action('wp_ajax_aa_ignore_learning_recommendation', [__CLASS__, 'handle_ignore']);
        add_action('wp_ajax_aa_dismiss_learning_recommendation', [__CLASS__, 'handle_dismiss']);
        add_action('wp_ajax_aa_complete_learning_recommendation', [__CLASS__, 'handle_complete']);
        add_action('wp_ajax_aa_reactivate_learning_recommendation', [__CLASS__, 'handle_reactivate']);
    }

    public static function handle_get(): void {
        self::authorize_read();

        $result = (new GetLearningRecommendationsUseCase())->execute();
        wp_send_json_success($result);
    }

    public static function handle_ignore(): void {
        $key = self::authorize_write();
        $result = (new IgnoreLearningRecommendationUseCase())->execute($key);
        self::respond_use_case($result);
    }

    public static function handle_dismiss(): void {
        $key = self::authorize_write();
        $result = (new DismissLearningRecommendationUseCase())->execute($key);
        self::respond_use_case($result);
    }

    public static function handle_complete(): void {
        $key = self::authorize_write();
        $result = (new CompleteLearningRecommendationUseCase())->execute($key);
        self::respond_use_case($result);
    }

    public static function handle_reactivate(): void {
        $key = self::authorize_write();
        $result = (new ReactivateLearningRecommendationUseCase())->execute($key);
        self::respond_use_case($result);
    }

    private static function authorize_read(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');
    }

    /**
     * @return string recommendation_key
     */
    private static function authorize_write(): string {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');

        $key = isset($_POST['recommendation_key'])
            ? sanitize_key(wp_unslash((string) $_POST['recommendation_key']))
            : '';

        if ($key === '') {
            wp_send_json_error([
                'message' => 'Clave de recomendación inválida.',
                'code' => 'invalid_recommendation_key',
            ], 400);
        }

        return $key;
    }

    /**
     * @param array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}} $result
     */
    private static function respond_use_case(array $result): void {
        if (!empty($result['success'])) {
            wp_send_json_success($result['data'] ?? []);
        }

        $error = $result['error'] ?? [];
        wp_send_json_error([
            'message' => (string) ($error['message'] ?? 'No se pudo completar la acción.'),
            'code' => (string) ($error['code'] ?? 'unknown_error'),
        ], 400);
    }
}
