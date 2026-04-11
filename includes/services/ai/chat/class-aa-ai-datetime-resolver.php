<?php
/**
 * AI Datetime Resolver
 *
 * Interpreta date_text y time_text del parser LLM a fecha/hora local
 * normalizada en la timezone del negocio.
 *
 * No debe: ejecutar SQL, conocer clientes/staff/servicios, crear reservas,
 * consultar disponibilidad ni renderizar UI.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Datetime_Resolver {

    /** @var \DateTimeImmutable */
    private $now;

    /** @var \DateTimeZone */
    private $tz;

    /** @var string */
    private $tz_name;

    private const DAY_MAP = [
        'lunes'      => 'Monday',
        'martes'     => 'Tuesday',
        'miércoles'  => 'Wednesday',
        'miercoles'  => 'Wednesday',
        'jueves'     => 'Thursday',
        'viernes'    => 'Friday',
        'sábado'     => 'Saturday',
        'sabado'     => 'Saturday',
        'domingo'    => 'Sunday',
    ];

    private const MONTH_MAP = [
        'enero'      => 1,
        'febrero'    => 2,
        'marzo'      => 3,
        'abril'      => 4,
        'mayo'       => 5,
        'junio'      => 6,
        'julio'      => 7,
        'agosto'     => 8,
        'septiembre' => 9,
        'octubre'    => 10,
        'noviembre'  => 11,
        'diciembre'  => 12,
    ];

    public function __construct() {
        $this->tz_name = get_option('aa_timezone', 'America/Mexico_City');
        $this->tz      = new \DateTimeZone($this->tz_name);
        $this->now     = new \DateTimeImmutable('now', $this->tz);
    }

    /**
     * Resuelve date_text y time_text a una estructura temporal normalizada.
     *
     * @param string|null $date_text
     * @param string|null $time_text
     * @return array
     */
    public function resolve($date_text, $time_text) {
        $date_text = is_string($date_text) ? mb_strtolower(trim($date_text)) : null;
        $time_text = is_string($time_text) ? mb_strtolower(trim($time_text)) : null;

        $date_result = $this->resolve_date($date_text);
        $time_result = $this->resolve_time($time_text);

        return $this->build_output($date_text, $time_text, $date_result, $time_result);
    }

    // ─── Date resolution ────────────────────────────────────────

    /**
     * @param string|null $text
     * @return array{date: \DateTimeImmutable|null, source_type: string, error: string|null}
     */
    private function resolve_date($text) {
        if ($text === null || $text === '') {
            return ['date' => null, 'source_type' => null, 'error' => 'missing'];
        }

        if ($text === 'hoy') {
            return ['date' => $this->now, 'source_type' => 'interpreted', 'error' => null];
        }

        if ($text === 'mañana') {
            return [
                'date'        => $this->now->modify('+1 day'),
                'source_type' => 'interpreted',
                'error'       => null,
            ];
        }

        if ($text === 'pasado mañana') {
            return [
                'date'        => $this->now->modify('+2 days'),
                'source_type' => 'interpreted',
                'error'       => null,
            ];
        }

        $day_result = $this->resolve_day_name($text);
        if ($day_result !== null) {
            return $day_result;
        }

        $explicit_result = $this->resolve_explicit_date($text);
        if ($explicit_result !== null) {
            return $explicit_result;
        }

        return ['date' => null, 'source_type' => 'captured', 'error' => 'unrecognized'];
    }

    /**
     * @param string $text
     * @return array|null
     */
    private function resolve_day_name($text) {
        $cleaned = str_replace(['el ', 'este ', 'próximo ', 'proximo '], '', $text);
        $cleaned = trim($cleaned);

        if (!isset(self::DAY_MAP[$cleaned])) {
            return null;
        }

        $target_en = self::DAY_MAP[$cleaned];
        $next = $this->now->modify("next {$target_en}");

        return ['date' => $next, 'source_type' => 'interpreted', 'error' => null];
    }

    /**
     * Handles: "15 de abril", "15 abril", "15/04", "15-04"
     *
     * @param string $text
     * @return array|null
     */
    private function resolve_explicit_date($text) {
        $day   = null;
        $month = null;

        // "15 de abril" or "15 abril"
        if (preg_match('/^(\d{1,2})\s+(?:de\s+)?(\w+)$/u', $text, $m)) {
            $day = (int) $m[1];
            $month_name = mb_strtolower($m[2]);
            $month = self::MONTH_MAP[$month_name] ?? null;
        }

        // "15/04" or "15-04"
        if ($day === null && preg_match('/^(\d{1,2})[\/\-](\d{1,2})$/', $text, $m)) {
            $day   = (int) $m[1];
            $month = (int) $m[2];
        }

        if ($day === null || $month === null || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        $year = (int) $this->now->format('Y');

        if (!checkdate($month, $day, $year)) {
            return ['date' => null, 'source_type' => 'captured', 'error' => 'invalid'];
        }

        try {
            $candidate = $this->now->setDate($year, $month, $day)->setTime(0, 0, 0);
        } catch (\Exception $e) {
            return ['date' => null, 'source_type' => 'captured', 'error' => 'invalid'];
        }

        return ['date' => $candidate, 'source_type' => 'captured', 'error' => null];
    }

    // ─── Time resolution ────────────────────────────────────────

    /**
     * @param string|null $text
     * @return array{hour: int|null, minute: int|null, source_type: string, error: string|null}
     */
    private function resolve_time($text) {
        if ($text === null || $text === '') {
            return ['hour' => null, 'minute' => null, 'source_type' => null, 'error' => 'missing'];
        }

        $cleaned = $text;
        $cleaned = preg_replace('/^a\s+las?\s+/u', '', $cleaned);
        $cleaned = trim($cleaned);

        $has_pm = (bool) preg_match('/p\.?\s*m\.?$/iu', $cleaned);
        $has_am = (bool) preg_match('/a\.?\s*m\.?$/iu', $cleaned);
        $has_meridiem = $has_am || $has_pm;

        $cleaned = preg_replace('/\s*[ap]\.?\s*m\.?\s*$/iu', '', $cleaned);
        $cleaned = trim($cleaned);

        $hour   = null;
        $minute = null;

        // "17:30" or "5:30"
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $cleaned, $m)) {
            $hour   = (int) $m[1];
            $minute = (int) $m[2];
        }
        // bare number "5", "17"
        elseif (preg_match('/^(\d{1,2})$/', $cleaned, $m)) {
            $hour   = (int) $m[1];
            $minute = 0;
        }

        if ($hour === null) {
            return ['hour' => null, 'minute' => null, 'source_type' => 'captured', 'error' => 'unrecognized'];
        }

        $source_type = 'captured';

        if ($has_meridiem) {
            if ($has_pm && $hour < 12) {
                $hour += 12;
            }
            if ($has_am && $hour === 12) {
                $hour = 0;
            }
            $source_type = 'captured';
        } elseif ($hour >= 13 && $hour <= 23) {
            $source_type = 'captured';
        } else {
            // Heurística: 1-7 → PM, 8-12 → AM
            if ($hour >= 1 && $hour <= 7) {
                $hour += 12;
                $source_type = 'inferred';
            } elseif ($hour >= 8 && $hour <= 12) {
                $source_type = 'inferred';
            } else {
                return ['hour' => null, 'minute' => null, 'source_type' => 'captured', 'error' => 'unrecognized'];
            }
        }

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return ['hour' => null, 'minute' => null, 'source_type' => 'captured', 'error' => 'unrecognized'];
        }

        return ['hour' => $hour, 'minute' => $minute, 'source_type' => $source_type, 'error' => null];
    }

    // ─── Output builder ─────────────────────────────────────────

    /**
     * @param string|null $raw_date
     * @param string|null $raw_time
     * @param array       $date_result
     * @param array       $time_result
     * @return array
     */
    private function build_output($raw_date, $raw_time, array $date_result, array $time_result) {
        $errors = [];

        if (!empty($date_result['error'])) {
            $errors[] = 'date:' . $date_result['error'];
        }
        if (!empty($time_result['error'])) {
            $errors[] = 'time:' . $time_result['error'];
        }

        $status = $this->compute_status($date_result, $time_result);

        $normalized = null;
        $is_past    = false;

        $has_date = $date_result['date'] !== null;
        $has_time = $time_result['hour'] !== null;

        if ($has_date && $has_time) {
            /** @var \DateTimeImmutable $resolved_dt */
            $resolved_dt = $date_result['date']->setTime($time_result['hour'], $time_result['minute']);

            if ($resolved_dt < $this->now) {
                $is_past = true;
                $status  = 'invalid_or_past';
            }

            $normalized = [
                'local_datetime' => $resolved_dt->format('Y-m-d H:i:s'),
                'local_date'     => $resolved_dt->format('Y-m-d'),
                'local_time'     => $resolved_dt->format('H:i:s'),
                'timezone'       => $this->tz_name,
            ];
        } elseif ($has_date && !$has_time) {
            $normalized = [
                'local_datetime' => null,
                'local_date'     => $date_result['date']->format('Y-m-d'),
                'local_time'     => null,
                'timezone'       => $this->tz_name,
            ];
        } elseif (!$has_date && $has_time) {
            $normalized = [
                'local_datetime' => null,
                'local_date'     => null,
                'local_time'     => sprintf('%02d:%02d:00', $time_result['hour'], $time_result['minute']),
                'timezone'       => $this->tz_name,
            ];
        }

        return [
            'status'                => $status,
            'requires_confirmation' => true,
            'source'                => [
                'date_text' => $raw_date,
                'time_text' => $raw_time,
            ],
            'normalized'            => $normalized,
            'meta'                  => [
                'date_source_type' => $date_result['source_type'],
                'time_source_type' => $time_result['source_type'],
                'is_past'          => $is_past,
            ],
            'errors'                => $errors,
        ];
    }

    /**
     * @param array $date_result
     * @param array $time_result
     * @return string
     */
    private function compute_status(array $date_result, array $time_result) {
        $date_ok = $date_result['date'] !== null;
        $time_ok = $time_result['hour'] !== null;

        $date_missing = ($date_result['error'] === 'missing');
        $time_missing = ($time_result['error'] === 'missing');

        if ($date_missing && $time_missing) {
            return 'partial';
        }

        if ($date_missing) {
            return 'missing_date';
        }

        if ($time_missing) {
            return 'missing_time';
        }

        $date_bad = (!$date_ok && !$date_missing);
        $time_bad = (!$time_ok && !$time_missing);

        if ($date_bad && $date_result['error'] === 'invalid') {
            return 'invalid_date';
        }

        if ($date_bad || $time_bad) {
            return 'unrecognized';
        }

        if ($date_ok && !$time_ok) {
            return 'partial';
        }

        return 'resolved';
    }
}
