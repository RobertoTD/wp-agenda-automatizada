<?php
/**
 * Canonical Shell Mutation Result — Resultado tipado de mutación del shell.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalShellManifest')) {
    require_once __DIR__ . '/CanonicalShellManifest.php';
}
if (!class_exists('CanonicalMutationReceipt')) {
    require_once __DIR__ . '/CanonicalMutationReceipt.php';
}

final class CanonicalShellMutationResult {

    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_UNCERTAIN = 'uncertain';
    public const STATE_WRITE_ADAPTER_PENDING = 'write_adapter_pending';
    public const STATE_CONTAINER_NOT_FOUND = 'container_not_found';
    public const STATE_RECORD_NOT_FOUND = 'record_not_found';
    public const STATE_PERSISTENCE_FAILED = 'persistence_failed';

    private const ALLOWED_STATES = [
        self::STATE_CONFIRMED,
        self::STATE_UNCERTAIN,
        self::STATE_WRITE_ADAPTER_PENDING,
        self::STATE_CONTAINER_NOT_FOUND,
        self::STATE_RECORD_NOT_FOUND,
        self::STATE_PERSISTENCE_FAILED,
    ];

    /** @var CanonicalShellManifest */
    private $manifest;

    /** @var string */
    private $state;

    /** @var CanonicalMutationReceipt|null */
    private $receipt;

    private function __construct(
        CanonicalShellManifest $manifest,
        string $state,
        ?CanonicalMutationReceipt $receipt
    ) {
        if (!in_array($state, self::ALLOWED_STATES, true)) {
            throw new \InvalidArgumentException('[invalid_mutation_state] Unsupported shell mutation state.');
        }

        $needs_receipt = $state === self::STATE_CONFIRMED || $state === self::STATE_UNCERTAIN;
        if ($needs_receipt && !$receipt instanceof CanonicalMutationReceipt) {
            throw new \InvalidArgumentException('[invalid_mutation_result] Receipt required for this state.');
        }
        if (!$needs_receipt && $receipt !== null) {
            throw new \InvalidArgumentException('[invalid_mutation_result] Receipt must be null for this state.');
        }

        if ($receipt instanceof CanonicalMutationReceipt) {
            if ($state === self::STATE_CONFIRMED && $receipt->outcome() !== CanonicalMutationReceipt::OUTCOME_CONFIRMED) {
                throw new \InvalidArgumentException('[invalid_mutation_result] Receipt outcome must be confirmed.');
            }
            if ($state === self::STATE_UNCERTAIN && $receipt->outcome() !== CanonicalMutationReceipt::OUTCOME_UNCERTAIN) {
                throw new \InvalidArgumentException('[invalid_mutation_result] Receipt outcome must be uncertain.');
            }
        }

        $this->manifest = $manifest;
        $this->state = $state;
        $this->receipt = $receipt;
    }

    public static function confirmed(
        CanonicalShellManifest $manifest,
        CanonicalMutationReceipt $receipt
    ): self {
        return new self($manifest, self::STATE_CONFIRMED, $receipt);
    }

    public static function uncertain(
        CanonicalShellManifest $manifest,
        CanonicalMutationReceipt $receipt
    ): self {
        return new self($manifest, self::STATE_UNCERTAIN, $receipt);
    }

    public static function write_adapter_pending(CanonicalShellManifest $manifest): self {
        return new self($manifest, self::STATE_WRITE_ADAPTER_PENDING, null);
    }

    public static function container_not_found(CanonicalShellManifest $manifest): self {
        return new self($manifest, self::STATE_CONTAINER_NOT_FOUND, null);
    }

    public static function record_not_found(CanonicalShellManifest $manifest): self {
        return new self($manifest, self::STATE_RECORD_NOT_FOUND, null);
    }

    public static function persistence_failed(CanonicalShellManifest $manifest): self {
        return new self($manifest, self::STATE_PERSISTENCE_FAILED, null);
    }

    public function manifest(): CanonicalShellManifest {
        return $this->manifest;
    }

    public function state(): string {
        return $this->state;
    }

    public function receipt(): ?CanonicalMutationReceipt {
        return $this->receipt;
    }
}
