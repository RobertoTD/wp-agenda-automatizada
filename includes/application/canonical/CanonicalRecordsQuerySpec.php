<?php
defined('ABSPATH') or die('No direct access');
require_once __DIR__ . '/CanonicalRecordCriterion.php';
require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-key.php';

/** One request, one container, one criterion per owner, composed with AND. */
final class CanonicalRecordsQuerySpec {
    private $container_id;
    private $criteria;

    public function __construct(int $container_id, array $criteria = []) {
        if ($container_id < 1) { throw new InvalidArgumentException('Invalid query container.'); }
        foreach ($criteria as $owner => $criterion) {
            if (!is_string($owner) || !AA_Canonical_Key::is_valid($owner) || !$criterion instanceof CanonicalRecordCriterion) {
                throw new InvalidArgumentException('Invalid owned criterion.');
            }
        }
        ksort($criteria);
        $this->container_id = $container_id;
        $this->criteria = $criteria;
    }

    public function container_id(): int { return $this->container_id; }
    /** @return array<string,CanonicalRecordCriterion> */
    public function criteria(): array { return $this->criteria; }
}
