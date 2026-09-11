<?php
/**
 * Registry de handlers de escritura de capacidades.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityWriteHandlerRegistry {

    /** @var array<string, CanonicalCapabilityWriteHandler> */
    private $handlers = [];

    /** @var bool */
    private $frozen = false;

    public function register(CanonicalCapabilityWriteHandler $handler): self {
        if ($this->frozen) {
            throw new \LogicException('Capability write handler registry is frozen.');
        }

        $key = $handler->capability_key();
        if (isset($this->handlers[$key])) {
            throw new \LogicException('Duplicate capability write handler: ' . $key);
        }

        $this->handlers[$key] = $handler;
        return $this;
    }

    public function freeze(): self {
        $this->frozen = true;
        return $this;
    }

    /**
     * @throws CanonicalCapabilityUnknown
     */
    public function require(string $capability_key): CanonicalCapabilityWriteHandler {
        if (!isset($this->handlers[$capability_key])) {
            throw new CanonicalCapabilityUnknown($capability_key);
        }

        return $this->handlers[$capability_key];
    }
}
