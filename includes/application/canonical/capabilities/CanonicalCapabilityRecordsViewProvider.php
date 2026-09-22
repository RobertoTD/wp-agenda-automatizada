<?php
defined('ABSPATH') or die('No direct access');
require_once __DIR__ . '/CanonicalCapabilityRecordsViewDefinition.php';

/** Owned read contributions. Null means inactive/not-ready, never a failed read. */
interface CanonicalCapabilityRecordsViewProvider {
    public function capability_key(): string;
    /** @return array{natural:?CanonicalRecordCriterion,views:array<string,CanonicalCapabilityRecordsViewDefinition>}|null */
    public function contributions(string $family_key, int $container_id): ?array;
    /** @return array<string,string> Legacy URL alias => own view key. */
    public function legacy_aliases(): array;
}
