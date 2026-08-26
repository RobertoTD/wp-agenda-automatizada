<?php
/**
 * Delete Expediente Use Case — eliminación canónica del contenedor (Ciclo B).
 *
 * Orden: preflight → Storage+metadata por adjunto → TX corta (registros+padre).
 * Padre siempre al final. Progreso parcial reintentable. Sin UI.
 *
 * Scope lock (Ciclo A):
 * - relacionado → client:{client_id}
 * - general → expediente:{expediente_id}
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('ExpedienteAdjuntoVariants')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoVariants.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}
if (!class_exists('ExpedienteRegistrosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteRegistrosRepository.php';
}
if (!class_exists('ExpedienteAdjuntosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteAdjuntosRepository.php';
}
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}
if (!class_exists('AA_Expediente_Aggregate_Lock')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
}

final class DeleteExpedienteUseCase {

    private const PAGE_SIZE = 100;

    /** @var object */
    private $backend;

    /** @var AA_Expediente_Aggregate_Lock */
    private $lock;

    /**
     * @param object|null $backend AA_Expediente_Attachments_Backend_Client o doble
     * @param AA_Expediente_Aggregate_Lock|null $lock
     */
    public function __construct($backend = null, ?AA_Expediente_Aggregate_Lock $lock = null) {
        $this->backend = $backend ?: new AA_Expediente_Attachments_Backend_Client();
        $this->lock = $lock ?: AA_Expediente_Aggregate_Lock::create_default();
    }

    /**
     * @param array{expediente_id?:mixed} $input
     * @return array{success:true,data:array{deleted:true,expediente_id:int}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $expediente_id = AA_Expediente_Id_Policy::normalize($input['expediente_id'] ?? null);
        if ($expediente_id === null) {
            return $this->fail('invalid_id', 'Expediente no válido.');
        }

        $preliminary = ExpedientesRepository::find_delete_context_by_id($expediente_id);
        if (is_wp_error($preliminary)) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }
        if ($preliminary === false) {
            return $this->fail('not_found', 'Expediente no encontrado.');
        }

        $identity = $this->normalize_parent_identity($preliminary);
        if ($identity === null) {
            error_log('[DeleteExpedienteUseCase] aggregate inconsistent parent');
            return $this->fail('aggregate_inconsistent', 'El expediente no es coherente.');
        }

        $scope_kind = $identity['client_id'] === null
            ? AA_Expediente_Aggregate_Lock::SCOPE_EXPEDIENTE
            : AA_Expediente_Aggregate_Lock::SCOPE_CLIENT;
        $scope_id = $identity['client_id'] === null
            ? $expediente_id
            : $identity['client_id'];

        $lease = $this->lock->acquire(
            $scope_kind,
            $scope_id,
            AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
        );
        if (is_wp_error($lease)) {
            return $this->fail_from_lock($lease);
        }

        try {
            $after = ExpedientesRepository::find_delete_context_by_id($expediente_id);
            if (is_wp_error($after)) {
                return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
            }
            if ($after === false) {
                return $this->fail('not_found', 'Expediente no encontrado.');
            }

            $after_identity = $this->normalize_parent_identity($after);
            if ($after_identity === null) {
                error_log('[DeleteExpedienteUseCase] aggregate inconsistent parent after lock');
                return $this->fail('aggregate_inconsistent', 'El expediente no es coherente.');
            }

            if (!$this->same_parent_identity($identity, $after_identity)) {
                return $this->fail(
                    'concurrent_change',
                    'El expediente cambió mientras se preparaba la operación.'
                );
            }

            $records_map = $this->collect_records_map($expediente_id, $identity['client_id']);
            if (is_wp_error($records_map)) {
                return $this->fail_from_wp_error($records_map);
            }

            $preflight = $this->preflight_attachments($expediente_id, $identity['client_id'], $records_map);
            if ($preflight !== null) {
                return $preflight;
            }

            if ($identity['client_id'] !== null) {
                $cleaned = $this->delete_attachments_pass(
                    $expediente_id,
                    $identity['client_id'],
                    $records_map,
                    $lease
                );
                if ($cleaned !== null) {
                    return $cleaned;
                }
            }

            $held = $this->lock->assert_held($lease);
            if (is_wp_error($held)) {
                return $this->fail_from_lock($held);
            }

            $remaining = ExpedienteAdjuntosRepository::has_any_joined_by_expediente_id($expediente_id);
            if (is_wp_error($remaining)) {
                return $this->fail('lookup_failed', 'No se pudo verificar adjuntos.');
            }
            if ($remaining === true) {
                return $this->fail(
                    'concurrent_change',
                    'El expediente cambió mientras se preparaba la operación.'
                );
            }

            return $this->finalize_sql_transaction($expediente_id, $identity, $records_map);
        } finally {
            $this->lock->release($lease);
        }
    }

    /**
     * @param array{id:mixed,category_id:mixed,client_id:mixed} $row
     * @return array{id:int,category_id:int,client_id:?int}|null
     */
    private function normalize_parent_identity(array $row): ?array {
        $id = AA_Expediente_Id_Policy::normalize($row['id'] ?? null);
        $category_id = AA_Expediente_Id_Policy::normalize($row['category_id'] ?? null);
        if ($id === null || $category_id === null) {
            return null;
        }

        $client = $this->normalize_stored_client_id($row['client_id'] ?? null);
        if (!$client['ok']) {
            return null;
        }

        return [
            'id' => $id,
            'category_id' => $category_id,
            'client_id' => $client['id'],
        ];
    }

    /**
     * @param array{id:int,category_id:int,client_id:?int} $a
     * @param array{id:int,category_id:int,client_id:?int} $b
     */
    private function same_parent_identity(array $a, array $b): bool {
        return $a['id'] === $b['id']
            && $a['category_id'] === $b['category_id']
            && $a['client_id'] === $b['client_id'];
    }

    /**
     * @param mixed $raw
     * @return array{ok:true,id:?int}|array{ok:false}
     */
    private function normalize_stored_client_id($raw): array {
        if ($raw === null) {
            return ['ok' => true, 'id' => null];
        }

        if (is_int($raw)) {
            if ($raw < 1) {
                return ['ok' => false];
            }

            return ['ok' => true, 'id' => $raw];
        }

        if (is_string($raw)) {
            if ($raw === '') {
                return ['ok' => false];
            }
            $normalized = AA_Expediente_Id_Policy::normalize($raw);
            if ($normalized === null) {
                return ['ok' => false];
            }

            return ['ok' => true, 'id' => $normalized];
        }

        return ['ok' => false];
    }

    /**
     * @return array<int,array{id:int,expediente_id:int,client_id:?int}>|WP_Error
     */
    private function collect_records_map(int $expediente_id, $expected_client_id) {
        $map = [];
        $after_id = 0;

        while (true) {
            $page = ExpedienteRegistrosRepository::list_identity_page_by_expediente_id(
                $expediente_id,
                $after_id,
                self::PAGE_SIZE
            );
            if (is_wp_error($page)) {
                return $page;
            }

            if ($page === []) {
                break;
            }

            foreach ($page as $row) {
                $validated = $this->validate_record_row($row, $expediente_id, $expected_client_id);
                if ($validated === null) {
                    error_log('[DeleteExpedienteUseCase] aggregate inconsistent record');
                    return new WP_Error(
                        'aggregate_inconsistent',
                        'El expediente no es coherente.'
                    );
                }
                $map[$validated['id']] = $validated;
                $after_id = $validated['id'];
            }

            if (count($page) < self::PAGE_SIZE) {
                break;
            }
        }

        return $map;
    }

    /**
     * @param array{id:int,expediente_id:int,client_id:mixed} $row
     * @return array{id:int,expediente_id:int,client_id:?int}|null
     */
    private function validate_record_row(array $row, int $expediente_id, $expected_client_id): ?array {
        $id = AA_Expediente_Id_Policy::normalize($row['id'] ?? null);
        $row_exp = AA_Expediente_Id_Policy::normalize($row['expediente_id'] ?? null);
        if ($id === null || $row_exp === null || $row_exp !== $expediente_id) {
            return null;
        }

        $client = $this->normalize_stored_client_id($row['client_id'] ?? null);
        if (!$client['ok']) {
            return null;
        }

        if ($expected_client_id === null) {
            if ($client['id'] !== null) {
                return null;
            }
        } elseif ($client['id'] !== $expected_client_id) {
            return null;
        }

        return [
            'id' => $id,
            'expediente_id' => $row_exp,
            'client_id' => $client['id'],
        ];
    }

    /**
     * @param array<int,array{id:int,expediente_id:int,client_id:?int}> $records_map
     * @return array{success:false,error:array{code:string,message:string}}|null null = OK
     */
    private function preflight_attachments(int $expediente_id, $expected_client_id, array $records_map) {
        $after_id = 0;

        while (true) {
            $page = ExpedienteAdjuntosRepository::list_joined_page_by_expediente_id(
                $expediente_id,
                $after_id,
                self::PAGE_SIZE
            );
            if (is_wp_error($page)) {
                return $this->fail('lookup_failed', 'No se pudo verificar adjuntos.');
            }

            if ($page === []) {
                break;
            }

            foreach ($page as $row) {
                if ($expected_client_id === null) {
                    error_log('[DeleteExpedienteUseCase] general with attachments');
                    return $this->fail('aggregate_inconsistent', 'El expediente no es coherente.');
                }

                $check = $this->validate_attachment_row($row, $expediente_id, $expected_client_id, $records_map);
                if ($check !== null) {
                    return $check;
                }

                $after_id = (int) ($row['id'] ?? 0);
            }

            if (count($page) < self::PAGE_SIZE) {
                break;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,array{id:int,expediente_id:int,client_id:?int}> $records_map
     * @return array{success:false,error:array{code:string,message:string}}|null
     */
    private function validate_attachment_row(
        array $row,
        int $expediente_id,
        int $expected_client_id,
        array $records_map
    ) {
        $attachment_id = AA_Expediente_Id_Policy::normalize($row['id'] ?? null);
        $record_id = AA_Expediente_Id_Policy::normalize($row['record_id'] ?? null);
        if ($attachment_id === null || $record_id === null) {
            error_log('[DeleteExpedienteUseCase] aggregate inconsistent attachment ids');
            return $this->fail('aggregate_inconsistent', 'El expediente no es coherente.');
        }

        if (!isset($records_map[$record_id])) {
            error_log('[DeleteExpedienteUseCase] attachment record not in map');
            return $this->fail('aggregate_inconsistent', 'El expediente no es coherente.');
        }

        $att_client = $this->normalize_stored_client_id($row['client_id'] ?? null);
        $rec_client = $this->normalize_stored_client_id($row['record_client_id'] ?? null);
        $rec_exp = AA_Expediente_Id_Policy::normalize($row['record_expediente_id'] ?? null);

        if (
            !$att_client['ok']
            || !$rec_client['ok']
            || $att_client['id'] !== $expected_client_id
            || $rec_client['id'] !== $expected_client_id
            || $rec_exp !== $expediente_id
        ) {
            error_log('[DeleteExpedienteUseCase] attachment owner mismatch');
            return $this->fail('aggregate_inconsistent', 'El expediente no es coherente.');
        }

        $operation_id = strtolower(trim((string) ($row['upload_operation_id'] ?? '')));
        $storage_path = (string) ($row['storage_path'] ?? '');
        $parsed = ExpedienteAdjuntoVariants::parse_original_path($storage_path);
        if ($parsed === null) {
            error_log('[DeleteExpedienteUseCase] invalid storage path');
            return $this->fail('aggregate_inconsistent', 'El expediente no es coherente.');
        }

        if (
            (int) $parsed['wp_client_id'] !== $expected_client_id
            || (int) $parsed['wp_record_id'] !== $record_id
            || strtolower((string) $parsed['upload_operation_id']) !== $operation_id
        ) {
            error_log('[DeleteExpedienteUseCase] storage path mismatch');
            return $this->fail('aggregate_inconsistent', 'El expediente no es coherente.');
        }

        return null;
    }

    /**
     * @param array<int,array{id:int,expediente_id:int,client_id:?int}> $records_map
     * @param AA_Expediente_Aggregate_Lock_Lease $lease
     * @return array{success:false,error:array{code:string,message:string}}|null null = OK
     */
    private function delete_attachments_pass(
        int $expediente_id,
        int $expected_client_id,
        array $records_map,
        $lease
    ) {
        $after_id = 0;

        while (true) {
            $page = ExpedienteAdjuntosRepository::list_joined_page_by_expediente_id(
                $expediente_id,
                $after_id,
                self::PAGE_SIZE
            );
            if (is_wp_error($page)) {
                return $this->fail('lookup_failed', 'No se pudo verificar adjuntos.');
            }

            if ($page === []) {
                break;
            }

            foreach ($page as $row) {
                $check = $this->validate_attachment_row(
                    $row,
                    $expediente_id,
                    $expected_client_id,
                    $records_map
                );
                if ($check !== null) {
                    return $check;
                }

                $storage_path = (string) ($row['storage_path'] ?? '');
                $deleted = $this->backend->delete_object($storage_path);
                if (empty($deleted['ok'])) {
                    $code = (string) ($deleted['code'] ?? 'storage_delete_failed');
                    if ($code === '') {
                        $code = 'storage_delete_failed';
                    }

                    return $this->fail($code, 'No se pudo eliminar el archivo remoto.');
                }

                $status = (string) ($deleted['result']['status'] ?? '');
                if ($status !== 'deleted' && $status !== 'already_absent') {
                    return $this->fail(
                        'expediente_attachments_invalid_response',
                        'Respuesta remota no válida.'
                    );
                }

                $held = $this->lock->assert_held($lease);
                if (is_wp_error($held)) {
                    return $this->fail_from_lock($held);
                }

                $att_client = $this->normalize_stored_client_id($row['client_id'] ?? null);
                if (!$att_client['ok'] || $att_client['id'] === null) {
                    error_log('[DeleteExpedienteUseCase] attachment client lost before meta delete');
                    return $this->fail('aggregate_inconsistent', 'El expediente no es coherente.');
                }

                $meta = ExpedienteAdjuntosRepository::delete_by_exact_identity([
                    'id' => (int) $row['id'],
                    'record_id' => (int) $row['record_id'],
                    'client_id' => $att_client['id'],
                    'upload_operation_id' => (string) ($row['upload_operation_id'] ?? ''),
                    'storage_path' => $storage_path,
                ]);

                if (is_wp_error($meta)) {
                    return $this->fail('persistence_failed', 'No se pudo eliminar la metadata.');
                }
                if ($meta !== true) {
                    return $this->fail(
                        'concurrent_change',
                        'El expediente cambió mientras se preparaba la operación.'
                    );
                }

                // Tras borrar, el cursor no avanza por id eliminado: reenumerar
                // desde after_id actual (ids > after_id). El borrado es del
                // primer id de la página; next page starts from same after_id.
            }

            // Reenumerar desde el mismo cursor: filas borradas ya no aparecen.
            // Si la página estaba llena y todas se borraron, after_id no cambia
            // y la siguiente list empieza igual — correcto.
            // Si quedan filas con id > after_id, aparecen en la siguiente.
            // Avanzar after_id al último id visto en la página original evita
            // re-procesar: pero esas filas ya fueron borradas. Usar el máximo
            // id de la página leída como nuevo after_id.
            $last = end($page);
            $after_id = is_array($last) ? (int) ($last['id'] ?? $after_id) : $after_id;

            if (count($page) < self::PAGE_SIZE) {
                break;
            }
        }

        return null;
    }

    /**
     * @param array{id:int,category_id:int,client_id:?int} $identity
     * @param array<int,array{id:int,expediente_id:int,client_id:?int}> $records_map
     * @return array{success:true,data:array{deleted:true,expediente_id:int}}|array{success:false,error:array{code:string,message:string}}
     */
    private function finalize_sql_transaction(int $expediente_id, array $identity, array $records_map): array {
        global $wpdb;

        $started = $wpdb->query('START TRANSACTION');
        if ($started === false) {
            return $this->fail('persistence_failed', 'No se pudo iniciar la transacción.');
        }

        try {
            $locked_parent = ExpedientesRepository::find_delete_context_by_id_for_update($expediente_id);
            if (is_wp_error($locked_parent)) {
                $this->rollback_quietly();
                return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
            }
            if ($locked_parent === false) {
                $this->rollback_quietly();
                return $this->fail('not_found', 'Expediente no encontrado.');
            }

            $locked_identity = $this->normalize_parent_identity($locked_parent);
            if ($locked_identity === null || !$this->same_parent_identity($identity, $locked_identity)) {
                $this->rollback_quietly();
                return $this->fail(
                    'concurrent_change',
                    'El expediente cambió mientras se preparaba la operación.'
                );
            }

            $locked_records = ExpedienteRegistrosRepository::list_identity_for_update_by_expediente_id(
                $expediente_id
            );
            if (is_wp_error($locked_records)) {
                $this->rollback_quietly();
                return $this->fail('lookup_failed', 'No se pudo verificar los registros.');
            }

            if (!$this->same_records_identity_set($records_map, $locked_records, $expediente_id, $identity['client_id'])) {
                $this->rollback_quietly();
                return $this->fail(
                    'concurrent_change',
                    'El expediente cambió mientras se preparaba la operación.'
                );
            }

            $remaining = ExpedienteAdjuntosRepository::has_any_joined_by_expediente_id($expediente_id);
            if (is_wp_error($remaining)) {
                $this->rollback_quietly();
                return $this->fail('lookup_failed', 'No se pudo verificar adjuntos.');
            }
            if ($remaining === true) {
                $this->rollback_quietly();
                return $this->fail(
                    'concurrent_change',
                    'El expediente cambió mientras se preparaba la operación.'
                );
            }

            $expected_count = count($records_map);
            $deleted_records = ExpedienteRegistrosRepository::delete_all_by_expediente_id($expediente_id);
            if (is_wp_error($deleted_records)) {
                $this->rollback_quietly();
                return $this->fail('persistence_failed', 'No se pudieron eliminar los registros.');
            }
            if ((int) $deleted_records !== $expected_count) {
                $this->rollback_quietly();
                return $this->fail(
                    'concurrent_change',
                    'El expediente cambió mientras se preparaba la operación.'
                );
            }

            $deleted_parent = ExpedientesRepository::delete_by_expected_identity(
                $identity['id'],
                $identity['category_id'],
                $identity['client_id']
            );
            if (is_wp_error($deleted_parent)) {
                $this->rollback_quietly();
                return $this->fail('persistence_failed', 'No se pudo eliminar el expediente.');
            }
            if ($deleted_parent !== true) {
                $this->rollback_quietly();
                return $this->fail(
                    'concurrent_change',
                    'El expediente cambió mientras se preparaba la operación.'
                );
            }

            $commit = $wpdb->query('COMMIT');
            if ($commit === false) {
                $this->rollback_quietly();
                return $this->fail('persistence_failed', 'No se pudo confirmar la eliminación.');
            }

            return $this->ok([
                'deleted' => true,
                'expediente_id' => $expediente_id,
            ]);
        } catch (Throwable $e) {
            $this->rollback_quietly();
            error_log('[DeleteExpedienteUseCase] transaction exception');
            return $this->fail('persistence_failed', 'No se pudo eliminar el expediente.');
        }
    }

    /**
     * @param array<int,array{id:int,expediente_id:int,client_id:?int}> $expected
     * @param list<array{id:int,expediente_id:int,client_id:mixed}> $locked
     */
    private function same_records_identity_set(
        array $expected,
        array $locked,
        int $expediente_id,
        $expected_client_id
    ): bool {
        if (count($expected) !== count($locked)) {
            return false;
        }

        $locked_map = [];
        foreach ($locked as $row) {
            $validated = $this->validate_record_row($row, $expediente_id, $expected_client_id);
            if ($validated === null) {
                return false;
            }
            $locked_map[$validated['id']] = $validated;
        }

        if (count($locked_map) !== count($expected)) {
            return false;
        }

        foreach ($expected as $id => $row) {
            if (!isset($locked_map[$id])) {
                return false;
            }
            $other = $locked_map[$id];
            if (
                $other['expediente_id'] !== $row['expediente_id']
                || $other['client_id'] !== $row['client_id']
            ) {
                return false;
            }
        }

        return true;
    }

    private function rollback_quietly(): void {
        global $wpdb;
        $wpdb->query('ROLLBACK');
    }

    /**
     * @param WP_Error $error
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function fail_from_wp_error($error): array {
        $code = (string) $error->get_error_code();
        $message = (string) $error->get_error_message();
        if ($code === 'db_error' || $code === '') {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }
        if ($message === '') {
            $message = 'No se pudo completar la operación.';
        }

        return $this->fail($code, $message);
    }

    /**
     * @param WP_Error $error
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function fail_from_lock($error): array {
        $code = (string) $error->get_error_code();
        $message = (string) $error->get_error_message();
        if ($code === '') {
            $code = AA_Expediente_Aggregate_Lock::ERROR_COORDINATION_FAILED;
        }
        if ($message === '') {
            $message = 'No se pudo coordinar la operación.';
        }

        return $this->fail($code, $message);
    }

    /**
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function fail(string $code, string $message): array {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param array{deleted:true,expediente_id:int} $data
     * @return array{success:true,data:array{deleted:true,expediente_id:int}}
     */
    private function ok(array $data): array {
        return [
            'success' => true,
            'data' => $data,
        ];
    }
}
