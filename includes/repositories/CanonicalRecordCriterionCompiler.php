<?php
defined('ABSPATH') or die('No direct access');
require_once __DIR__ . '/../application/canonical/CanonicalRecordCriterion.php';
require_once __DIR__ . '/CanonicalRecordsPredicate.php';

interface CanonicalRecordCriterionCompiler {
    public function compile(CanonicalRecordCriterion $criterion): CanonicalRecordsPredicate;
}
