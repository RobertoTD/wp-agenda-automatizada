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
     * @return array{kind:string,value?:string,is_negative?:bool}|null null = no ofrecer nodo
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
            return [
                'kind' => 'value',
                'value' => $value,
                'is_negative' => str_starts_with($value, '-'),
            ];
        }

        return null;
    }

    /**
     * Proyección de total de lista desde capability_contributions.
     *
     * @param array<string,mixed>|null $capability_contributions
     * @return array{kind:string,value?:string,display?:string,is_negative?:bool}|null
     */
    public static function list_details_view(?array $capability_contributions): ?array {
        if (!is_array($capability_contributions) || !isset($capability_contributions['amount'])) {
            return null;
        }
        $amount = $capability_contributions['amount'];
        if (!is_array($amount) || empty($amount['offered'])) {
            return null;
        }
        if (!isset($amount['list_sum']) || !is_array($amount['list_sum'])) {
            return ['kind' => 'error'];
        }

        $status = isset($amount['list_sum']['status']) ? (string) $amount['list_sum']['status'] : '';
        if ($status === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED) {
            return ['kind' => 'error'];
        }
        if ($status !== CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE) {
            return ['kind' => 'error'];
        }

        $value = isset($amount['list_sum']['value']) ? (string) $amount['list_sum']['value'] : '';
        try {
            $display = AA_Canonical_Amount_List_Sum::format_display($value);
        } catch (\InvalidArgumentException $e) {
            return ['kind' => 'error'];
        }

        return [
            'kind' => 'value',
            'value' => $value,
            'display' => $display,
            'is_negative' => AA_Canonical_Amount_List_Sum::is_negative($value),
        ];
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
