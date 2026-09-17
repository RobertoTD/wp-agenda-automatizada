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

    /** @var CanonicalCapabilityRecordReadState|null proyección de lista (p. ej. suma amount) */
    private $list_sum;

    /**
     * @param array<int, CanonicalCapabilityRecordReadState> $records
     */
    public function __construct(
        string $capability_key,
        bool $offered,
        array $records = [],
        ?CanonicalCapabilityRecordReadState $list_sum = null
    ) {
        $this->capability_key = $capability_key;
        $this->offered = $offered;
        $this->records = $records;
        $this->list_sum = $list_sum;
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

    public function list_sum(): ?CanonicalCapabilityRecordReadState {
        return $this->list_sum;
    }

    /**
     * @return array{offered:bool,list_sum?:array{status:string,value?:string}}
     */
    public function list_summary(): array {
        $out = ['offered' => $this->offered];
        if ($this->offered && $this->list_sum !== null) {
            $out['list_sum'] = $this->list_sum->to_array();
        }

        return $out;
    }
}
