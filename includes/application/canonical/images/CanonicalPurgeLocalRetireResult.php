<?php
/**
 * Resultado de la TX local de retiro canónico (IMG-5 inc. 3).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalPurgeLocalRetireResult {

    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_FAILED = 'failed';

    /** @var string */
    private $state;

    /** @var string|null */
    private $code;

    private function __construct(string $state, ?string $code) {
        $this->state = $state;
        $this->code = $code;
    }

    public static function confirmed(): self {
        return new self(self::STATE_CONFIRMED, null);
    }

    public static function failed(string $code): self {
        return new self(self::STATE_FAILED, $code);
    }

    public function state(): string {
        return $this->state;
    }

    public function code(): ?string {
        return $this->code;
    }

    public function is_confirmed(): bool {
        return $this->state === self::STATE_CONFIRMED;
    }
}
