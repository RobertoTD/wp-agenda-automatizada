<?php
/**
 * Executable Visible Actions Enricher — agrega visible_actions al feed común.
 *
 * Proyección application: recorre ExecutableList[] ya mapeadas y delega reglas
 * a AA_Executable_Visible_Actions_Policy. No muta el input original.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executable/class-aa-executable-contract.php';
require_once dirname(__DIR__, 2) . '/domain/executable/class-aa-executable-visible-actions-policy.php';

final class ExecutableVisibleActionsEnricher {

    /**
     * @param list<array<string,mixed>> $lists
     * @param array<string,mixed>       $context view opcional (default active).
     * @return list<array<string,mixed>>
     */
    public static function enrich_lists(array $lists, array $context = []): array {
        $view = self::resolve_view($context['view'] ?? AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE);
        $enriched = [];

        foreach ($lists as $list) {
            if (!is_array($list)) {
                continue;
            }

            $enriched[] = self::enrich_list($list, $view);
        }

        return $enriched;
    }

    /**
     * @param array<string,mixed> $list
     */
    private static function enrich_list(array $list, string $view): array {
        $list_copy = $list;
        $buckets = [];

        foreach ($list['buckets'] ?? [] as $bucket) {
            if (!is_array($bucket)) {
                continue;
            }

            $buckets[] = self::enrich_bucket($bucket, $list, $view);
        }

        $list_copy['buckets'] = $buckets;

        return $list_copy;
    }

    /**
     * @param array<string,mixed> $bucket
     * @param array<string,mixed> $list
     */
    private static function enrich_bucket(array $bucket, array $list, string $view): array {
        $bucket_copy = $bucket;
        $bucket_key = self::resolve_bucket_key($bucket['key'] ?? AA_Executable_Contract::BUCKET_DEFAULT);
        $items = [];

        foreach ($bucket['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = self::enrich_item($item, [
                'view' => $view,
                'bucket_key' => $bucket_key,
                'source' => self::resolve_source($list, $item),
            ]);
        }

        $bucket_copy['items'] = $items;

        return $bucket_copy;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function enrich_item(array $item, array $context): array {
        $visible_actions = AA_Executable_Visible_Actions_Policy::resolve($item, $context);

        return AA_Executable_Contract::normalize_item(array_merge($item, [
            'visible_actions' => $visible_actions,
        ]));
    }

    /**
     * @param mixed $value
     */
    private static function resolve_view($value): string {
        $view = is_string($value) ? strtolower(trim($value)) : '';

        if ($view === AA_Executable_Visible_Actions_Policy::VIEW_COMPLETED) {
            return AA_Executable_Visible_Actions_Policy::VIEW_COMPLETED;
        }

        if ($view === AA_Executable_Visible_Actions_Policy::VIEW_IGNORED) {
            return AA_Executable_Visible_Actions_Policy::VIEW_IGNORED;
        }

        return AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE;
    }

    /**
     * @param mixed $value
     */
    private static function resolve_bucket_key($value): string {
        $key = is_string($value) ? strtolower(trim($value)) : '';

        if (
            $key === AA_Executable_Contract::BUCKET_PRIMARY
            || $key === AA_Executable_Contract::BUCKET_SECONDARY
            || $key === AA_Executable_Contract::BUCKET_DEFAULT
            || $key === AA_Executable_Visible_Actions_Policy::VIEW_COMPLETED
            || $key === AA_Executable_Visible_Actions_Policy::VIEW_IGNORED
        ) {
            return $key;
        }

        return AA_Executable_Contract::BUCKET_DEFAULT;
    }

    /**
     * @param array<string,mixed> $list
     * @param array<string,mixed> $item
     */
    private static function resolve_source(array $list, array $item): string {
        $item_source = self::normalize_source_value($item['source'] ?? null);

        if ($item_source !== null) {
            return $item_source;
        }

        $list_source = self::normalize_source_value($list['source'] ?? null);

        if ($list_source !== null) {
            return $list_source;
        }

        return AA_Executable_Contract::SOURCE_USER;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_source_value($value): ?string {
        $source = is_string($value) ? strtolower(trim($value)) : '';

        if (
            $source === AA_Executable_Contract::SOURCE_SYSTEM
            || $source === AA_Executable_Contract::SOURCE_USER
            || $source === AA_Executable_Contract::SOURCE_AI
        ) {
            return $source;
        }

        return null;
    }
}
