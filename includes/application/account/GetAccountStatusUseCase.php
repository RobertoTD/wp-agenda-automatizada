<?php
/**
 * Get Account Status Use Case
 *
 * Orchestrates a read-only fetch of commercial account status from the
 * Node backend. No billing rules — backend remains source of truth.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-account-status-backend-client.php';

final class GetAccountStatusUseCase {

    /** @var AA_Account_Status_Backend_Client|null */
    private $client;

    /** @var list<string> */
    private const ALLOWED_KEYS = [
        'plan_tier',
        'stripe_status',
        'effective_access_tier',
        'billing_state',
        'current_period_end',
        'cancel_at',
        'is_cancel_scheduled',
        'sync_pending',
        'payment_action_required',
        'messages',
        'benefit_quotas',
        'upgrade_to_pro_available',
        'upgrade_to_pro_reason',
    ];

    /**
     * @param AA_Account_Status_Backend_Client|null $client Optional inject for tests.
     */
    public function __construct(?AA_Account_Status_Backend_Client $client = null) {
        $this->client = $client;
    }

    /**
     * @return array{
     *     success: true,
     *     data: array{
     *         account_status: array<string,mixed>,
     *         public_site: array<string,mixed>
     *     }
     * }|array{
     *     success: false,
     *     error: array{code: string, message: string, reason?: string},
     *     data: array{public_site: array<string,mixed>}
     * }
     */
    public function execute(): array {
        $public_site = $this->resolvePublicSitePayload();

        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->failure(
                'account_backend_not_configured',
                'missing_client_secret',
                $public_site,
                ['reason' => 'missing_client_secret']
            );
        }

        $backend = $this->resolveClient()->fetch();

        if (empty($backend['ok'])) {
            return $this->failure(
                (string) ($backend['code'] ?? 'account_backend_error'),
                (string) ($backend['error'] ?? 'No se pudo consultar el estado de cuenta.'),
                $public_site,
                isset($backend['reason']) && is_string($backend['reason'])
                    ? ['reason' => $backend['reason']]
                    : []
            );
        }

        $sanitized = $this->sanitizeAccountStatus($backend['account_status']);

        return [
            'success' => true,
            'data'    => [
                'account_status' => $sanitized,
                'public_site'    => $public_site,
            ],
        ];
    }

    private function resolveClient(): AA_Account_Status_Backend_Client {
        if ($this->client instanceof AA_Account_Status_Backend_Client) {
            return $this->client;
        }

        return new AA_Account_Status_Backend_Client();
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function sanitizeAccountStatus(array $raw): array {
        $out = [];

        foreach (self::ALLOWED_KEYS as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }

            $value = $raw[$key];

            if ($key === 'messages') {
                $out[$key] = $this->normalizeMessages($value);
                continue;
            }

            if ($key === 'benefit_quotas') {
                $normalized = $this->normalizeBenefitQuotas($value);
                if ($normalized !== null) {
                    $out[$key] = $normalized;
                }
                continue;
            }

            if ($key === 'is_cancel_scheduled' || $key === 'sync_pending' || $key === 'payment_action_required' || $key === 'upgrade_to_pro_available') {
                $out[$key] = (bool) $value;
                continue;
            }

            if ($key === 'upgrade_to_pro_reason') {
                $out[$key] = $this->normalizeUpgradeToProReason($value);
                continue;
            }

            if ($value === null) {
                $out[$key] = null;
                continue;
            }

            if (is_scalar($value)) {
                $trimmed = trim((string) $value);
                $out[$key] = $trimmed === '' ? null : $trimmed;
            }
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeMessages($value): array {
        if (!is_array($value)) {
            return [];
        }

        $messages = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $text = trim($item);
            if ($text !== '') {
                $messages[] = $text;
            }
        }

        return $messages;
    }

    /** @var list<string> */
    private const UPGRADE_TO_PRO_REASONS = [
        'payment_action_required',
        'pro_active',
        'sync_pending',
        'missing_subscription',
        'inactive',
        'unsupported_state',
    ];

    /**
     * @param mixed $value
     * @return string|null
     */
    private function normalizeUpgradeToProReason($value): ?string {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        if (!in_array($trimmed, self::UPGRADE_TO_PRO_REASONS, true)) {
            return null;
        }

        return $trimmed;
    }

    /** @var list<string> */
    private const BENEFIT_QUOTA_ITEM_KEYS = [
        'deoia_email_sends',
        'deoia_ai_chat_queries',
        'deoia_google_calendar_syncs',
    ];

    /** @var list<string> */
    private const BENEFIT_QUOTA_ITEM_ALLOWED_KEYS = [
        'key',
        'limit',
        'consumed',
        'remaining',
        'can_consume',
        'at_limit',
        'exceeded',
    ];

    private const SHARED_POOL_CALENDAR_AND_PUSH = 'calendar_and_push';

    /** @var list<string> */
    private const SHARED_POOL_ALLOWED_KEYS = [
        'limit',
        'consumed',
        'reserved',
        'allocated',
        'remaining',
        'can_consume',
        'at_limit',
        'exceeded',
        'member_keys',
        'breakdown',
    ];

    /**
     * @param mixed $value
     * @return int|null|false false when not a finite int (or null)
     */
    private function normalizeOptionalInt($value) {
        if ($value === null) {
            return null;
        }
        if (!is_numeric($value)) {
            return false;
        }
        return (int) $value;
    }

    /**
     * Strict normalization of shared_pools.calendar_and_push.
     * Incomplete/invalid pool is omitted (legacy Calendar fallback in UX).
     *
     * @param mixed $value
     * @return array<string,array<string,mixed>>
     */
    private function normalizeSharedPools($value): array {
        if (!is_array($value)) {
            return [];
        }

        if (!isset($value[self::SHARED_POOL_CALENDAR_AND_PUSH]) || !is_array($value[self::SHARED_POOL_CALENDAR_AND_PUSH])) {
            return [];
        }

        $raw = $value[self::SHARED_POOL_CALENDAR_AND_PUSH];
        foreach (self::SHARED_POOL_ALLOWED_KEYS as $required) {
            if (!array_key_exists($required, $raw)) {
                return [];
            }
        }

        $limit = $this->normalizeOptionalInt($raw['limit']);
        $consumed = $this->normalizeOptionalInt($raw['consumed']);
        $reserved = $this->normalizeOptionalInt($raw['reserved']);
        $allocated = $this->normalizeOptionalInt($raw['allocated']);
        $remaining = $this->normalizeOptionalInt($raw['remaining']);

        if ($limit === false || $consumed === false || $reserved === false || $allocated === false || $remaining === false) {
            return [];
        }

        if (!is_array($raw['breakdown'])) {
            return [];
        }
        if (!array_key_exists('calendar', $raw['breakdown']) || !array_key_exists('push', $raw['breakdown'])) {
            return [];
        }

        $calendar = $this->normalizeOptionalInt($raw['breakdown']['calendar']);
        $push = $this->normalizeOptionalInt($raw['breakdown']['push']);
        if ($calendar === false || $push === false || $calendar === null || $push === null) {
            return [];
        }

        if (!is_array($raw['member_keys'])) {
            return [];
        }
        $member_keys = [];
        foreach ($raw['member_keys'] as $member_key) {
            if (!is_scalar($member_key)) {
                continue;
            }
            $trimmed = trim((string) $member_key);
            if ($trimmed !== '') {
                $member_keys[] = $trimmed;
            }
        }
        if (count($member_keys) === 0) {
            return [];
        }

        $normalized = [
            'limit'       => $limit,
            'consumed'    => $consumed,
            'reserved'    => $reserved,
            'allocated'   => $allocated,
            'remaining'   => $remaining,
            'can_consume' => (bool) $raw['can_consume'],
            'at_limit'    => (bool) $raw['at_limit'],
            'exceeded'    => (bool) $raw['exceeded'],
            'member_keys' => $member_keys,
            'breakdown'   => [
                'calendar' => $calendar,
                'push'     => $push,
            ],
        ];

        return [
            self::SHARED_POOL_CALENDAR_AND_PUSH => $normalized,
        ];
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function normalizeOptionalErrorString($value): ?string {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>|null
     */
    private function normalizeBenefitQuotas($value): ?array {
        if (!is_array($value)) {
            return null;
        }

        $period = isset($value['period_yyyymm']) ? trim((string) $value['period_yyyymm']) : '';
        if ($period === '' || !preg_match('/^[0-9]{6}$/', $period)) {
            return null;
        }

        $items = [];
        if (isset($value['items']) && is_array($value['items'])) {
            foreach ($value['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $key = isset($item['key']) ? trim((string) $item['key']) : '';
                if ($key === '' || !in_array($key, self::BENEFIT_QUOTA_ITEM_KEYS, true)) {
                    continue;
                }

                $normalized_item = ['key' => $key];
                foreach (self::BENEFIT_QUOTA_ITEM_ALLOWED_KEYS as $field) {
                    if ($field === 'key') {
                        continue;
                    }

                    if (!array_key_exists($field, $item)) {
                        continue;
                    }

                    $field_value = $item[$field];

                    if (in_array($field, ['can_consume', 'at_limit', 'exceeded'], true)) {
                        $normalized_item[$field] = (bool) $field_value;
                        continue;
                    }

                    if ($field_value === null) {
                        $normalized_item[$field] = null;
                        continue;
                    }

                    if (is_numeric($field_value)) {
                        $normalized_item[$field] = (int) $field_value;
                    }
                }

                $items[] = $normalized_item;
            }
        }

        return [
            'period_yyyymm'             => $period,
            'usage_counters_applicable' => !empty($value['usage_counters_applicable']),
            'quota_read_error'          => $this->normalizeOptionalErrorString(
                array_key_exists('quota_read_error', $value) ? $value['quota_read_error'] : null
            ),
            'shared_pool_read_error'    => $this->normalizeOptionalErrorString(
                array_key_exists('shared_pool_read_error', $value) ? $value['shared_pool_read_error'] : null
            ),
            'access_reason'             => $this->normalizeOptionalErrorString(
                array_key_exists('access_reason', $value) ? $value['access_reason'] : null
            ),
            'items'                     => $items,
            'shared_pools'              => $this->normalizeSharedPools(
                array_key_exists('shared_pools', $value) ? $value['shared_pools'] : []
            ),
        ];
    }

    /**
     * Local read-only snapshot for the public website status section in Account.
     *
     * @return array{
     *     is_provisioned: bool,
     *     status: string,
     *     show_section: bool,
     *     can_activate: bool,
     *     can_deactivate: bool,
     *     public_url: string|null
     * }
     */
    private function resolvePublicSitePayload(): array {
        $is_provisioned = class_exists('AA_Installation_Provisioning_Detector')
            && AA_Installation_Provisioning_Detector::is_provisioned();

        if (!$is_provisioned) {
            return [
                'is_provisioned' => false,
                'status'         => AA_Public_Site_Status::STATUS_ACTIVE,
                'show_section'   => false,
                'can_activate'   => false,
                'can_deactivate' => false,
                'public_url'     => null,
            ];
        }

        $status = class_exists('AA_Public_Site_Status')
            ? AA_Public_Site_Status::current()
            : AA_Public_Site_Status::STATUS_ACTIVE;

        return [
            'is_provisioned' => true,
            'status'         => $status,
            'show_section'   => true,
            'can_activate'   => false,
            'can_deactivate' => false,
            'public_url'     => $this->resolvePublicSitePreviewUrl(),
        ];
    }

    private function resolvePublicSitePreviewUrl(): string {
        if (class_exists('AA_Public_Site_Preview')) {
            return AA_Public_Site_Preview::public_url();
        }

        return add_query_arg('deoia_public_preview', '1', home_url('/'));
    }

    /**
     * @param array<string,mixed> $public_site
     * @param array<string,mixed> $context
     * @return array{
     *     success: false,
     *     error: array{code: string, message: string, reason?: string},
     *     data: array{public_site: array<string,mixed>}
     * }
     */
    private function failure(string $code, string $message, array $public_site, array $context = []): array {
        $error = [
            'code'    => $code,
            'message' => $message,
        ];

        if (isset($context['reason']) && is_string($context['reason']) && $context['reason'] !== '') {
            $error['reason'] = $context['reason'];
        }

        return [
            'success' => false,
            'error'   => $error,
            'data'    => [
                'public_site' => $public_site,
            ],
        ];
    }
}
