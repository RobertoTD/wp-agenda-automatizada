<?php
/**
 * Registry de contributors de página de registros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityRecordPageContributorRegistry {

    /** @var array<string, CanonicalCapabilityRecordPageContributor> */
    private $contributors = [];

    /** @var bool */
    private $frozen = false;

    public function register(CanonicalCapabilityRecordPageContributor $contributor): self {
        if ($this->frozen) {
            throw new \LogicException('Capability page contributor registry is frozen.');
        }
        $key = $contributor->capability_key();
        if (isset($this->contributors[$key])) {
            throw new \LogicException('Duplicate capability page contributor: ' . $key);
        }
        $this->contributors[$key] = $contributor;
        return $this;
    }

    public function freeze(): self {
        $this->frozen = true;
        return $this;
    }

    /**
     * @return list<CanonicalCapabilityRecordPageContributor>
     */
    public function all(): array {
        return array_values($this->contributors);
    }
}
