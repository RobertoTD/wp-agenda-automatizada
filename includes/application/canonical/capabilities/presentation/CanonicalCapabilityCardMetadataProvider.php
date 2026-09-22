<?php
/** Provider de metadata temporal para el slot común de cards. */
defined('ABSPATH') or die('No direct access');

interface CanonicalCapabilityCardMetadataProvider {
    public function capability_key(): string;

    /**
     * @param array<string, array<string,mixed>> $record_capabilities
     * @return list<CanonicalCapabilityCardMetadata>
     */
    public function metadata_for_record(int $record_id, array $record_capabilities): array;
}
