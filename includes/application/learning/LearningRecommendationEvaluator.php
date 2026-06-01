<?php
/**
 * Evalúa una recomendación con catálogo, estado persistido, facts y policy.
 *
 * Auxiliar para use cases de escritura que necesitan effective_list / visible.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/learning/class-aa-learning-catalog.php';
require_once dirname(__DIR__, 2) . '/domain/learning/class-aa-learning-visibility-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/LearningRecommendationStateRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
require_once dirname(__DIR__, 2) . '/services/SyncService.php';

final class LearningRecommendationEvaluator {

    /**
     * @param array<string,mixed>      $definition
     * @param array<string,mixed>|null $state
     * @return array<string,mixed>
     */
    public static function evaluate(array $definition, ?array $state = null): array {
        return (new AA_Learning_Visibility_Policy())->evaluate(
            $definition,
            $state,
            self::build_facts(),
            self::resolve_now()
        );
    }

    /**
     * @param string $recommendation_key
     * @return array<string,mixed>|null
     */
    public static function evaluate_key(string $recommendation_key): ?array {
        $definition = AA_Learning_Catalog::get($recommendation_key);

        if ($definition === null || empty($definition['active'])) {
            return null;
        }

        $state = LearningRecommendationStateRepository::find_by_key($recommendation_key);

        return self::evaluate($definition, $state);
    }

    /**
     * @return array<string,bool>
     */
    public static function build_facts(): array {
        $business_name = get_option('aa_business_name', '');
        $business_address = get_option('aa_business_address', '');

        return [
            'google_connected' => SyncService::has_google_connection(),
            'business_data_complete' => is_string($business_name) && trim($business_name) !== ''
                && is_string($business_address) && trim($business_address) !== '',
            'has_active_service' => AssignmentsRepository::count_active_services() > 0,
            'has_active_area' => AssignmentsRepository::count_active_service_areas() > 0,
            'has_staff_with_service' => AssignmentsRepository::count_active_staff_with_active_services() > 0,
            'has_registered_client' => ClientsRepository::count_registered_clients() > 0,
        ];
    }

    /**
     * @return string Y-m-d H:i:s en zona del negocio.
     */
    public static function resolve_now(): string {
        if (function_exists('aa_get_current_datetime')) {
            return aa_get_current_datetime();
        }

        return current_time('mysql');
    }
}
