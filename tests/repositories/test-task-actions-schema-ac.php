<?php
/**
 * AC MC13O-B2 — Schema aa_task_actions.
 *
 * Ejecutar: php tests/repositories/test-task-actions-schema-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';

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

// ─── Estáticos: Schema ───────────────────────────────────────

$schema_src = file_get_contents($schema_file);
ac_assert('Schema file readable', $schema_src !== false);
ac_assert('DB_VERSION is 8', strpos($schema_src, "DB_VERSION = '8'") !== false);
ac_assert(
    'CREATE TABLE aa_task_actions',
    strpos($schema_src, 'aa_task_actions') !== false
        && strpos($schema_src, 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT') !== false
        && strpos($schema_src, 'task_id bigint(20) unsigned NOT NULL') !== false
        && strpos($schema_src, 'action_key varchar(100) NOT NULL') !== false
        && strpos($schema_src, 'type varchar(20) NOT NULL') !== false
        && strpos($schema_src, 'label varchar(120) NOT NULL') !== false
        && strpos($schema_src, "placement varchar(20) NOT NULL DEFAULT 'primary'") !== false
        && strpos($schema_src, "category varchar(20) NOT NULL DEFAULT 'mechanical'") !== false
        && strpos($schema_src, 'target_status varchar(20) DEFAULT NULL') !== false
        && strpos($schema_src, 'target_module varchar(100) DEFAULT NULL') !== false
        && strpos($schema_src, 'target_setup_focus varchar(100) DEFAULT NULL') !== false
        && strpos($schema_src, 'target_fragment varchar(100) DEFAULT NULL') !== false
        && strpos($schema_src, 'url text DEFAULT NULL') !== false
        && strpos($schema_src, 'handler varchar(100) DEFAULT NULL') !== false
        && strpos($schema_src, 'payload_json longtext DEFAULT NULL') !== false
        && strpos($schema_src, 'enabled tinyint(1) NOT NULL DEFAULT 1') !== false
        && strpos($schema_src, 'position int NOT NULL DEFAULT 0') !== false
        && strpos($schema_src, 'created_at datetime DEFAULT CURRENT_TIMESTAMP') !== false
        && strpos($schema_src, 'updated_at datetime DEFAULT NULL') !== false
);
ac_assert(
    'aa_task_actions DDL has expected indexes',
    strpos($schema_src, 'UNIQUE KEY uniq_task_action (task_id, action_key)') !== false
        && strpos($schema_src, 'KEY task_id (task_id)') !== false
        && strpos($schema_src, 'KEY action_key (action_key)') !== false
        && strpos($schema_src, 'KEY type (type)') !== false
        && strpos($schema_src, 'KEY enabled (enabled)') !== false
        && strpos($schema_src, 'KEY position (position)') !== false
);

$actions_block_start = strpos($schema_src, 'aa_task_actions');
$actions_block_end = $actions_block_start !== false
    ? strpos($schema_src, ') $charset;";', $actions_block_start)
    : false;
$actions_block = ($actions_block_start !== false && $actions_block_end !== false)
    ? substr($schema_src, $actions_block_start, $actions_block_end - $actions_block_start)
    : '';
ac_assert(
    'aa_task_actions DDL has no FOREIGN KEY',
    $actions_block !== ''
        && strpos($actions_block, 'FOREIGN KEY') === false
        && strpos($actions_block, 'list_id') === false
        && strpos($actions_block, 'subject_type') === false
        && strpos($actions_block, 'subject_id') === false
);

// ─── Integración WordPress (opcional) ────────────────────────

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $schema_file;

    AA_Schema::install();
    AA_Schema::install();

    global $wpdb;
    $table = $wpdb->prefix . 'aa_task_actions';
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    ac_assert('aa_task_actions table exists after install', $exists === $table, $table);

    foreach ([
        'id',
        'task_id',
        'action_key',
        'type',
        'label',
        'placement',
        'category',
        'target_status',
        'target_module',
        'target_setup_focus',
        'target_fragment',
        'url',
        'handler',
        'payload_json',
        'enabled',
        'position',
        'created_at',
        'updated_at',
    ] as $column) {
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        ac_assert("aa_task_actions has {$column}", !empty($col));
    }

    foreach (['uniq_task_action', 'task_id', 'action_key', 'type', 'enabled', 'position'] as $index) {
        $idx = $wpdb->get_results($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index));
        ac_assert("aa_task_actions has index {$index}", !empty($idx));
    }
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar migración.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
