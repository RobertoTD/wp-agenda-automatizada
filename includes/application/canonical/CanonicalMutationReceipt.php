<?php
/**
 * Canonical Mutation Receipt — Recibo tipado de mutación canónica.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalReadIdentity')) {
    require_once __DIR__ . '/CanonicalReadIdentity.php';
}

final class CanonicalMutationReceipt {

    public const OUTCOME_CONFIRMED = 'confirmed';
    public const OUTCOME_UNCERTAIN = 'uncertain';

    public const OPERATION_CREATE = 'create';
    public const OPERATION_UPDATE = 'update';
    public const OPERATION_DELETE = 'delete';

    public const RESOURCE_CONTAINER = 'container';
    public const RESOURCE_RECORD = 'record';

    private const ALLOWED_OUTCOMES = [
        self::OUTCOME_CONFIRMED,
        self::OUTCOME_UNCERTAIN,
    ];

    private const ALLOWED_OPERATIONS = [
        self::OPERATION_CREATE,
        self::OPERATION_UPDATE,
        self::OPERATION_DELETE,
    ];

    private const ALLOWED_RESOURCE_TYPES = [
        self::RESOURCE_CONTAINER,
        self::RESOURCE_RECORD,
    ];

    /** @var CanonicalReadIdentity */
    private $identity;

    /** @var string */
    private $outcome;

    /** @var string */
    private $operation;

    /** @var string */
    private $resource_type;

    /** @var int|null */
    private $resource_id;

    /** @var int|null */
    private $container_id;

    private function __construct(
        CanonicalReadIdentity $identity,
        string $outcome,
        string $operation,
        string $resource_type,
        ?int $resource_id,
        ?int $container_id
    ) {
        if (!in_array($outcome, self::ALLOWED_OUTCOMES, true)) {
            throw new \InvalidArgumentException('[invalid_mutation_receipt] Invalid outcome.');
        }
        if (!in_array($operation, self::ALLOWED_OPERATIONS, true)) {
            throw new \InvalidArgumentException('[invalid_mutation_receipt] Invalid operation.');
        }
        if (!in_array($resource_type, self::ALLOWED_RESOURCE_TYPES, true)) {
            throw new \InvalidArgumentException('[invalid_mutation_receipt] Invalid resource_type.');
        }

        self::assert_id_null_or_positive($resource_id, 'resource_id');
        self::assert_id_null_or_positive($container_id, 'container_id');

        if ($resource_type === self::RESOURCE_CONTAINER) {
            if ($container_id !== null) {
                throw new \InvalidArgumentException('[invalid_mutation_receipt] container resource must have container_id null.');
            }
            if ($outcome === self::OUTCOME_CONFIRMED && ($resource_id === null || $resource_id < 1)) {
                throw new \InvalidArgumentException('[invalid_mutation_receipt] confirmed container requires resource_id >= 1.');
            }
            if ($outcome === self::OUTCOME_UNCERTAIN && $operation === self::OPERATION_CREATE) {
                if ($resource_id !== null && $resource_id < 1) {
                    throw new \InvalidArgumentException('[invalid_mutation_receipt] uncertain container create resource_id invalid.');
                }
            } elseif ($outcome === self::OUTCOME_UNCERTAIN) {
                if ($resource_id === null || $resource_id < 1) {
                    throw new \InvalidArgumentException('[invalid_mutation_receipt] uncertain container update/delete requires resource_id >= 1.');
                }
            }
        }

        if ($resource_type === self::RESOURCE_RECORD) {
            if ($container_id === null || $container_id < 1) {
                throw new \InvalidArgumentException('[invalid_mutation_receipt] record resource requires container_id >= 1.');
            }
            if ($outcome === self::OUTCOME_CONFIRMED && ($resource_id === null || $resource_id < 1)) {
                throw new \InvalidArgumentException('[invalid_mutation_receipt] confirmed record requires resource_id >= 1.');
            }
            if ($outcome === self::OUTCOME_UNCERTAIN && $operation === self::OPERATION_CREATE) {
                if ($resource_id !== null && $resource_id < 1) {
                    throw new \InvalidArgumentException('[invalid_mutation_receipt] uncertain record create resource_id invalid.');
                }
            } elseif ($outcome === self::OUTCOME_UNCERTAIN) {
                if ($resource_id === null || $resource_id < 1) {
                    throw new \InvalidArgumentException('[invalid_mutation_receipt] uncertain record update/delete requires resource_id >= 1.');
                }
            }
        }

        $this->identity = $identity;
        $this->outcome = $outcome;
        $this->operation = $operation;
        $this->resource_type = $resource_type;
        $this->resource_id = $resource_id;
        $this->container_id = $container_id;
    }

    public static function confirmed(
        CanonicalReadIdentity $identity,
        string $operation,
        string $resource_type,
        int $resource_id,
        ?int $container_id
    ): self {
        return new self(
            $identity,
            self::OUTCOME_CONFIRMED,
            $operation,
            $resource_type,
            $resource_id,
            $container_id
        );
    }

    public static function uncertain(
        CanonicalReadIdentity $identity,
        string $operation,
        string $resource_type,
        ?int $resource_id,
        ?int $container_id
    ): self {
        return new self(
            $identity,
            self::OUTCOME_UNCERTAIN,
            $operation,
            $resource_type,
            $resource_id,
            $container_id
        );
    }

    public function identity(): CanonicalReadIdentity {
        return $this->identity;
    }

    public function outcome(): string {
        return $this->outcome;
    }

    public function operation(): string {
        return $this->operation;
    }

    public function resource_type(): string {
        return $this->resource_type;
    }

    public function resource_id(): ?int {
        return $this->resource_id;
    }

    public function container_id(): ?int {
        return $this->container_id;
    }

    public function family_key(): string {
        return $this->identity->family_key();
    }

    public function variant_key(): string {
        return $this->identity->variant_key();
    }

    /**
     * @param int|null $value
     */
    private static function assert_id_null_or_positive($value, string $label): void {
        if ($value === null) {
            return;
        }
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException('[invalid_mutation_receipt] ' . $label . ' must be null or >= 1.');
        }
    }
}
