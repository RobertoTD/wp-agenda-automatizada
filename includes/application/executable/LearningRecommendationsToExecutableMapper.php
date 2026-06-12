<?php
/**
 * Learning Recommendations → Executable projection.
 *
 * Mapea la salida enriquecida de GetLearningRecommendationsUseCase al contrato común.
 * No reevalúa policy ni recalcula buckets.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executable/class-aa-executable-contract.php';

final class LearningRecommendationsToExecutableMapper {

    public const LIST_ID = 'system:learning.recommendations';

    public const LIST_ORIGIN_KEY = 'learning.recommendations';

    /**
     * @param array{
     *     list_1?:list<array<string,mixed>>,
     *     list_2?:list<array<string,mixed>>,
     *     all_visible?:list<array<string,mixed>>
     * } $payload
     * @return array<string,mixed> ExecutableList normalizada
     */
    public static function map(array $payload): array {
        $list_1 = is_array($payload['list_1'] ?? null) ? $payload['list_1'] : [];
        $list_2 = is_array($payload['list_2'] ?? null) ? $payload['list_2'] : [];

        $buckets = [];

        if ($list_1 !== []) {
            $buckets[] = [
                'key' => AA_Executable_Contract::BUCKET_PRIMARY,
                'label' => AA_Executable_Contract::bucket_label(AA_Executable_Contract::BUCKET_PRIMARY),
                'items' => self::map_items($list_1, AA_Executable_Contract::BUCKET_PRIMARY),
            ];
        }

        if ($list_2 !== []) {
            $buckets[] = [
                'key' => AA_Executable_Contract::BUCKET_SECONDARY,
                'label' => AA_Executable_Contract::bucket_label(AA_Executable_Contract::BUCKET_SECONDARY),
                'items' => self::map_items($list_2, AA_Executable_Contract::BUCKET_SECONDARY),
            ];
        }

        return AA_Executable_Contract::normalize_list([
            'id' => self::LIST_ID,
            'source' => AA_Executable_Contract::SOURCE_SYSTEM,
            'source_category' => AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP,
            'source_label' => 'Agenda app',
            'origin_key' => self::LIST_ORIGIN_KEY,
            'title' => 'Activación de tu agenda',
            'description' => 'Sugerencias para configurar y usar tu agenda.',
            'importance' => 0,
            'position' => 0,
            'status' => AA_Executable_Contract::LIST_STATUS_ACTIVE,
            'capabilities' => [
                'can_archive' => false,
                'can_edit' => false,
                'can_restore_archived_tasks' => false,
            ],
            'buckets' => $buckets,
        ]);
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private static function map_items(array $items, string $bucket_key): array {
        $mapped = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $mapped[] = self::map_item($item, $bucket_key);
        }

        return $mapped;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private static function map_item(array $item, string $bucket_key): array {
        $key = isset($item['key']) ? (string) $item['key'] : '';

        return AA_Executable_Contract::normalize_item([
            'id' => $key,
            'source' => AA_Executable_Contract::SOURCE_SYSTEM,
            'origin_key' => $key !== '' ? $key : null,
            'title' => (string) ($item['title'] ?? ''),
            'description' => isset($item['description']) ? (string) $item['description'] : null,
            'importance' => (int) ($item['importance'] ?? 0),
            'due_at' => null,
            'default_bucket' => self::resolve_default_bucket($item, $bucket_key),
            'status' => !empty($item['is_completed'])
                ? AA_Executable_Contract::ITEM_STATUS_DONE
                : AA_Executable_Contract::ITEM_STATUS_PENDING,
            'state' => [
                'completed' => !empty($item['is_completed']),
                'ignored' => !empty($item['is_ignored']),
                'dismissed' => !empty($item['is_dismissed']),
                'dismiss_active' => !empty($item['is_dismiss_active']),
                'auto_completed' => !empty($item['is_auto_completed']),
            ],
            'capabilities' => [
                'can_complete' => !empty($item['can_complete_manually']),
                'can_reopen' => false,
                'can_defer' => !empty($item['can_defer']),
                'can_dismiss' => !empty($item['can_dismiss']),
                'can_reactivate' => !empty($item['can_reactivate']),
                'can_edit' => false,
                'can_archive' => false,
                'can_restore' => false,
            ],
            'primary_action' => self::map_primary_action($item['action'] ?? null),
            'is_executive_candidate' => false,
        ]);
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function resolve_default_bucket(array $item, string $bucket_key): string {
        if (array_key_exists('default_list', $item)) {
            return (int) $item['default_list'] === 1
                ? AA_Executable_Contract::BUCKET_PRIMARY
                : AA_Executable_Contract::BUCKET_SECONDARY;
        }

        if ($bucket_key === AA_Executable_Contract::BUCKET_SECONDARY) {
            return AA_Executable_Contract::BUCKET_SECONDARY;
        }

        return AA_Executable_Contract::BUCKET_PRIMARY;
    }

    /**
     * @param mixed $action
     * @return array<string,mixed>|null
     */
    private static function map_primary_action($action): ?array {
        if (!is_array($action)) {
            return null;
        }

        $type = isset($action['type']) ? (string) $action['type'] : '';

        if ($type === AA_Executable_Contract::ACTION_NAVIGATE) {
            return [
                'type' => AA_Executable_Contract::ACTION_NAVIGATE,
                'label' => (string) ($action['label'] ?? 'Ir'),
                'url' => (string) ($action['url'] ?? ''),
            ];
        }

        if ($type === AA_Executable_Contract::ACTION_HANDLER) {
            return [
                'type' => AA_Executable_Contract::ACTION_HANDLER,
                'label' => (string) ($action['label'] ?? ''),
                'handler' => (string) ($action['handler'] ?? ''),
            ];
        }

        return null;
    }
}
