<?php
/**
 * Resultado del avance HMAC de un mandato de purge (accept/seal/status).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalPurgeRemoteAdvanceResult {

    public const STATE_SEALED = 'sealed';
    public const STATE_INCOMPLETE = 'incomplete';
    public const STATE_INTERVENTION = 'intervention';
    public const STATE_PERSISTENCE_FAILED = 'persistence_failed';

    /** @var string */
    private $state;

    /** @var string|null */
    private $conflict_code;

    private function __construct(string $state, ?string $conflict_code = null) {
        $this->state = $state;
        $this->conflict_code = $conflict_code;
    }

    public static function sealed(): self {
        return new self(self::STATE_SEALED);
    }

    public static function incomplete(): self {
        return new self(self::STATE_INCOMPLETE);
    }

    public static function intervention(string $conflict_code): self {
        return new self(self::STATE_INTERVENTION, $conflict_code);
    }

    public static function persistence_failed(): self {
        return new self(self::STATE_PERSISTENCE_FAILED);
    }

    public function state(): string {
        return $this->state;
    }

    public function conflict_code(): ?string {
        return $this->conflict_code;
    }

    public function is_sealed(): bool {
        return $this->state === self::STATE_SEALED;
    }
}
