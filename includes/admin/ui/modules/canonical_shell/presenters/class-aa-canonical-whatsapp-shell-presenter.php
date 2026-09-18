<?php
/**
 * Presentación whatsapp en tarjeta/modal del shell.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Whatsapp_Shell_Presenter {

    /**
     * @param array<string,mixed>|null $cap_map
     * @return array{kind:string,value?:string,display?:string,href?:string}|null
     */
    public static function card_view(?array $cap_map): ?array {
        if (!is_array($cap_map) || !isset($cap_map['whatsapp']) || !is_array($cap_map['whatsapp'])) {
            return null;
        }
        $state = $cap_map['whatsapp'];
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
            $href = self::wa_me_url($value);
            if ($display === null || $href === null) {
                return ['kind' => 'error'];
            }

            return [
                'kind' => 'value',
                'value' => $value,
                'display' => $display,
                'href' => $href,
            ];
        }

        return null;
    }

    /**
     * @param array<string,mixed>|null $cap_map
     * @return array{status:string,value?:string}|null
     */
    public static function edit_payload_fragment(?array $cap_map): ?array {
        if (!is_array($cap_map) || !isset($cap_map['whatsapp']) || !is_array($cap_map['whatsapp'])) {
            return null;
        }
        $state = $cap_map['whatsapp'];
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

    /**
     * URL wa.me a partir de E.164 con +: +525555555555 → https://wa.me/525555555555
     */
    public static function wa_me_url(string $e164): ?string {
        if ($e164 === '' || $e164[0] !== '+') {
            return null;
        }
        $digits = substr($e164, 1);
        if ($digits === '' || !ctype_digit($digits)) {
            return null;
        }

        return 'https://wa.me/' . $digits;
    }
}
