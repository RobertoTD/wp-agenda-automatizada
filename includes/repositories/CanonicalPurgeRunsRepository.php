<?php
/**
 * Canonical Purge Runs Repository — consulta mínima de corridas abiertas (IMG-3b).
 *
 * Sin runner de purge. Solo bloqueo de attach si hay corrida in_progress|incomplete
 * sobre el registro o su contenedor.
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

final class CanonicalPurgeRunsRepository {

    public const SCOPE_RECORD = 'record';
    public const SCOPE_CONTAINER = 'container';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_INCOMPLETE = 'incomplete';

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
     * True si existe purge abierta sobre el registro o su contenedor.
     *
     * @throws CanonicalImageUploadPersistenceFailed
     * @throws CanonicalImageUploadSchemaNotReady
     */
    public function has_blocking_purge(int $record_id, int $container_id): bool {
        $table = AA_Canonical_Schema::purge_runs_table_name();
        $this->assert_table_exists($table);

        if ($record_id < 1 || $container_id < 1) {
            throw new CanonicalImageUploadPersistenceFailed('Invalid purge target ids.');
        }

        $this->clear_error_state();
        $safe = str_replace('`', '``', $table);
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM `{$safe}`
                 WHERE status IN (%s, %s)
                   AND (
                        (scope = %s AND target_id = %d)
                     OR (scope = %s AND target_id = %d)
                   )
                 LIMIT 1",
                self::STATUS_IN_PROGRESS,
                self::STATUS_INCOMPLETE,
                self::SCOPE_RECORD,
                $record_id,
                self::SCOPE_CONTAINER,
                $container_id
            )
        );

        if ($this->wpdb->last_error !== '') {
            throw new CanonicalImageUploadPersistenceFailed('Failed to query blocking purge runs.');
        }

        return $found !== null && $found !== false && (int) $found >= 1;
    }

    /**
     * @throws CanonicalImageUploadSchemaNotReady
     */
    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $found = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($this->wpdb->last_error !== '' || $found !== $table) {
            throw new CanonicalImageUploadSchemaNotReady('Required purge runs table missing: ' . $table);
        }
    }

    private function clear_error_state(): void {
        $this->wpdb->last_error = '';
    }
}
