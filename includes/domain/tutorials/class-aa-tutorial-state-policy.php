<?php
/**
 * Tutorial State Policy — contrato durable de tutoriales (site-scoped).
 *
 * Regla pura: no consulta BD ni WordPress.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Tutorial_State_Policy {

    public const STATE_VERSION = 1;

    public const TUTORIAL_CREATE_TEST_APPOINTMENT = 'create_test_appointment_v1';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    /** @var list<string> */
    public const ALLOWED_TUTORIAL_IDS = [
        self::TUTORIAL_CREATE_TEST_APPOINTMENT,
    ];

    /** @var list<string> */
    private const ALLOWED_STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_IN_PROGRESS,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
    ];

    /** @var list<string> */
    private const ALLOWED_TUTORIAL_KEYS = [
        'status',
        'current_step_id',
        'accepted_at',
        'started_at',
        'paused_at',
        'completed_at',
        'updated_at',
    ];

    /** @var array<string,list<string>> */
    public const STEP_ORDER_BY_TUTORIAL = [
        self::TUTORIAL_CREATE_TEST_APPOINTMENT => [
            'open_sidebar',
            'open_calendar',
            'calendar_overview',
            'create_test_appointment',
        ],
    ];

    /**
     * @return array<string,mixed>
     */
    public static function empty_state(): array {
        return [
            'version' => self::STATE_VERSION,
            'tutorials' => [],
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

        $tutorials_in = $raw['tutorials'] ?? [];
        $tutorials_out = [];

        if (!is_array($tutorials_in)) {
            return self::empty_state();
        }

        foreach ($tutorials_in as $tutorial_id => $tutorial_state) {
            if (!is_string($tutorial_id) || !in_array($tutorial_id, self::ALLOWED_TUTORIAL_IDS, true)) {
                continue;
            }

            if (!is_array($tutorial_state)) {
                continue;
            }

            $sanitized_tutorial = self::sanitize_tutorial($tutorial_id, $tutorial_state);

            if ($sanitized_tutorial !== null) {
                $tutorials_out[$tutorial_id] = $sanitized_tutorial;
            }
        }

        return [
            'version' => self::STATE_VERSION,
            'tutorials' => $tutorials_out,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param string              $tutorial_id
     * @return array<string,mixed>
     */
    public static function get_effective_tutorial(array $state, string $tutorial_id): array {
        $sanitized = self::sanitize($state);
        $stored = $sanitized['tutorials'][$tutorial_id] ?? null;

        if (!is_array($stored)) {
            return self::virtual_available_tutorial();
        }

        return $stored;
    }

    /**
     * @param array<string,mixed> $state
     * @param string              $tutorial_id
     * @param array<string,mixed> $input
     * @return array{ok:bool,state?:array<string,mixed>,transition_kind?:string,error?:array{code:string,message:string}}
     */
    public static function apply_transition(array $state, string $tutorial_id, array $input): array {
        $tutorial_id = sanitize_key($tutorial_id);

        if ($tutorial_id === '' || !in_array($tutorial_id, self::ALLOWED_TUTORIAL_IDS, true)) {
            return self::error('invalid_tutorial_id', 'Tutorial no permitido.');
        }

        $requested_status = isset($input['status']) ? sanitize_key((string) $input['status']) : '';

        if ($requested_status === '' || !in_array($requested_status, self::ALLOWED_STATUSES, true)) {
            return self::error('invalid_status', 'Estado de tutorial inválido.');
        }

        if ($requested_status === self::STATUS_AVAILABLE) {
            return self::error('invalid_transition', 'No se puede persistir un tutorial en estado available.');
        }

        $sanitized = self::sanitize($state);
        $before = self::get_effective_tutorial($sanitized, $tutorial_id);
        $before_status = (string) ($before['status'] ?? self::STATUS_AVAILABLE);

        $requested_step = null;
        if (array_key_exists('current_step_id', $input)) {
            $requested_step = self::normalize_step_id(
                $tutorial_id,
                $input['current_step_id'],
                $requested_status === self::STATUS_COMPLETED
            );

            if ($requested_step === false) {
                return self::error('invalid_step_id', 'Paso de tutorial no permitido.');
            }
        }

        $validation = self::validate_transition(
            $tutorial_id,
            $before_status,
            (string) ($before['current_step_id'] ?? ''),
            $requested_status,
            $requested_step
        );

        if (empty($validation['ok'])) {
            return $validation;
        }

        $transition_kind = (string) ($validation['transition_kind'] ?? '');
        $next_step = (string) ($validation['next_step_id'] ?? '');

        $next_tutorial = self::build_next_tutorial($before, $requested_status, $next_step);
        $sanitized['tutorials'][$tutorial_id] = $next_tutorial;

        return [
            'ok' => true,
            'state' => $sanitized,
            'transition_kind' => $transition_kind,
        ];
    }

    /**
     * Reconcilia el tutorial cuando ya existe al menos una cita creada.
     *
     * Regla pura: $exists debe estar confirmado por capa de aplicación.
     *
     * @param array<string,mixed> $state
     * @param string              $tutorial_id
     * @param bool                $exists
     * @return array{changed:bool,state:array<string,mixed>}
     */
    public static function reconcile_for_reservation_existence(
        array $state,
        string $tutorial_id,
        bool $exists
    ): array {
        $tutorial_id = sanitize_key($tutorial_id);

        if ($tutorial_id === '' || !in_array($tutorial_id, self::ALLOWED_TUTORIAL_IDS, true)) {
            return [
                'changed' => false,
                'state' => self::sanitize($state),
            ];
        }

        $sanitized = self::sanitize($state);
        $before = self::get_effective_tutorial($sanitized, $tutorial_id);
        $before_status = (string) ($before['status'] ?? self::STATUS_AVAILABLE);

        if (!$exists || $before_status === self::STATUS_COMPLETED) {
            return [
                'changed' => false,
                'state' => $sanitized,
            ];
        }

        $sanitized['tutorials'][$tutorial_id] = self::build_reconciled_completed_tutorial($before);

        return [
            'changed' => true,
            'state' => $sanitized,
        ];
    }

    /**
     * @param string $tutorial_id
     * @param string|null $current_step_id
     * @return string|null
     */
    public static function get_next_step(string $tutorial_id, ?string $current_step_id): ?string {
        $order = self::STEP_ORDER_BY_TUTORIAL[$tutorial_id] ?? [];

        if ($current_step_id === null || $current_step_id === '') {
            return $order[0] ?? null;
        }

        $index = array_search($current_step_id, $order, true);

        if ($index === false) {
            return null;
        }

        return $order[$index + 1] ?? null;
    }

    /**
     * @param string $tutorial_id
     * @return string|null
     */
    public static function get_first_step(string $tutorial_id): ?string {
        $order = self::STEP_ORDER_BY_TUTORIAL[$tutorial_id] ?? [];

        return $order[0] ?? null;
    }

    /**
     * @param string $tutorial_id
     * @return string|null
     */
    public static function get_last_step(string $tutorial_id): ?string {
        $order = self::STEP_ORDER_BY_TUTORIAL[$tutorial_id] ?? [];

        if ($order === []) {
            return null;
        }

        return $order[count($order) - 1];
    }

    /**
     * @return array<string,mixed>
     */
    private static function virtual_available_tutorial(): array {
        return [
            'status' => self::STATUS_AVAILABLE,
            'current_step_id' => null,
            'accepted_at' => null,
            'started_at' => null,
            'paused_at' => null,
            'completed_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * @param string              $tutorial_id
     * @param array<string,mixed> $tutorial_state
     * @return array<string,mixed>|null
     */
    private static function sanitize_tutorial(string $tutorial_id, array $tutorial_state): ?array {
        $status = isset($tutorial_state['status']) ? sanitize_key((string) $tutorial_state['status']) : '';

        if ($status === '' || !in_array($status, self::ALLOWED_STATUSES, true)) {
            return null;
        }

        if ($status === self::STATUS_AVAILABLE) {
            return null;
        }

        $result = self::virtual_available_tutorial();
        $result['status'] = $status;

        foreach (self::ALLOWED_TUTORIAL_KEYS as $key) {
            if ($key === 'status' || !array_key_exists($key, $tutorial_state)) {
                continue;
            }

            if ($key === 'current_step_id') {
                $step_id = self::normalize_step_id(
                    $tutorial_id,
                    $tutorial_state[$key],
                    $status === self::STATUS_COMPLETED
                );

                if ($step_id === false) {
                    return null;
                }

                $result[$key] = $step_id;
                continue;
            }

            $datetime = self::normalize_datetime($tutorial_state[$key]);

            if ($datetime === false) {
                return null;
            }

            $result[$key] = $datetime;
        }

        if ($status === self::STATUS_COMPLETED) {
            $result['current_step_id'] = null;
        }

        if ($status === self::STATUS_IN_PROGRESS || $status === self::STATUS_PAUSED) {
            if ($result['current_step_id'] === null || $result['current_step_id'] === '') {
                return null;
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $before
     * @return array<string,mixed>
     */
    private static function build_reconciled_completed_tutorial(array $before): array {
        $before_status = (string) ($before['status'] ?? self::STATUS_AVAILABLE);

        if ($before_status === self::STATUS_AVAILABLE) {
            $next = self::virtual_available_tutorial();
            $next['status'] = self::STATUS_COMPLETED;
            $next['current_step_id'] = null;

            return $next;
        }

        $next = $before;
        $next['status'] = self::STATUS_COMPLETED;
        $next['current_step_id'] = null;

        return $next;
    }

    /**
     * @param array<string,mixed> $before
     * @param string              $status
     * @param string              $next_step_id
     * @return array<string,mixed>
     */
    private static function build_next_tutorial(array $before, string $status, string $next_step_id): array {
        $next = $before;
        $next['status'] = $status;

        if ($status === self::STATUS_COMPLETED) {
            $next['current_step_id'] = null;
        } else {
            $next['current_step_id'] = $next_step_id;
        }

        return $next;
    }

    /**
     * @return array{ok:bool,transition_kind?:string,next_step_id?:string,error?:array{code:string,message:string}}
     */
    private static function validate_transition(
        string $tutorial_id,
        string $before_status,
        string $before_step_id,
        string $requested_status,
        $requested_step
    ): array {
        if ($before_status === self::STATUS_COMPLETED) {
            return self::error('invalid_transition', 'El tutorial ya está completado.');
        }

        if ($before_status === self::STATUS_AVAILABLE) {
            if ($requested_status !== self::STATUS_IN_PROGRESS) {
                return self::error('invalid_transition', 'Un tutorial available solo puede pasar a in_progress.');
            }

            $first_step = self::get_first_step($tutorial_id);

            if ($first_step === null) {
                return self::error('invalid_step_id', 'El tutorial no tiene pasos configurados.');
            }

            if ($requested_step !== $first_step) {
                return self::error('invalid_step_transition', 'La aceptación del tutorial debe iniciar en el primer paso.');
            }

            return [
                'ok' => true,
                'transition_kind' => 'accept',
                'next_step_id' => $first_step,
            ];
        }

        if ($before_status === self::STATUS_IN_PROGRESS) {
            if ($requested_status === self::STATUS_IN_PROGRESS) {
                $next_step = self::get_next_step($tutorial_id, $before_step_id);

                if ($next_step === null) {
                    return self::error('invalid_step_transition', 'No hay un paso siguiente válido.');
                }

                if ($requested_step !== $next_step) {
                    return self::error('invalid_step_transition', 'Solo se permite avanzar al siguiente paso lineal.');
                }

                return [
                    'ok' => true,
                    'transition_kind' => 'advance',
                    'next_step_id' => $next_step,
                ];
            }

            if ($requested_status === self::STATUS_PAUSED) {
                if ($requested_step !== null && $requested_step !== $before_step_id) {
                    return self::error('invalid_step_transition', 'Al pausar no se puede cambiar el paso actual.');
                }

                return [
                    'ok' => true,
                    'transition_kind' => 'pause',
                    'next_step_id' => $before_step_id,
                ];
            }

            if ($requested_status === self::STATUS_COMPLETED) {
                $last_step = self::get_last_step($tutorial_id);

                if ($before_step_id !== $last_step) {
                    return self::error('invalid_transition', 'Solo se puede completar el tutorial desde el último paso.');
                }

                if ($requested_step !== null && $requested_step !== '') {
                    return self::error('invalid_step_transition', 'Al completar el paso actual debe ser null.');
                }

                return [
                    'ok' => true,
                    'transition_kind' => 'complete',
                    'next_step_id' => '',
                ];
            }
        }

        if ($before_status === self::STATUS_PAUSED) {
            if ($requested_status === self::STATUS_IN_PROGRESS) {
                if ($requested_step !== null && $requested_step !== $before_step_id) {
                    return self::error('invalid_step_transition', 'Al reanudar no se puede cambiar el paso actual.');
                }

                return [
                    'ok' => true,
                    'transition_kind' => 'resume',
                    'next_step_id' => $before_step_id,
                ];
            }

            if ($requested_status === self::STATUS_COMPLETED) {
                $last_step = self::get_last_step($tutorial_id);

                if ($before_step_id !== $last_step) {
                    return self::error('invalid_transition', 'Solo se puede completar el tutorial desde el último paso.');
                }

                if ($requested_step !== null && $requested_step !== '') {
                    return self::error('invalid_step_transition', 'Al completar el paso actual debe ser null.');
                }

                return [
                    'ok' => true,
                    'transition_kind' => 'complete',
                    'next_step_id' => '',
                ];
            }
        }

        return self::error('invalid_transition', 'Transición de tutorial no permitida.');
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
     * @param string $tutorial_id
     * @param mixed  $value
     * @param bool   $allow_null_only
     * @return string|null|false
     */
    private static function normalize_step_id(string $tutorial_id, $value, bool $allow_null_only = false) {
        if ($value === null || $value === '') {
            return $allow_null_only ? null : null;
        }

        if (!is_string($value)) {
            return false;
        }

        $step_id = sanitize_key($value);

        if ($step_id === '') {
            return false;
        }

        $allowed = self::STEP_ORDER_BY_TUTORIAL[$tutorial_id] ?? [];

        if (!in_array($step_id, $allowed, true)) {
            return false;
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
