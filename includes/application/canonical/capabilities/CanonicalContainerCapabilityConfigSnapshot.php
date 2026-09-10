<?php
/**
 * Snapshot de configuración efectiva de capacidades de una lista.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalContainerCapabilityConfigSnapshot {

    /** @var string */
    private $family_key;

    /** @var int */
    private $container_id;

    /** @var array<string, array{assigned:bool,active:bool,scope:string}> */
    private $capabilities;

    /**
     * @param array<string, array{assigned:bool,active:bool,scope:string}> $capabilities
     */
    public function __construct(string $family_key, int $container_id, array $capabilities) {
        $this->family_key = $family_key;
        $this->container_id = $container_id;
        $this->capabilities = $capabilities;
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function container_id(): int {
        return $this->container_id;
    }

    /**
     * @return array<string, array{assigned:bool,active:bool,scope:string}>
     */
    public function capabilities(): array {
        return $this->capabilities;
    }

    public function is_assigned(string $capability_key): bool {
        return isset($this->capabilities[$capability_key])
            && !empty($this->capabilities[$capability_key]['assigned']);
    }

    public function is_active(string $capability_key): bool {
        return isset($this->capabilities[$capability_key])
            && !empty($this->capabilities[$capability_key]['active']);
    }

    /**
     * @return array{family_key:string,container_id:int,capabilities:array<string,array{assigned:bool,active:bool,scope:string}>}
     */
    public function to_array(): array {
        return [
            'family_key' => $this->family_key,
            'container_id' => $this->container_id,
            'capabilities' => $this->capabilities,
        ];
    }
}
