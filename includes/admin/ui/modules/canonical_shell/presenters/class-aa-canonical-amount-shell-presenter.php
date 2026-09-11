<?php
/**
 * Presentación amount en tarjeta/modal del shell (A1b).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Amount_Shell_Presenter {

    /**
     * @param array<string,mixed>|null $cap_map mapa capabilities del item (clave → {status,value?})
     * @return array{kind:string,value?:string}|null null = no ofrecer nodo
     */
    public static function card_view(?array $cap_map): ?array {
        if (!is_array($cap_map) || !isset($cap_map['amount']) || !is_array($cap_map['amount'])) {
            return null;
        }
        $state = $cap_map['amount'];
        $status = isset($state['status']) ? (string) $state['status'] : '';

        if ($status === CanonicalCapabilityRecordReadState::STATUS_KNOWN_ABSENT) {
            return null;
        }
        if ($status === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED) {
            return ['kind' => 'error'];
        }
        if ($status === CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE) {
            $value = isset($state['value']) ? (string) $state['value'] : '';
            return ['kind' => 'value', 'value' => $value];
        }

        return null;
    }

    /**
     * Payload para data-aa-record (solo si amount está ofrecido en la lista).
     *
     * @param array<string,mixed>|null $cap_map
     * @return array{status:string,value?:string}|null
     */
    public static function edit_payload_fragment(?array $cap_map): ?array {
        if (!is_array($cap_map) || !isset($cap_map['amount']) || !is_array($cap_map['amount'])) {
            return null;
        }
        $state = $cap_map['amount'];
        $status = isset($state['status']) ? (string) $state['status'] : '';
        if ($status === '') {
            return null;
        }
        $out = ['status' => $status];
        if (
            $status === CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE
            && isset($state['value'])
        ) {
            $out['value'] = (string) $state['value'];
        }

        return $out;
    }
}
