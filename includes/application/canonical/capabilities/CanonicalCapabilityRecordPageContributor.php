<?php
/**
 * Contributor de página de registros del shell (por capability_key).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalCapabilityRecordPageContributor {

    public function capability_key(): string;

    /**
     * @param list<int> $record_ids
     */
    public function contribute_for_records_page(
        string $family_key,
        int $container_id,
        array $record_ids
    ): CanonicalCapabilityRecordsPageContribution;
}
