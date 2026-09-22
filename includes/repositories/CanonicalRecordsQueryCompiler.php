<?php
defined('ABSPATH') or die('No direct access');
require_once __DIR__ . '/CanonicalRecordCriterionCompiler.php';
require_once __DIR__ . '/../application/canonical/CanonicalRecordsQuerySpec.php';

final class CanonicalRecordsQueryCompiler {
    private $compilers = [];
    private $frozen = false;
    public function register(string $owner, CanonicalRecordCriterionCompiler $compiler): self {
        if ($this->frozen || isset($this->compilers[$owner]) || !AA_Canonical_Key::is_valid($owner)) {
            throw new LogicException('Duplicate, invalid or frozen criterion compiler.');
        }
        $this->compilers[$owner] = $compiler;
        return $this;
    }
    public function freeze(): self { $this->frozen = true; return $this; }
    public function compile(CanonicalRecordsQuerySpec $spec): CanonicalRecordsPredicate {
        $sql = ['r.container_id = %d'];
        $parameters = [$spec->container_id()];
        foreach ($spec->criteria() as $owner => $criterion) {
            if (!isset($this->compilers[$owner])) { throw new LogicException('Criterion compiler missing.'); }
            $predicate = $this->compilers[$owner]->compile($criterion);
            $sql[] = '(' . $predicate->sql() . ')';
            $parameters = array_merge($parameters, $predicate->parameters());
        }
        return new CanonicalRecordsPredicate(implode(' AND ', $sql), $parameters);
    }
}
