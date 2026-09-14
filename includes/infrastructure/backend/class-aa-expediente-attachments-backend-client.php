<?php
/**
 * Backend client — Expediente attachment Storage ops (HMAC JSON) (MC4a2/MC5c1).
 *
 * authorize-upload / finalize / sign-read / delete / delete-mandates accept|seal|status.
 * No registra signed_url, token ni upload_intent.
 * El campo separado `token` de authorize se descarta; `signed_url` solo vive
 * dentro de `objects` pendientes para el transporte PUT interno, nunca en UI.
 *
 * Mandatos: installation_id solo lo deriva el backend del HMAC. Este cliente
 * no lo envía en el body, no inventa mandate_id/batch_seq tras una respuesta
 * perdida y no trata transporte ambiguo ni payloads incompletos como éxito.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}

class AA_Expediente_Attachments_Backend_Client {

    /** @var list<string> */
    private const KNOWN_ERRORS = [
        'unauthorized',
        'invalid_operation_id',
        'invalid_image_meta',
        'upload_intent_invalid',
        'path_invalid',
        'path_forbidden',
        'installation_missing',
        'object_missing',
        'object_mismatch',
        'sign_failed',
        'delete_failed',
        'storage_not_included',
        'storage_quota_exceeded',
        'invalid_usage_report',
        'manifest_version_invalid',
        'invalid_variant_meta',
        'variant_bytes_exceeded',
        'variant_invalid',
        'path_contract_invalid',
        'invalid_content_sha256',
        'object_orphan',
        'object_probe_failed',
        'delete_mandate_unavailable',
        'delete_mandate_malformed',
        'mandate_id_required',
        'invalid_batch_seq',
        'invalid_items',
        'batch_items_empty',
        'batch_too_large',
        'invalid_upload_operation_id',
        'invalid_wp_record_id',
        'invalid_byte_size',
        'storage_path_mismatch',
        'batch_fingerprint_null',
        'invalid_request',
        'invalid_item_limit',
        'invalid_expected_batch_count',
        'invalid_batch_fingerprint',
        'batch_content_mismatch',
        'batch_seq_gap',
        'inventory_sealed',
        'item_duplicate_in_batch',
        'item_duplicate_in_mandate',
        'item_metadata_conflict',
        'item_issuance_metadata_conflict',
        'seal_mismatch',
        'seal_prefix_mismatch',
        'seal_batch_row_mismatch',
        'mandate_installation_mismatch',
        'mandate_not_found',
    ];

    /** @var list<string> */
    private const OBJECT_KEYS = ['original', 'summary', 'gallery', 'display'];

    private const DELETE_MANDATE_MAX_ITEMS = 50;

    private const DELETE_MANDATE_MAX_ITEM_LIMIT = 100;

    private const DELETE_MANDATE_MAX_BYTE_SIZE = 1048576;

    /** UUID v1–v5, mismo recorte que el backend de mandatos. */
    private const DELETE_MANDATE_UUID_RE =
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private const DELETE_MANDATE_SHA_RE = '/^[0-9a-f]{64}$/';

    /** @var array<string, string> */
    private const DELETE_MANDATE_FAILURE_CLASS = [
        'unauthorized' => 'auth',
        'installation_missing' => 'auth',
        'installation_id_required' => 'auth',
        'mandate_id_required' => 'invalid_request',
        'invalid_batch_seq' => 'invalid_request',
        'invalid_items' => 'invalid_request',
        'batch_items_empty' => 'invalid_request',
        'batch_too_large' => 'invalid_request',
        'invalid_upload_operation_id' => 'invalid_request',
        'invalid_wp_record_id' => 'invalid_request',
        'invalid_content_sha256' => 'invalid_request',
        'invalid_byte_size' => 'invalid_request',
        'storage_path_mismatch' => 'invalid_request',
        'batch_fingerprint_null' => 'invalid_request',
        'invalid_request' => 'invalid_request',
        'invalid_item_limit' => 'invalid_request',
        'invalid_expected_batch_count' => 'invalid_request',
        'invalid_batch_fingerprint' => 'invalid_request',
        'path_contract_invalid' => 'invalid_request',
        'batch_content_mismatch' => 'conflict',
        'batch_seq_gap' => 'conflict',
        'inventory_sealed' => 'conflict',
        'item_duplicate_in_batch' => 'conflict',
        'item_duplicate_in_mandate' => 'conflict',
        'item_metadata_conflict' => 'conflict',
        'item_issuance_metadata_conflict' => 'conflict',
        'seal_mismatch' => 'conflict',
        'seal_prefix_mismatch' => 'conflict',
        'seal_batch_row_mismatch' => 'conflict',
        'mandate_installation_mismatch' => 'conflict',
        'mandate_not_found' => 'not_found',
        'delete_mandate_unavailable' => 'unreachable',
        'delete_mandate_malformed' => 'malformed',
        'expediente_attachments_unreachable' => 'unreachable',
        'expediente_attachments_invalid_response' => 'malformed',
        'expediente_attachments_backend_error' => 'unknown',
    ];

    /**
     * @param array{
     *   upload_operation_id:string,
     *   wp_client_id:int,
     *   wp_record_id:int,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   used_bytes:int,
     *   variants_manifest_version:int,
     *   variant_byte_sizes:array{summary:int,gallery:int,display:int}
     * } $input
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function authorize_upload(array $input): array {
        $manifest = $input['variants_manifest_version'] ?? null;
        if (
            !isset($input['variants_manifest_version'])
            || !is_int($manifest)
            || $manifest !== ExpedienteAdjuntoVariants::MANIFEST_VERSION
        ) {
            return $this->failure('manifest_version_invalid', '', 0);
        }

        $variant_sizes = $this->normalize_variant_byte_sizes($input['variant_byte_sizes'] ?? null);
        if ($variant_sizes === null) {
            return $this->failure('invalid_variant_meta', '', 0);
        }

        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $payload = [
            'upload_operation_id' => (string) ($input['upload_operation_id'] ?? ''),
            'wp_client_id' => (int) ($input['wp_client_id'] ?? 0),
            'wp_record_id' => (int) ($input['wp_record_id'] ?? 0),
            'mime_type' => (string) ($input['mime_type'] ?? ''),
            'byte_size' => (int) ($input['byte_size'] ?? 0),
            'width' => (int) ($input['width'] ?? 0),
            'height' => (int) ($input['height'] ?? 0),
            'used_bytes' => (int) ($input['used_bytes'] ?? 0),
            'variants_manifest_version' => ExpedienteAdjuntoVariants::MANIFEST_VERSION,
            'variant_byte_sizes' => $variant_sizes,
        ];

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/authorize-upload';
        $response = aa_send_authenticated_request($endpoint, 'POST', $payload);

        return $this->parseAuthorizeResponse($response);
    }

    /**
     * Authorize upload canónico expediente_v2 (P3 / N1).
     * Nunca envía wp_client_id. Path lo construye Node.
     *
     * @param array{
     *   upload_operation_id:string,
     *   wp_expediente_id:int,
     *   wp_record_id:int,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   used_bytes:int,
     *   variants_manifest_version:int,
     *   variant_byte_sizes:array{summary:int,gallery:int,display:int}
     * } $input
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function authorize_expediente_upload(array $input): array {
        $expediente_id = (int) ($input['wp_expediente_id'] ?? 0);
        $record_id = (int) ($input['wp_record_id'] ?? 0);
        if ($expediente_id < 1 || $record_id < 1) {
            return $this->failure('path_contract_invalid', '', 0);
        }

        if (array_key_exists('wp_client_id', $input)) {
            return $this->failure('path_contract_invalid', '', 0);
        }

        $manifest = $input['variants_manifest_version'] ?? null;
        if (
            !isset($input['variants_manifest_version'])
            || !is_int($manifest)
            || $manifest !== ExpedienteAdjuntoVariants::MANIFEST_VERSION
        ) {
            return $this->failure('manifest_version_invalid', '', 0);
        }

        $variant_sizes = $this->normalize_variant_byte_sizes($input['variant_byte_sizes'] ?? null);
        if ($variant_sizes === null) {
            return $this->failure('invalid_variant_meta', '', 0);
        }

        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $payload = [
            'path_contract' => ExpedienteAdjuntoVariants::CONTRACT_EXPEDIENTE_V2,
            'upload_operation_id' => (string) ($input['upload_operation_id'] ?? ''),
            'wp_expediente_id' => $expediente_id,
            'wp_record_id' => $record_id,
            'mime_type' => (string) ($input['mime_type'] ?? ''),
            'byte_size' => (int) ($input['byte_size'] ?? 0),
            'width' => (int) ($input['width'] ?? 0),
            'height' => (int) ($input['height'] ?? 0),
            'used_bytes' => (int) ($input['used_bytes'] ?? 0),
            'variants_manifest_version' => ExpedienteAdjuntoVariants::MANIFEST_VERSION,
            'variant_byte_sizes' => $variant_sizes,
        ];

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/authorize-upload';
        $response = aa_send_authenticated_request($endpoint, 'POST', $payload);

        return $this->parseAuthorizeResponse($response);
    }

    /**
     * Authorize upload canónico canonical_v1 (IMG-3b).
     * Path lo construye Node. Exige content_sha256. Nunca envía client/expediente.
     *
     * @param array{
     *   upload_operation_id:string,
     *   wp_record_id:int,
     *   mime_type:string,
     *   byte_size:int,
     *   width:int,
     *   height:int,
     *   content_sha256:string,
     *   used_bytes:int,
     *   variants_manifest_version:int,
     *   variant_byte_sizes:array{summary:int,gallery:int,display:int},
     *   prior_upload_intent?:string
     * } $input
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function authorize_canonical_upload(array $input): array {
        $record_id = (int) ($input['wp_record_id'] ?? 0);
        if ($record_id < 1) {
            return $this->failure('path_contract_invalid', '', 0);
        }

        if (array_key_exists('wp_client_id', $input) || array_key_exists('wp_expediente_id', $input)) {
            return $this->failure('path_contract_invalid', '', 0);
        }

        $sha = isset($input['content_sha256']) ? strtolower(trim((string) $input['content_sha256'])) : '';
        if ($sha === '' || !preg_match('/^[0-9a-f]{64}$/', $sha)) {
            return $this->failure('invalid_content_sha256', '', 0);
        }

        $manifest = $input['variants_manifest_version'] ?? null;
        if (
            !isset($input['variants_manifest_version'])
            || !is_int($manifest)
            || $manifest !== ExpedienteAdjuntoVariants::MANIFEST_VERSION
        ) {
            return $this->failure('manifest_version_invalid', '', 0);
        }

        $variant_sizes = $this->normalize_variant_byte_sizes($input['variant_byte_sizes'] ?? null);
        if ($variant_sizes === null) {
            return $this->failure('invalid_variant_meta', '', 0);
        }

        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $payload = [
            'path_contract' => ExpedienteAdjuntoVariants::CONTRACT_CANONICAL_V1,
            'upload_operation_id' => (string) ($input['upload_operation_id'] ?? ''),
            'wp_record_id' => $record_id,
            'mime_type' => (string) ($input['mime_type'] ?? ''),
            'byte_size' => (int) ($input['byte_size'] ?? 0),
            'width' => (int) ($input['width'] ?? 0),
            'height' => (int) ($input['height'] ?? 0),
            'content_sha256' => $sha,
            'used_bytes' => (int) ($input['used_bytes'] ?? 0),
            'variants_manifest_version' => ExpedienteAdjuntoVariants::MANIFEST_VERSION,
            'variant_byte_sizes' => $variant_sizes,
        ];

        $resume = false;
        if (array_key_exists('prior_upload_intent', $input)) {
            $prior = $input['prior_upload_intent'];
            if (!is_string($prior) || trim($prior) === '') {
                return $this->failure('upload_intent_invalid', '', 0);
            }
            $payload['prior_upload_intent'] = trim($prior);
            $resume = true;
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/authorize-upload';
        $response = aa_send_authenticated_request($endpoint, 'POST', $payload);

        return $this->parseAuthorizeResponse($response, $resume);
    }

    /**
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function finalize(string $upload_intent): array {
        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/finalize';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'upload_intent' => $upload_intent,
        ]);

        return $this->parseFinalizeResponse($response);
    }

    /**
     * @param mixed $variant
     * @return array{ok:true,result:array{url:string,expires_in:int,variant:string}}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function sign_read(string $storage_path, $variant): array {
        if (!is_string($variant) || !ExpedienteAdjuntoVariants::is_allowed_variant($variant)) {
            return $this->failure('variant_invalid', '', 0);
        }

        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/sign-read';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'storage_path' => $storage_path,
            'variant' => $variant,
        ]);

        return $this->parseSignReadResponse($response, $variant);
    }

    /**
     * MC5c1: elimina un objeto privado. Éxito solo con status deleted|already_absent.
     *
     * @return array{ok:true,result:array{status:string}}|array{ok:false,code:string,error:string,http_status:int}
     */
    public function delete_object(string $storage_path): array {
        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $preflight;
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/delete';
        $response = aa_send_authenticated_request($endpoint, 'POST', [
            'storage_path' => $storage_path,
        ]);

        return $this->parseDeleteResponse($response);
    }

    /**
     * POST /expediente/attachments/delete-mandates/accept
     *
     * Aceptación durable de una tanda. No autoriza el retiro SQL local
     * (`retire_authorized` siempre false aquí). No genera mandate_id ni
     * batch_seq: el caller los aporta y reutiliza tras una respuesta perdida.
     *
     * @param array{
     *   mandate_id:string,
     *   batch_seq:int,
     *   items:list<array{
     *     upload_operation_id:string,
     *     wp_record_id:int,
     *     content_sha256:string,
     *     byte_size:int,
     *     storage_path?:string
     *   }>
     * } $input
     * @return array{
     *   ok:true,
     *   outcome:'accepted'|'already_accepted',
     *   retire_authorized:false,
     *   result:array<string,mixed>
     * }|array{
     *   ok:false,
     *   code:string,
     *   error:string,
     *   http_status:int,
     *   failure_class:string,
     *   retire_authorized:false
     * }
     */
    public function accept_delete_batch(array $input): array {
        $mandate_id = $this->normalize_mandate_uuid($input['mandate_id'] ?? null);
        if ($mandate_id === null) {
            return $this->mandate_failure('mandate_id_required', 0, 'invalid_request');
        }

        $batch_seq = $this->mandate_non_neg_int($input['batch_seq'] ?? null);
        if ($batch_seq === null) {
            return $this->mandate_failure('invalid_batch_seq', 0, 'invalid_request');
        }

        $items = $this->normalize_accept_delete_items($input['items'] ?? null);
        if ($items === null) {
            return $this->mandate_failure('invalid_items', 0, 'invalid_request');
        }
        if ($items === []) {
            return $this->mandate_failure('batch_items_empty', 0, 'invalid_request');
        }
        if (count($items) > self::DELETE_MANDATE_MAX_ITEMS) {
            return $this->mandate_failure('batch_too_large', 0, 'invalid_request');
        }

        $ops_seen = [];
        foreach ($items as $item) {
            $op = $item['upload_operation_id'];
            if (isset($ops_seen[$op])) {
                return $this->mandate_failure('item_duplicate_in_batch', 0, 'conflict');
            }
            $ops_seen[$op] = true;
        }

        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $this->mandate_failure(
                $preflight['code'],
                $preflight['http_status'],
                $this->mandate_failure_class($preflight['code'])
            );
        }

        $payload = [
            'path_contract' => ExpedienteAdjuntoVariants::CONTRACT_CANONICAL_V1,
            'mandate_id' => $mandate_id,
            'batch_seq' => $batch_seq,
            'items' => $items,
        ];

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/delete-mandates/accept';
        $response = aa_send_authenticated_request($endpoint, 'POST', $payload);

        return $this->parseAcceptDeleteBatchResponse($response, $mandate_id, $batch_seq, count($items));
    }

    /**
     * POST /expediente/attachments/delete-mandates/seal
     *
     * Cierra la recepción. Solo `sealed` / `already_sealed` con
     * `structural_retire_authorized === true` e `inventory_status === sealed`
     * autorizan el retiro estructural local.
     *
     * @param array{mandate_id:string,expected_batch_count:int} $input
     * @return array{
     *   ok:true,
     *   outcome:'sealed'|'already_sealed',
     *   retire_authorized:true,
     *   result:array<string,mixed>
     * }|array{
     *   ok:false,
     *   code:string,
     *   error:string,
     *   http_status:int,
     *   failure_class:string,
     *   retire_authorized:false
     * }
     */
    public function seal_delete_mandate(array $input): array {
        $mandate_id = $this->normalize_mandate_uuid($input['mandate_id'] ?? null);
        if ($mandate_id === null) {
            return $this->mandate_failure('mandate_id_required', 0, 'invalid_request');
        }

        $expected = $this->mandate_non_neg_int($input['expected_batch_count'] ?? null);
        if ($expected === null) {
            return $this->mandate_failure('invalid_expected_batch_count', 0, 'invalid_request');
        }

        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $this->mandate_failure(
                $preflight['code'],
                $preflight['http_status'],
                $this->mandate_failure_class($preflight['code'])
            );
        }

        $payload = [
            'mandate_id' => $mandate_id,
            'expected_batch_count' => $expected,
        ];

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/delete-mandates/seal';
        $response = aa_send_authenticated_request($endpoint, 'POST', $payload);

        return $this->parseSealDeleteMandateResponse($response, $mandate_id, $expected);
    }

    /**
     * POST /expediente/attachments/delete-mandates/status
     *
     * Recupera un mandato o tanda sin inventar identidades. Evidencia real:
     * - `found: false` no acredita aceptación ni retiro.
     * - Fingerprint e ítems de una tanda concreta solo vienen si se pide
     *   `batch_seq` y `batch_found` no es false (`can_credit_batch`).
     * - `items` de cabecera es una página (`items_page.has_more`); no cubre
     *   el inventario completo cuando has_more es true.
     * - `structural_retire_authorized` ⇔ inventario sellado; no certifica
     *   el DELETE SQL de WordPress.
     *
     * @param array{
     *   mandate_id:string,
     *   batch_seq?:int|null,
     *   item_cursor?:string|null,
     *   item_limit?:int|null
     * } $input
     * @return array{
     *   ok:true,
     *   outcome:'found'|'not_found',
     *   retire_authorized:bool,
     *   result:array<string,mixed>
     * }|array{
     *   ok:false,
     *   code:string,
     *   error:string,
     *   http_status:int,
     *   failure_class:string,
     *   retire_authorized:false
     * }
     */
    public function get_delete_mandate_status(array $input): array {
        $mandate_id = $this->normalize_mandate_uuid($input['mandate_id'] ?? null);
        if ($mandate_id === null) {
            return $this->mandate_failure('mandate_id_required', 0, 'invalid_request');
        }

        $payload = [
            'mandate_id' => $mandate_id,
        ];

        $requested_batch_seq = null;
        if (array_key_exists('batch_seq', $input) && $input['batch_seq'] !== null) {
            $requested_batch_seq = $this->mandate_non_neg_int($input['batch_seq']);
            if ($requested_batch_seq === null) {
                return $this->mandate_failure('invalid_batch_seq', 0, 'invalid_request');
            }
            $payload['batch_seq'] = $requested_batch_seq;
        }

        if (array_key_exists('item_cursor', $input) && $input['item_cursor'] !== null && $input['item_cursor'] !== '') {
            $cursor = $this->normalize_mandate_uuid($input['item_cursor']);
            if ($cursor === null) {
                return $this->mandate_failure('invalid_request', 0, 'invalid_request');
            }
            $payload['item_cursor'] = $cursor;
        }

        if (array_key_exists('item_limit', $input) && $input['item_limit'] !== null) {
            $limit = $this->mandate_positive_int($input['item_limit']);
            if ($limit === null || $limit > self::DELETE_MANDATE_MAX_ITEM_LIMIT) {
                return $this->mandate_failure('invalid_item_limit', 0, 'invalid_request');
            }
            $payload['item_limit'] = $limit;
        }

        $preflight = $this->preflight();
        if ($preflight !== null) {
            return $this->mandate_failure(
                $preflight['code'],
                $preflight['http_status'],
                $this->mandate_failure_class($preflight['code'])
            );
        }

        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/expediente/attachments/delete-mandates/status';
        $response = aa_send_authenticated_request($endpoint, 'POST', $payload);

        return $this->parseDeleteMandateStatusResponse($response, $mandate_id, $requested_batch_seq);
    }

    /**
     * @return array{ok:false,code:string,error:string,http_status:int}|null
     */
    private function preflight(): ?array {
        if (!defined('AA_API_BASE_URL') || (string) AA_API_BASE_URL === '') {
            return $this->failure('expediente_attachments_unreachable', '', 0);
        }
        if (!function_exists('aa_send_authenticated_request')) {
            return $this->failure('expediente_attachments_unreachable', '', 0);
        }
        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->failure('expediente_attachments_unreachable', '', 0);
        }

        return null;
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseAuthorizeResponse($response, bool $resume_mode = false): array {
        $parsed = $this->parseJsonOk($response, [
            'variants_manifest_version',
            'upload_operation_id',
            'storage_path',
            'upload_intent',
            'objects',
        ]);
        if ($parsed['ok'] !== true) {
            return $parsed;
        }

        /** @var array<string,mixed> $result */
        $result = $parsed['result'];
        unset($result['token']);

        if (array_key_exists('status', $result) || array_key_exists('signed_url', $result)) {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

        $manifest = $result['variants_manifest_version'] ?? null;
        $operation_id = $result['upload_operation_id'] ?? null;
        $storage_path = $result['storage_path'] ?? null;
        $upload_intent = $result['upload_intent'] ?? null;
        $objects = $result['objects'] ?? null;

        if (
            !is_int($manifest)
            || $manifest !== ExpedienteAdjuntoVariants::MANIFEST_VERSION
            || !is_string($operation_id) || $operation_id === ''
            || !is_string($storage_path) || $storage_path === ''
            || !is_string($upload_intent) || $upload_intent === ''
            || !is_array($objects)
        ) {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

        $normalized_objects = $this->normalize_authorize_objects($objects, $resume_mode);
        if ($normalized_objects === null) {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

        $result['objects'] = $normalized_objects;

        return [
            'ok' => true,
            'result' => $result,
        ];
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseFinalizeResponse($response): array {
        return $this->parseJsonOk($response, [
            'storage_path',
            'upload_operation_id',
            'installation_id',
            'mime_type',
            'byte_size',
            'width',
            'height',
        ]);
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok:true,result:array{url:string,expires_in:int,variant:string}}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseSignReadResponse($response, string $variant): array {
        $parsed = $this->parseJsonOk($response, ['url', 'expires_in', 'variant']);
        if ($parsed['ok'] !== true) {
            return $parsed;
        }

        /** @var array<string,mixed> $result */
        $result = $parsed['result'];
        $url = $result['url'] ?? null;
        $expires_in = $result['expires_in'] ?? null;
        $got_variant = $result['variant'] ?? null;

        if (
            !is_string($url) || $url === ''
            || !is_int($expires_in) || $expires_in < 1
            || !is_string($got_variant)
            || $got_variant !== $variant
        ) {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

        return [
            'ok' => true,
            'result' => [
                'url' => $url,
                'expires_in' => $expires_in,
                'variant' => $got_variant,
            ],
        ];
    }

    /**
     * @param array|\WP_Error $response
     * @return array{ok:true,result:array{status:string}}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseDeleteResponse($response): array {
        $parsed = $this->parseJsonOk($response, ['status']);
        if ($parsed['ok'] !== true) {
            return $parsed;
        }

        /** @var array<string,mixed> $result */
        $result = $parsed['result'];
        $status = (string) ($result['status'] ?? '');
        if ($status !== 'deleted' && $status !== 'already_absent') {
            return $this->failure('expediente_attachments_invalid_response', '', 0);
        }

        return [
            'ok' => true,
            'result' => ['status' => $status],
        ];
    }

    /**
     * @param mixed $value
     * @return array{summary:int,gallery:int,display:int}|null
     */
    private function normalize_variant_byte_sizes($value): ?array {
        if (!is_array($value) || array_is_list($value)) {
            return null;
        }

        $expected = ExpedienteAdjuntoVariants::ALLOWED_VARIANTS;
        if (count($value) !== count($expected)) {
            return null;
        }

        $sizes = [];
        foreach ($expected as $key) {
            if (!array_key_exists($key, $value) || !is_int($value[$key]) || $value[$key] < 1) {
                return null;
            }
            $sizes[$key] = $value[$key];
        }

        foreach (array_keys($value) as $key) {
            if (!in_array($key, $expected, true)) {
                return null;
            }
        }

        return $sizes;
    }

    /**
     * @param array<string,mixed> $objects
     * @return array<string,array{status:string,signed_url?:string}>|null
     */
    private function normalize_authorize_objects(array $objects, bool $resume_mode = false): ?array {
        if (count($objects) !== count(self::OBJECT_KEYS)) {
            return null;
        }

        $normalized = [];
        foreach (self::OBJECT_KEYS as $key) {
            if (!array_key_exists($key, $objects) || !is_array($objects[$key])) {
                return null;
            }

            $entry = $objects[$key];
            unset($entry['token']);

            $status = isset($entry['status']) ? (string) $entry['status'] : '';
            if ($status === 'pending_upload') {
                if ($resume_mode) {
                    if (array_key_exists('signed_url', $entry) && $entry['signed_url'] !== null && $entry['signed_url'] !== '') {
                        return null;
                    }
                    $normalized[$key] = ['status' => 'pending_upload'];
                    continue;
                }

                $signed_url = $entry['signed_url'] ?? null;
                if (!is_string($signed_url) || $signed_url === '') {
                    return null;
                }
                $normalized[$key] = [
                    'status' => 'pending_upload',
                    'signed_url' => $signed_url,
                ];
                continue;
            }

            if ($status === 'already_uploaded') {
                if (array_key_exists('signed_url', $entry)) {
                    return null;
                }
                $normalized[$key] = ['status' => 'already_uploaded'];
                continue;
            }

            return null;
        }

        foreach (array_keys($objects) as $key) {
            if (!in_array($key, self::OBJECT_KEYS, true)) {
                return null;
            }
        }

        return $normalized;
    }

    /**
     * @param array|\WP_Error $response
     * @param list<string> $required_keys
     * @return array{ok:true,result:array<string,mixed>}|array{ok:false,code:string,error:string,http_status:int}
     */
    private function parseJsonOk($response, array $required_keys): array {
        if (is_wp_error($response)) {
            return $this->failure('expediente_attachments_unreachable', '', 0);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded = $this->decode_json_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
                $backend_error = trim((string) $decoded['error']);
                if (in_array($backend_error, self::KNOWN_ERRORS, true)) {
                    return $this->failure($backend_error, '', $status_code > 0 ? $status_code : 0);
                }
            }

            if ($status_code >= 500) {
                return $this->failure('expediente_attachments_unreachable', '', $status_code);
            }

            return $this->failure('expediente_attachments_backend_error', '', $status_code > 0 ? $status_code : 0);
        }

        if (!is_array($decoded) || empty($decoded['ok'])) {
            return $this->failure(
                'expediente_attachments_invalid_response',
                '',
                $status_code > 0 ? $status_code : 0
            );
        }

        $result = $decoded;
        unset($result['ok']);

        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $result)) {
                return $this->failure(
                    'expediente_attachments_invalid_response',
                    '',
                    $status_code > 0 ? $status_code : 0
                );
            }
        }

        return [
            'ok' => true,
            'result' => $result,
        ];
    }

    /**
     * @param array|\WP_Error $response
     * @return array<string,mixed>|null
     */
    private function decode_json_body($response): ?array {
        $raw_body = wp_remote_retrieve_body($response);
        $decoded = json_decode($raw_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array{ok:false,code:string,error:string,http_status:int}
     */
    private function failure(string $code, string $error, int $http_status): array {
        return [
            'ok' => false,
            'code' => $code,
            'error' => $error,
            'http_status' => $http_status,
        ];
    }

    /**
     * @param array|\WP_Error $response
     * @return array{
     *   ok:true,
     *   outcome:'accepted'|'already_accepted',
     *   retire_authorized:false,
     *   result:array<string,mixed>
     * }|array{
     *   ok:false,
     *   code:string,
     *   error:string,
     *   http_status:int,
     *   failure_class:string,
     *   retire_authorized:false
     * }
     */
    private function parseAcceptDeleteBatchResponse(
        $response,
        string $mandate_id,
        int $batch_seq,
        int $expected_item_count
    ): array {
        $envelope = $this->parse_mandate_http_envelope($response);
        if ($envelope['ok'] !== true) {
            return $envelope;
        }

        /** @var array<string,mixed> $body */
        $body = $envelope['body'];
        $got_mandate = $this->normalize_mandate_uuid($body['mandate_id'] ?? null);
        $inventory_status = $body['inventory_status'] ?? null;
        $batch = $body['batch'] ?? null;
        $structural = $body['structural_retire_authorized'] ?? null;
        $items = $body['items'] ?? null;

        if (
            $got_mandate !== $mandate_id
            || ($inventory_status !== 'open' && $inventory_status !== 'sealed')
            || !is_bool($structural)
            || !is_array($batch)
            || !is_array($items)
            || !array_is_list($items)
        ) {
            return $this->mandate_failure(
                'expediente_attachments_invalid_response',
                $envelope['http_status'],
                'malformed'
            );
        }

        $seq = $this->mandate_non_neg_int($batch['seq'] ?? null);
        $fingerprint = $this->normalize_mandate_sha($batch['fingerprint'] ?? null);
        $acceptance = $batch['acceptance'] ?? null;
        $prefix = $this->mandate_non_neg_int($batch['contiguous_prefix_len'] ?? null);
        if (
            $seq !== $batch_seq
            || $fingerprint === null
            || ($acceptance !== 'accepted' && $acceptance !== 'already_accepted')
            || $prefix === null
            || count($items) !== $expected_item_count
        ) {
            return $this->mandate_failure(
                'expediente_attachments_invalid_response',
                $envelope['http_status'],
                'malformed'
            );
        }

        $normalized_items = [];
        foreach ($items as $raw_item) {
            $item = $this->normalize_mandate_item_row($raw_item, false);
            if ($item === null) {
                return $this->mandate_failure(
                    'expediente_attachments_invalid_response',
                    $envelope['http_status'],
                    'malformed'
                );
            }
            $normalized_items[] = $item;
        }

        $result = [
            'mandate_id' => $got_mandate,
            'inventory_status' => $inventory_status,
            'structural_retire_authorized' => $structural,
            'batch' => [
                'seq' => $seq,
                'fingerprint' => $fingerprint,
                'acceptance' => $acceptance,
                'contiguous_prefix_len' => $prefix,
            ],
            'items' => $normalized_items,
        ];
        if (array_key_exists('max_seq_accepted', $batch)) {
            $max_seq = $this->mandate_int($batch['max_seq_accepted']);
            if ($max_seq !== null) {
                $result['batch']['max_seq_accepted'] = $max_seq;
            }
        }
        foreach (['reception_item_count', 'owned_obligation_count', 'tech_bytes_owned'] as $count_key) {
            if (array_key_exists($count_key, $body)) {
                $count = $this->mandate_non_neg_int($body[$count_key]);
                if ($count !== null) {
                    $result[$count_key] = $count;
                }
            }
        }

        return $this->mandate_success($acceptance, false, $result);
    }

    /**
     * @param array|\WP_Error $response
     * @return array{
     *   ok:true,
     *   outcome:'sealed'|'already_sealed',
     *   retire_authorized:true,
     *   result:array<string,mixed>
     * }|array{
     *   ok:false,
     *   code:string,
     *   error:string,
     *   http_status:int,
     *   failure_class:string,
     *   retire_authorized:false
     * }
     */
    private function parseSealDeleteMandateResponse($response, string $mandate_id, int $expected): array {
        $envelope = $this->parse_mandate_http_envelope($response);
        if ($envelope['ok'] !== true) {
            return $envelope;
        }

        /** @var array<string,mixed> $body */
        $body = $envelope['body'];
        $got_mandate = $this->normalize_mandate_uuid($body['mandate_id'] ?? null);
        $inventory_status = $body['inventory_status'] ?? null;
        $structural = $body['structural_retire_authorized'] ?? null;
        $seal = $body['seal'] ?? null;

        if (
            $got_mandate !== $mandate_id
            || $inventory_status !== 'sealed'
            || $structural !== true
            || !is_array($seal)
        ) {
            return $this->mandate_failure(
                'expediente_attachments_invalid_response',
                $envelope['http_status'],
                'malformed'
            );
        }

        $outcome = $seal['outcome'] ?? null;
        $seal_expected = $this->mandate_non_neg_int($seal['expected_batch_count'] ?? null);
        $prefix = $this->mandate_non_neg_int($seal['contiguous_prefix_len'] ?? null);
        if (
            ($outcome !== 'sealed' && $outcome !== 'already_sealed')
            || $seal_expected !== $expected
            || $prefix === null
        ) {
            return $this->mandate_failure(
                'expediente_attachments_invalid_response',
                $envelope['http_status'],
                'malformed'
            );
        }

        $result = [
            'mandate_id' => $got_mandate,
            'inventory_status' => $inventory_status,
            'structural_retire_authorized' => true,
            'seal' => [
                'outcome' => $outcome,
                'expected_batch_count' => $seal_expected,
                'contiguous_prefix_len' => $prefix,
            ],
        ];
        if (array_key_exists('sealed_at', $seal) && is_string($seal['sealed_at']) && $seal['sealed_at'] !== '') {
            $result['seal']['sealed_at'] = $seal['sealed_at'];
        }
        foreach (['reception_item_count', 'owned_obligation_count', 'tech_bytes_owned'] as $count_key) {
            if (array_key_exists($count_key, $body)) {
                $count = $this->mandate_non_neg_int($body[$count_key]);
                if ($count !== null) {
                    $result[$count_key] = $count;
                }
            }
        }

        return $this->mandate_success($outcome, true, $result);
    }

    /**
     * @param array|\WP_Error $response
     * @return array{
     *   ok:true,
     *   outcome:'found'|'not_found',
     *   retire_authorized:bool,
     *   result:array<string,mixed>
     * }|array{
     *   ok:false,
     *   code:string,
     *   error:string,
     *   http_status:int,
     *   failure_class:string,
     *   retire_authorized:false
     * }
     */
    private function parseDeleteMandateStatusResponse(
        $response,
        string $mandate_id,
        ?int $requested_batch_seq
    ): array {
        $envelope = $this->parse_mandate_http_envelope($response);
        if ($envelope['ok'] !== true) {
            return $envelope;
        }

        /** @var array<string,mixed> $body */
        $body = $envelope['body'];
        $found = $body['found'] ?? null;
        if ($found === false) {
            return $this->mandate_success('not_found', false, [
                'found' => false,
                'can_credit_batch' => false,
                'inventory_page_complete' => false,
            ]);
        }
        if ($found !== true) {
            return $this->mandate_failure(
                'expediente_attachments_invalid_response',
                $envelope['http_status'],
                'malformed'
            );
        }

        $got_mandate = $this->normalize_mandate_uuid($body['mandate_id'] ?? null);
        $inventory_status = $body['inventory_status'] ?? null;
        $prefix = $this->mandate_non_neg_int($body['contiguous_prefix_len'] ?? null);
        $structural = $body['structural_retire_authorized'] ?? null;
        if (
            $got_mandate !== $mandate_id
            || ($inventory_status !== 'open' && $inventory_status !== 'sealed')
            || $prefix === null
            || !is_bool($structural)
            || ($structural === true && $inventory_status !== 'sealed')
            || ($structural === false && $inventory_status === 'sealed')
        ) {
            return $this->mandate_failure(
                'expediente_attachments_invalid_response',
                $envelope['http_status'],
                'malformed'
            );
        }

        $result = [
            'found' => true,
            'mandate_id' => $got_mandate,
            'inventory_status' => $inventory_status,
            'contiguous_prefix_len' => $prefix,
            'structural_retire_authorized' => $structural,
            'can_credit_batch' => false,
            'inventory_page_complete' => false,
        ];

        if (array_key_exists('max_seq_accepted', $body)) {
            $max_seq = $this->mandate_int($body['max_seq_accepted']);
            if ($max_seq !== null) {
                $result['max_seq_accepted'] = $max_seq;
            }
        }
        foreach (
            [
                'sealed_at',
                'sealed_expected_batch_count',
                'persisted_batch_count',
                'reception_item_count',
                'owned_obligation_count',
                'tech_bytes_owned',
            ] as $optional_key
        ) {
            if (!array_key_exists($optional_key, $body) || $body[$optional_key] === null) {
                continue;
            }
            if ($optional_key === 'sealed_at') {
                if (is_string($body[$optional_key]) && $body[$optional_key] !== '') {
                    $result[$optional_key] = $body[$optional_key];
                }
                continue;
            }
            $count = $this->mandate_non_neg_int($body[$optional_key]);
            if ($count !== null) {
                $result[$optional_key] = $count;
            }
        }

        $batch_found_key = array_key_exists('batch_found', $body);
        if ($batch_found_key && $body['batch_found'] === false) {
            if ($requested_batch_seq === null) {
                return $this->mandate_failure(
                    'expediente_attachments_invalid_response',
                    $envelope['http_status'],
                    'malformed'
                );
            }
            $result['batch_found'] = false;
            $result['requested_batch_seq'] = $requested_batch_seq;
            $result['can_credit_batch'] = false;
            return $this->mandate_success(
                'found',
                $structural === true,
                $result
            );
        }

        if ($requested_batch_seq !== null) {
            $batch = $this->normalize_status_batch($body['batch'] ?? null, $requested_batch_seq);
            if ($batch === null) {
                return $this->mandate_failure(
                    'expediente_attachments_invalid_response',
                    $envelope['http_status'],
                    'malformed'
                );
            }
            $result['batch'] = $batch;
            $result['batch_found'] = true;
            $result['can_credit_batch'] = true;
        }

        $page = $this->normalize_status_items_page($body['items_page'] ?? null);
        $page_items = $body['items'] ?? null;
        if ($page === null || !is_array($page_items) || !array_is_list($page_items)) {
            return $this->mandate_failure(
                'expediente_attachments_invalid_response',
                $envelope['http_status'],
                'malformed'
            );
        }

        $normalized_page_items = [];
        foreach ($page_items as $raw_item) {
            $item = $this->normalize_mandate_item_row($raw_item, true);
            if ($item === null) {
                return $this->mandate_failure(
                    'expediente_attachments_invalid_response',
                    $envelope['http_status'],
                    'malformed'
                );
            }
            $normalized_page_items[] = $item;
        }

        $result['items'] = $normalized_page_items;
        $result['items_page'] = $page;
        $result['inventory_page_complete'] = $page['has_more'] !== true;

        return $this->mandate_success(
            'found',
            $structural === true,
            $result
        );
    }

    /**
     * @param array|\WP_Error $response
     * @return array{
     *   ok:true,
     *   http_status:int,
     *   body:array<string,mixed>
     * }|array{
     *   ok:false,
     *   code:string,
     *   error:string,
     *   http_status:int,
     *   failure_class:string,
     *   retire_authorized:false
     * }
     */
    private function parse_mandate_http_envelope($response): array {
        if (is_wp_error($response)) {
            return $this->mandate_failure('expediente_attachments_unreachable', 0, 'unreachable');
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded = $this->decode_json_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            $backend_error = '';
            if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
                $backend_error = trim($decoded['error']);
            }

            if ($backend_error !== '' && isset(self::DELETE_MANDATE_FAILURE_CLASS[$backend_error])) {
                return $this->mandate_failure(
                    $backend_error,
                    $status_code > 0 ? $status_code : 0,
                    self::DELETE_MANDATE_FAILURE_CLASS[$backend_error]
                );
            }

            if ($status_code >= 500) {
                return $this->mandate_failure(
                    'expediente_attachments_unreachable',
                    $status_code,
                    'unreachable'
                );
            }

            return $this->mandate_failure(
                'expediente_attachments_backend_error',
                $status_code > 0 ? $status_code : 0,
                'unknown'
            );
        }

        if (!is_array($decoded) || !array_key_exists('ok', $decoded) || $decoded['ok'] !== true) {
            return $this->mandate_failure(
                'expediente_attachments_invalid_response',
                $status_code > 0 ? $status_code : 0,
                'malformed'
            );
        }

        return [
            'ok' => true,
            'http_status' => $status_code,
            'body' => $decoded,
        ];
    }

    /**
     * @param mixed $items
     * @return list<array<string,mixed>>|null
     */
    private function normalize_accept_delete_items($items): ?array {
        if (!is_array($items) || !array_is_list($items)) {
            return null;
        }

        $normalized = [];
        foreach ($items as $raw) {
            if (!is_array($raw) || array_is_list($raw)) {
                return null;
            }
            $op = $this->normalize_mandate_uuid($raw['upload_operation_id'] ?? null);
            $record_id = $this->mandate_positive_int($raw['wp_record_id'] ?? null);
            $sha = $this->normalize_mandate_sha($raw['content_sha256'] ?? null);
            $byte_size = $this->mandate_positive_int($raw['byte_size'] ?? null);
            if (
                $op === null
                || $record_id === null
                || $sha === null
                || $byte_size === null
                || $byte_size > self::DELETE_MANDATE_MAX_BYTE_SIZE
            ) {
                return null;
            }

            $item = [
                'upload_operation_id' => $op,
                'wp_record_id' => $record_id,
                'content_sha256' => $sha,
                'byte_size' => $byte_size,
            ];
            if (
                array_key_exists('storage_path', $raw)
                && $raw['storage_path'] !== null
                && trim((string) $raw['storage_path']) !== ''
            ) {
                $item['storage_path'] = trim((string) $raw['storage_path']);
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param mixed $raw
     * @return array<string,mixed>|null
     */
    private function normalize_mandate_item_row($raw, bool $allow_status_extras): ?array {
        if (!is_array($raw) || array_is_list($raw)) {
            return null;
        }

        $op = $this->normalize_mandate_uuid($raw['upload_operation_id'] ?? null);
        $outcome = $raw['outcome'] ?? null;
        $retire = $raw['retire_authorized'] ?? null;
        $obligation = $this->normalize_mandate_uuid($raw['obligation_id'] ?? null);
        $record_id = $this->mandate_positive_int($raw['wp_record_id'] ?? null);
        $sha = $this->normalize_mandate_sha($raw['content_sha256'] ?? null);
        $byte_size = $this->mandate_positive_int($raw['byte_size'] ?? null);
        $path = $raw['storage_path'] ?? null;
        if (
            $op === null
            || ($outcome !== 'accepted' && $outcome !== 'already_obligated')
            || $retire !== true
            || $obligation === null
            || $record_id === null
            || $sha === null
            || $byte_size === null
            || $byte_size > self::DELETE_MANDATE_MAX_BYTE_SIZE
            || !is_string($path)
            || $path === ''
        ) {
            return null;
        }

        $item = [
            'upload_operation_id' => $op,
            'outcome' => $outcome,
            'retire_authorized' => true,
            'obligation_id' => $obligation,
            'storage_path' => $path,
            'wp_record_id' => $record_id,
            'content_sha256' => $sha,
            'byte_size' => $byte_size,
            'prior_mandate_id' => null,
        ];

        if (array_key_exists('prior_mandate_id', $raw) && $raw['prior_mandate_id'] !== null) {
            $prior = $this->normalize_mandate_uuid($raw['prior_mandate_id']);
            if ($prior === null) {
                return null;
            }
            $item['prior_mandate_id'] = $prior;
        } elseif ($outcome === 'already_obligated') {
            return null;
        }

        if ($allow_status_extras) {
            if (array_key_exists('batch_seq', $raw)) {
                $item_seq = $this->mandate_non_neg_int($raw['batch_seq']);
                if ($item_seq === null) {
                    return null;
                }
                $item['batch_seq'] = $item_seq;
            }
            if (array_key_exists('physical_status', $raw)) {
                if (!is_string($raw['physical_status']) || $raw['physical_status'] === '') {
                    return null;
                }
                $item['physical_status'] = $raw['physical_status'];
            }
        }

        return $item;
    }

    /**
     * @param mixed $raw
     * @return array<string,mixed>|null
     */
    private function normalize_status_batch($raw, int $requested_seq): ?array {
        if (!is_array($raw)) {
            return null;
        }
        $seq = $this->mandate_non_neg_int($raw['seq'] ?? null);
        $fingerprint = $this->normalize_mandate_sha($raw['fingerprint'] ?? null);
        $item_count = $this->mandate_positive_int($raw['item_count'] ?? null);
        $items = $raw['items'] ?? null;
        if (
            $seq !== $requested_seq
            || $fingerprint === null
            || $item_count === null
            || !is_array($items)
            || !array_is_list($items)
            || count($items) !== $item_count
        ) {
            return null;
        }

        $normalized_items = [];
        foreach ($items as $raw_item) {
            $item = $this->normalize_mandate_item_row($raw_item, true);
            if ($item === null) {
                return null;
            }
            $normalized_items[] = $item;
        }

        $batch = [
            'seq' => $seq,
            'fingerprint' => $fingerprint,
            'item_count' => $item_count,
            'items' => $normalized_items,
        ];
        if (array_key_exists('accepted_at', $raw) && is_string($raw['accepted_at']) && $raw['accepted_at'] !== '') {
            $batch['accepted_at'] = $raw['accepted_at'];
        }

        return $batch;
    }

    /**
     * @param mixed $raw
     * @return array{limit:int,has_more:bool,next_cursor:?string}|null
     */
    private function normalize_status_items_page($raw): ?array {
        if (!is_array($raw)) {
            return null;
        }
        $limit = $this->mandate_positive_int($raw['limit'] ?? null);
        $has_more = $raw['has_more'] ?? null;
        if ($limit === null || $limit > self::DELETE_MANDATE_MAX_ITEM_LIMIT || !is_bool($has_more)) {
            return null;
        }

        $cursor = null;
        if (array_key_exists('next_cursor', $raw) && $raw['next_cursor'] !== null) {
            $cursor = $this->normalize_mandate_uuid($raw['next_cursor']);
            if ($cursor === null) {
                return null;
            }
        }

        return [
            'limit' => $limit,
            'has_more' => $has_more,
            'next_cursor' => $cursor,
        ];
    }

    /**
     * @param mixed $value
     */
    private function normalize_mandate_uuid($value): ?string {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = strtolower(trim($value));
        if ($trimmed === '' || preg_match(self::DELETE_MANDATE_UUID_RE, $trimmed) !== 1) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param mixed $value
     */
    private function normalize_mandate_sha($value): ?string {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = strtolower(trim($value));
        if (preg_match(self::DELETE_MANDATE_SHA_RE, $trimmed) !== 1) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param mixed $value
     */
    private function mandate_int($value): ?int {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value && $value <= PHP_INT_MAX && $value >= PHP_INT_MIN) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function mandate_non_neg_int($value): ?int {
        $n = $this->mandate_int($value);
        if ($n === null || $n < 0) {
            return null;
        }

        return $n;
    }

    /**
     * @param mixed $value
     */
    private function mandate_positive_int($value): ?int {
        $n = $this->mandate_int($value);
        if ($n === null || $n < 1) {
            return null;
        }

        return $n;
    }

    private function mandate_failure_class(string $code): string {
        return self::DELETE_MANDATE_FAILURE_CLASS[$code] ?? 'unknown';
    }

    /**
     * @param array<string,mixed> $result
     * @return array{ok:true,outcome:string,retire_authorized:bool,result:array<string,mixed>}
     */
    private function mandate_success(string $outcome, bool $retire_authorized, array $result): array {
        return [
            'ok' => true,
            'outcome' => $outcome,
            'retire_authorized' => $retire_authorized,
            'result' => $result,
        ];
    }

    /**
     * @return array{
     *   ok:false,
     *   code:string,
     *   error:string,
     *   http_status:int,
     *   failure_class:string,
     *   retire_authorized:false
     * }
     */
    private function mandate_failure(string $code, int $http_status, string $failure_class): array {
        return [
            'ok' => false,
            'code' => $code,
            'error' => '',
            'http_status' => $http_status,
            'failure_class' => $failure_class,
            'retire_authorized' => false,
        ];
    }
}
