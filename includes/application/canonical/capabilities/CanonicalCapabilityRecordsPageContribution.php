<?php
/**
 * Contribución de una capacidad a la página de registros del shell.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityRecordsPageContribution {

    /** @var string */
    private $capability_key;

    /** @var bool */
    private $offered;

    /** @var array<int, CanonicalCapabilityRecordReadState> */
    private $records;

    /**
     * @param array<int, CanonicalCapabilityRecordReadState> $records
     */
    public function __construct(string $capability_key, bool $offered, array $records = []) {
        $this->capability_key = $capability_key;
        $this->offered = $offered;
        $this->records = $records;
    }

    public function capability_key(): string {
        return $this->capability_key;
    }

    public function offered(): bool {
        return $this->offered;
    }

    /**
     * @return array<int, CanonicalCapabilityRecordReadState>
     */
    public function records(): array {
        return $this->records;
    }

    public function state_for(int $record_id): ?CanonicalCapabilityRecordReadState {
        return isset($this->records[$record_id]) ? $this->records[$record_id] : null;
    }

    /**
     * @return array{offered:bool}
     */
    public function list_summary(): array {
        return ['offered' => $this->offered];
    }
}
