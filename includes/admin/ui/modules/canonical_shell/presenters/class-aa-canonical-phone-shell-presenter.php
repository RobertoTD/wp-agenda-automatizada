<?php
/**
 * Presentación phone en tarjeta/modal del shell.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Phone_Shell_Presenter {

    /**
     * @param array<string,mixed>|null $cap_map
     * @return array{kind:string,value?:string,display?:string}|null
     */
    public static function card_view(?array $cap_map): ?array {
        if (!is_array($cap_map) || !isset($cap_map['phone']) || !is_array($cap_map['phone'])) {
            return null;
        }
        $state = $cap_map['phone'];
        $status = isset($state['status']) ? (string) $state['status'] : '';

        if ($status === CanonicalCapabilityRecordReadState::STATUS_KNOWN_ABSENT) {
            return null;
        }
        if ($status === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED) {
            return ['kind' => 'error'];
        }
        if ($status === CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE) {
            $value = isset($state['value']) ? (string) $state['value'] : '';
            $display = AA_Canonical_Phone_Normalizer::format_display($value);
            if ($display === null) {
                return ['kind' => 'error'];
            }

            return [
                'kind' => 'value',
                'value' => $value,
                'display' => $display,
            ];
        }

        return null;
    }

    /**
     * @param array<string,mixed>|null $cap_map
     * @return array{status:string,value?:string}|null
     */
    public static function edit_payload_fragment(?array $cap_map): ?array {
        if (!is_array($cap_map) || !isset($cap_map['phone']) || !is_array($cap_map['phone'])) {
            return null;
        }
        $state = $cap_map['phone'];
        $status = isset($state['status']) ? (string) $state['status'] : '';
        if ($status === '') {
            return null;
        }

        if ($status === CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE) {
            $value = isset($state['value']) ? (string) $state['value'] : '';
            if (AA_Canonical_Phone_Normalizer::parse_stored($value) === null) {
                return ['status' => CanonicalCapabilityRecordReadState::STATUS_READ_FAILED];
            }
            return [
                'status' => $status,
                'value' => $value,
            ];
        }

        return ['status' => $status];
    }
}
