<?php
/**
 * Initial Seed Eligibility Policy — regla pura para decidir si una agenda
 * puede recibir el seed inicial de Cliente de Prueba.
 *
 * No consulta BD ni WordPress.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Initial_Seed_Eligibility_Policy {

    public const ELIGIBLE = 'eligible';

    public const INELIGIBLE = 'ineligible';

    /**
     * @param array<string,mixed> $facts
     */
    public function evaluate(array $facts): string {
        if (empty($facts['has_installation_initialized_at'])) {
            return self::INELIGIBLE;
        }

        if ($this->int_fact($facts, 'registered_client_count') > 0) {
            return self::INELIGIBLE;
        }

        if ($this->int_fact($facts, 'active_service_count') > 0) {
            return self::INELIGIBLE;
        }

        if ($this->int_fact($facts, 'active_staff_count') > 0) {
            return self::INELIGIBLE;
        }

        if ($this->int_fact($facts, 'active_area_count') > 0) {
            return self::INELIGIBLE;
        }

        if ($this->int_fact($facts, 'created_reservation_count') > 0) {
            return self::INELIGIBLE;
        }

        return self::ELIGIBLE;
    }

    /**
     * @param array<string,mixed> $facts
     */
    private function int_fact(array $facts, string $key): int {
        return isset($facts[$key]) ? max(0, (int) $facts[$key]) : 0;
    }
}
