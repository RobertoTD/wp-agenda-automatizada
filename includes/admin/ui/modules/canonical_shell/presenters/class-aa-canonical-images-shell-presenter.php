<?php
/**
 * Presentación mínima images en tarjeta del shell (Paso 2).
 *
 * Solo última imagen confirmada (orden persistido id DESC = primera del collection).
 * Variant de lectura: summary. Sin galería, contador ni visor.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Images_Shell_Presenter {

    public const SUMMARY_VARIANT = 'summary';

    /**
     * Vista de card: null = no ofrecer nodo; thumb = pendiente de URL firmada;
     * error = fallo discreto de lectura (sin paths).
     *
     * @param array<string,mixed>|null $cap_map
     * @return array{kind:string,image_id?:int}|null
     */
    public static function card_view(?array $cap_map): ?array {
        if (!is_array($cap_map) || !isset($cap_map['images']) || !is_array($cap_map['images'])) {
            return null;
        }
        $state = $cap_map['images'];
        $status = isset($state['status']) ? (string) $state['status'] : '';

        if ($status === CanonicalCapabilityRecordReadState::STATUS_KNOWN_ABSENT) {
            return null;
        }
        if ($status === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED) {
            return ['kind' => 'error'];
        }
        if ($status !== CanonicalCapabilityRecordReadState::STATUS_KNOWN_COLLECTION) {
            return null;
        }

        $latest_id = self::latest_image_id_from_collection($state);
        if ($latest_id === null) {
            return null;
        }

        return ['kind' => 'thumb', 'image_id' => $latest_id];
    }

    /**
     * Fragmento para data-aa-record: señal de offered para el picker (sin collection).
     *
     * @param array<string,mixed>|null $cap_map
     * @return array{status:string}|null
     */
    public static function edit_payload_fragment(?array $cap_map): ?array {
        if (!is_array($cap_map) || !isset($cap_map['images']) || !is_array($cap_map['images'])) {
            return null;
        }
        $status = isset($cap_map['images']['status']) ? (string) $cap_map['images']['status'] : '';
        if ($status === '') {
            return null;
        }

        return ['status' => CanonicalCapabilityRecordReadState::STATUS_KNOWN_ABSENT];
    }

    /**
     * Primera entrada del collection = última persistida (ORDER BY id DESC).
     *
     * @param array<string,mixed> $state
     */
    public static function latest_image_id_from_collection(array $state): ?int {
        $items = isset($state['items']) && is_array($state['items']) ? $state['items'] : [];
        if ($items === []) {
            return null;
        }
        $first = $items[0];
        if (!is_array($first)) {
            return null;
        }
        $id = isset($first['id']) ? (int) $first['id'] : 0;
        return $id >= 1 ? $id : null;
    }

    /**
     * Firma discreta de lectura summary. Nunca expone paths ni errores internos.
     *
     * @param object|null $read_url_use_case GetCanonicalRecordImageReadUrlUseCase-compatible
     * @return string|null URL firmada o null si falla
     */
    public static function resolve_summary_url(
        $read_url_use_case,
        string $family_key,
        int $container_id,
        int $record_id,
        int $image_id
    ): ?string {
        if ($read_url_use_case === null || $family_key === '' || $container_id < 1 || $record_id < 1 || $image_id < 1) {
            return null;
        }
        if (!is_object($read_url_use_case) || !method_exists($read_url_use_case, 'execute')) {
            return null;
        }

        try {
            $result = $read_url_use_case->execute(
                $family_key,
                $container_id,
                $record_id,
                $image_id,
                self::SUMMARY_VARIANT
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($result) || empty($result['ok'])) {
            return null;
        }
        $url = isset($result['url']) ? (string) $result['url'] : '';
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return null;
        }

        return $url;
    }
}
