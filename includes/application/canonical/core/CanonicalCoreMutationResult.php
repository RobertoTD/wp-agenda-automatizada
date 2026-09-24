<?php
defined('ABSPATH') or die('No direct access');

final class CanonicalCoreMutationResult {
    public const CONFIRMED = 'confirmed';
    public const NOT_FOUND = 'not_found';
    public const PERSISTENCE_FAILED = 'persistence_failed';
    public const UNCERTAIN = 'uncertain';

    /** @var string */
    private $state;
    /** @var array|null */
    private $resource;

    private function __construct(string $state, ?array $resource = null) {
        $this->state = $state;
        $this->resource = $resource;
    }
    public static function confirmed(array $resource): self { return new self(self::CONFIRMED, $resource); }
    public static function not_found(): self { return new self(self::NOT_FOUND); }
    public static function persistence_failed(): self { return new self(self::PERSISTENCE_FAILED); }
    public static function uncertain(): self { return new self(self::UNCERTAIN); }
    public function state(): string { return $this->state; }
    public function resource(): ?array { return $this->resource; }
}
