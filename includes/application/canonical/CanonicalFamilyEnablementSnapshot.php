<?php
/**
 * Canonical Family Enablement Snapshot — mapa tipado request-local.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalFamilyEnablementSnapshot {

    /** @var array<string, CanonicalFamilyEnablementStatus> */
    private $by_key;

    /**
     * @param array<string, CanonicalFamilyEnablementStatus> $by_key
     */
    public function __construct(array $by_key) {
        $this->by_key = $by_key;
    }

    /**
     * @param list<string> $family_keys
     */
    public static function all_unprovisioned(array $family_keys): self {
        $by_key = [];
        foreach ($family_keys as $key) {
            $by_key[$key] = new CanonicalFamilyEnablementStatus($key, false, false);
        }

        return new self($by_key);
    }

    /**
     * @return list<string>
     */
    public function family_keys(): array {
        return array_keys($this->by_key);
    }

    public function has(string $family_key): bool {
        return isset($this->by_key[$family_key]);
    }

    public function status_for(string $family_key): CanonicalFamilyEnablementStatus {
        if (!isset($this->by_key[$family_key])) {
            throw new \OutOfBoundsException(
                '[enablement_snapshot_miss] Family key not in snapshot: ' . $family_key
            );
        }

        return $this->by_key[$family_key];
    }

    public function is_provisioned(string $family_key): bool {
        return $this->status_for($family_key)->is_provisioned();
    }

    public function is_enabled(string $family_key): bool {
        return $this->status_for($family_key)->is_enabled();
    }

    /**
     * @return list<string>
     */
    public function enabled_family_keys(): array {
        $enabled = [];
        foreach ($this->by_key as $key => $status) {
            if ($status->is_enabled()) {
                $enabled[] = $key;
            }
        }

        return $enabled;
    }
}
