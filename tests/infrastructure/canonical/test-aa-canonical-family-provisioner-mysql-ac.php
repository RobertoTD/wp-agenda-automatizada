<?php
/**
 * AC Test — AA_Canonical_Family_Provisioner (PCU-4) con MySQL real.
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/canonical/test-aa-canonical-family-provisioner-mysql-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$provisioner_file = $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
$exception_file = $plugin_root . '/includes/infrastructure/canonical/CanonicalFamilyProvisioningFailed.php';
$schema_file = $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
$bootstrap_file = $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';

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
$prov_src = (string) file_get_contents($provisioner_file);
ac_assert('Provisioner existe', is_readable($provisioner_file));
ac_assert('Excepción existe', is_readable($exception_file));
ac_assert('No usa CanonicalRelationalRepository', strpos($prov_src, 'CanonicalRelationalRepository') === false);
ac_assert('No menciona containers/records tables', strpos($prov_src, 'aa_canonical_containers') === false
    && strpos($prov_src, 'aa_canonical_records') === false);
ac_assert('No INSERT IGNORE', stripos($prov_src, 'INSERT IGNORE') === false);
ac_assert('No ON DUPLICATE KEY UPDATE', stripos($prov_src, 'ON DUPLICATE KEY UPDATE') === false);
ac_assert('Usa gmdate UTC', strpos($prov_src, "gmdate('Y-m-d H:i:s')") !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL real no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $schema_file;
require_once $bootstrap_file;
require_once $exception_file;
require_once $provisioner_file;

global $wpdb;

echo "=== 2. MySQL ===\n";

$original_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_cfam_' . substr(md5(uniqid('cf', true)), 0, 8) . '_';
$temp_prefix_2 = 'tmp_cfam2_' . substr(md5(uniqid('c2', true)), 0, 8) . '_';

$cleanup = static function (string $p) use ($wpdb): void {
    if (strpos($p, 'tmp_cfam') !== 0) {
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

$registry = AA_Canonical_Core_Bootstrap::build_registry();

try {
    $wpdb->prefix = $temp_prefix;
    $cleanup($temp_prefix);
    AA_Canonical_Schema::install();

    $f_table = AA_Canonical_Schema::families_table_name();
    $c_table = AA_Canonical_Schema::containers_table_name();
    $r_table = AA_Canonical_Schema::records_table_name();

    $provisioner = new AA_Canonical_Family_Provisioner($wpdb);
    $provisioner->ensure_declared_families($registry);

    $rows = $wpdb->get_results("SELECT family_key, is_enabled, seed_version, created_at, updated_at FROM `{$f_table}` ORDER BY family_key ASC", ARRAY_A);
    ac_assert('MySQL: exactamente 2 familias', is_array($rows) && count($rows) === 2);
    ac_assert('MySQL: claves archive+finance', ($rows[0]['family_key'] ?? '') === 'archive' && ($rows[1]['family_key'] ?? '') === 'finance');
    ac_assert('MySQL: is_enabled=0 ambas', (int) $rows[0]['is_enabled'] === 0 && (int) $rows[1]['is_enabled'] === 0);
    ac_assert('MySQL: seed_version=0 ambas', (int) $rows[0]['seed_version'] === 0 && (int) $rows[1]['seed_version'] === 0);
    ac_assert(
        'MySQL: timestamps iguales por fila',
        $rows[0]['created_at'] === $rows[0]['updated_at'] && $rows[1]['created_at'] === $rows[1]['updated_at']
    );
    ac_assert('MySQL: cero containers', (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$c_table}`") === 0);
    ac_assert('MySQL: cero records', (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$r_table}`") === 0);

    $snap = $rows;
    $provisioner->ensure_declared_families($registry);
    $rows2 = $wpdb->get_results("SELECT family_key, is_enabled, seed_version, created_at, updated_at FROM `{$f_table}` ORDER BY family_key ASC", ARRAY_A);
    ac_assert('MySQL: 2ª ejecución sigue con 2 filas', is_array($rows2) && count($rows2) === 2);
    ac_assert('MySQL: 2ª ejecución preserva timestamps', $rows2 === $snap);

    $wpdb->update($f_table, ['is_enabled' => 1, 'seed_version' => 3], ['family_key' => 'finance'], ['%d', '%d'], ['%s']);
    $finance_before = $wpdb->get_row("SELECT is_enabled, seed_version, created_at, updated_at FROM `{$f_table}` WHERE family_key = 'finance'", ARRAY_A);
    $provisioner->ensure_declared_families($registry);
    $finance_after = $wpdb->get_row("SELECT is_enabled, seed_version, created_at, updated_at FROM `{$f_table}` WHERE family_key = 'finance'", ARRAY_A);
    ac_assert('MySQL: preserva is_enabled modificado', (int) $finance_after['is_enabled'] === 1);
    ac_assert('MySQL: preserva seed_version modificado', (int) $finance_after['seed_version'] === 3);
    ac_assert('MySQL: preserva timestamps tras modificación previa', $finance_after === $finance_before);

    // Familia faltante única
    $wpdb->query("DELETE FROM `{$f_table}` WHERE family_key = 'archive'");
    $provisioner->ensure_declared_families($registry);
    $count_after_partial = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f_table}`");
    $archive_row = $wpdb->get_row("SELECT is_enabled, seed_version FROM `{$f_table}` WHERE family_key = 'archive'", ARRAY_A);
    ac_assert('MySQL: reinserta solo archive faltante', $count_after_partial === 2 && is_array($archive_row));
    ac_assert('MySQL: archive reinsertada disabled/seed0', (int) $archive_row['is_enabled'] === 0 && (int) $archive_row['seed_version'] === 0);
    $finance_still = $wpdb->get_row("SELECT is_enabled, seed_version FROM `{$f_table}` WHERE family_key = 'finance'", ARRAY_A);
    ac_assert('MySQL: finance modificada intacta tras reinsert archive', (int) $finance_still['is_enabled'] === 1 && (int) $finance_still['seed_version'] === 3);

    // Fila desconocida
    $now = gmdate('Y-m-d H:i:s');
    $wpdb->insert(
        $f_table,
        [
            'family_key' => 'legacy_orphan',
            'is_enabled' => 1,
            'seed_version' => 9,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%s', '%d', '%d', '%s', '%s']
    );
    $provisioner->ensure_declared_families($registry);
    $orphan = $wpdb->get_row("SELECT is_enabled, seed_version FROM `{$f_table}` WHERE family_key = 'legacy_orphan'", ARRAY_A);
    ac_assert('MySQL: fila desconocida conservada', is_array($orphan) && (int) $orphan['is_enabled'] === 1 && (int) $orphan['seed_version'] === 9);
    ac_assert('MySQL: total 3 filas con huérfana', (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f_table}`") === 3);

    // Fallo parcial simulado: borrar archive, fallar primer insert, segundo intento OK
    $wpdb->query("DELETE FROM `{$f_table}` WHERE family_key = 'archive'");
    $fail_once = new class($wpdb) {
        public $inner;
        public $prefix;
        public $last_error = '';
        public $insert_id = 0;
        public $fail_next = true;
        public function __construct($inner) {
            $this->inner = $inner;
            $this->prefix = $inner->prefix;
        }
        private function sync(): void {
            $this->prefix = $this->inner->prefix;
            $this->last_error = (string) ($this->inner->last_error ?? '');
            $this->insert_id = (int) ($this->inner->insert_id ?? 0);
        }
        public function prepare($q, ...$a) { return $this->inner->prepare($q, ...$a); }
        public function query($sql) { $r = $this->inner->query($sql); $this->sync(); return $r; }
        public function get_var($q = null, $x = 0, $y = 0) { $r = $this->inner->get_var($q, $x, $y); $this->sync(); return $r; }
        public function get_row($q = null, $o = OBJECT, $y = 0) { $r = $this->inner->get_row($q, $o, $y); $this->sync(); return $r; }
        public function get_results($q = null, $o = OBJECT) { $r = $this->inner->get_results($q, $o); $this->sync(); return $r; }
        public function insert($t, $d, $f = null) {
            if ($this->fail_next) {
                $this->fail_next = false;
                $this->last_error = 'forced';
                return false;
            }
            $r = $this->inner->insert($t, $d, $f);
            $this->sync();
            return $r;
        }
        public function update($t, $d, $w, $f = null, $wf = null) { $r = $this->inner->update($t, $d, $w, $f, $wf); $this->sync(); return $r; }
        public function delete($t, $w, $wf = null) { $r = $this->inner->delete($t, $w, $wf); $this->sync(); return $r; }
    };
    $partial_failed = false;
    try {
        (new AA_Canonical_Family_Provisioner($fail_once))->ensure_declared_families($registry);
    } catch (CanonicalFamilyProvisioningFailed $e) {
        $partial_failed = true;
    }
    ac_assert('MySQL: fallo parcial lanza excepción', $partial_failed);
    ac_assert('MySQL: archive aún ausente tras fallo', (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f_table}` WHERE family_key = 'archive'") === 0);
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($registry);
    ac_assert('MySQL: reintento completa archive', (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f_table}` WHERE family_key = 'archive'") === 1);

    // Carrera UNIQUE: INSERT falla pero fila ya existe → éxito
    $wpdb->query("DELETE FROM `{$f_table}` WHERE family_key = 'archive'");
    $race = new class($wpdb) {
        public $inner;
        public $prefix;
        public $last_error = '';
        public $insert_id = 0;
        public $injected = false;
        public function __construct($inner) {
            $this->inner = $inner;
            $this->prefix = $inner->prefix;
        }
        private function sync(): void {
            $this->prefix = $this->inner->prefix;
            $this->last_error = (string) ($this->inner->last_error ?? '');
            $this->insert_id = (int) ($this->inner->insert_id ?? 0);
        }
        public function prepare($q, ...$a) { return $this->inner->prepare($q, ...$a); }
        public function query($sql) { $r = $this->inner->query($sql); $this->sync(); return $r; }
        public function get_var($q = null, $x = 0, $y = 0) { $r = $this->inner->get_var($q, $x, $y); $this->sync(); return $r; }
        public function get_row($q = null, $o = OBJECT, $y = 0) { $r = $this->inner->get_row($q, $o, $y); $this->sync(); return $r; }
        public function get_results($q = null, $o = OBJECT) { $r = $this->inner->get_results($q, $o); $this->sync(); return $r; }
        public function insert($t, $d, $f = null) {
            if (!$this->injected && isset($d['family_key']) && $d['family_key'] === 'archive') {
                $this->injected = true;
                $now = gmdate('Y-m-d H:i:s');
                $this->inner->insert($t, [
                    'family_key' => 'archive',
                    'is_enabled' => 0,
                    'seed_version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], ['%s', '%d', '%d', '%s', '%s']);
                $this->last_error = 'Duplicate entry';
                return false;
            }
            $r = $this->inner->insert($t, $d, $f);
            $this->sync();
            return $r;
        }
        public function update($t, $d, $w, $f = null, $wf = null) { $r = $this->inner->update($t, $d, $w, $f, $wf); $this->sync(); return $r; }
        public function delete($t, $w, $wf = null) { $r = $this->inner->delete($t, $w, $wf); $this->sync(); return $r; }
    };
    $race_ok = true;
    try {
        (new AA_Canonical_Family_Provisioner($race))->ensure_declared_families($registry);
    } catch (CanonicalFamilyProvisioningFailed $e) {
        $race_ok = false;
    }
    ac_assert('MySQL: carrera UNIQUE → relectura y éxito', $race_ok
        && (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f_table}` WHERE family_key = 'archive'") === 1);

    // Error real INSERT sin fila concurrente
    $hard_fail = new class($wpdb) {
        public $inner;
        public $prefix;
        public $last_error = '';
        public $insert_id = 0;
        public function __construct($inner) {
            $this->inner = $inner;
            $this->prefix = $inner->prefix;
        }
        private function sync(): void {
            $this->prefix = $this->inner->prefix;
            $this->last_error = (string) ($this->inner->last_error ?? '');
            $this->insert_id = (int) ($this->inner->insert_id ?? 0);
        }
        public function prepare($q, ...$a) { return $this->inner->prepare($q, ...$a); }
        public function query($sql) { $r = $this->inner->query($sql); $this->sync(); return $r; }
        public function get_var($q = null, $x = 0, $y = 0) { $r = $this->inner->get_var($q, $x, $y); $this->sync(); return $r; }
        public function get_row($q = null, $o = OBJECT, $y = 0) { $r = $this->inner->get_row($q, $o, $y); $this->sync(); return $r; }
        public function get_results($q = null, $o = OBJECT) { $r = $this->inner->get_results($q, $o); $this->sync(); return $r; }
        public function insert($t, $d, $f = null) {
            $this->last_error = 'forced hard fail';
            return false;
        }
        public function update($t, $d, $w, $f = null, $wf = null) { $r = $this->inner->update($t, $d, $w, $f, $wf); $this->sync(); return $r; }
        public function delete($t, $w, $wf = null) { $r = $this->inner->delete($t, $w, $wf); $this->sync(); return $r; }
    };
    $wpdb->query("DELETE FROM `{$f_table}` WHERE family_key = 'archive'");
    $hard = false;
    try {
        (new AA_Canonical_Family_Provisioner($hard_fail))->ensure_declared_families($registry);
    } catch (CanonicalFamilyProvisioningFailed $e) {
        $hard = true;
    }
    ac_assert('MySQL: INSERT fallido sin fila → excepción', $hard);

    // Tabla ausente
    $missing_prefix = 'tmp_cfamx_' . substr(md5(uniqid('x', true)), 0, 8) . '_';
    $wpdb->prefix = $missing_prefix;
    $missing_ok = false;
    try {
        (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($registry);
    } catch (CanonicalFamilyProvisioningFailed $e) {
        $missing_ok = strpos($e->getMessage(), 'missing') !== false;
    }
    ac_assert('MySQL: tabla ausente → excepción', $missing_ok);
    $wpdb->prefix = $temp_prefix;

    // Prefijo 2 aislado
    $wpdb->prefix = $temp_prefix_2;
    $cleanup($temp_prefix_2);
    AA_Canonical_Schema::install();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($registry);
    $count_p2 = (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . AA_Canonical_Schema::families_table_name() . '`');
    $wpdb->prefix = $temp_prefix;
    $count_p1 = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f_table}`");
    ac_assert('MySQL: prefijos aislados', $count_p2 === 2 && $count_p1 >= 2);

    // Legacy finance tables no tocadas en este prefijo (no existen)
    $fin_like = $wpdb->esc_like($temp_prefix . 'aa_finance_') . '%';
    $fin_tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $fin_like));
    ac_assert('MySQL: sin tablas finance en prefijo de prueba', $fin_tables === [] || $fin_tables === null);

} finally {
    $cleanup($temp_prefix);
    $cleanup($temp_prefix_2);
    $wpdb->prefix = $original_prefix;
    echo "Limpieza garantizada.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
