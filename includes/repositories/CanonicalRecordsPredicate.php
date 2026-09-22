<?php
defined('ABSPATH') or die('No direct access');

/** Trusted repository contribution; values always supplied separately for prepare(). */
final class CanonicalRecordsPredicate {
    private $sql;
    private $parameters;
    public function __construct(string $sql, array $parameters = []) {
        if (trim($sql) === '') { throw new InvalidArgumentException('Empty predicate.'); }
        foreach ($parameters as $value) {
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Invalid predicate parameter.');
            }
        }
        $this->sql = $sql;
        $this->parameters = array_values($parameters);
    }
    public function sql(): string { return $this->sql; }
    public function parameters(): array { return $this->parameters; }
}
