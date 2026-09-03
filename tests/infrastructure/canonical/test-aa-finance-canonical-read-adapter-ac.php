<?php
/**
 * AC Test — Finance canonical read adapter (SB1-4B).
 *
 * Ejecutar: php tests/infrastructure/canonical/test-aa-finance-canonical-read-adapter-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$plugin_root = dirname(__DIR__, 3);
$wp_timezone = new DateTimeZone('America/Mexico_City');

if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone {
        global $wp_timezone;
        return $wp_timezone;
    }
}

require_once $plugin_root . '/includes/infrastructure/wp/FinanceSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-instant.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-record.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalContainerNotFound.php';
require_once $plugin_root . '/includes/infrastructure/canonical/finance/class-aa-finance-canonical-read-adapter.php';

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

/**
 * Mock wpdb mínimo para lecturas Finance del adaptador canónico.
 */
final class FinanceReadAdapterWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    /** @var list<object> */
    public $containers = [];
    /** @var list<object> */
    public $records = [];
    public $fail_next = '';

    public function prepare(string $query, ...$args): string {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? (string) (int) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        return $query;
    }

    /**
     * @return mixed
     */
    public function get_var(string $query) {
        if ($this->fail_next === 'count') {
            $this->last_error = 'Simulated count failure';
            $this->fail_next = '';
            return null;
        }
        $this->last_error = '';

        if (preg_match('/SELECT COUNT\(\*\) FROM `[^`]+` WHERE variant_key =/', $query, $m)) {
            $variant = $this->extract_quoted($query, 'variant_key = ');
            $n = 0;
            foreach ($this->containers as $row) {
                if ($row->variant_key === $variant) {
                    $n++;
                }
            }
            return (string) $n;
        }

        if (preg_match('/SELECT COUNT\(\*\) FROM `[^`]+` WHERE container_id =/', $query)) {
            $container_id = (int) $this->extract_number($query, 'container_id = ');
            $n = 0;
            foreach ($this->records as $row) {
                if ((int) $row->container_id === $container_id) {
                    $n++;
                }
            }
            return (string) $n;
        }

        return null;
    }

    /**
     * @return object|null
     */
    public function get_row(string $query) {
        if ($this->fail_next === 'row') {
            $this->last_error = 'Simulated row failure';
            $this->fail_next = '';
            return null;
        }
        $this->last_error = '';

        if (strpos($query, 'variant_key = ') !== false && strpos($query, 'AND id = ') !== false) {
            $variant = $this->extract_quoted($query, 'variant_key = ');
            $id = (int) $this->extract_number($query, 'AND id = ');
            foreach ($this->containers as $row) {
                if ($row->variant_key === $variant && (int) $row->id === $id) {
                    return clone $row;
                }
            }
            return null;
        }

        return null;
    }

    /**
     * @return array<int,object>|null
     */
    public function get_results(string $query) {
        if ($this->fail_next === 'select') {
            $this->last_error = 'Simulated select failure';
            $this->fail_next = '';
            return null;
        }
        $this->last_error = '';

        if (strpos($query, 'FROM `' . $this->prefix . AA_Finance_Schema::TABLE_CONTAINERS . '`') !== false) {
            $variant = $this->extract_quoted($query, 'variant_key = ');
            $rows = [];
            foreach ($this->containers as $row) {
                if ($row->variant_key === $variant) {
                    $rows[] = clone $row;
                }
            }
            usort($rows, static function (object $a, object $b): int {
                $cmp = strcmp($b->updated_at, $a->updated_at);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return (int) $b->id <=> (int) $a->id;
            });
            return $this->apply_limit_offset($query, $rows);
        }

        if (strpos($query, 'FROM `' . $this->prefix . AA_Finance_Schema::TABLE_RECORDS . '`') !== false) {
            $container_id = (int) $this->extract_number($query, 'container_id = ');
            $rows = [];
            foreach ($this->records as $row) {
                if ((int) $row->container_id === $container_id) {
                    $rows[] = clone $row;
                }
            }
            usort($rows, static function (object $a, object $b): int {
                $cmp = strcmp($b->updated_at, $a->updated_at);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return (int) $b->id <=> (int) $a->id;
            });
            return $this->apply_limit_offset($query, $rows);
        }

        return [];
    }

    /**
     * @param list<object> $rows
     * @return list<object>
     */
    private function apply_limit_offset(string $query, array $rows): array {
        if (!preg_match('/LIMIT (\d+) OFFSET (\d+)/', $query, $m)) {
            return $rows;
        }
        $limit = (int) $m[1];
        $offset = (int) $m[2];
        return array_slice($rows, $offset, $limit);
    }

    private function extract_quoted(string $query, string $needle): string {
        $pos = strpos($query, $needle);
        if ($pos === false) {
            return '';
        }
        $rest = substr($query, $pos + strlen($needle));
        if ($rest === '' || $rest[0] !== "'") {
            return '';
        }
        $end = strpos($rest, "'", 1);
        return $end === false ? '' : substr($rest, 1, $end - 1);
    }

    private function extract_number(string $query, string $needle): int {
        $pos = strpos($query, $needle);
        if ($pos === false) {
            return 0;
        }
        $rest = trim(substr($query, $pos + strlen($needle)));
        if ($rest !== '' && $rest[0] === "'") {
            $rest = trim($rest, "'");
        }
        return (int) preg_replace('/\D.*/', '', $rest);
    }
}

function make_container(int $id, string $variant, string $title, ?string $details, string $updated_at, ?string $amount = '99.00'): object {
    $row = (object) [
        'id' => $id,
        'variant_key' => $variant,
        'title' => $title,
        'details' => $details,
        'updated_at' => $updated_at,
    ];
    if ($amount !== null) {
        $row->amount = $amount;
    }
    return $row;
}

function make_record(int $id, int $container_id, string $title, ?string $details, string $updated_at, ?string $amount = '10.00'): object {
    $row = (object) [
        'id' => $id,
        'container_id' => $container_id,
        'title' => $title,
        'details' => $details,
        'updated_at' => $updated_at,
    ];
    if ($amount !== null) {
        $row->amount = $amount;
    }
    return $row;
}

echo "=== Finance Canonical Read Adapter ===\n";

$wpdb = new FinanceReadAdapterWpdbMock();
$wpdb->containers = [
    make_container(1, 'general', 'Alpha', 'Detalle A', '2026-03-10 10:00:00'),
    make_container(2, 'general', 'Beta', null, '2026-03-10 10:00:00'),
    make_container(3, 'general', 'Gamma', 'Detalle G', '2026-03-11 08:00:00'),
    make_container(4, 'other', 'Otro', 'X', '2026-03-12 08:00:00'),
];
for ($i = 5; $i <= 20; $i++) {
    $day = 28 - ($i - 5);
    $wpdb->containers[] = make_container(
        $i,
        'general',
        'Item ' . $i,
        ($i === 7) ? null : ('N' . $i),
        sprintf('2026-03-%02d 09:00:00', max(1, $day))
    );
}

$wpdb->records = [
    make_record(1, 1, 'R-A', 'd1', '2026-04-01 12:00:00'),
    make_record(2, 1, 'R-B', null, '2026-04-01 12:00:00'),
    make_record(3, 1, 'R-C', 'd3', '2026-04-02 09:00:00'),
    make_record(99, 2, 'Otro contenedor', 'x', '2026-04-03 09:00:00'),
];

$adapter = new AA_Finance_Canonical_Read_Adapter($wpdb);
$per_page = CanonicalReadGateway::PAGE_SIZE;

$page1 = $adapter->list_containers('general', 1, $per_page);
ac_assert('Containers page 1 resolved', $page1->total() === 19 && count($page1->items()) === 15);
$first = $page1->items()[0];
ac_assert('First container is most recent activity', $first->id() === 5 && $first->title() === 'Item 5');
ac_assert('Container projection has base fields', $first->variant_key() === 'general' && $first->details() === 'N5');
$null_details_item = null;
foreach ($page1->items() as $item) {
    if ($item->id() === 7) {
        $null_details_item = $item;
        break;
    }
}
ac_assert('Container details null preserved', $null_details_item !== null && $null_details_item->details() === null);
$container_arr = $first->to_canonical_array();
ac_assert('Container canonical array excludes amount', !array_key_exists('amount', $container_arr) && !array_key_exists('amount_total', $container_arr));

$page2 = $adapter->list_containers('general', 2, $per_page);
ac_assert('Containers page 2 has remainder', count($page2->items()) === 4 && $page2->has_previous());

$far = $adapter->list_containers('general', 99, $per_page);
ac_assert('Page out of range clamps to last', $far->page() === 2 && count($far->items()) === 4);

$empty_db = new FinanceReadAdapterWpdbMock();
$empty_adapter = new AA_Finance_Canonical_Read_Adapter($empty_db);
$empty_page = $empty_adapter->list_containers('general', 1, $per_page);
ac_assert('Empty containers coherent page', $empty_page->total() === 0 && $empty_page->page() === 1 && $empty_page->items() === []);

$got = $adapter->get_container('general', 2);
ac_assert('get_container returns matching row', $got->id() === 2 && $got->title() === 'Beta');

$missing = false;
try {
    $adapter->get_container('general', 999);
} catch (CanonicalContainerNotFound $e) {
    $missing = $e->variant_key() === 'general' && $e->container_id() === 999;
}
ac_assert('Missing container throws CanonicalContainerNotFound', $missing);

$wrong_variant = false;
try {
    $adapter->get_container('general', 4);
} catch (CanonicalContainerNotFound $e) {
    $wrong_variant = true;
}
ac_assert('Container in other variant not returned without variant filter breach', $wrong_variant);

$records_page = $adapter->list_records('general', 1, 1, $per_page);
ac_assert('Records ordered by updated_at DESC id DESC', $records_page->items()[0]->id() === 3
    && $records_page->items()[1]->id() === 2
    && $records_page->items()[2]->id() === 1);
$rec_arr = $records_page->items()[0]->to_canonical_array();
ac_assert('Record projection excludes amount', !array_key_exists('amount', $rec_arr));
ac_assert('Record details null', $records_page->items()[1]->details() === null);

$empty_records = $adapter->list_records('general', 3, 1, $per_page);
ac_assert('Existing container without records → empty page', $empty_records->total() === 0 && $empty_records->items() === []);

$records_missing = false;
try {
    $adapter->list_records('general', 999, 1, $per_page);
} catch (CanonicalContainerNotFound $e) {
    $records_missing = true;
}
ac_assert('Records for missing container → CanonicalContainerNotFound', $records_missing);

ac_assert('Records isolated by container_id', count($adapter->list_records('general', 1, 1, $per_page)->items()) === 3);

// Timestamp: America/Mexico_City local → UTC Z
$wp_timezone = new DateTimeZone('America/Mexico_City');
$ts_db = new FinanceReadAdapterWpdbMock();
$ts_db->containers = [make_container(1, 'general', 'TZ', null, '2026-06-15 14:30:00')];
$ts_adapter = new AA_Finance_Canonical_Read_Adapter($ts_db);
$tz_item = $ts_adapter->get_container('general', 1);
ac_assert('Local timestamp converted to UTC Z', $tz_item->updated_at_canonical() === '2026-06-15T20:30:00Z');

$wp_timezone = new DateTimeZone('UTC');
$utc_db = new FinanceReadAdapterWpdbMock();
$utc_db->containers = [make_container(1, 'general', 'UTC', null, '2026-01-01 00:00:00')];
$utc_item = (new AA_Finance_Canonical_Read_Adapter($utc_db))->get_container('general', 1);
ac_assert('UTC timezone round-trip', $utc_item->updated_at_canonical() === '2026-01-01T00:00:00Z');

$bad_db = new FinanceReadAdapterWpdbMock();
$bad_db->containers = [make_container(1, 'general', 'Bad', null, 'not-a-date')];
$bad_contract = false;
try {
    (new AA_Finance_Canonical_Read_Adapter($bad_db))->get_container('general', 1);
} catch (InvalidArgumentException $e) {
    $bad_contract = strpos($e->getMessage(), '[invalid_page_contract]') === 0;
}
ac_assert('Invalid timestamp → contract error', $bad_contract);

$fail_db = new FinanceReadAdapterWpdbMock();
$fail_db->containers = [make_container(1, 'general', 'X', null, '2026-01-01 00:00:00')];
$fail_db->fail_next = 'count';
$fail_count = false;
try {
    (new AA_Finance_Canonical_Read_Adapter($fail_db))->list_containers('general', 1, $per_page);
} catch (InvalidArgumentException $e) {
    $fail_count = strpos($e->getMessage(), '[invalid_page_contract]') === 0;
}
ac_assert('SQL count failure ≠ empty set', $fail_count);

$fail_db2 = new FinanceReadAdapterWpdbMock();
$fail_db2->containers = [make_container(1, 'general', 'X', null, '2026-01-01 00:00:00')];
$fail_db2->fail_next = 'select';
$fail_select = false;
try {
    (new AA_Finance_Canonical_Read_Adapter($fail_db2))->list_containers('general', 1, $per_page);
} catch (InvalidArgumentException $e) {
    $fail_select = strpos($e->getMessage(), '[invalid_page_contract]') === 0;
}
ac_assert('SQL select failure ≠ empty page', $fail_select);

$adapter_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/finance/class-aa-finance-canonical-read-adapter.php');
ac_assert('Adapter uses prepare()', strpos($adapter_src, '$this->wpdb->prepare(') !== false);
ac_assert('Adapter selects container base fields only', strpos($adapter_src, 'SELECT id, variant_key, title, details, updated_at') !== false);
ac_assert('Adapter does not select amount', strpos($adapter_src, 'amount') === false);
ac_assert('Adapter uses wp_timezone()', strpos($adapter_src, 'wp_timezone()') !== false);
ac_assert('Tables from schema constants', strpos($adapter_src, 'AA_Finance_Schema::TABLE_CONTAINERS') !== false
    && strpos($adapter_src, 'AA_Finance_Schema::TABLE_RECORDS') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
