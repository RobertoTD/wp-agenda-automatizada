<?php
/**
 * AC A1a — caminos uncertain (COMMIT/ROLLBACK/relectura) con effects y create lista.
 *
 * Simulación vía probe de $wpdb (sin caídas reales de MySQL ni tocar BD de uso).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-a1a-uncertain-paths-ac.php
 */

$plugin_root = dirname(__DIR__, 4);

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

echo "=== Cobertura estática / inventario previo ===\n";
$repo_mysql = (string) file_get_contents(
    $plugin_root . '/tests/repositories/test-canonical-relational-repository-mysql-ac.php'
);
$adapters = (string) file_get_contents(
    $plugin_root . '/tests/infrastructure/canonical/test-aa-canonical-relational-adapters-ac.php'
);
$a1a_amount = (string) file_get_contents(
    $plugin_root . '/tests/application/canonical/capabilities/test-canonical-amount-write-ac.php'
);
ac_assert(
    'Preexistente: COMMIT false en create_record (repo mysql AC)',
    strpos($repo_mysql, "fail_query = 'COMMIT'") !== false
    && strpos($repo_mysql, 'COMMIT false → AmbiguousOutcome') !== false
);
ac_assert(
    'Preexistente: ROLLBACK false tras touch fail (repo mysql AC)',
    strpos($repo_mysql, "fail_query = 'ROLLBACK'") !== false
    && strpos($repo_mysql, 'ROLLBACK false tras mutación → AmbiguousOutcome') !== false
);
ac_assert(
    'Preexistente: adapter mapea COMMIT false → uncertain',
    strpos($adapters, 'Ambiguous COMMIT → uncertain') !== false
);
ac_assert(
    'A1a amount: effect fail + rollback OK → confirmed persistence path',
    strpos($a1a_amount, 'Fallo de effect → rollback confirmado') !== false
);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL harness ausente.\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalQueryFailed.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalAmbiguousOutcome.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationPersistenceFailed.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalMutationReceipt.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellMutationResult.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalWriteGateway.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php';
require_once $plugin_root . '/includes/application/canonical/WriteCanonicalShellRecordUseCase.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalRecordCapabilityEffect.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalRecordMutationContext.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerCapabilityEffect.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalContainerMutationContext.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/relational/class-aa-canonical-relational-write-adapter.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';

/**
 * Probe mínimo: fuerza COMMIT/ROLLBACK false y relectura nula post-commit.
 */
final class A1aUncertainWpdbProbe {
    /** @var object */
    public $inner;
    /** @var string|null */
    public $fail_query = null;
    /** @var bool */
    public $null_next_container_row = false;
    /** @var bool */
    public $committed = false;
    /** @var string */
    public $prefix;
    /** @var string */
    public $last_error = '';
    /** @var int */
    public $insert_id = 0;

    public function __construct($inner) {
        $this->inner = $inner;
        $this->sync();
    }

    private function sync(): void {
        $this->prefix = $this->inner->prefix;
        $this->last_error = (string) ($this->inner->last_error ?? '');
        $this->insert_id = (int) ($this->inner->insert_id ?? 0);
    }

    public function prepare($query, ...$args) {
        return $this->inner->prepare($query, ...$args);
    }

    public function query($sql) {
        $trimmed = strtoupper(ltrim((string) $sql));
        if ($this->fail_query !== null) {
            $needle = strtoupper($this->fail_query);
            if (strpos($trimmed, $needle) === 0) {
                $this->last_error = 'forced ' . $this->fail_query;
                return false;
            }
        }
        if (strpos($trimmed, 'COMMIT') === 0) {
            $this->committed = true;
        }
        $result = $this->inner->query($sql);
        $this->sync();
        return $result;
    }

    public function get_var($query = null, $x = 0, $y = 0) {
        $r = $this->inner->get_var($query, $x, $y);
        $this->sync();
        return $r;
    }

    public function get_row($query = null, $output = OBJECT, $y = 0) {
        if ($this->null_next_container_row && $this->committed) {
            $this->null_next_container_row = false;
            $this->sync();
            return null;
        }
        $r = $this->inner->get_row($query, $output, $y);
        $this->sync();
        return $r;
    }

    public function get_results($query = null, $output = OBJECT) {
        $r = $this->inner->get_results($query, $output);
        $this->sync();
        return $r;
    }

    public function insert($table, $data, $format = null) {
        $r = $this->inner->insert($table, $data, $format);
        $this->sync();
        return $r;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        $r = $this->inner->update($table, $data, $where, $format, $where_format);
        $this->sync();
        return $r;
    }

    public function delete($table, $where, $where_format = null) {
        $r = $this->inner->delete($table, $where, $where_format);
        $this->sync();
        return $r;
    }
}

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_a1au_' . substr(md5((string) microtime(true)), 0, 8) . '_';
$prior_db_version = (string) get_option('aa_db_version', '0');

$cleanup = static function () use ($wpdb, $temp_prefix): void {
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $like = $wpdb->esc_like($temp_prefix) . '%';
    $rows = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
    if (is_array($rows)) {
        foreach ($rows as $t) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $t) . '`');
        }
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
};

$noop_effect = new class implements CanonicalRecordCapabilityEffect {
    public $applied = false;
    public function apply(CanonicalRecordMutationContext $context): void {
        $this->applied = true;
    }
};

$failing_effect = new class implements CanonicalRecordCapabilityEffect {
    public function apply(CanonicalRecordMutationContext $context): void {
        throw new CanonicalMutationPersistenceFailed('simulated effect failure');
    }
};

echo "\n=== Caminos A1a uncertain (probe) ===\n";
try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();
    AA_Canonical_Schema::install();

    $now = gmdate('Y-m-d H:i:s');
    $wpdb->insert(
        $wpdb->prefix . AA_Canonical_Schema::TABLE_FAMILIES,
        [
            'family_key' => 'finance',
            'is_enabled' => 1,
            'seed_version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%s', '%d', '%d', '%s', '%s']
    );
    $family_id = (int) $wpdb->insert_id;
    $wpdb->insert(
        $wpdb->prefix . AA_Canonical_Schema::TABLE_CONTAINERS,
        [
            'public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'family_id' => $family_id,
            'title' => 'Lista base',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%s', '%d', '%s', null, '%s', '%s']
    );
    $container_id = (int) $wpdb->insert_id;

    $identity = new CanonicalReadIdentity('finance');
    $manifest = new CanonicalShellManifest(
        $identity,
        new AA_Canonical_Family_Definition('finance', 'Finanzas')
    );

    // 1) Effect aplicado + COMMIT false → AmbiguousOutcome → receipt uncertain (con IDs)
    $probe_commit = new A1aUncertainWpdbProbe($wpdb);
    $probe_commit->fail_query = 'COMMIT';
    $effect1 = clone $noop_effect;
    // clone of anonymous class may not work - recreate
    $effect1 = new class implements CanonicalRecordCapabilityEffect {
        public $applied = false;
        public function apply(CanonicalRecordMutationContext $context): void {
            $this->applied = true;
        }
    };
    $repo_commit = new CanonicalRelationalRepository($probe_commit);
    $ambiguous_commit = null;
    try {
        $repo_commit->create_record($family_id, $container_id, 'Commit fail + effect', null, [$effect1]);
    } catch (CanonicalRelationalAmbiguousOutcome $e) {
        $ambiguous_commit = $e;
    }
    $wpdb->query('ROLLBACK');
    ac_assert('Effect aplicado antes del COMMIT fallido', $effect1->applied === true);
    ac_assert(
        'COMMIT false tras effect → AmbiguousOutcome con IDs',
        $ambiguous_commit instanceof CanonicalRelationalAmbiguousOutcome
        && $ambiguous_commit->resource_id() >= 1
        && $ambiguous_commit->container_id() === $container_id
        && $ambiguous_commit->resource_type() === 'record'
    );

    $probe_commit2 = new A1aUncertainWpdbProbe($wpdb);
    $probe_commit2->fail_query = 'COMMIT';
    $effect2 = new class implements CanonicalRecordCapabilityEffect {
        public function apply(CanonicalRecordMutationContext $context): void {
        }
    };
    $adapter = new AA_Canonical_Relational_Write_Adapter(new CanonicalRelationalRepository($probe_commit2));
    $receipt = $adapter->create_record(
        $identity,
        new CanonicalCreateRecordCommand($container_id, 'Via adapter', null),
        [$effect2]
    );
    $wpdb->query('ROLLBACK');
    ac_assert(
        'Adapter: COMMIT false + effect → uncertain (no ausencia confirmada)',
        $receipt->outcome() === CanonicalMutationReceipt::OUTCOME_UNCERTAIN
        && $receipt->resource_id() >= 1
        && $receipt->container_id() === $container_id
    );

    $write_registry = new AA_Canonical_Write_Binding_Registry();
    $probe_commit3 = new A1aUncertainWpdbProbe($wpdb);
    $probe_commit3->fail_query = 'COMMIT';
    $write_registry->register(
        $identity,
        new AA_Canonical_Relational_Write_Adapter(new CanonicalRelationalRepository($probe_commit3))
    );
    $uc = new WriteCanonicalShellRecordUseCase(new CanonicalWriteGateway($write_registry));
    $uc_result = $uc->create($manifest, new CanonicalCreateRecordCommand($container_id, 'Via UC', null));
    $wpdb->query('ROLLBACK');
    ac_assert(
        'UC: COMMIT false → STATE_UNCERTAIN',
        $uc_result->state() === CanonicalShellMutationResult::STATE_UNCERTAIN
        && $uc_result->receipt() instanceof CanonicalMutationReceipt
        && $uc_result->receipt()->resource_id() >= 1
    );

    // 2a) Effect fail + ROLLBACK OK → QueryFailed (persistencia confirmada fallida)
    $probe_rb_ok = new A1aUncertainWpdbProbe($wpdb);
    $before = (int) $wpdb->get_var(
        'SELECT COUNT(*) FROM `' . $wpdb->prefix . AA_Canonical_Schema::TABLE_RECORDS . '`'
    );
    $confirmed_fail = false;
    try {
        (new CanonicalRelationalRepository($probe_rb_ok))
            ->create_record($family_id, $container_id, 'Effect rb ok', null, [$failing_effect]);
    } catch (CanonicalRelationalQueryFailed $e) {
        $confirmed_fail = true;
    } catch (CanonicalRelationalAmbiguousOutcome $e) {
        $confirmed_fail = false;
    }
    $after = (int) $wpdb->get_var(
        'SELECT COUNT(*) FROM `' . $wpdb->prefix . AA_Canonical_Schema::TABLE_RECORDS . '`'
    );
    ac_assert(
        'Effect fail + ROLLBACK OK → QueryFailed y sin fila',
        $confirmed_fail && $after === $before
    );

    // 2b) Effect fail + ROLLBACK false → AmbiguousOutcome / uncertain
    $probe_rb_fail = new A1aUncertainWpdbProbe($wpdb);
    $probe_rb_fail->fail_query = 'ROLLBACK';
    $ambiguous_rb = null;
    try {
        (new CanonicalRelationalRepository($probe_rb_fail))
            ->create_record($family_id, $container_id, 'Effect rb fail', null, [$failing_effect]);
    } catch (CanonicalRelationalAmbiguousOutcome $e) {
        $ambiguous_rb = $e;
    }
    $wpdb->query('ROLLBACK');
    ac_assert(
        'Effect fail + ROLLBACK false → AmbiguousOutcome (no ausencia confirmada)',
        $ambiguous_rb instanceof CanonicalRelationalAmbiguousOutcome
        && $ambiguous_rb->resource_id() >= 1
        && $ambiguous_rb->container_id() === $container_id
    );

    $probe_rb_fail2 = new A1aUncertainWpdbProbe($wpdb);
    $probe_rb_fail2->fail_query = 'ROLLBACK';
    $adapter_rb = new AA_Canonical_Relational_Write_Adapter(new CanonicalRelationalRepository($probe_rb_fail2));
    $receipt_rb = $adapter_rb->create_record(
        $identity,
        new CanonicalCreateRecordCommand($container_id, 'Effect rb adapter', null),
        [$failing_effect]
    );
    $wpdb->query('ROLLBACK');
    ac_assert(
        'Adapter: effect+ROLLBACK false → uncertain',
        $receipt_rb->outcome() === CanonicalMutationReceipt::OUTCOME_UNCERTAIN
        && $receipt_rb->resource_id() >= 1
    );

    // 3) create_container: COMMIT OK + relectura null → AmbiguousOutcome → UC uncertain
    $probe_reread = new A1aUncertainWpdbProbe($wpdb);
    $probe_reread->null_next_container_row = true;
    $ambiguous_list = null;
    try {
        (new CanonicalRelationalRepository($probe_reread))
            ->create_container($family_id, 'Lista relectura', null, []);
    } catch (CanonicalRelationalAmbiguousOutcome $e) {
        $ambiguous_list = $e;
    }
    ac_assert(
        'Lista: COMMIT + relectura null → AmbiguousOutcome con resource_id',
        $ambiguous_list instanceof CanonicalRelationalAmbiguousOutcome
        && $ambiguous_list->operation() === 'create'
        && $ambiguous_list->resource_type() === 'container'
        && $ambiguous_list->resource_id() >= 1
    );

    $probe_reread2 = new A1aUncertainWpdbProbe($wpdb);
    $probe_reread2->null_next_container_row = true;
    $reg2 = new AA_Canonical_Write_Binding_Registry();
    $reg2->register(
        $identity,
        new AA_Canonical_Relational_Write_Adapter(new CanonicalRelationalRepository($probe_reread2))
    );
    $list_uc = new WriteCanonicalShellContainerUseCase(new CanonicalWriteGateway($reg2));
    $list_result = $list_uc->create($manifest, new CanonicalCreateContainerCommand('Lista UC', null));
    ac_assert(
        'UC lista: relectura post-commit fallida → STATE_UNCERTAIN (no confirmed vacío)',
        $list_result->state() === CanonicalShellMutationResult::STATE_UNCERTAIN
        && $list_result->receipt() instanceof CanonicalMutationReceipt
        && $list_result->receipt()->resource_id() >= 1
        && $list_result->state() !== CanonicalShellMutationResult::STATE_PERSISTENCE_FAILED
    );
} finally {
    $cleanup();
    $wpdb->prefix = $prior_prefix;
    update_option('aa_db_version', $prior_db_version);
}

$live = (string) get_option('aa_db_version', '0');
ac_assert('BD de uso intacta tras harness', $live === $prior_db_version);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
