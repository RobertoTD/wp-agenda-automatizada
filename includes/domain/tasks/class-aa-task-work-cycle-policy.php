<?php
/**
 * Task Work Cycle Policy — cálculo de ignore_until por ciclo de trabajo (dominio puro).
 *
 * MVP: reinicio diario a las 12:00 en la hora local implícita de $now.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Task_Work_Cycle_Policy {

    public const DEFAULT_RESET_HOUR = 12;

    public const DEFAULT_RESET_MINUTE = 0;

    public const DEFAULT_RESET_SECOND = 0;

    public const DEFAULT_IGNORE_CYCLES = 1;

    /**
     * @param string $now    Y-m-d H:i:s en hora local del sistema.
     * @param int    $cycles Número de ciclos de trabajo (mínimo 1).
     */
    public function resolve_ignore_until(string $now, int $cycles = self::DEFAULT_IGNORE_CYCLES): string {
        $normalized_cycles = max(1, $cycles);
        $now_dt = $this->parse_datetime($now);
        $reset_today = $now_dt->setTime(
            self::DEFAULT_RESET_HOUR,
            self::DEFAULT_RESET_MINUTE,
            self::DEFAULT_RESET_SECOND
        );

        if ($now_dt < $reset_today) {
            $next_reset = $reset_today;
        } else {
            $next_reset = $reset_today->modify('+1 day');
        }

        if ($normalized_cycles > 1) {
            $next_reset = $next_reset->modify('+' . ($normalized_cycles - 1) . ' days');
        }

        return $next_reset->format('Y-m-d H:i:s');
    }

    private function parse_datetime(string $now): \DateTimeImmutable {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($now));

        if ($parsed instanceof \DateTimeImmutable) {
            return $parsed;
        }

        return new \DateTimeImmutable(trim($now));
    }
}
