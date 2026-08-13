<?php
/**
 * Update Service Use Case — actualización atómica de campos del modal de servicio.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';

final class UpdateServiceUseCase {

    public const NAME_MAX_LENGTH = 191;
    public const CODE_MAX_LENGTH = 191;

    /** @var array<int,int> */
    public const ALLOWED_DURATIONS = [30, 60, 90];

    /** @var array<int,string> */
    public const ALLOWED_TYPES = ['physical', 'virtual'];

    /** @var array<int,string> */
    public const ALLOWED_CHANNELS = ['whatsapp', 'google_meet', 'custom_link'];

    /** @var callable(int): ?array */
    private $find_by_id;

    /** @var callable(int, array): bool */
    private $update_fields;

    /**
     * @param callable|null $find_by_id
     * @param callable|null $update_fields
     */
    public function __construct(?callable $find_by_id = null, ?callable $update_fields = null) {
        $this->find_by_id = $find_by_id ?? [AssignmentsRepository::class, 'find_service_by_id'];
        $this->update_fields = $update_fields ?? [AssignmentsRepository::class, 'update_service_fields'];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array{service:array<string,mixed>},error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $name = isset($input['name']) ? trim((string) $input['name']) : '';

        if ($id <= 0) {
            return $this->fail('invalid_id', 'ID inválido');
        }

        if ($name === '') {
            return $this->fail('invalid_name', 'El nombre no puede estar vacío');
        }

        if ($this->string_length($name) > self::NAME_MAX_LENGTH) {
            return $this->fail(
                'invalid_name',
                'El nombre no puede superar ' . self::NAME_MAX_LENGTH . ' caracteres'
            );
        }

        $existing = ($this->find_by_id)($id);
        if (!is_array($existing)) {
            return $this->fail('not_found', 'Servicio no encontrado');
        }

        $current = $this->canonical_from_row($existing);
        $desired = $this->normalize_desired($input, $current);
        if (isset($desired['error'])) {
            return $this->fail($desired['error']['code'], $desired['error']['message']);
        }

        if ($this->snapshots_equal($current, $desired)) {
            return $this->ok($this->with_id($id, $current));
        }

        $updated = ($this->update_fields)($id, $desired);
        if ($updated !== true) {
            return $this->fail('persistence_failed', 'Error al actualizar el servicio');
        }

        $reloaded = ($this->find_by_id)($id);
        $row = is_array($reloaded)
            ? $this->canonical_from_row($reloaded)
            : $desired;

        return $this->ok($this->with_id($id, $row));
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $current
     * @return array<string,mixed>|array{error:array{code:string,message:string}}
     */
    private function normalize_desired(array $input, array $current): array {
        $code = isset($input['code']) ? trim((string) $input['code']) : '';
        if ($this->string_length($code) > self::CODE_MAX_LENGTH) {
            return $this->normalize_fail('invalid_code', 'El código no puede superar ' . self::CODE_MAX_LENGTH . ' caracteres');
        }

        $price = $this->normalize_price($input['price'] ?? '');
        if ($price === false) {
            return $this->normalize_fail('invalid_price', 'El precio no es válido');
        }

        $public_calendar = ((int) ($input['public_calendar'] ?? 0) === 1) ? 1 : 0;

        $indicaciones = isset($input['indicaciones_cita']) ? trim((string) $input['indicaciones_cita']) : '';
        $indicaciones = $indicaciones === '' ? null : $indicaciones;

        $duration = $this->normalize_duration($input['duration_minutes'] ?? '', $current['duration_minutes'] ?? null);
        if ($duration === false) {
            return $this->normalize_fail('invalid_duration', 'La duración del servicio debe ser 30, 60 o 90 minutos');
        }

        $attendance = $this->normalize_attendance_type($input['attendance_type'] ?? '');
        if ($attendance === false) {
            return $this->normalize_fail('invalid_attendance_type', 'El tipo de atención no es válido');
        }

        $channel = $this->normalize_virtual_channel(
            $input['virtual_channel'] ?? '',
            $attendance,
            $current['virtual_channel'] ?? null
        );
        if ($channel === false) {
            return $this->normalize_fail('invalid_virtual_channel', 'El canal virtual no es válido');
        }

        return [
            'name' => isset($input['name']) ? trim((string) $input['name']) : '',
            'code' => $code,
            'price' => $price,
            'public_calendar' => $public_calendar,
            'indicaciones_cita' => $indicaciones,
            'duration_minutes' => $duration,
            'attendance_type' => $attendance,
            'virtual_channel' => $channel,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function canonical_from_row(array $row): array {
        $price = $this->normalize_price($row['price'] ?? null);
        if ($price === false) {
            $raw = $row['price'] ?? null;
            $price = ($raw === null || $raw === '') ? null : (string) $raw;
        }

        $indicaciones = isset($row['indicaciones_cita']) ? trim((string) $row['indicaciones_cita']) : '';
        $attendance = isset($row['attendance_type']) ? trim((string) $row['attendance_type']) : '';
        $channel = isset($row['virtual_channel']) ? trim((string) $row['virtual_channel']) : '';
        $duration = $row['duration_minutes'] ?? null;

        return [
            'name' => trim((string) ($row['name'] ?? '')),
            'code' => trim((string) ($row['code'] ?? '')),
            'price' => $price,
            'public_calendar' => ((int) ($row['public_calendar'] ?? 0) === 1) ? 1 : 0,
            'indicaciones_cita' => $indicaciones === '' ? null : $indicaciones,
            'duration_minutes' => ($duration === null || $duration === '') ? null : (int) $duration,
            'attendance_type' => $attendance === '' ? null : $attendance,
            'virtual_channel' => $channel === '' ? null : $channel,
        ];
    }

    /**
     * @param mixed $raw
     * @return string|null|false
     */
    private function normalize_price($raw) {
        if ($raw === null) {
            return null;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        if (isset($value[0]) && $value[0] === '-') {
            return false;
        }

        if (!preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/', $value, $matches)) {
            return false;
        }

        $integer = ltrim($matches[1], '0');
        if ($integer === '') {
            $integer = '0';
        }

        $decimals = isset($matches[2]) ? str_pad($matches[2], 2, '0') : '00';

        return $integer . '.' . $decimals;
    }

    /**
     * @param mixed $raw
     * @param int|null $current
     * @return int|null|false
     */
    private function normalize_duration($raw, $current) {
        if ($raw === null) {
            return null;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d+$/', $value)) {
            return false;
        }

        $minutes = (int) $value;
        if (in_array($minutes, self::ALLOWED_DURATIONS, true)) {
            return $minutes;
        }

        if ($current !== null && $minutes === (int) $current && $minutes > 0) {
            return $minutes;
        }

        return false;
    }

    /**
     * @param mixed $raw
     * @return string|null|false
     */
    private function normalize_attendance_type($raw) {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        if (in_array($value, self::ALLOWED_TYPES, true)) {
            return $value;
        }

        return false;
    }

    /**
     * @param mixed $raw
     * @param string|null $attendance
     * @param string|null $current
     * @return string|null|false
     */
    private function normalize_virtual_channel($raw, $attendance, $current) {
        if ($attendance !== 'virtual') {
            return null;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        if (in_array($value, self::ALLOWED_CHANNELS, true)) {
            return $value;
        }

        if (is_string($current) && $current !== '' && $value === $current) {
            return $value;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    private function snapshots_equal(array $left, array $right): bool {
        return $left['name'] === $right['name']
            && $left['code'] === $right['code']
            && $left['price'] === $right['price']
            && (int) $left['public_calendar'] === (int) $right['public_calendar']
            && $left['indicaciones_cita'] === $right['indicaciones_cita']
            && $left['duration_minutes'] === $right['duration_minutes']
            && $left['attendance_type'] === $right['attendance_type']
            && $left['virtual_channel'] === $right['virtual_channel'];
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    private function with_id(int $id, array $snapshot): array {
        $snapshot['id'] = $id;
        return $snapshot;
    }

    private function string_length(string $value): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value);
        }

        return strlen($value);
    }

    /**
     * @return array{error:array{code:string,message:string}}
     */
    private function normalize_fail(string $code, string $message): array {
        return [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $service
     * @return array{success:true,data:array{service:array<string,mixed>}}
     */
    private function ok(array $service): array {
        return [
            'success' => true,
            'data' => [
                'service' => $service,
            ],
        ];
    }

    /**
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function fail(string $code, string $message): array {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
