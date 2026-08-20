<?php
/**
 * Create Expediente Registro For Client — materializa padre + hijo bridged.
 *
 * Transacción MySQL: get-or-create padre (UNIQUE client_id) + insert hijo
 * con client_id + expediente_id. Imagen fuera de alcance (post-commit legacy).
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Registro_Create_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-registro-create-policy.php';
}
if (!class_exists('AA_Expediente_Create_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-create-policy.php';
}
if (!class_exists('ClientsRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';
}
if (!class_exists('ExpedienteCategoriesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteCategoriesRepository.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}
if (!class_exists('ExpedienteRegistrosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteRegistrosRepository.php';
}

final class CreateExpedienteRegistroForClientUseCase {

    public const CLIENTES_SLUG = 'clientes';

    private const MAX_TX_ATTEMPTS = 3;

    /** @var AA_Expediente_Registro_Create_Policy */
    private $registro_policy;

    /** @var AA_Expediente_Create_Policy */
    private $expediente_policy;

    public function __construct(
        ?AA_Expediente_Registro_Create_Policy $registro_policy = null,
        ?AA_Expediente_Create_Policy $expediente_policy = null
    ) {
        $this->registro_policy = $registro_policy ?: new AA_Expediente_Registro_Create_Policy();
        $this->expediente_policy = $expediente_policy ?: new AA_Expediente_Create_Policy();
    }

    /**
     * @param array{client_id?:mixed,title?:mixed,body?:mixed} $input
     * @return array{
     *   success:true,
     *   data:array{record:array<string,mixed>,expediente_id:int}
     * }|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $client_id = isset($input['client_id']) ? (int) $input['client_id'] : 0;
        if ($client_id < 1) {
            return $this->fail('invalid_client', 'Cliente no válido.');
        }

        $title = $this->registro_policy->normalize_title($input['title'] ?? null);
        if ($title === null) {
            return $this->fail('missing_title', 'El título es obligatorio.');
        }
        if ($this->registro_policy->title_exceeds_max($title)) {
            return $this->fail('title_too_long', 'El título es demasiado largo.');
        }

        $body = $this->registro_policy->normalize_body($input['body'] ?? null);
        if ($body === null) {
            return $this->fail('missing_body', 'El texto es obligatorio.');
        }
        if ($this->registro_policy->body_exceeds_max($body)) {
            return $this->fail('body_too_long', 'El texto es demasiado largo.');
        }

        $client = ClientsRepository::find_by_id($client_id);
        if ($client === null) {
            return $this->fail('not_found', 'Cliente no encontrado.');
        }

        $parent_title = $this->normalize_parent_title_from_client(
            (string) ($client['nombre'] ?? ''),
            $client_id
        );

        $category = ExpedienteCategoriesRepository::find_by_slug(self::CLIENTES_SLUG);
        if ($category === null) {
            return $this->fail('category_not_found', 'La categoría Clientes no está disponible.');
        }

        $now = current_time('mysql');
        $category_id = (int) $category['id'];

        $last_error = null;
        for ($attempt = 1; $attempt <= self::MAX_TX_ATTEMPTS; $attempt++) {
            $result = $this->run_transaction(
                $client_id,
                $parent_title,
                $category_id,
                $title,
                $body,
                $now
            );

            if (!empty($result['success'])) {
                return $result;
            }

            $code = (string) ($result['error']['code'] ?? '');
            if ($code === 'tx_retryable' && $attempt < self::MAX_TX_ATTEMPTS) {
                $last_error = $result;
                continue;
            }

            return $result;
        }

        return is_array($last_error)
            ? $last_error
            : $this->fail('persistence_failed', 'No se pudo guardar el registro.');
    }

    /**
     * @return array{success:true,data:array{record:array<string,mixed>,expediente_id:int}}|array{success:false,error:array{code:string,message:string}}
     */
    private function run_transaction(
        int $client_id,
        string $parent_title,
        int $category_id,
        string $title,
        string $body,
        string $now
    ): array {
        global $wpdb;

        $started = $wpdb->query('START TRANSACTION');
        if ($started === false) {
            return $this->fail('persistence_failed', 'No se pudo iniciar la transacción.');
        }

        $committed = false;

        try {
            $expediente_id = ExpedientesRepository::get_or_create_for_client(
                $client_id,
                $parent_title,
                $category_id,
                $now
            );

            if (is_wp_error($expediente_id)) {
                $this->rollback_quietly();
                if ($this->is_retryable_db_error((string) $wpdb->last_error)) {
                    return $this->fail('tx_retryable', 'Conflicto transitorio al resolver el expediente.');
                }

                return $this->fail('persistence_failed', $expediente_id->get_error_message());
            }

            $expediente_id = (int) $expediente_id;
            if ($expediente_id < 1) {
                $this->rollback_quietly();

                return $this->fail('persistence_failed', 'No se pudo resolver el expediente del cliente.');
            }

            $record = ExpedienteRegistrosRepository::insert_for_client_expediente([
                'client_id' => $client_id,
                'expediente_id' => $expediente_id,
                'title' => $title,
                'body' => $body,
                'recorded_at' => $now,
                'created_at' => $now,
            ]);

            if (is_wp_error($record)) {
                $this->rollback_quietly();
                if ($this->is_retryable_db_error((string) $wpdb->last_error)) {
                    return $this->fail('tx_retryable', 'Conflicto transitorio al guardar el registro.');
                }

                return $this->fail('persistence_failed', $record->get_error_message());
            }

            $commit = $wpdb->query('COMMIT');
            if ($commit === false) {
                $this->rollback_quietly();

                return $this->fail('persistence_failed', 'No se pudo confirmar el registro.');
            }

            $committed = true;

            return [
                'success' => true,
                'data' => [
                    'record' => $record,
                    'expediente_id' => $expediente_id,
                ],
            ];
        } catch (Throwable $e) {
            if (!$committed) {
                $this->rollback_quietly();
            }

            error_log('[CreateExpedienteRegistroForClientUseCase] ' . $e->getMessage());

            return $this->fail('persistence_failed', 'No se pudo guardar el registro.');
        }
    }

    private function rollback_quietly(): void {
        global $wpdb;
        $wpdb->query('ROLLBACK');
    }

    private function is_retryable_db_error(string $error): bool {
        if ($error === '') {
            return false;
        }

        $haystack = strtolower($error);

        return strpos($haystack, 'deadlock') !== false
            || strpos($haystack, 'lock wait timeout') !== false;
    }

    /**
     * Snapshot del nombre del cliente para el título del padre (≤200 mb-safe).
     */
    public function normalize_parent_title_from_client(string $nombre, int $client_id): string {
        $title = $this->expediente_policy->normalize_title($nombre);
        if ($title === null) {
            $title = 'Cliente #' . $client_id;
        }

        $max = AA_Expediente_Create_Policy::TITLE_MAX_LENGTH;
        if ($this->length($title) > $max) {
            if (function_exists('mb_substr')) {
                $title = mb_substr($title, 0, $max);
            } else {
                $title = substr($title, 0, $max);
            }
            $title = rtrim((string) $title);
            if ($title === '') {
                $title = 'Cliente #' . $client_id;
            }
        }

        return $title;
    }

    private function length(string $value): int {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
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
}
