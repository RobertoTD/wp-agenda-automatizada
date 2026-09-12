<?php
/**
 * Solicitud de modificación de capacidades de una lista (scope + selection).
 *
 * Ausencia de instancia = omisión. Instancia presente (incluso con arrays vacíos)
 * = modificación explícita. No es el setup completo persistido.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalContainerCapabilitySelection {

    /** @var list<string> */
    private $scope;

    /** @var list<string> */
    private $selection;

    /**
     * @param list<string> $scope
     * @param list<string> $selection
     */
    private function __construct(array $scope, array $selection) {
        $this->scope = $scope;
        $this->selection = $selection;
    }

    /**
     * @param list<string> $scope
     * @param list<string> $selection
     */
    public static function present(array $scope, array $selection): self {
        return new self($scope, $selection);
    }

    /**
     * @return list<string>
     */
    public function scope(): array {
        return $this->scope;
    }

    /**
     * @return list<string>
     */
    public function selection(): array {
        return $this->selection;
    }
}
