<?php
/**
 * Canonical Record Images Repository — SQL de aa_canonical_record_images.
 *
 * Solo suma de consumo confirmado canónico en este incremento (IMG-3a).
 * Sin TX propia.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__) . '/infrastructure/wp/CanonicalSchema.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__) . '/application/storage/CanonicalImageUploadPersistenceFailed.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__) . '/application/storage/CanonicalImageUploadSchemaNotReady.php';
}

final class CanonicalRecordImagesRepository {

    /** @var object */
    private $wpdb;

    /**
     * @param object|null $wpdb
     */
    public function __construct($wpdb = null) {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;
            return;
        }

        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * @return object
     */
    public function connection() {
        return $this->wpdb;
    }

    /**
     * Suma de byte_size de originales confirmados del blog actual ($wpdb->prefix).
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function sum_byte_size_total(): int {
        $table = AA_Canonical_Schema::record_images_table_name();
        $this->assert_table_exists($table);

        $this->clear_error_state();
        $sum = $this->wpdb->get_var("SELECT COALESCE(SUM(byte_size), 0) FROM `{$table}`");

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SUM record images byte_size.');
        }

        if ($sum === null || $sum === false) {
            throw new CanonicalImageUploadPersistenceFailed('Failed to SUM record images byte_size.');
        }

        $value = (int) $sum;
        if ($value < 0) {
            throw new CanonicalImageUploadPersistenceFailed('Record images byte_size sum was negative.');
        }

        return $value;
    }

    /**
     * @throws CanonicalImageUploadSchemaNotReady
     */
    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($this->wpdb->last_error !== '' || $found !== $table) {
            throw new CanonicalImageUploadSchemaNotReady('Required images table missing: ' . $table);
        }
    }

    private function clear_error_state(): void {
        $this->wpdb->last_error = '';
    }
}
