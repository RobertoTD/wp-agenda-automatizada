<?php
/**
 * Executable Visible Actions Policy — resuelve acciones visibles por vista/bucket.
 *
 * Dominio puro: sin WordPress, SQL, handlers runtime ni render.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/class-aa-executable-contract.php';
require_once dirname(__DIR__) . '/appointments/class-aa-appointment-actions-catalog.php';

final class AA_Executable_Visible_Actions_Policy {

    public const VIEW_ACTIVE = 'active';

    public const VIEW_COMPLETED = 'completed';

    public const VIEW_IGNORED = 'ignored';

    public const ACTION_TYPE_INTENT = 'intent';

    public const CATEGORY_MECHANICAL = 'mechanical';

    public const CATEGORY_DECLARATIVE = 'declarative';

    public const CATEGORY_INTENT = 'intent';

    public const CATEGORY_RECOVERY = 'recovery';

    public const PLACEMENT_PRIMARY = 'primary';

    public const PLACEMENT_SECONDARY = 'secondary';

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $context view, bucket_key, source.
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
    public static function resolve(array $item, array $context = []): array {
        $view = self::normalize_view($context['view'] ?? self::VIEW_ACTIVE);
        $bucket_key = self::normalize_bucket_key($context['bucket_key'] ?? AA_Executable_Contract::BUCKET_DEFAULT);
        $capabilities = self::capabilities($item);
        $actions = [];

        $mechanical_action = self::mechanical_action($item['primary_action'] ?? null);
        if ($mechanical_action !== null && !self::should_suppress_mechanical_action($item, $mechanical_action)) {
            $actions[] = $mechanical_action;
        }

        if (!empty($capabilities['can_complete'])) {
            $actions[] = self::status_action(
                'complete',
                'Completar',
                AA_Executable_Contract::ITEM_STATUS_DONE,
                self::CATEGORY_DECLARATIVE
            );
        }

        if ($view === self::VIEW_ACTIVE) {
            $source = self::normalize_source($context['source'] ?? '');

            if ($source === AA_Executable_Contract::SOURCE_USER) {
                if (!empty($capabilities['can_dismiss'])) {
                    $actions[] = self::intent_action('dismiss', 'Ahora no');
                }
            } else {
                if (!empty($capabilities['can_dismiss'])) {
                    $actions[] = self::intent_action('dismiss', 'Ahora no');
                }
            }
        }

        if ($view === self::VIEW_COMPLETED && !empty($capabilities['can_reopen'])) {
            $actions[] = self::status_action(
                'reopen',
                'Reabrir',
                AA_Executable_Contract::ITEM_STATUS_PENDING,
                self::CATEGORY_RECOVERY
            );
        }

        if ($view === self::VIEW_IGNORED && !empty($capabilities['can_reactivate'])) {
            $actions[] = [
                'key' => 'reactivate',
                'type' => self::ACTION_TYPE_INTENT,
                'category' => self::CATEGORY_RECOVERY,
                'label' => 'Reactivar',
                'placement' => self::PLACEMENT_SECONDARY,
                'target_status' => null,
                'url' => null,
                'handler' => null,
            ];
        }

        return $actions;
    }

    /**
     * @param mixed $action
     * @return array<string,mixed>|null
     */
    private static function mechanical_action($action): ?array {
        if (!is_array($action)) {
            return null;
        }

        $type = isset($action['type']) ? (string) $action['type'] : '';

        if ($type === AA_Executable_Contract::ACTION_NAVIGATE) {
            $url = isset($action['url']) ? trim((string) $action['url']) : '';

            if ($url === '') {
                return null;
            }

            $label = isset($action['label']) ? trim((string) $action['label']) : '';

            return [
                'key' => self::stable_key($action['key'] ?? null, 'navigate'),
                'type' => AA_Executable_Contract::ACTION_NAVIGATE,
                'category' => self::CATEGORY_MECHANICAL,
                'label' => $label !== '' ? $label : 'Ir',
                'placement' => self::PLACEMENT_PRIMARY,
                'target_status' => null,
                'url' => $url,
                'handler' => null,
            ];
        }

        if ($type === AA_Executable_Contract::ACTION_HANDLER) {
            $handler = isset($action['handler']) ? trim((string) $action['handler']) : '';
            $label = isset($action['label']) ? trim((string) $action['label']) : '';

            if ($handler === '' || $label === '') {
                return null;
            }

            return [
                'key' => self::stable_key($action['key'] ?? null, $handler),
                'type' => AA_Executable_Contract::ACTION_HANDLER,
                'category' => self::CATEGORY_MECHANICAL,
                'label' => $label,
                'placement' => self::PLACEMENT_PRIMARY,
                'target_status' => null,
                'url' => null,
                'handler' => $handler,
            ];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $mechanical_action
     */
    private static function should_suppress_mechanical_action(array $item, array $mechanical_action): bool {
        if (!self::is_overdue_appointment_confirmation($item)) {
            return false;
        }

        return self::is_appointment_confirm_action($mechanical_action);
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function is_overdue_appointment_confirmation(array $item): bool {
        if (empty($item['is_overdue'])) {
            return false;
        }

        $origin_key = isset($item['origin_key']) && is_string($item['origin_key'])
            ? trim($item['origin_key'])
            : '';

        if ($origin_key === '') {
            return false;
        }

        return strpos($origin_key, AA_Appointment_Actions_Catalog::TASK_ORIGIN_KEY_PREFIX) === 0;
    }

    /**
     * @param array<string,mixed> $action
     */
    private static function is_appointment_confirm_action(array $action): bool {
        $handler = trim((string) ($action['handler'] ?? ''));
        $key = trim((string) ($action['key'] ?? ''));

        return $handler === AA_Appointment_Actions_Catalog::TASK_ACTION_HANDLER
            || $key === AA_Appointment_Actions_Catalog::TASK_ACTION_KEY;
    }

    /**
     * @return array<string,bool>
     */
    private static function capabilities(array $item): array {
        $capabilities = is_array($item['capabilities'] ?? null) ? $item['capabilities'] : [];

        return [
            'can_complete' => !empty($capabilities['can_complete']),
            'can_reopen' => !empty($capabilities['can_reopen']),
            'can_defer' => !empty($capabilities['can_defer']),
            'can_dismiss' => !empty($capabilities['can_dismiss']),
            'can_reactivate' => !empty($capabilities['can_reactivate']),
        ];
    }

    private static function status_action(string $key, string $label, string $target_status, string $category): array {
        return [
            'key' => $key,
            'type' => AA_Executable_Contract::ACTION_STATUS,
            'category' => $category,
            'label' => $label,
            'placement' => self::PLACEMENT_SECONDARY,
            'target_status' => $target_status,
            'url' => null,
            'handler' => null,
        ];
    }

    private static function intent_action(string $key, string $label): array {
        return [
            'key' => $key,
            'type' => self::ACTION_TYPE_INTENT,
            'category' => self::CATEGORY_INTENT,
            'label' => $label,
            'placement' => self::PLACEMENT_SECONDARY,
            'target_status' => null,
            'url' => null,
            'handler' => null,
        ];
    }

    /**
     * @param mixed $value
     */
    private static function normalize_view($value): string {
        $view = is_string($value) ? strtolower(trim($value)) : '';

        if ($view === self::VIEW_COMPLETED || $view === self::VIEW_IGNORED) {
            return $view;
        }

        return self::VIEW_ACTIVE;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_bucket_key($value): string {
        $key = is_string($value) ? strtolower(trim($value)) : '';

        if (
            $key === AA_Executable_Contract::BUCKET_PRIMARY
            || $key === AA_Executable_Contract::BUCKET_SECONDARY
            || $key === AA_Executable_Contract::BUCKET_DEFAULT
            || $key === self::VIEW_COMPLETED
            || $key === self::VIEW_IGNORED
        ) {
            return $key;
        }

        return AA_Executable_Contract::BUCKET_DEFAULT;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_source($value): string {
        $source = is_string($value) ? strtolower(trim($value)) : '';

        if (
            $source === AA_Executable_Contract::SOURCE_SYSTEM
            || $source === AA_Executable_Contract::SOURCE_USER
            || $source === AA_Executable_Contract::SOURCE_AI
        ) {
            return $source;
        }

        return AA_Executable_Contract::SOURCE_SYSTEM;
    }

    /**
     * @param mixed $raw_key
     */
    private static function stable_key($raw_key, string $fallback): string {
        $key = is_string($raw_key) ? trim($raw_key) : '';

        if ($key !== '') {
            return $key;
        }

        $fallback = trim($fallback);

        return $fallback !== '' ? $fallback : 'primary';
    }
}
