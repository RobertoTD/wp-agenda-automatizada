<?php
/**
 * Reset destructivo LOCAL para C1.
 * Ejecutar solo mediante WP-CLI con --url=http://localhost/deoia-platform/policyytest/.
 */
if (!defined('ABSPATH') || get_current_blog_id() !== 61 || home_url('/') !== 'http://localhost/deoia-platform/policyytest/') {
    throw new RuntimeException('Este reset solo permite policyytest (blog 61).');
}
global $wpdb;
$names = [
    'aa_canonical_purge_inventory_items','aa_canonical_purge_runs','aa_canonical_image_upload_operations',
    'aa_canonical_record_images','aa_canonical_contact_dossier_applications','aa_canonical_contact_dossier',
    'aa_canonical_record_completion','aa_canonical_record_email','aa_canonical_record_whatsapp','aa_canonical_record_phone',
    'aa_canonical_record_amount','aa_canonical_container_capabilities','aa_canonical_family_capabilities',
    'aa_canonical_records','aa_canonical_containers','aa_canonical_families',
];
foreach ($names as $name) {
    $table = str_replace('`', '``', $wpdb->prefix . $name);
    if ($wpdb->query("DROP TABLE IF EXISTS `{$table}`") === false) throw new RuntimeException("No se pudo borrar {$table}");
}
if (!class_exists('AA_Canonical_Schema')) require_once WP_PLUGIN_DIR . '/wp-agenda-automatizada-feature-admin-ai-assistant/includes/infrastructure/wp/CanonicalSchema.php';
AA_Canonical_Schema::install();
update_option('aa_db_version', AA_Schema::DB_VERSION);
WP_CLI::success('Canon libre C1 reiniciado para policyytest.');
