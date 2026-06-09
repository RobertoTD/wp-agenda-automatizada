<?php
/**
 * Executable Contract — forma estable de proyección para listas/items ejecutables.
 *
 * Dominio puro: normaliza arrays a un contrato común consumible por UI futura.
 * Sin WordPress, SQL ni reglas de negocio de fuentes concretas.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Executable_Contract {

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_USER = 'user';

    public const SOURCE_AI = 'ai';

    public const SOURCE_CATEGORY_AGENDA_APP = 'agenda_app';

    public const SOURCE_CATEGORY_USER = 'user';

    public const SOURCE_CATEGORY_AI = 'ai';

    public const SOURCE_CATEGORY_UNKNOWN = 'unknown';

    public const LIST_STATUS_ACTIVE = 'active';

    public const LIST_STATUS_ARCHIVED = 'archived';

    public const BUCKET_PRIMARY = 'primary';

    public const BUCKET_SECONDARY = 'secondary';

    public const BUCKET_DEFAULT = 'default';

    public const BUCKET_LABEL_PRIMARY = 'Principales';

    public const BUCKET_LABEL_SECONDARY = 'Secundarias';

    public const ITEM_STATUS_PENDING = 'pending';

    public const ITEM_STATUS_DONE = 'done';

    public const ACTION_NAVIGATE = 'navigate';

    public const ACTION_HANDLER = 'handler';

    public const ACTION_STATUS = 'status';

    public const ACTION_INTENT = 'intent';

    public const VISIBLE_CATEGORY_MECHANICAL = 'mechanical';

    public const VISIBLE_CATEGORY_DECLARATIVE = 'declarative';

    public const VISIBLE_CATEGORY_INTENT = 'intent';

    public const VISIBLE_CATEGORY_RECOVERY = 'recovery';

    public const VISIBLE_PLACEMENT_PRIMARY = 'primary';

    public const VISIBLE_PLACEMENT_SECONDARY = 'secondary';

    /**
     * @param array<string,mixed> $list
     * @return array<string,mixed>
     */
    public static function normalize_list(array $list): array {
        $buckets = [];

        foreach ($list['buckets'] ?? [] as $bucket) {
            if (!is_array($bucket)) {
                continue;
            }

            $buckets[] = self::normalize_bucket($bucket);
        }

        $source = self::normalize_source($list['source'] ?? self::SOURCE_USER);
        $status = self::normalize_list_status($list['status'] ?? self::LIST_STATUS_ACTIVE);
        $source_category = self::normalize_source_category($list['source_category'] ?? null, $source);
        $source_label = self::normalize_source_label($list['source_label'] ?? null, $source_category, $source);

        return [
            'id' => self::normalize_scalar_id($list['id'] ?? ''),
            'source' => $source,
            'source_category' => $source_category,
            'source_label' => $source_label,
            'origin_key' => self::nullable_string($list['origin_key'] ?? null),
            'title' => self::normalize_string($list['title'] ?? ''),
            'description' => self::nullable_string($list['description'] ?? null),
            'importance' => (int) ($list['importance'] ?? 0),
            'position' => (int) ($list['position'] ?? 0),
            'status' => $status,
            'capabilities' => self::normalize_list_capabilities($list['capabilities'] ?? []),
            'buckets' => $buckets,
        ];
    }

    /**
     * @param array<string,mixed> $bucket
     * @return array<string,mixed>
     */
    /**
     * Label canónico de bucket para la vista executable (MC13O-0).
     *
     * @param string $bucket_key primary|secondary|default
     */
    public static function bucket_label(string $bucket_key): string {
        $key = self::normalize_bucket_key($bucket_key);

        if ($key === self::BUCKET_PRIMARY) {
            return self::BUCKET_LABEL_PRIMARY;
        }

        if ($key === self::BUCKET_SECONDARY) {
            return self::BUCKET_LABEL_SECONDARY;
        }

        return '';
    }

    public static function normalize_bucket(array $bucket): array {
        $items = [];

        foreach ($bucket['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = self::normalize_item($item);
        }

        $normalized_key = self::normalize_bucket_key($bucket['key'] ?? self::BUCKET_DEFAULT);
        $label = self::normalize_string($bucket['label'] ?? '');

        if ($label === '') {
            $label = self::bucket_label($normalized_key);
        }

        return [
            'key' => $normalized_key,
            'label' => $label,
            'items' => $items,
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public static function normalize_item(array $item): array {
        $source = self::normalize_source($item['source'] ?? self::SOURCE_USER);
        $status = self::normalize_item_status($item['status'] ?? self::ITEM_STATUS_PENDING);

        return [
            'id' => self::normalize_scalar_id($item['id'] ?? ''),
            'source' => $source,
            'source_category' => self::normalize_source_category($item['source_category'] ?? null, $source),
            'origin_key' => self::nullable_string($item['origin_key'] ?? null),
            'title' => self::normalize_string($item['title'] ?? ''),
            'description' => self::nullable_string($item['description'] ?? null),
            'importance' => (int) ($item['importance'] ?? 0),
            'due_at' => self::nullable_string($item['due_at'] ?? null),
            'status' => $status,
            'state' => self::normalize_item_state($item['state'] ?? []),
            'capabilities' => self::normalize_item_capabilities($item['capabilities'] ?? []),
            'primary_action' => self::normalize_primary_action($item['primary_action'] ?? null),
            'visible_actions' => self::normalize_visible_actions($item['visible_actions'] ?? []),
            'is_executive_candidate' => !empty($item['is_executive_candidate']),
        ];
    }

    /**
     * @param array<string,mixed> $list
     * @return list<string>
     */
    public static function required_list_keys(): array {
        return [
            'id',
            'source',
            'source_category',
            'source_label',
            'origin_key',
            'title',
            'description',
            'importance',
            'position',
            'status',
            'capabilities',
            'buckets',
        ];
    }

    /**
     * @return list<string>
     */
    public static function required_bucket_keys(): array {
        return ['key', 'label', 'items'];
    }

    /**
     * @return list<string>
     */
    public static function required_item_keys(): array {
        return [
            'id',
            'source',
            'source_category',
            'origin_key',
            'title',
            'description',
            'importance',
            'due_at',
            'status',
            'state',
            'capabilities',
            'primary_action',
            'visible_actions',
            'is_executive_candidate',
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return list<string>
     */
    public static function missing_list_keys(array $row): array {
        return self::missing_keys($row, self::required_list_keys());
    }

    /**
     * @param array<string,mixed> $row
     * @return list<string>
     */
    public static function missing_bucket_keys(array $row): array {
        return self::missing_keys($row, self::required_bucket_keys());
    }

    /**
     * @param array<string,mixed> $row
     * @return list<string>
     */
    public static function missing_item_keys(array $row): array {
        return self::missing_keys($row, self::required_item_keys());
    }

    /**
     * @param string $source
     */
    public static function default_source_category(string $source): string {
        $normalized = strtolower(trim($source));

        if ($normalized === self::SOURCE_SYSTEM) {
            return self::SOURCE_CATEGORY_AGENDA_APP;
        }

        if ($normalized === self::SOURCE_USER) {
            return self::SOURCE_CATEGORY_USER;
        }

        if ($normalized === self::SOURCE_AI) {
            return self::SOURCE_CATEGORY_AI;
        }

        if ($normalized === '') {
            return self::SOURCE_CATEGORY_UNKNOWN;
        }

        return $normalized;
    }

    /**
     * @param string $source_or_category
     */
    public static function default_source_label(string $source_or_category): string {
        $normalized = strtolower(trim($source_or_category));

        if ($normalized === self::SOURCE_SYSTEM || $normalized === self::SOURCE_CATEGORY_AGENDA_APP) {
            return 'Agenda app';
        }

        if ($normalized === self::SOURCE_USER || $normalized === self::SOURCE_CATEGORY_USER) {
            return 'Mis listas';
        }

        if ($normalized === self::SOURCE_AI || $normalized === self::SOURCE_CATEGORY_AI) {
            return 'IA';
        }

        if ($normalized === '' || $normalized === self::SOURCE_CATEGORY_UNKNOWN) {
            return '';
        }

        return ucfirst(str_replace('_', ' ', $normalized));
    }

    /**
     * @param mixed $value
     * @param string $source
     */
    private static function normalize_source_category($value, string $source): string {
        $category = is_string($value) ? strtolower(trim($value)) : '';

        if ($category !== '') {
            return $category;
        }

        return self::default_source_category($source);
    }

    /**
     * @param mixed  $value
     * @param string $source_category
     * @param string $source
     */
    private static function normalize_source_label($value, string $source_category, string $source): string {
        $label = is_string($value) ? trim($value) : '';

        if ($label !== '') {
            return $label;
        }

        $from_category = self::default_source_label($source_category);

        if ($from_category !== '') {
            return $from_category;
        }

        return self::default_source_label($source);
    }

    /**
     * @param mixed $value
     */
    private static function normalize_source($value): string {
        $source = is_string($value) ? strtolower(trim($value)) : '';

        if ($source === self::SOURCE_SYSTEM || $source === self::SOURCE_USER || $source === self::SOURCE_AI) {
            return $source;
        }

        return self::SOURCE_USER;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_list_status($value): string {
        $status = is_string($value) ? strtolower(trim($value)) : '';

        if ($status === self::LIST_STATUS_ARCHIVED) {
            return self::LIST_STATUS_ARCHIVED;
        }

        return self::LIST_STATUS_ACTIVE;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_item_status($value): string {
        $status = is_string($value) ? strtolower(trim($value)) : '';

        if ($status === self::ITEM_STATUS_DONE) {
            return self::ITEM_STATUS_DONE;
        }

        return self::ITEM_STATUS_PENDING;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_bucket_key($value): string {
        $key = is_string($value) ? strtolower(trim($value)) : '';

        if (
            $key === self::BUCKET_PRIMARY
            || $key === self::BUCKET_SECONDARY
            || $key === self::BUCKET_DEFAULT
        ) {
            return $key;
        }

        return self::BUCKET_DEFAULT;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_scalar_id($value): string {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<string,mixed> $capabilities
     * @return array{can_archive:bool}
     */
    private static function normalize_list_capabilities(array $capabilities): array {
        return [
            'can_archive' => !empty($capabilities['can_archive']),
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array{
     *     completed:bool,
     *     ignored:bool,
     *     dismissed:bool,
     *     dismiss_active:bool,
     *     auto_completed:bool
     * }
     */
    private static function normalize_item_state(array $state): array {
        return [
            'completed' => !empty($state['completed']),
            'ignored' => !empty($state['ignored']),
            'dismissed' => !empty($state['dismissed']),
            'dismiss_active' => !empty($state['dismiss_active']),
            'auto_completed' => !empty($state['auto_completed']),
        ];
    }

    /**
     * @param array<string,mixed> $capabilities
     * @return array{
     *     can_complete:bool,
     *     can_reopen:bool,
     *     can_defer:bool,
     *     can_dismiss:bool,
     *     can_reactivate:bool
     * }
     */
    private static function normalize_item_capabilities(array $capabilities): array {
        return [
            'can_complete' => !empty($capabilities['can_complete']),
            'can_reopen' => !empty($capabilities['can_reopen']),
            'can_defer' => !empty($capabilities['can_defer']),
            'can_dismiss' => !empty($capabilities['can_dismiss']),
            'can_reactivate' => !empty($capabilities['can_reactivate']),
        ];
    }

    /**
     * @param mixed $action
     * @return array<string,mixed>|null
     */
    private static function normalize_primary_action($action): ?array {
        if (!is_array($action)) {
            return null;
        }

        $type = isset($action['type']) ? (string) $action['type'] : '';

        if ($type === self::ACTION_NAVIGATE) {
            $url = isset($action['url']) ? trim((string) $action['url']) : '';

            if ($url === '') {
                return null;
            }

            $label = isset($action['label']) ? trim((string) $action['label']) : '';
            $key = isset($action['key']) ? trim((string) $action['key']) : '';

            return [
                'key' => $key !== '' ? $key : self::ACTION_NAVIGATE,
                'type' => self::ACTION_NAVIGATE,
                'label' => $label !== '' ? $label : 'Ir',
                'url' => $url,
            ];
        }

        if ($type === self::ACTION_HANDLER) {
            $handler = isset($action['handler']) ? trim((string) $action['handler']) : '';
            $label = isset($action['label']) ? trim((string) $action['label']) : '';

            if ($handler === '' || $label === '') {
                return null;
            }

            $key = isset($action['key']) ? trim((string) $action['key']) : '';

            return [
                'key' => $key !== '' ? $key : $handler,
                'type' => self::ACTION_HANDLER,
                'label' => $label,
                'handler' => $handler,
            ];
        }

        if ($type === self::ACTION_STATUS) {
            $to = isset($action['to']) ? strtolower(trim((string) $action['to'])) : '';
            $label = isset($action['label']) ? trim((string) $action['label']) : '';

            if ($to !== self::ITEM_STATUS_DONE && $to !== self::ITEM_STATUS_PENDING) {
                return null;
            }

            $key = isset($action['key']) ? trim((string) $action['key']) : '';

            return [
                'key' => $key !== '' ? $key : ($to === self::ITEM_STATUS_DONE ? 'complete' : 'reopen'),
                'type' => self::ACTION_STATUS,
                'label' => $label !== '' ? $label : ($to === self::ITEM_STATUS_DONE ? 'Completar' : 'Reabrir'),
                'to' => $to,
            ];
        }

        return null;
    }

    /**
     * @param mixed $actions
     * @return list<array{
     *     key:string,
     *     type:string,
     *     category:string,
     *     label:string,
     *     placement:string,
     *     target_status:string|null,
     *     url:string|null,
     *     handler:string|null
     * }>
     */
    private static function normalize_visible_actions($actions): array {
        if (!is_array($actions)) {
            return [];
        }

        $normalized = [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $row = self::normalize_visible_action($action);

            if ($row !== null) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $action
     * @return array<string,mixed>|null
     */
    private static function normalize_visible_action(array $action): ?array {
        $type = isset($action['type']) ? strtolower(trim((string) $action['type'])) : '';
        $label = isset($action['label']) ? trim((string) $action['label']) : '';
        $key = isset($action['key']) ? trim((string) $action['key']) : '';

        if ($key === '' || $label === '') {
            return null;
        }

        if (
            $type !== self::ACTION_NAVIGATE
            && $type !== self::ACTION_HANDLER
            && $type !== self::ACTION_STATUS
            && $type !== self::ACTION_INTENT
        ) {
            return null;
        }

        $category = isset($action['category']) ? strtolower(trim((string) $action['category'])) : '';

        if (
            $category !== self::VISIBLE_CATEGORY_MECHANICAL
            && $category !== self::VISIBLE_CATEGORY_DECLARATIVE
            && $category !== self::VISIBLE_CATEGORY_INTENT
            && $category !== self::VISIBLE_CATEGORY_RECOVERY
        ) {
            return null;
        }

        $placement = isset($action['placement']) ? strtolower(trim((string) $action['placement'])) : '';

        if ($placement !== self::VISIBLE_PLACEMENT_PRIMARY && $placement !== self::VISIBLE_PLACEMENT_SECONDARY) {
            return null;
        }

        $target_status = null;
        $raw_target = $action['target_status'] ?? null;

        if ($raw_target !== null && $raw_target !== '') {
            $target_status = self::normalize_item_status((string) $raw_target);
        }

        $url = null;
        $handler = null;

        if ($type === self::ACTION_NAVIGATE) {
            $normalized_url = isset($action['url']) ? trim((string) $action['url']) : '';

            if ($normalized_url === '') {
                return null;
            }

            $url = $normalized_url;
        }

        if ($type === self::ACTION_HANDLER) {
            $normalized_handler = isset($action['handler']) ? trim((string) $action['handler']) : '';

            if ($normalized_handler === '') {
                return null;
            }

            $handler = $normalized_handler;
        }

        if ($type === self::ACTION_STATUS && $target_status === null) {
            return null;
        }

        return [
            'key' => $key,
            'type' => $type,
            'category' => $category,
            'label' => $label,
            'placement' => $placement,
            'target_status' => $target_status,
            'url' => $url,
            'handler' => $handler,
        ];
    }

    /**
     * @param mixed $value
     */
    private static function nullable_string($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_string($value): string {
        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string>        $required
     * @return list<string>
     */
    private static function missing_keys(array $row, array $required): array {
        $missing = [];

        foreach ($required as $key) {
            if (!array_key_exists($key, $row)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
