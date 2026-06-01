<?php
/**
 * Get Learning Recommendations Use Case
 *
 * Orquesta catálogo, estados persistidos, facts y policy para la UI de Guías/Aprendizaje.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/learning/class-aa-learning-catalog.php';
require_once dirname(__DIR__, 2) . '/domain/learning/class-aa-learning-visibility-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/LearningRecommendationStateRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
require_once dirname(__DIR__, 2) . '/services/SyncService.php';

final class GetLearningRecommendationsUseCase {

    /**
     * @return array{
     *     list_1:list<array<string,mixed>>,
     *     list_2:list<array<string,mixed>>,
     *     all_visible:list<array<string,mixed>>
     * }
     */
    public function execute(): array {
        $definitions = AA_Learning_Catalog::definitions();
        $states_by_key = LearningRecommendationStateRepository::get_all();
        $facts = $this->build_facts();
        $now = $this->resolve_now();

        $grouped = (new AA_Learning_Visibility_Policy())->evaluate_all(
            $definitions,
            $states_by_key,
            $facts,
            $now
        );

        return [
            'list_1' => $this->enrich_items($grouped['list_1']),
            'list_2' => $this->enrich_items($grouped['list_2']),
            'all_visible' => $this->enrich_items($grouped['all_visible']),
        ];
    }

    /**
     * @return array<string,bool>
     */
    private function build_facts(): array {
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
    private function resolve_now(): string {
        if (function_exists('aa_get_current_datetime')) {
            return aa_get_current_datetime();
        }

        return current_time('mysql');
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private function enrich_items(array $items): array {
        $enriched = [];

        foreach ($items as $item) {
            $enriched[] = $this->enrich_item($item);
        }

        return $enriched;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function enrich_item(array $item): array {
        $navigation = is_array($item['navigation'] ?? null) ? $item['navigation'] : [];
        $action_url = self::resolve_navigation_url($navigation);

        unset($item['navigation']);

        $item['action_url'] = $action_url;
        $item['action_label'] = $action_url !== null ? 'Ir' : null;

        return $item;
    }

    /**
     * @param array<string,string|null> $navigation
     */
    public static function resolve_navigation_url(array $navigation): ?string {
        $module = isset($navigation['module']) ? (string) $navigation['module'] : '';

        if ($module === '') {
            return null;
        }

        $args = [
            'action' => 'aa_iframe_content',
            'module' => sanitize_key($module),
        ];

        if ($args['module'] === '') {
            return null;
        }

        $setup_focus = $navigation['setup_focus'] ?? null;

        if (is_string($setup_focus) && $setup_focus !== '') {
            $args['setup_focus'] = sanitize_key($setup_focus);
        }

        $url = add_query_arg($args, admin_url('admin-post.php'));

        $fragment = $navigation['fragment'] ?? null;

        if (is_string($fragment) && $fragment !== '') {
            $url .= '#' . ltrim($fragment, '#');
        }

        return $url;
    }
}
