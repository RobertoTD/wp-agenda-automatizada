<?php
/**
 * AC Test — CanonicalRelationalRepository (PCU-3) con MySQL real.
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/repositories/test-canonical-relational-repository-mysql-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$repo_file = $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
$qf_file = $plugin_root . '/includes/repositories/CanonicalRelationalQueryFailed.php';
$ao_file = $plugin_root . '/includes/repositories/CanonicalRelationalAmbiguousOutcome.php';
$schema_file = $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';

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

echo "=== 1. Contención / estático ===\n";

$repo_src = (string) file_get_contents($repo_file);
$qf_src = (string) file_get_contents($qf_file);
$ao_src = (string) file_get_contents($ao_file);

ac_assert('CanonicalRelationalRepository existe', is_readable($repo_file));
ac_assert('QueryFailed existe', is_readable($qf_file));
ac_assert('AmbiguousOutcome existe', is_readable($ao_file));
ac_assert('Repo no menciona finance', stripos($repo_src, 'finance') === false);
ac_assert('Repo no menciona aa_finance_', strpos($repo_src, 'aa_finance_') === false);
ac_assert('Repo no menciona Expedientes', stripos($repo_src, 'expediente') === false);
ac_assert('Repo no menciona amount/currency/SKU', !preg_match('/\b(amount|currency|sku)\b/i', $repo_src));
ac_assert('Repo usa gmdate UTC', strpos($repo_src, "gmdate('Y-m-d H:i:s')") !== false);
ac_assert('Repo no usa current_time', strpos($repo_src, 'current_time') === false);
ac_assert('Repo usa wp_generate_uuid4', strpos($repo_src, 'wp_generate_uuid4') !== false);
ac_assert('Repo no SELECT *', !preg_match('/SELECT\s+\*/i', $repo_src));
ac_assert('Repo PAGE_SIZE 15', strpos($repo_src, 'PAGE_SIZE = 15') !== false);
ac_assert('QueryFailed tag estable', strpos($qf_src, '[canonical_relational_query_failed]') !== false);
ac_assert('AmbiguousOutcome exige resource_id', strpos($ao_src, 'resource_id must be >= 1') !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] Integración MySQL real no ejecutada (AA_WP_ROOT no definido o inaccesible).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    if ($failed !== []) {
        echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
        exit(1);
    }
    exit(0);
}

require_once $wp_load;
require_once $schema_file;
require_once $qf_file;
require_once $ao_file;
require_once $repo_file;

/**
 * Decorador mínimo de $wpdb para forzar fallos START/COMMIT/ROLLBACK/update/insert.
 */
class CanonicalRelationalWpdbProbe {
    /** @var object */
    public $inner;
    /** @var string|null */
    public $fail_query = null;
    /** @var int|null */
    public $fail_nth_update = null;
    /** @var int */
    public $update_calls = 0;
    /** @var bool */
    public $fail_next_insert = false;
    /** @var string */
    public $prefix;
    /** @var string */
    public $last_error = '';
    /** @var int */
    public $insert_id = 0;

    public function __construct($inner) {
        $this->inner = $inner;
        $this->prefix = $inner->prefix;
        $this->last_error = (string) ($inner->last_error ?? '');
        $this->insert_id = (int) ($inner->insert_id ?? 0);
    }

    public function sync_from_inner(): void {
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
        $result = $this->inner->query($sql);
        $this->sync_from_inner();
        return $result;
    }

    public function get_var($query = null, $x = 0, $y = 0) {
        $result = $this->inner->get_var($query, $x, $y);
        $this->sync_from_inner();
        return $result;
    }

    public function get_row($query = null, $output = OBJECT, $y = 0) {
        $result = $this->inner->get_row($query, $output, $y);
        $this->sync_from_inner();
        return $result;
    }

    public function get_results($query = null, $output = OBJECT) {
        $result = $this->inner->get_results($query, $output);
        $this->sync_from_inner();
        return $result;
    }

    public function insert($table, $data, $format = null) {
        if ($this->fail_next_insert) {
            $this->fail_next_insert = false;
            $this->last_error = 'forced insert failure';
            return false;
        }
        $result = $this->inner->insert($table, $data, $format);
        $this->sync_from_inner();
        return $result;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        $this->update_calls++;
        if ($this->fail_nth_update !== null && $this->update_calls === $this->fail_nth_update) {
            $this->last_error = 'forced update failure';
            return false;
        }
        $result = $this->inner->update($table, $data, $where, $format, $where_format);
        $this->sync_from_inner();
        return $result;
    }

    public function delete($table, $where, $where_format = null) {
        $result = $this->inner->delete($table, $where, $where_format);
        $this->sync_from_inner();
        return $result;
    }
}

global $wpdb;

echo "=== 2. Integración MySQL ===\n";
echo "Host: " . DB_HOST . " | Base: " . DB_NAME . "\n";

$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_crel_' . substr(md5(uniqid('cr', true)), 0, 8) . '_';

$cleanup_tables = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_crel_') !== 0) {
        return;
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $like = $wpdb->esc_like($p) . '%';
    $rows = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
    if (is_array($rows)) {
        foreach ($rows as $t) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $t) . '`');
        }
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
};

$seed_family = static function (string $family_key) use ($wpdb): int {
    $table = AA_Canonical_Schema::families_table_name();
    $now = gmdate('Y-m-d H:i:s');
    $wpdb->insert(
        $table,
        [
            'family_key' => $family_key,
            'is_enabled' => 0,
            'seed_version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%s', '%d', '%d', '%s', '%s']
    );
    return (int) $wpdb->insert_id;
};

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup_tables($temp_prefix);
    AA_Canonical_Schema::install();
    AA_Canonical_Schema::verify();
    ac_assert('MySQL: schema canónico instalado', true);

    $repo = new CanonicalRelationalRepository($wpdb);

    $finance_id = $seed_family('finance');
    $archive_id = $seed_family('archive');
    ac_assert('MySQL: dos familias provisionadas', $finance_id >= 1 && $archive_id >= 1);

    $resolved = $repo->resolve_family_id('finance');
    ac_assert('MySQL: resolve_family_id finance', $resolved === $finance_id);
    ac_assert('MySQL: familia no provisionada → null', $repo->resolve_family_id('missing') === null);

    // Contenedores CRUD + variantes
    $c_gen = $repo->create_container($finance_id, 'general', 'Caja General', null);
    ac_assert('MySQL: create container title + details null', $c_gen['title'] === 'Caja General' && $c_gen['details'] === null);
    ac_assert('MySQL: public_id UUID v4-ish', (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $c_gen['public_id']));
    $public_before = $c_gen['public_id'];

    $c_pers = $repo->create_container($finance_id, 'personal', 'Caja Personal', 'Notas');
    $c_arch = $repo->create_container($archive_id, 'general', 'Archivo A', null);
    ac_assert('MySQL: misma clase sirve dos familias/variantes', $c_pers['id'] !== $c_gen['id'] && $c_arch['family_id'] === $archive_id);

    $updated = $repo->update_container($finance_id, 'general', $c_gen['id'], 'Caja Renombrada', null);
    ac_assert('MySQL: update container title', $updated !== null && $updated['title'] === 'Caja Renombrada');
    ac_assert('MySQL: update no modifica public_id', $updated['public_id'] === $public_before);

    $wrong = $repo->find_container($finance_id, 'personal', $c_gen['id']);
    ac_assert('MySQL: contenedor fuera de identidad → null', $wrong === null);

    // Orden updated_at DESC, id DESC
    $c_table = AA_Canonical_Schema::containers_table_name();
    $wpdb->update($c_table, ['updated_at' => '2020-01-01 00:00:00'], ['id' => $c_gen['id']], ['%s'], ['%d']);
    $wpdb->update($c_table, ['updated_at' => '2021-01-01 00:00:00'], ['id' => $c_pers['id']], ['%s'], ['%d']);
    $newer = $repo->create_container($finance_id, 'general', 'Más nuevo', null);
    $list = $repo->list_containers($finance_id, 'general', 1, 15);
    ac_assert('MySQL: orden containers updated_at DESC', $list[0]['id'] === $newer['id']);

    // Paginación 15 + clamp + empty
    for ($i = 0; $i < 16; $i++) {
        $repo->create_container($archive_id, 'bulk', 'Bulk ' . $i, null);
    }
    $page1 = $repo->list_containers($archive_id, 'bulk', 1, 15);
    $count_bulk = $repo->count_containers($archive_id, 'bulk');
    ac_assert('MySQL: page size 15', count($page1) === 15 && $count_bulk === 16);
    $empty_variant = $repo->list_containers($archive_id, 'emptyv', 1, 15);
    ac_assert('MySQL: página vacía', $empty_variant === [] && $repo->count_containers($archive_id, 'emptyv') === 0);

    // UTC independiente de zonas
    $prev_tz = date_default_timezone_get();
    date_default_timezone_set('America/Mexico_City');
    if (function_exists('update_option')) {
        update_option('timezone_string', 'Pacific/Honolulu');
        update_option('aa_timezone', 'Asia/Tokyo');
    }
    $before_utc = gmdate('Y-m-d H:i:s');
    $utc_row = $repo->create_container($finance_id, 'general', 'UTC probe', null);
    $after_utc = gmdate('Y-m-d H:i:s');
    ac_assert(
        'MySQL: created_at en UTC gmdate',
        $utc_row['created_at'] >= $before_utc && $utc_row['created_at'] <= $after_utc
    );
    date_default_timezone_set($prev_tz);

    // Registros CRUD + touch
    $parent_before = $repo->find_container($finance_id, 'general', $c_gen['id']);
    usleep(1100000);
    $rec = $repo->create_record($finance_id, 'general', $c_gen['id'], 'Registro 1', null);
    ac_assert('MySQL: create record details null', $rec['details'] === null && $rec['title'] === 'Registro 1');
    $parent_after = $repo->find_container($finance_id, 'general', $c_gen['id']);
    ac_assert(
        'MySQL: create record toca containers.updated_at',
        $parent_after['updated_at'] !== $parent_before['updated_at']
        && $parent_after['updated_at'] === $rec['updated_at']
    );

    $rec2 = $repo->create_record($finance_id, 'general', $c_gen['id'], 'Registro 2', 'd');
    $r_table = AA_Canonical_Schema::records_table_name();
    $wpdb->update($r_table, ['updated_at' => '2019-01-01 00:00:00'], ['id' => $rec['id']], ['%s'], ['%d']);
    $listed = $repo->list_records($c_gen['id'], 1, 15);
    ac_assert('MySQL: orden records updated_at DESC', $listed[0]['id'] === $rec2['id']);

    $upd_rec = $repo->update_record($finance_id, 'general', $c_gen['id'], $rec['id'], 'Registro 1b', null);
    ac_assert('MySQL: update record', $upd_rec !== null && $upd_rec['title'] === 'Registro 1b');
    $pub_rec = $upd_rec['public_id'];
    $upd_same = $repo->update_record($finance_id, 'general', $c_gen['id'], $rec['id'], 'Registro 1b', null);
    ac_assert('MySQL: update record no cambia public_id', $upd_same['public_id'] === $pub_rec);

    $wrong_rec = $repo->find_record($c_pers['id'], $rec['id']);
    ac_assert('MySQL: registro bajo contenedor incorrecto → null', $wrong_rec === null);

    $del_missing = false;
    try {
        $repo->delete_record($finance_id, 'general', $c_gen['id'], 999999);
    } catch (CanonicalRelationalQueryFailed $e) {
        $del_missing = $e->code_key() === CanonicalRelationalQueryFailed::CODE_RECORD_NOT_FOUND;
    }
    ac_assert('MySQL: delete record inexistente → RecordNotFound code', $del_missing);

    $deleted = $repo->delete_record($finance_id, 'general', $c_gen['id'], $rec2['id']);
    ac_assert('MySQL: delete record OK', $deleted === true && $repo->find_record($c_gen['id'], $rec2['id']) === null);

    // CASCADE / RESTRICT
    $cascade_parent = $repo->create_container($finance_id, 'general', 'Cascade parent', null);
    $cascade_rec = $repo->create_record($finance_id, 'general', $cascade_parent['id'], 'Child', null);
    $repo->delete_container($finance_id, 'general', $cascade_parent['id']);
    ac_assert('MySQL: CASCADE borra records', $repo->find_record($cascade_parent['id'], $cascade_rec['id']) === null);

    $restr_parent = $repo->create_container($finance_id, 'general', 'Restrict parent', null);
    $f_table = AA_Canonical_Schema::families_table_name();
    $wpdb->last_error = '';
    $wpdb->query($wpdb->prepare("DELETE FROM `{$f_table}` WHERE id = %d", $finance_id));
    $still = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$f_table}` WHERE id = %d", $finance_id));
    ac_assert('MySQL: RESTRICT impide borrar familia con contenedores', $still === 1);
    unset($restr_parent);

    // Cero consultas a tablas Finance/Expedientes en el código del repo (ya estático);
    // verificar que el prefijo temporal no tiene tablas finance.
    $fin_like = $wpdb->esc_like($temp_prefix . 'aa_finance_') . '%';
    $fin_tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $fin_like));
    ac_assert('MySQL: sin tablas finance en prefijo de prueba', $fin_tables === [] || $fin_tables === null);

    // START TRANSACTION === false
    $probe = new CanonicalRelationalWpdbProbe($wpdb);
    $probe->fail_query = 'START TRANSACTION';
    $repo_probe = new CanonicalRelationalRepository($probe);
    $start_failed = false;
    $count_before = $repo->count_records($c_gen['id']);
    try {
        $repo_probe->create_record($finance_id, 'general', $c_gen['id'], 'Should fail', null);
    } catch (CanonicalRelationalQueryFailed $e) {
        $start_failed = true;
    }
    ac_assert('MySQL: START TRANSACTION false → QueryFailed', $start_failed);
    ac_assert('MySQL: START fail sin mutación', $repo->count_records($c_gen['id']) === $count_before);

    // Insert fail → rollback sin parcial
    $probe2 = new CanonicalRelationalWpdbProbe($wpdb);
    $probe2->fail_next_insert = true;
    $repo_probe2 = new CanonicalRelationalRepository($probe2);
    $insert_failed = false;
    try {
        $repo_probe2->create_record($finance_id, 'general', $c_gen['id'], 'Insert fail', null);
    } catch (CanonicalRelationalQueryFailed $e) {
        $insert_failed = true;
    }
    ac_assert('MySQL: insert fail → QueryFailed + rollback', $insert_failed && $repo->count_records($c_gen['id']) === $count_before);

    // Touch fail (2nd update on create_record path: only touch is update) → rollback
    $probe3 = new CanonicalRelationalWpdbProbe($wpdb);
    $probe3->fail_nth_update = 1;
    $repo_probe3 = new CanonicalRelationalRepository($probe3);
    $touch_failed = false;
    try {
        $repo_probe3->create_record($finance_id, 'general', $c_gen['id'], 'Touch fail', null);
    } catch (CanonicalRelationalQueryFailed $e) {
        $touch_failed = true;
    } catch (CanonicalRelationalAmbiguousOutcome $e) {
        $touch_failed = true;
    }
    ac_assert('MySQL: touch fail → sin registro parcial', $touch_failed && $repo->count_records($c_gen['id']) === $count_before);

    // COMMIT === false → AmbiguousOutcome con IDs
    $probe4 = new CanonicalRelationalWpdbProbe($wpdb);
    $probe4->fail_query = 'COMMIT';
    $repo_probe4 = new CanonicalRelationalRepository($probe4);
    $ambiguous = null;
    try {
        $repo_probe4->create_record($finance_id, 'general', $c_gen['id'], 'Commit fail', null);
    } catch (CanonicalRelationalAmbiguousOutcome $e) {
        $ambiguous = $e;
    }
    ac_assert(
        'MySQL: COMMIT false → AmbiguousOutcome con IDs',
        $ambiguous instanceof CanonicalRelationalAmbiguousOutcome
        && $ambiguous->resource_id() >= 1
        && $ambiguous->container_id() === $c_gen['id']
        && $ambiguous->operation() === 'create'
        && $ambiguous->resource_type() === 'record'
    );

    // ROLLBACK === false después de mutación posible (touch fail + rollback fail)
    $probe5 = new CanonicalRelationalWpdbProbe($wpdb);
    $probe5->fail_nth_update = 1;
    $probe5->fail_query = 'ROLLBACK';
    $repo_probe5 = new CanonicalRelationalRepository($probe5);
    $ambiguous_rb = null;
    try {
        $repo_probe5->create_record($finance_id, 'general', $c_gen['id'], 'Rollback fail', null);
    } catch (CanonicalRelationalAmbiguousOutcome $e) {
        $ambiguous_rb = $e;
    }
    // El probe bloqueó ROLLBACK: cerrar TX real para no contaminar el resto.
    $wpdb->query('ROLLBACK');
    ac_assert(
        'MySQL: ROLLBACK false tras mutación → AmbiguousOutcome',
        $ambiguous_rb instanceof CanonicalRelationalAmbiguousOutcome
        && $ambiguous_rb->resource_id() >= 1
        && $ambiguous_rb->container_id() === $c_gen['id']
    );

    // Touch 0 filas + padre existente → éxito (probe devuelve 0 en touch)
    $probe6 = new CanonicalRelationalWpdbProbe($wpdb);
    $inner_update = [$probe6, 'update'];
    // Override: first update (touch) returns 0 after real update no — return 0 without writing
    $touch_zero_ok = false;
    $repo_real = new CanonicalRelationalRepository($wpdb);
    // Force same timestamp: set parent updated_at to future far, then use probe that returns 0 for update
    $probe6b = new class($wpdb) extends CanonicalRelationalWpdbProbe {
        public $return_zero_on_touch = true;
        public function update($table, $data, $where, $format = null, $where_format = null) {
            if ($this->return_zero_on_touch && isset($data['updated_at']) && count($data) === 1) {
                $this->sync_from_inner();
                return 0;
            }
            return parent::update($table, $data, $where, $format, $where_format);
        }
    };
    $repo6 = new CanonicalRelationalRepository($probe6b);
    $row6 = $repo6->create_record($finance_id, 'general', $c_gen['id'], 'Touch zero', null);
    ac_assert('MySQL: touch 0 + padre existe → confirmed row', is_array($row6) && $row6['id'] >= 1);

    // Update 0 filas + entidad existe: force update return 0 then find succeeds
    $probe7 = new class($wpdb) extends CanonicalRelationalWpdbProbe {
        public function update($table, $data, $where, $format = null, $where_format = null) {
            if (isset($data['title'])) {
                // apply real update then report 0
                parent::update($table, $data, $where, $format, $where_format);
                $this->sync_from_inner();
                return 0;
            }
            return parent::update($table, $data, $where, $format, $where_format);
        }
    };
    $repo7 = new CanonicalRelationalRepository($probe7);
    $u7 = $repo7->update_record($finance_id, 'general', $c_gen['id'], $row6['id'], 'Touch zero b', null);
    ac_assert('MySQL: update 0 + entidad existe → éxito', $u7 !== null && $u7['title'] === 'Touch zero b');

} finally {
    $cleanup_tables($temp_prefix);
    $wpdb->prefix = $original_prefix;
    echo "Limpieza garantizada: tablas temporales eliminadas.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
