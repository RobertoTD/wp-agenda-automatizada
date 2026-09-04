<?php
/**
 * Canonical Relational Ambiguous Outcome — Mutación posiblemente aplicada sin confirmación.
 *
 * Solo tras COMMIT === false o ROLLBACK === false cuando la mutación pudo aplicarse.
 * Transporta IDs obligatorios para armar CanonicalMutationReceipt::uncertain().
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalRelationalAmbiguousOutcome extends \RuntimeException {

    /** @var string */
    private $operation;

    /** @var string */
    private $resource_type;

    /** @var int */
    private $resource_id;

    /** @var int|null */
    private $container_id;

    /**
     * @param string   $operation     create|update|delete
     * @param string   $resource_type container|record
     * @param int      $resource_id   >= 1 (ya conocido)
     * @param int|null $container_id  null para container; >= 1 para record
     */
    public function __construct(
        string $operation,
        string $resource_type,
        int $resource_id,
        ?int $container_id,
        string $message = 'Canonical relational mutation outcome is ambiguous.'
    ) {
        if ($resource_id < 1) {
            throw new \InvalidArgumentException(
                '[canonical_relational_ambiguous] resource_id must be >= 1.'
            );
        }
        if ($resource_type === 'record' && ($container_id === null || $container_id < 1)) {
            throw new \InvalidArgumentException(
                '[canonical_relational_ambiguous] record requires container_id >= 1.'
            );
        }
        if ($resource_type === 'container' && $container_id !== null) {
            throw new \InvalidArgumentException(
                '[canonical_relational_ambiguous] container must have container_id null.'
            );
        }

        $this->operation = $operation;
        $this->resource_type = $resource_type;
        $this->resource_id = $resource_id;
        $this->container_id = $container_id;

        parent::__construct('[canonical_relational_ambiguous] ' . $message);
    }

    public function operation(): string {
        return $this->operation;
    }

    public function resource_type(): string {
        return $this->resource_type;
    }

    public function resource_id(): int {
        return $this->resource_id;
    }

    public function container_id(): ?int {
        return $this->container_id;
    }
}
