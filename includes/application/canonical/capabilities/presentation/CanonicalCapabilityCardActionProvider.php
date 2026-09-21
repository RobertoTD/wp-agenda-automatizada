<?php
/** Provider de acciones de card para una capability. */
defined('ABSPATH') or die('No direct access');

interface CanonicalCapabilityCardActionProvider {
    public function capability_key(): string;

    /**
     * @param array<string, array<string,mixed>> $record_capabilities
     * @return list<CanonicalCapabilityCardAction>
     */
    public function actions_for_record(int $record_id, array $record_capabilities): array;
}
