<?php
/**
 * AC — DeleteExpedienteUseCase (Ciclo B).
 *
 * Ejecutar: php tests/application/expediente/test-delete-expediente-use-case-ac.php
 */

$plugin_root = dirname(__DIR__, 3);

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }
    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/tests/support/aa-test-expediente-aggregate-lock-passthrough.php';
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';

$IID = '11111111-2222-4333-8444-555555555555';
$OP_A = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
$OP_B = 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee';
$OP_G = 'cccccccc-bbbb-4ccc-8ddd-eeeeeeeeeeee';
$PATH_A = ExpedienteAdjuntoVariants::build_client_original_path($IID, 7, 11, $OP_A);
$PATH_B = ExpedienteAdjuntoVariants::build_client_original_path($IID, 7, 12, $OP_B);
$PATH_G_V2 = ExpedienteAdjuntoVariants::build_expediente_record_original_path($IID, 11, 11, $OP_G);

final class ExpedientesRepository {
    /** @var array<int,array{id:int,category_id:int,client_id:?int}> */
    public static $parents = [];
    public static $sql_error = false;
    public static $deleted = [];
    public static $for_update_calls = 0;
    public static $tx_parent_flip = null;

    public static function find_delete_context_by_id(int $id) {
        if (self::$sql_error) {
            return new WP_Error('db_error', 'sql');
        }
        if (!isset(self::$parents[$id])) {
            return false;
        }
        $p = self::$parents[$id];
        return ['id' => $p['id'], 'category_id' => $p['category_id'], 'client_id' => $p['client_id']];
    }

    public static function find_delete_context_by_id_for_update(int $id) {
        self::$for_update_calls++;
        if (is_callable(self::$tx_parent_flip)) {
            (self::$tx_parent_flip)();
        }
        return self::find_delete_context_by_id($id);
    }

    public static function delete_by_expected_identity(int $id, int $category_id, $client_id) {
        if (!isset(self::$parents[$id])) {
            return false;
        }
        $p = self::$parents[$id];
        if ((int) $p['category_id'] !== $category_id || $p['client_id'] !== $client_id) {
            return false;
        }
        self::$deleted[] = $id;
        unset(self::$parents[$id]);
        return true;
    }
}

final class ExpedienteRegistrosRepository {
    /** @var array<int,array{id:int,expediente_id:int,client_id:?int}> */
    public static $rows = [];
    public static $sql_error = false;
    public static $deleted_by_exp = [];
    public static $tx_rows_flip = null;

    public static function list_identity_page_by_expediente_id(int $expediente_id, int $after_id, int $limit = 100) {
        if (self::$sql_error) {
            return new WP_Error('db_error', 'sql');
        }
        $out = [];
        foreach (self::$rows as $row) {
            if ((int) $row['expediente_id'] === $expediente_id && (int) $row['id'] > $after_id) {
                $out[] = $row;
            }
        }
        usort($out, static function ($a, $b) {
            return $a['id'] <=> $b['id'];
        });
        return array_slice($out, 0, $limit);
    }

    public static function list_identity_for_update_by_expediente_id(int $expediente_id) {
        if (is_callable(self::$tx_rows_flip)) {
            (self::$tx_rows_flip)();
        }
        return self::list_identity_page_by_expediente_id($expediente_id, 0, 100000);
    }

    public static function delete_all_by_expediente_id(int $expediente_id) {
        $n = 0;
        foreach (self::$rows as $id => $row) {
            if ((int) $row['expediente_id'] === $expediente_id) {
                unset(self::$rows[$id]);
                $n++;
            }
        }
        self::$deleted_by_exp[] = ['expediente_id' => $expediente_id, 'n' => $n];
        return $n;
    }
}

final class ExpedienteAdjuntosRepository {
    /** @var array<int,array<string,mixed>> */
    public static $rows = [];
    public static $sql_error = false;
    public static $deleted = [];

    public static function list_joined_page_by_expediente_id(int $expediente_id, int $after_id, int $limit = 100) {
        if (self::$sql_error) {
            return new WP_Error('db_error', 'sql');
        }
        $out = [];
        foreach (self::$rows as $row) {
            if ((int) ($row['record_expediente_id'] ?? 0) === $expediente_id && (int) $row['id'] > $after_id) {
                $out[] = $row;
            }
        }
        usort($out, static function ($a, $b) {
            return $a['id'] <=> $b['id'];
        });
        return array_slice($out, 0, $limit);
    }

    public static function has_any_joined_by_expediente_id(int $expediente_id) {
        if (self::$sql_error) {
            return new WP_Error('db_error', 'sql');
        }
        foreach (self::$rows as $row) {
            if ((int) ($row['record_expediente_id'] ?? 0) === $expediente_id) {
                return true;
            }
        }
        return false;
    }

    public static function delete_by_exact_identity(array $identity) {
        $id = (int) ($identity['id'] ?? 0);
        if (!isset(self::$rows[$id])) {
            return false;
        }
        $row = self::$rows[$id];
        if (
            (int) $row['record_id'] !== (int) $identity['record_id']
            || (int) $row['client_id'] !== (int) $identity['client_id']
            || (string) $row['upload_operation_id'] !== (string) $identity['upload_operation_id']
            || (string) $row['storage_path'] !== (string) $identity['storage_path']
        ) {
            return false;
        }
        self::$deleted[] = $id;
        unset(self::$rows[$id]);
        return true;
    }
}

final class FakeBackend {
    public $calls = [];
    /** @var list<array>|callable|null */
    public $responses = null;

    public function delete_object(string $storage_path): array {
        $this->calls[] = $storage_path;
        if (is_callable($this->responses)) {
            return ($this->responses)($storage_path, count($this->calls));
        }
        if (is_array($this->responses) && isset($this->responses[count($this->calls) - 1])) {
            return $this->responses[count($this->calls) - 1];
        }
        return ['ok' => true, 'result' => ['status' => 'deleted']];
    }
}

/** @var object $wpdb stub for TX */
$wpdb = new class {
    public $last_error = '';
    public $queries = [];
    public $fail_start = false;
    public $fail_commit = false;

    public function query($sql) {
        $this->queries[] = $sql;
        if ($this->fail_start && strpos((string) $sql, 'START TRANSACTION') !== false) {
            return false;
        }
        if ($this->fail_commit && strpos((string) $sql, 'COMMIT') !== false) {
            return false;
        }
        return 1;
    }
};
$GLOBALS['wpdb'] = $wpdb;

$lock = aa_test_install_passthrough_expediente_lock();
require_once $plugin_root . '/includes/application/expediente/DeleteExpedienteUseCase.php';

function aa_reset_delete_exp_state(): void {
    global $wpdb;
    ExpedientesRepository::$parents = [];
    ExpedientesRepository::$sql_error = false;
    ExpedientesRepository::$deleted = [];
    ExpedientesRepository::$for_update_calls = 0;
    ExpedientesRepository::$tx_parent_flip = null;
    ExpedienteRegistrosRepository::$rows = [];
    ExpedienteRegistrosRepository::$sql_error = false;
    ExpedienteRegistrosRepository::$deleted_by_exp = [];
    ExpedienteRegistrosRepository::$tx_rows_flip = null;
    ExpedienteAdjuntosRepository::$rows = [];
    ExpedienteAdjuntosRepository::$sql_error = false;
    ExpedienteAdjuntosRepository::$deleted = [];
    $wpdb->queries = [];
    $wpdb->fail_start = false;
    $wpdb->fail_commit = false;
    $wpdb->last_error = '';
}

// --- Source contracts ---
$src = file_get_contents($plugin_root . '/includes/application/expediente/DeleteExpedienteUseCase.php');
ac_assert('padre al final (delete_by_expected_identity tras delete_all)',
    strpos($src, 'delete_all_by_expediente_id') < strpos($src, 'delete_by_expected_identity'));
ac_assert('Storage antes de metadata',
    strpos($src, 'delete_object') < strpos($src, 'delete_by_exact_identity'));
ac_assert('assert_held tras Storage',
    strpos($src, 'delete_object') < strrpos($src, 'assert_held'));
ac_assert('sin absint', strpos($src, 'absint(') === false);
ac_assert('usa Identity Policy', strpos($src, 'AA_Expediente_Adjunto_Identity_Policy::validate') !== false);
ac_assert('release en finally', strpos($src, 'lock->release') !== false && strpos($src, 'finally') !== false);
ac_assert('START TRANSACTION solo en finalize', substr_count($src, 'START TRANSACTION') === 1);

// --- General vacío ---
aa_reset_delete_exp_state();
$lock->acquire_calls = [];
$lock->release_calls = 0;
ExpedientesRepository::$parents[11] = ['id' => 11, 'category_id' => 2, 'client_id' => null];
$uc = new DeleteExpedienteUseCase(new FakeBackend(), $lock);
$out = $uc->execute(['expediente_id' => 11]);
ac_assert('general vacío ok', !empty($out['success']) && !empty($out['data']['deleted']));
ac_assert('general vacío envelope', ($out['data']['expediente_id'] ?? 0) === 11);
ac_assert('general scope expediente', ($lock->acquire_calls[0]['scope_kind'] ?? '') === 'expediente'
    && (int) ($lock->acquire_calls[0]['scope_id'] ?? 0) === 11);
ac_assert('general padre eliminado', !isset(ExpedientesRepository::$parents[11]));
ac_assert('release finally', $lock->release_calls === 1);
ac_assert('FOR UPDATE en TX', ExpedientesRepository::$for_update_calls === 1);

// --- General con registros sin adjuntos ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[11] = ['id' => 11, 'category_id' => 2, 'client_id' => null];
ExpedienteRegistrosRepository::$rows = [
    21 => ['id' => 21, 'expediente_id' => 11, 'client_id' => null],
    22 => ['id' => 22, 'expediente_id' => 11, 'client_id' => null],
    99 => ['id' => 99, 'expediente_id' => 12, 'client_id' => null],
];
$uc = new DeleteExpedienteUseCase(new FakeBackend(), $lock);
$out = $uc->execute(['expediente_id' => '11']);
ac_assert('general+registros ok', !empty($out['success']));
ac_assert('registros del objetivo borrados', !isset(ExpedienteRegistrosRepository::$rows[21])
    && !isset(ExpedienteRegistrosRepository::$rows[22]));
ac_assert('otro expediente intacto', isset(ExpedienteRegistrosRepository::$rows[99]));

// --- Relacionado vacío ---
aa_reset_delete_exp_state();
$lock->acquire_calls = [];
ExpedientesRepository::$parents[5] = ['id' => 5, 'category_id' => 3, 'client_id' => 7];
$uc = new DeleteExpedienteUseCase(new FakeBackend(), $lock);
$out = $uc->execute(['expediente_id' => 5]);
ac_assert('relacionado vacío ok', !empty($out['success']));
ac_assert('scope client', ($lock->acquire_calls[0]['scope_kind'] ?? '') === 'client'
    && (int) ($lock->acquire_calls[0]['scope_id'] ?? 0) === 7);

// --- Relacionado con adjuntos ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[5] = ['id' => 5, 'category_id' => 3, 'client_id' => 7];
ExpedienteRegistrosRepository::$rows = [
    11 => ['id' => 11, 'expediente_id' => 5, 'client_id' => 7],
    12 => ['id' => 12, 'expediente_id' => 5, 'client_id' => 7],
];
ExpedienteAdjuntosRepository::$rows = [
    41 => [
        'id' => 41, 'record_id' => 11, 'client_id' => 7,
        'upload_operation_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'storage_path' => $PATH_A,
        'record_client_id' => 7, 'record_expediente_id' => 5,
    ],
    42 => [
        'id' => 42, 'record_id' => 12, 'client_id' => 7,
        'upload_operation_id' => 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'storage_path' => $PATH_B,
        'record_client_id' => 7, 'record_expediente_id' => 5,
    ],
];
$backend = new FakeBackend();
$backend->responses = [
    ['ok' => true, 'result' => ['status' => 'deleted']],
    ['ok' => true, 'result' => ['status' => 'already_absent']],
];
$uc = new DeleteExpedienteUseCase($backend, $lock);
$out = $uc->execute(['expediente_id' => 5]);
ac_assert('relacionado+adjuntos ok', !empty($out['success']));
ac_assert('2 delete_object', count($backend->calls) === 2);
ac_assert('metadata borrada', ExpedienteAdjuntosRepository::$rows === []);
ac_assert('registros borrados tras adjuntos', ExpedienteRegistrosRepository::$rows === []);
ac_assert('padre borrado al final', !isset(ExpedientesRepository::$parents[5]));
ac_assert('TX tras Storage', strpos(implode("\n", $wpdb->queries), 'START TRANSACTION') !== false);

// --- Fallo remoto intermedio: padre/registros permanecen; progreso parcial ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[5] = ['id' => 5, 'category_id' => 3, 'client_id' => 7];
ExpedienteRegistrosRepository::$rows = [
    11 => ['id' => 11, 'expediente_id' => 5, 'client_id' => 7],
    12 => ['id' => 12, 'expediente_id' => 5, 'client_id' => 7],
];
ExpedienteAdjuntosRepository::$rows = [
    41 => [
        'id' => 41, 'record_id' => 11, 'client_id' => 7,
        'upload_operation_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'storage_path' => $PATH_A,
        'record_client_id' => 7, 'record_expediente_id' => 5,
    ],
    42 => [
        'id' => 42, 'record_id' => 12, 'client_id' => 7,
        'upload_operation_id' => 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'storage_path' => $PATH_B,
        'record_client_id' => 7, 'record_expediente_id' => 5,
    ],
];
$backend = new FakeBackend();
$backend->responses = [
    ['ok' => true, 'result' => ['status' => 'deleted']],
    ['ok' => false, 'code' => 'delete_failed'],
];
$uc = new DeleteExpedienteUseCase($backend, $lock);
$out = $uc->execute(['expediente_id' => 5]);
ac_assert('fallo intermedio code', ($out['error']['code'] ?? '') === 'delete_failed');
ac_assert('progreso: 1 metadata borrada', count(ExpedienteAdjuntosRepository::$deleted) === 1
    && !isset(ExpedienteAdjuntosRepository::$rows[41])
    && isset(ExpedienteAdjuntosRepository::$rows[42]));
ac_assert('registros permanecen', count(ExpedienteRegistrosRepository::$rows) === 2);
ac_assert('padre permanece', isset(ExpedientesRepository::$parents[5]));

// Retry continúa
$backend2 = new FakeBackend();
$uc = new DeleteExpedienteUseCase($backend2, $lock);
$out = $uc->execute(['expediente_id' => 5]);
ac_assert('retry completa', !empty($out['success']));
ac_assert('retry 1 Storage (restante)', count($backend2->calls) === 1);
ac_assert('retry limpia todo', ExpedienteAdjuntosRepository::$rows === []
    && ExpedienteRegistrosRepository::$rows === []
    && !isset(ExpedientesRepository::$parents[5]));

// --- Lock lost after Storage ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[5] = ['id' => 5, 'category_id' => 3, 'client_id' => 7];
ExpedienteRegistrosRepository::$rows = [
    11 => ['id' => 11, 'expediente_id' => 5, 'client_id' => 7],
];
ExpedienteAdjuntosRepository::$rows = [
    41 => [
        'id' => 41, 'record_id' => 11, 'client_id' => 7,
        'upload_operation_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'storage_path' => $PATH_A,
        'record_client_id' => 7, 'record_expediente_id' => 5,
    ],
];
$flip = new class extends AA_Test_Passthrough_Expediente_Aggregate_Lock {
    public $n = 0;
    public function assert_held($lease) {
        $this->n++;
        if ($this->n >= 1) {
            return new WP_Error('coordination_lost', 'lost');
        }
        return true;
    }
};
$backend = new FakeBackend();
$uc = new DeleteExpedienteUseCase($backend, $flip);
$out = $uc->execute(['expediente_id' => 5]);
ac_assert('lock lost → coordination_lost', ($out['error']['code'] ?? '') === 'coordination_lost');
ac_assert('lock lost: Storage sí', count($backend->calls) === 1);
ac_assert('lock lost: cero metadata delete', ExpedienteAdjuntosRepository::$deleted === []);
ac_assert('lock lost: padre permanece', isset(ExpedientesRepository::$parents[5]));

// --- General con adjunto v1 → inconsistent, cero efectos ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[11] = ['id' => 11, 'category_id' => 2, 'client_id' => null];
ExpedienteRegistrosRepository::$rows = [
    11 => ['id' => 11, 'expediente_id' => 11, 'client_id' => null],
];
ExpedienteAdjuntosRepository::$rows = [
    41 => [
        'id' => 41, 'record_id' => 11, 'client_id' => null,
        'upload_operation_id' => $OP_A,
        'storage_path' => $PATH_A,
        'record_client_id' => null, 'record_expediente_id' => 11,
    ],
];
$backend = new FakeBackend();
$uc = new DeleteExpedienteUseCase($backend, $lock);
$out = $uc->execute(['expediente_id' => 11]);
ac_assert('general+v1 path → aggregate_inconsistent', ($out['error']['code'] ?? '') === 'aggregate_inconsistent');
ac_assert('cero Storage', $backend->calls === []);
ac_assert('cero DELETE', ExpedientesRepository::$deleted === [] && ExpedienteAdjuntosRepository::$deleted === []);

// --- General con adjunto v2 coherente → OK ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[11] = ['id' => 11, 'category_id' => 2, 'client_id' => null];
ExpedienteRegistrosRepository::$rows = [
    11 => ['id' => 11, 'expediente_id' => 11, 'client_id' => null],
];
ExpedienteAdjuntosRepository::$rows = [
    51 => [
        'id' => 51, 'record_id' => 11, 'client_id' => null,
        'upload_operation_id' => $OP_G,
        'storage_path' => $PATH_G_V2,
        'record_client_id' => null, 'record_expediente_id' => 11,
    ],
];
$backend = new FakeBackend();
$uc = new DeleteExpedienteUseCase($backend, $lock);
$out = $uc->execute(['expediente_id' => 11]);
ac_assert('general+v2 coherente ok', !empty($out['success']));
ac_assert('general+v2 Storage', count($backend->calls) === 1);
ac_assert('general+v2 metadata borrada', ExpedienteAdjuntosRepository::$rows === []);
ac_assert('general+v2 padre borrado', !isset(ExpedientesRepository::$parents[11]));

// --- Path inválido ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[5] = ['id' => 5, 'category_id' => 3, 'client_id' => 7];
ExpedienteRegistrosRepository::$rows = [
    11 => ['id' => 11, 'expediente_id' => 5, 'client_id' => 7],
];
ExpedienteAdjuntosRepository::$rows = [
    41 => [
        'id' => 41, 'record_id' => 11, 'client_id' => 7,
        'upload_operation_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'storage_path' => 'bad/path.jpg',
        'record_client_id' => 7, 'record_expediente_id' => 5,
    ],
];
$backend = new FakeBackend();
$uc = new DeleteExpedienteUseCase($backend, $lock);
$out = $uc->execute(['expediente_id' => 5]);
ac_assert('path inválido → aggregate_inconsistent', ($out['error']['code'] ?? '') === 'aggregate_inconsistent');
ac_assert('path inválido cero Storage', $backend->calls === []);

// --- resource_busy ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[5] = ['id' => 5, 'category_id' => 3, 'client_id' => 7];
$busy = aa_test_install_passthrough_expediente_lock();
$busy->next_acquire = new WP_Error('resource_busy', 'busy');
$uc = new DeleteExpedienteUseCase(new FakeBackend(), $busy);
$out = $uc->execute(['expediente_id' => 5]);
ac_assert('resource_busy', ($out['error']['code'] ?? '') === 'resource_busy');
ac_assert('busy sin persistencia', ExpedientesRepository::$deleted === []);

// --- segundo delete ---
aa_reset_delete_exp_state();
$lock = aa_test_install_passthrough_expediente_lock();
$uc = new DeleteExpedienteUseCase(new FakeBackend(), $lock);
$out = $uc->execute(['expediente_id' => 5]);
ac_assert('segundo delete → not_found', ($out['error']['code'] ?? '') === 'not_found');

// --- invalid_id ---
$out = $uc->execute(['expediente_id' => '01']);
ac_assert('invalid_id', ($out['error']['code'] ?? '') === 'invalid_id');

// --- owner malformado ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[5] = ['id' => 5, 'category_id' => 3, 'client_id' => 0];
$out = (new DeleteExpedienteUseCase(new FakeBackend(), $lock))->execute(['expediente_id' => 5]);
ac_assert('owner 0 → aggregate_inconsistent', ($out['error']['code'] ?? '') === 'aggregate_inconsistent');

// --- record client contradictorio ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[5] = ['id' => 5, 'category_id' => 3, 'client_id' => 7];
ExpedienteRegistrosRepository::$rows = [
    11 => ['id' => 11, 'expediente_id' => 5, 'client_id' => 8],
];
$out = (new DeleteExpedienteUseCase(new FakeBackend(), $lock))->execute(['expediente_id' => 5]);
ac_assert('record client mismatch → aggregate_inconsistent', ($out['error']['code'] ?? '') === 'aggregate_inconsistent');

// --- concurrent: conjunto de registros cambia en TX ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[11] = ['id' => 11, 'category_id' => 2, 'client_id' => null];
ExpedienteRegistrosRepository::$rows = [
    21 => ['id' => 21, 'expediente_id' => 11, 'client_id' => null],
];
ExpedienteRegistrosRepository::$tx_rows_flip = static function () {
    ExpedienteRegistrosRepository::$rows[22] = ['id' => 22, 'expediente_id' => 11, 'client_id' => null];
};
$out = (new DeleteExpedienteUseCase(new FakeBackend(), $lock))->execute(['expediente_id' => 11]);
ac_assert('registros cambian → concurrent_change', ($out['error']['code'] ?? '') === 'concurrent_change');
ac_assert('concurrent: padre permanece', isset(ExpedientesRepository::$parents[11]));

// --- lookup SQL ---
aa_reset_delete_exp_state();
ExpedientesRepository::$sql_error = true;
$out = (new DeleteExpedienteUseCase(new FakeBackend(), $lock))->execute(['expediente_id' => 11]);
ac_assert('lookup SQL → lookup_failed', ($out['error']['code'] ?? '') === 'lookup_failed');

// --- TX start fail ---
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[11] = ['id' => 11, 'category_id' => 2, 'client_id' => null];
$wpdb->fail_start = true;
$out = (new DeleteExpedienteUseCase(new FakeBackend(), $lock))->execute(['expediente_id' => 11]);
ac_assert('TX start fail → persistence_failed', ($out['error']['code'] ?? '') === 'persistence_failed');
ac_assert('TX fail padre intacto', isset(ExpedientesRepository::$parents[11]));

// Envelope no filtra client_id
aa_reset_delete_exp_state();
ExpedientesRepository::$parents[5] = ['id' => 5, 'category_id' => 3, 'client_id' => 7];
$out = (new DeleteExpedienteUseCase(new FakeBackend(), $lock))->execute(['expediente_id' => 5]);
$encoded = json_encode($out);
ac_assert('éxito sin client_id en envelope', strpos($encoded, 'client_id') === false);
ac_assert('éxito sin storage_path', strpos($encoded, 'storage_path') === false);

echo "\nResultado: {$passed}/{$total}" . (count($failed) ? (' FAIL: ' . implode(', ', $failed)) : ' OK') . "\n";
exit(count($failed) === 0 ? 0 : 1);
