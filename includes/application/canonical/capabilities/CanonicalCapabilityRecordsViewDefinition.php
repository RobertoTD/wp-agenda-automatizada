<?php
defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__) . '/CanonicalRecordCriterion.php';
require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-key.php';

/** Definición tipada de una vista perteneciente a una capability. */
final class CanonicalCapabilityRecordsViewDefinition {
    private $key;
    private $label;
    private $criterion;
    private $allows_record_creation;

    public function __construct(
        string $key,
        string $label,
        ?CanonicalRecordCriterion $criterion,
        bool $allows_record_creation
    ) {
        $this->key = AA_Canonical_Key::assert_valid($key, 'capability_records_view_key');
        $label = trim($label);
        if ($label === '' || strlen($label) > 100) {
            throw new InvalidArgumentException('Invalid capability records view label.');
        }
        $this->label = $label;
        $this->criterion = $criterion;
        $this->allows_record_creation = $allows_record_creation;
    }

    public function key(): string { return $this->key; }
    public function label(): string { return $this->label; }
    public function criterion(): ?CanonicalRecordCriterion { return $this->criterion; }
    public function allows_record_creation(): bool { return $this->allows_record_creation; }
}
