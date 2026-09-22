<?php
/**
 * RVC-1: composition, URL transport, lifecycle and optional isolated MySQL.
 * php tests/application/canonical/capabilities/test-canonical-record-view-composition-ac.php
 * AA_WP_ROOT=/var/www/html/deoia-platform php <same file>
 * SHORTINIT avoids plugin lifecycle. Only connection-local temporary tables are written.
 */
$root = dirname(__DIR__, 4);
$wp_root = getenv('AA_WP_ROOT');
if ($wp_root) {
    define('SHORTINIT', true);
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REQUEST_URI'] = '/deoia-platform/policyytest/';
    $_SERVER['SERVER_NAME'] = 'localhost';
    require rtrim($wp_root, '/') . '/wp-load.php';
} else {
    define('ABSPATH', $root . '/');
}
require_once $root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordsViewRegistry.php';
require_once $root . '/includes/application/canonical/capabilities/completed/CanonicalCompletedRecordsViewProvider.php';
require_once $root . '/includes/repositories/CanonicalRecordsQueryCompiler.php';
require_once $root . '/includes/repositories/capabilities/CanonicalCompletedCriterionCompiler.php';
require_once $root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
if (!function_exists('admin_url')) { function admin_url($path) { return 'https://example.test/wp-admin/' . $path; } }
if (!function_exists('add_query_arg')) { function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); } }
if (!function_exists('wp_parse_url')) { function wp_parse_url($url) { return parse_url($url); } }

$count = 0;
function check($condition, $message) {
    global $count;
    if (!$condition) { throw new RuntimeException($message); }
    $count++;
    echo "[OK] $message\n";
}
function rejects(callable $call, string $class, string $message) {
    try { $call(); } catch (Throwable $e) { check($e instanceof $class, $message); return; }
    throw new RuntimeException('Expected rejection: ' . $message);
}
final class RvcConfig {
    public $active = true;
    public $fail = false;
    public function find_container_capability($id, $key) {
        if ($this->fail) { throw new RuntimeException('config read failed'); }
        return ['is_active' => $this->active ? 1 : 0];
    }
}
final class RvcFlag implements CanonicalRecordCriterion {
    private $value;
    public function __construct(bool $value) { $this->value = $value; }
    public function value(): bool { return $this->value; }
}
final class RvcFlagProvider implements CanonicalCapabilityRecordsViewProvider {
    public $active = true;
    public function capability_key(): string { return 'test_flag'; }
    public function legacy_aliases(): array { return []; }
    public function contributions(string $family_key, int $container_id): ?array {
        return $this->active ? [
            'natural' => new RvcFlag(false),
            'views' => ['flagged' => new CanonicalCapabilityRecordsViewDefinition(
                'flagged',
                'Test flag',
                new RvcFlag(true),
                true
            )],
        ] : null;
    }
}
final class RvcFlagCompiler implements CanonicalRecordCriterionCompiler {
    public function compile(CanonicalRecordCriterion $criterion): CanonicalRecordsPredicate {
        if (!$criterion instanceof RvcFlag) { throw new LogicException('Wrong flag type'); }
        global $wpdb;
        $table = $wpdb->prefix . 'test_flags';
        return new CanonicalRecordsPredicate(($criterion->value() ? '' : 'NOT ') .
            "EXISTS (SELECT 1 FROM `{$table}` flag_state WHERE flag_state.record_id = r.id AND flag_state.value = %s)", ['yes']);
    }
}
$config = new RvcConfig();
$catalog = (new AA_Canonical_Capability_Registry())->register(new AA_Canonical_Capability_Definition('completed', 'record', true))->freeze();
$completed = new CanonicalCompletedRecordsViewProvider($config, $catalog);
$flag = new RvcFlagProvider();
$registry = (new CanonicalCapabilityRecordsViewRegistry())->register($completed)->register($flag)->freeze();
$base = $registry->resolve_query('action', 41);
check(count($base['spec']->criteria()) === 2 && !$base['spec']->criteria()['completed']->completed() && !$base['spec']->criteria()['test_flag']->value(), 'Both natural criteria');
check($base['simple']['active'] && $base['policy']['allows_record_creation'], 'Simple is active and permits record creation');
check(!$base['available'][0]['active'] && $base['available'][0]['target_selections'] === ['completed' => 'completed'], 'Inactive view target adds only its owner');
$done = $registry->resolve_query('action', 41, ['completed' => 'completed']);
check($done['spec']->criteria()['completed']->completed() && !$done['spec']->criteria()['test_flag']->value(), 'View replaces only its owner');
check(!$done['simple']['active'] && !$done['policy']['allows_record_creation'], 'Completed view disallows record creation');
$done_views = array_column($done['available'], null, 'owner');
check($done_views['completed']['active'] && $done_views['completed']['target_selections'] === [], 'Active view target removes only its owner');
check(!$done_views['test_flag']['active'] && $done_views['test_flag']['target_selections'] === ['completed' => 'completed', 'test_flag' => 'flagged'], 'Inactive second view target preserves existing owners');
$flag_only = $registry->resolve_query('action', 41, ['test_flag' => 'flagged']);
check($flag_only['policy']['allows_record_creation'], 'Permissive capability view keeps record creation available');
$both = $registry->resolve_query('action', 41, ['test_flag' => 'flagged', 'completed' => 'completed']);
check($both['spec']->criteria()['completed']->completed() && $both['spec']->criteria()['test_flag']->value(), 'Explicit combination');
check(!$both['policy']['allows_record_creation'], 'Combined policy uses the most restrictive selected view');
$both_views = array_column($both['available'], null, 'owner');
check($both_views['completed']['target_selections'] === ['test_flag' => 'flagged'] && $both_views['test_flag']['target_selections'] === ['completed' => 'completed'], 'Each active toggle removes only its owner');
foreach ($both_views as $owner => $view) {
    $toggle_url = AA_Canonical_Shell_Base_Url_Policy::build_records_url(
        'action',
        41,
        null,
        3,
        'all',
        'simple',
        $view['target_selections']
    );
    parse_str(parse_url($toggle_url, PHP_URL_QUERY), $toggle_query);
    check(
        ($toggle_query['capability_views'] ?? []) === $view['target_selections']
        && !isset($toggle_query['page'])
        && ($toggle_query['containers_page'] ?? '') === '3'
        && ($toggle_query['lists_scope'] ?? '') === 'all',
        'Toggle URL resets record page and preserves origin for ' . $owner
    );
}
$reverse = (new CanonicalCapabilityRecordsViewRegistry())->register($flag)->register($completed)->freeze();
check($reverse->resolve_query('action', 41, $both['selections'])['selections'] === $both['selections'], 'Stable registration order');
rejects(function () use ($registry, $flag) { $registry->register($flag); }, LogicException::class, 'Frozen registry');
rejects(function () use ($flag) { (new CanonicalCapabilityRecordsViewRegistry())->register($flag)->register($flag); }, LogicException::class, 'Duplicate owner rejected');
rejects(function () { new CanonicalCapabilityRecordsViewDefinition('bad key', 'Bad', null, true); }, InvalidArgumentException::class, 'Typed view rejects invalid key');
rejects(function () { new CanonicalCapabilityRecordsViewDefinition('valid', ' ', null, true); }, InvalidArgumentException::class, 'Typed view rejects blank label');
$config->active = false;
$off = $registry->resolve_query('action', 41, $both['selections']);
check($off['redirect'] && $off['criteria_changed'] && $off['selections'] === ['test_flag' => 'flagged'] && count($off['spec']->criteria()) === 1, 'Deactivation removes only own selection and criterion');
$config->active = true;
check($registry->resolve_query('action', 41, $both['selections'])['selections'] === $both['selections'], 'Reactivation restores projection');
$config->fail = true;
rejects(function () use ($registry) { $registry->resolve_query('action', 41); }, RuntimeException::class, 'Read failure never becomes inactive');
$config->fail = false;
$absent = $registry->resolve_query('action', 41, ['removed' => 'old_view', 'test_flag' => 'flagged']);
check($absent['redirect'] && $absent['selections'] === ['test_flag' => 'flagged'], 'Absent package preserves other selections');
rejects(function () use ($registry) { $registry->resolve_query('action', 41, ['completed' => 'unknown']); }, InvalidArgumentException::class, 'Active invalid view rejected');
foreach (['value', ['completed' => []], ['completed'], ['bad key' => 'value']] as $bad) {
    rejects(function () use ($bad) { AA_Canonical_Shell_Base_Url_Policy::parse_capability_views($bad); }, InvalidArgumentException::class, 'Malformed transport rejected');
}
$legacy = $registry->resolve_query('action', 41, [], 'completed');
check($legacy['redirect'] && !$legacy['criteria_changed'] && $legacy['selections'] === ['completed' => 'completed'], 'Legacy alias normalizes without changing criteria');
$not_ready_catalog = (new AA_Canonical_Capability_Registry())->register(new AA_Canonical_Capability_Definition('completed', 'record', false))->freeze();
$not_ready = (new CanonicalCapabilityRecordsViewRegistry())->register(new CanonicalCompletedRecordsViewProvider($config, $not_ready_catalog))->resolve_query('action', 41, ['completed' => 'completed']);
check($not_ready['redirect'] && $not_ready['spec']->criteria() === [], 'Not-ready retires projection');
$other_family = (new CanonicalCapabilityRecordsViewRegistry())->register($completed)->resolve_query('contact', 41, ['completed' => 'completed']);
check($other_family['selections'] === [] && $other_family['spec']->criteria() === [], 'Compatibility belongs to capability');
$url = AA_Canonical_Shell_Base_Url_Policy::build_records_url('action', 41, 2, 3, 'all', 'simple', $both['selections']);
parse_str(parse_url($url, PHP_URL_QUERY), $query);
check($query['capability_views'] === $both['selections'] && $query['page'] === '2' && $query['containers_page'] === '3' && $query['lists_scope'] === 'all', 'URL round trip preserves full context');
check(AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($url), 'Structured URL accepted');
check(!AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($url . '&capability_views=bad'), 'Malformed structured URL rejected');
$return = AA_Canonical_Shell_Base_Url_Policy::parse_mutation_return_context(['capability_views' => $both['selections'], 'page' => '2', 'containers_page' => '3', 'lists_scope' => 'all']);
check($return['capability_views'] === $both['selections'] && $return['page'] === 2, 'Mutation return context retains selections');
check(AA_Canonical_Shell_Base_Url_Policy::parse_mutation_return_context(['capability_views' => ['completed' => []]]) === null, 'Bad mutation context rejected');
$compiler = (new CanonicalRecordsQueryCompiler())->register('completed', new CanonicalCompletedCriterionCompiler())->register('test_flag', new RvcFlagCompiler())->freeze();

if ($wp_root) {
    global $wpdb;
    $original_prefix = $wpdb->prefix;
    $wpdb->prefix = 'rvc1_' . bin2hex(random_bytes(6)) . '_';
    $records = AA_Canonical_Schema::records_table_name();
    $completion = AA_Canonical_Schema::record_completion_table_name();
    $flags = $wpdb->prefix . 'test_flags';
    $tables = [$records, $completion, $flags];
    try {
        foreach ([
            "CREATE TEMPORARY TABLE `{$records}` (id BIGINT PRIMARY KEY, public_id VARCHAR(36), container_id BIGINT, title VARCHAR(100), details TEXT NULL, created_at DATETIME, updated_at DATETIME)",
            "CREATE TEMPORARY TABLE `{$completion}` (record_id BIGINT PRIMARY KEY, completed_at DATETIME)",
            "CREATE TEMPORARY TABLE `{$flags}` (record_id BIGINT, value VARCHAR(20))"
        ] as $sql) { if ($wpdb->query($sql) === false) { throw new RuntimeException($wpdb->last_error); } }
        for ($id = 1; $id <= 65; $id++) {
            $wpdb->insert($records, ['id'=>$id,'public_id'=>sprintf('00000000-0000-4000-8000-%012d', $id),'container_id'=>$id === 65 ? 99 : 41,'title'=>'Record '.$id,'details'=>null,'created_at'=>'2026-01-01 00:00:00','updated_at'=>'2026-01-01 00:00:00']);
            if ($id % 2 === 0) { $wpdb->insert($completion, ['record_id'=>$id, 'completed_at'=>'2026-01-02 00:00:00']); }
            if ($id % 4 >= 2) {
                $wpdb->insert($flags, ['record_id'=>$id,'value'=>'yes']);
                $wpdb->insert($flags, ['record_id'=>$id,'value'=>'yes']);
            }
        }
        $repository = new CanonicalRelationalRepository($wpdb, $compiler);
        foreach ([[], ['completed'=>'completed'], ['test_flag'=>'flagged'], ['completed'=>'completed','test_flag'=>'flagged']] as $selection) {
            $resolution = $registry->resolve_query('action', 41, $selection);
            $predicate = $repository->compile_records_query($resolution['spec']);
            $total = $repository->count_records_matching($predicate);
            $first = $repository->list_records_matching($predicate, 1, 15);
            $second = $repository->list_records_matching($predicate, 2, 15);
            $ids = array_column(array_merge($first, $second), 'id');
            check($total === 16 && count($first) === 15 && count($second) === 1 && count(array_unique($ids)) === 16, 'SQL combination count/page agrees without duplicate joins');
            $expected = [];
            for ($id = 64; $id >= 1; $id--) {
                if (($id % 2 === 0) === isset($selection['completed']) && ($id % 4 >= 2) === isset($selection['test_flag'])) { $expected[] = $id; }
            }
            check($ids === $expected, 'SQL results, ordering and container boundary');
        }
        $config->active = false;
        $off = $registry->resolve_query('action', 41, ['completed'=>'completed']);
        check($repository->count_records_matching($repository->compile_records_query($off['spec'])) === 32, 'SQL inactive completion no longer excludes rows');
        check((int)$wpdb->get_var("SELECT COUNT(*) FROM `{$completion}`") === 32, 'Completion data conserved');
        $config->active = true;
        check($repository->count_records_matching($repository->compile_records_query($registry->resolve_query('action', 41, ['completed'=>'completed'])['spec'])) === 16, 'SQL reactivation restores results');
        $flag->active = false;
        $config->active = false;
        check($repository->count_records_matching($repository->compile_records_query($registry->resolve_query('action', 41)['spec'])) === 64, 'Both inactive returns canonical base');
        $flag->active = true;
        $wpdb->query("DROP TEMPORARY TABLE `{$flags}`");
        $old_suppress = $wpdb->suppress_errors(true);
        rejects(function () use ($repository, $registry) { $repository->count_records_matching($repository->compile_records_query($registry->resolve_query('action', 41)['spec'])); }, CanonicalRelationalQueryFailed::class, 'SQL failure remains an error');
        $wpdb->suppress_errors($old_suppress);
    } finally {
        foreach ($tables as $table) { $wpdb->query("DROP TEMPORARY TABLE IF EXISTS `{$table}`"); }
        $wpdb->prefix = $original_prefix;
    }
} else {
    echo "[SKIP] MySQL: AA_WP_ROOT absent.\n";
}
echo "$count checks passed.\n";
