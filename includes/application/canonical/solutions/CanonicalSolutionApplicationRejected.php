<?php
defined('ABSPATH') or die('No direct access');

final class CanonicalSolutionApplicationRejected extends \RuntimeException {

    /** @var string */
    private $reason_code;

    public function __construct(string $reason_code, string $message) {
        parent::__construct($message);
        $this->reason_code = $reason_code;
    }

    public function reason_code(): string { return $this->reason_code; }
}
