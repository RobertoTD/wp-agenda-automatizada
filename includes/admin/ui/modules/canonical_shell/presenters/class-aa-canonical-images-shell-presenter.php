<?php
/**
 * Presentación images en tarjeta del shell.
 *
 * Superficies:
 * - summary (cabecera compacta): última imagen, SSR tras recarga.
 * - galería (panel/card): principal display, tira gallery, visor display.
 *
 * Infra compartida de imágenes (nombres históricos; conservar si se retira
 * la UI/tablas/AJAX de Expedientes): ExpedienteAdjuntoVariants,
 * ExpedienteAdjuntoJpegValidator, AA_Expediente_Adjunto_Variant_Generator,
 * AA_Expediente_Aggregate_Lock, AA_Expediente_Attachments_Backend_Client,
 * AA_Expediente_Attachment_Signed_Uploader,
 * AA_Expediente_Attachment_Read_Url_Validator.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Images_Shell_Presenter {

    public const SUMMARY_VARIANT = 'summary';
    public const GALLERY_VARIANT = 'gallery';
    public const DISPLAY_VARIANT = 'display';

    /**
     * Vista de card: null = no ofrecer; gallery = ids ordenados (id DESC);
     * error = fallo discreto de lectura (sin paths).
     *
     * @param array<string,mixed>|null $cap_map
     * @return array{kind:string,image_id?:int,image_ids?:list<int>}|null
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

        $ids = self::image_ids_from_collection($state);
        if ($ids === []) {
            return null;
        }

        return [
            'kind' => 'gallery',
            'image_id' => $ids[0],
            'image_ids' => $ids,
        ];
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
     * Ids del collection en orden persistido (id DESC = índice 0 = última).
     *
     * @param array<string,mixed> $state
     * @return list<int>
     */
    public static function image_ids_from_collection(array $state): array {
        $items = isset($state['items']) && is_array($state['items']) ? $state['items'] : [];
        $ids = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($id >= 1) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Primera entrada del collection = última persistida (ORDER BY id DESC).
     *
     * @param array<string,mixed> $state
     */
    public static function latest_image_id_from_collection(array $state): ?int {
        $ids = self::image_ids_from_collection($state);
        return $ids !== [] ? $ids[0] : null;
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
