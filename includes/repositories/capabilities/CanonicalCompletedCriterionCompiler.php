<?php
defined('ABSPATH') or die('No direct access');
require_once dirname(__DIR__) . '/CanonicalRecordCriterionCompiler.php';
require_once dirname(__DIR__, 2) . '/application/canonical/capabilities/completed/CanonicalCompletedCriterion.php';

final class CanonicalCompletedCriterionCompiler implements CanonicalRecordCriterionCompiler {
    public function compile(CanonicalRecordCriterion $criterion): CanonicalRecordsPredicate {
        if (!$criterion instanceof CanonicalCompletedCriterion) { throw new LogicException('Wrong completion criterion type.'); }
        $table = AA_Canonical_Schema::record_completion_table_name();
        return new CanonicalRecordsPredicate(
            ($criterion->completed() ? '' : 'NOT ') . "EXISTS (SELECT 1 FROM `{$table}` completion_state WHERE completion_state.record_id = r.id)"
        );
    }
}
