<?php
/**
 * Onboarding Tutor State Policy — contrato durable UX del tutor (site-scoped).
 *
 * Regla pura: no consulta BD ni WordPress.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Onboarding_Tutor_State_Policy {

    public const STATE_VERSION = 1;

    public const FLOW_TEST_APPOINTMENT = 'test_appointment_v1';

    /** @var list<string> */
    public const ALLOWED_FLOW_IDS = [
        self::FLOW_TEST_APPOINTMENT,
    ];

    /** @var list<string> */
    private const ALLOWED_FLOW_KEYS = [
        'intro_seen_at',
        'completed_at',
        'dismissed_at',
        'last_durable_step_id',
        'updated_at',
    ];

    /** @var array<string,list<string>> */
    private const ALLOWED_STEP_IDS_BY_FLOW = [
        self::FLOW_TEST_APPOINTMENT => [
            'intro',
            'sidebar_agenda',
            'agenda_page',
            'create_button',
            'quick_modal',
            'appointment_created',
            'complete',
        ],
    ];

    /**
     * @return array<string,mixed>
     */
    public static function empty_state(): array {
        return [
            'version' => self::STATE_VERSION,
            'flows' => [],
        ];
    }

    /**
     * @param mixed $raw
     * @return array<string,mixed>
     */
    public static function sanitize($raw): array {
        if (!is_array($raw)) {
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);

                return self::sanitize(is_array($decoded) ? $decoded : []);
            }

            return self::empty_state();
        }

        $version = (int) ($raw['version'] ?? 0);

        if ($version !== self::STATE_VERSION) {
            return self::empty_state();
        }

        $flows_in = $raw['flows'] ?? [];
        $flows_out = [];

        if (!is_array($flows_in)) {
            return self::empty_state();
        }

        foreach ($flows_in as $flow_id => $flow_state) {
            if (!is_string($flow_id) || !in_array($flow_id, self::ALLOWED_FLOW_IDS, true)) {
                continue;
            }

            if (!is_array($flow_state)) {
                continue;
            }

            $sanitized_flow = self::sanitize_flow($flow_id, $flow_state);

            if ($sanitized_flow !== null) {
                $flows_out[$flow_id] = $sanitized_flow;
            }
        }

        return [
            'version' => self::STATE_VERSION,
            'flows' => $flows_out,
        ];
    }

    /**
     * @param string               $flow_id
     * @param array<string,mixed>  $state
     * @param array<string,mixed>  $patch
     * @return array{ok:bool,state?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public static function apply_flow_patch(array $state, string $flow_id, array $patch): array {
        $flow_id = sanitize_key($flow_id);

        if ($flow_id === '' || !in_array($flow_id, self::ALLOWED_FLOW_IDS, true)) {
            return self::error('invalid_flow_id', 'Flujo de tutor no permitido.');
        }

        if ($patch === []) {
            return self::error('empty_patch', 'No se recibieron cambios para el tutor.');
        }

        $sanitized = self::sanitize($state);
        $current_flow = $sanitized['flows'][$flow_id] ?? self::empty_flow($flow_id);
        $next_flow = $current_flow;

        foreach ($patch as $key => $value) {
            if (!in_array($key, self::ALLOWED_FLOW_KEYS, true)) {
                return self::error('invalid_patch_key', 'Campo de tutor no permitido: ' . $key);
            }

            if ($key === 'updated_at') {
                continue;
            }

            if ($key === 'last_durable_step_id') {
                $step_id = self::normalize_step_id($flow_id, $value);

                if ($step_id === null && $value !== null && $value !== '') {
                    return self::error('invalid_step_id', 'Paso durable de tutor no permitido.');
                }

                $next_flow[$key] = $step_id;
                continue;
            }

            $datetime = self::normalize_datetime($value);

            if ($datetime === false) {
                return self::error('invalid_datetime', 'Fecha de tutor inválida para ' . $key . '.');
            }

            $next_flow[$key] = $datetime;
        }

        $sanitized['flows'][$flow_id] = $next_flow;

        return [
            'ok' => true,
            'state' => $sanitized,
        ];
    }

    /**
     * @param string $flow_id
     * @return array<string,mixed>
     */
    public static function empty_flow(string $flow_id): array {
        return [
            'intro_seen_at' => null,
            'completed_at' => null,
            'dismissed_at' => null,
            'last_durable_step_id' => null,
            'updated_at' => null,
        ];
    }

    /**
     * @param string              $flow_id
     * @param array<string,mixed> $flow_state
     * @return array<string,mixed>|null
     */
    private static function sanitize_flow(string $flow_id, array $flow_state): ?array {
        $result = self::empty_flow($flow_id);

        foreach (self::ALLOWED_FLOW_KEYS as $key) {
            if (!array_key_exists($key, $flow_state)) {
                continue;
            }

            if ($key === 'last_durable_step_id') {
                $result[$key] = self::normalize_step_id($flow_id, $flow_state[$key]);
                continue;
            }

            $datetime = self::normalize_datetime($flow_state[$key]);

            if ($datetime === false) {
                return null;
            }

            $result[$key] = $datetime;
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @return string|null|false null válido, false inválido
     */
    private static function normalize_datetime($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return false;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $trimmed)) {
            return false;
        }

        return $trimmed;
    }

    /**
     * @param string $flow_id
     * @param mixed  $value
     * @return string|null
     */
    private static function normalize_step_id(string $flow_id, $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $step_id = sanitize_key($value);

        if ($step_id === '') {
            return null;
        }

        $allowed = self::ALLOWED_STEP_IDS_BY_FLOW[$flow_id] ?? [];

        if (!in_array($step_id, $allowed, true)) {
            return null;
        }

        return $step_id;
    }

    /**
     * @param string $code
     * @param string $message
     * @return array{ok:false,error:array{code:string,message:string}}
     */
    private static function error(string $code, string $message): array {
        return [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
