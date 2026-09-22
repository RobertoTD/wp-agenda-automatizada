<?php
defined('ABSPATH') or die('No direct access');
require_once dirname(__DIR__, 2) . '/CanonicalRecordCriterion.php';

final class CanonicalCompletedCriterion implements CanonicalRecordCriterion {
    private $completed;
    public function __construct(bool $completed) { $this->completed = $completed; }
    public function completed(): bool { return $this->completed; }
}
