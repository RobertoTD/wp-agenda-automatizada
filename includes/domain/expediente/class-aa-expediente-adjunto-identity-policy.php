<?php
/**
 * Expediente Adjunto Identity Policy — pertenencia dual client_v1 / expediente_v2.
 *
 * Valida conjuntamente padre + registro + metadata + path parseado.
 * Sin SQL, Node ni Storage. P2: autoridad expediente → registro → adjunto.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once __DIR__ . '/ExpedienteAdjuntoVariants.php';
}

final class AA_Expediente_Adjunto_Identity_Policy {

    public const CODE_INCONSISTENT = 'adjunto_inconsistent';

    /**
     * Valida identidad canónica (padre con expediente_id en el registro).
     *
     * Identidades válidas:
     * - general v2: client NULL en padre/registro/adjunto + path expediente_v2
     * - relacionado v1: client N en los tres + path client_v1
     * - relacionado v2: client N en los tres + path expediente_v2
     *
     * @param array{id:mixed,client_id:mixed} $parent
     * @param array{id:mixed,expediente_id:mixed,client_id:mixed} $record
     * @param array{
     *   id?:mixed,
     *   record_id:mixed,
     *   client_id:mixed,
     *   upload_operation_id:mixed,
     *   storage_path:mixed
     * } $attachment
     * @return array{ok:true,parsed:array<string,mixed>}|array{ok:false,code:string}
     */
    public static function validate(array $parent, array $record, array $attachment): array {
        $parent_id = self::positive_id($parent['id'] ?? null);
        $parent_client = self::normalize_client_id($parent['client_id'] ?? null);
        if ($parent_id === null || !$parent_client['ok']) {
            return self::fail();
        }

        $record_id = self::positive_id($record['id'] ?? null);
        $record_expediente = self::positive_id($record['expediente_id'] ?? null);
        $record_client = self::normalize_client_id($record['client_id'] ?? null);
        if ($record_id === null || $record_expediente === null || !$record_client['ok']) {
            return self::fail();
        }

        if ($record_expediente !== $parent_id) {
            return self::fail();
        }

        if ($record_client['id'] !== $parent_client['id']) {
            return self::fail();
        }

        $att_record = self::positive_id($attachment['record_id'] ?? null);
        $att_client = self::normalize_client_id($attachment['client_id'] ?? null);
        $operation_id = strtolower(trim((string) ($attachment['upload_operation_id'] ?? '')));
        $storage_path = (string) ($attachment['storage_path'] ?? '');

        if ($att_record === null || $att_record !== $record_id || !$att_client['ok']) {
            return self::fail();
        }

        if ($att_client['id'] !== $parent_client['id']) {
            return self::fail();
        }

        if ($operation_id === '' || $storage_path === '') {
            return self::fail();
        }

        $parsed = ExpedienteAdjuntoVariants::parse_original_path($storage_path);
        if ($parsed === null) {
            return self::fail();
        }

        if ((int) ($parsed['record_id'] ?? 0) !== $record_id) {
            return self::fail();
        }

        if (strtolower((string) ($parsed['operation_id'] ?? '')) !== $operation_id) {
            return self::fail();
        }

        $contract = (string) ($parsed['contract'] ?? '');

        if ($parent_client['id'] === null) {
            // General: solo expediente_v2 coherente, sin snapshot de cliente.
            if ($contract !== ExpedienteAdjuntoVariants::CONTRACT_EXPEDIENTE_V2) {
                return self::fail();
            }
            if ((int) ($parsed['expediente_id'] ?? 0) !== $parent_id) {
                return self::fail();
            }
            if (($parsed['client_id'] ?? null) !== null) {
                return self::fail();
            }

            return self::ok($parsed);
        }

        // Relacionado: v1 o v2.
        if ($contract === ExpedienteAdjuntoVariants::CONTRACT_CLIENT_V1) {
            if ((int) ($parsed['client_id'] ?? 0) !== $parent_client['id']) {
                return self::fail();
            }
            if (($parsed['expediente_id'] ?? null) !== null) {
                return self::fail();
            }

            return self::ok($parsed);
        }

        if ($contract === ExpedienteAdjuntoVariants::CONTRACT_EXPEDIENTE_V2) {
            if ((int) ($parsed['expediente_id'] ?? 0) !== $parent_id) {
                return self::fail();
            }
            if (($parsed['client_id'] ?? null) !== null) {
                return self::fail();
            }

            return self::ok($parsed);
        }

        return self::fail();
    }

    /**
     * Histórico legacy sin expediente_id: solo client_v1 anclado al cliente.
     *
     * @param int $client_id
     * @param array{id:mixed,client_id:mixed,expediente_id?:mixed} $record
     * @param array{
     *   record_id:mixed,
     *   client_id:mixed,
     *   upload_operation_id:mixed,
     *   storage_path:mixed
     * } $attachment
     * @return array{ok:true,parsed:array<string,mixed>}|array{ok:false,code:string}
     */
    public static function validate_legacy_orphan(int $client_id, array $record, array $attachment): array {
        if ($client_id < 1) {
            return self::fail();
        }

        $record_id = self::positive_id($record['id'] ?? null);
        $record_client = self::normalize_client_id($record['client_id'] ?? null);
        $record_expediente_raw = $record['expediente_id'] ?? null;
        if ($record_id === null || !$record_client['ok'] || $record_client['id'] !== $client_id) {
            return self::fail();
        }

        // Orphan: expediente_id ausente o no positivo.
        $record_expediente = self::positive_id($record_expediente_raw);
        if ($record_expediente !== null) {
            return self::fail();
        }

        $att_record = self::positive_id($attachment['record_id'] ?? null);
        $att_client = self::normalize_client_id($attachment['client_id'] ?? null);
        $operation_id = strtolower(trim((string) ($attachment['upload_operation_id'] ?? '')));
        $storage_path = (string) ($attachment['storage_path'] ?? '');

        if (
            $att_record === null
            || $att_record !== $record_id
            || !$att_client['ok']
            || $att_client['id'] !== $client_id
            || $operation_id === ''
            || $storage_path === ''
        ) {
            return self::fail();
        }

        $parsed = ExpedienteAdjuntoVariants::parse_original_path($storage_path);
        if ($parsed === null) {
            return self::fail();
        }

        if ((string) ($parsed['contract'] ?? '') !== ExpedienteAdjuntoVariants::CONTRACT_CLIENT_V1) {
            return self::fail();
        }

        if (
            (int) ($parsed['client_id'] ?? 0) !== $client_id
            || (int) ($parsed['record_id'] ?? 0) !== $record_id
            || strtolower((string) ($parsed['operation_id'] ?? '')) !== $operation_id
        ) {
            return self::fail();
        }

        return self::ok($parsed);
    }

    /**
     * @param mixed $raw
     * @return array{ok:true,id:?int}|array{ok:false}
     */
    public static function normalize_client_id($raw): array {
        if ($raw === null || $raw === '') {
            return ['ok' => true, 'id' => null];
        }

        if (is_int($raw)) {
            if ($raw < 1) {
                return ['ok' => false];
            }

            return ['ok' => true, 'id' => $raw];
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '' || !ctype_digit($trimmed)) {
                return ['ok' => false];
            }
            $n = (int) $trimmed;
            if ($n < 1 || (string) $n !== $trimmed) {
                return ['ok' => false];
            }

            return ['ok' => true, 'id' => $n];
        }

        return ['ok' => false];
    }

    /**
     * @param mixed $value
     */
    private static function positive_id($value): ?int {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]{0,18}$/', $value)) {
            $n = (int) $value;
            if ($n > 0 && (string) $n === $value) {
                return $n;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $parsed
     * @return array{ok:true,parsed:array<string,mixed>}
     */
    private static function ok(array $parsed): array {
        return [
            'ok' => true,
            'parsed' => $parsed,
        ];
    }

    /**
     * @return array{ok:false,code:string}
     */
    private static function fail(): array {
        return [
            'ok' => false,
            'code' => self::CODE_INCONSISTENT,
        ];
    }
}
