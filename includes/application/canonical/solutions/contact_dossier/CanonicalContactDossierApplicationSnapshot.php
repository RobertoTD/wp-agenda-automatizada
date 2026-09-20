<?php
/**
 * Snapshot de aplicación de contact_dossier para una lista.
 *
 * `active` es estado persistido; `available` expresa requisitos actuales.
 * Nunca se deriva uno del otro.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Solutions\ContactDossier
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalContactDossierApplicationSnapshot {

    /** @var string */ private $solution_key;
    /** @var string */ private $family_key;
    /** @var int */ private $container_id;
    /** @var bool */ private $applicable;
    /** @var bool */ private $ready;
    /** @var bool */ private $available;
    /** @var bool */ private $active;
    /** @var list<string> */ private $blockers;

    /** @param list<string> $blockers */
    public function __construct(
        string $solution_key,
        string $family_key,
        int $container_id,
        bool $applicable,
        bool $ready,
        bool $available,
        bool $active,
        array $blockers
    ) {
        $this->solution_key = $solution_key;
        $this->family_key = $family_key;
        $this->container_id = $container_id;
        $this->applicable = $applicable;
        $this->ready = $ready;
        $this->available = $available;
        $this->active = $active;
        $this->blockers = array_values($blockers);
    }

    public function solution_key(): string { return $this->solution_key; }
    public function family_key(): string { return $this->family_key; }
    public function container_id(): int { return $this->container_id; }
    public function is_applicable(): bool { return $this->applicable; }
    public function is_ready(): bool { return $this->ready; }
    public function is_available(): bool { return $this->available; }
    public function is_active(): bool { return $this->active; }
    /** @return list<string> */
    public function blockers(): array { return $this->blockers; }

    /** @return array<string,mixed> */
    public function to_array(): array {
        return [
            'solution_key' => $this->solution_key,
            'family_key' => $this->family_key,
            'container_id' => $this->container_id,
            'applicable' => $this->applicable,
            'ready' => $this->ready,
            'available' => $this->available,
            'active' => $this->active,
            'blockers' => $this->blockers,
        ];
    }
}
