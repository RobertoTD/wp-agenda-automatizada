<?php
/**
 * Persistencia específica de application de contact_dossier por lista.
 *
 * No consulta ni escribe container_capabilities.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalContactDossierApplicationRepository {

    /** @var object */ private $wpdb;

    /** @param object|null $wpdb */
    public function __construct($wpdb = null) {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;
            return;
        }
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function assert_schema_ready(): void {
        $this->assert_table_exists(AA_Canonical_Schema::contact_dossier_applications_table_name());
    }

    /**
     * @return array{contact_container_id:int,is_active:bool,created_at:string,updated_at:string}|null
     */
    public function find(int $contact_container_id): ?array {
        if ($contact_container_id < 1) {
            return null;
        }
        $table = AA_Canonical_Schema::contact_dossier_applications_table_name();
        $this->assert_table_exists($table);
        $this->clear_error_state();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT contact_container_id, is_active, created_at, updated_at
                 FROM `{$table}` WHERE contact_container_id = %d LIMIT 1",
                $contact_container_id
            ),
            ARRAY_A
        );
        if ($row === false || $this->wpdb->last_error !== '') {
            throw new CanonicalSolutionPersistenceFailed('Failed to SELECT contact_dossier application.');
        }

        return $this->map_row(is_array($row) ? $row : null);
    }

    /**
     * @return array{contact_container_id:int,is_active:bool,created_at:string,updated_at:string}
     */
    public function upsert(int $contact_container_id, bool $active, string $now_utc): array {
        if ($contact_container_id < 1 || trim($now_utc) === '') {
            throw new CanonicalSolutionPersistenceFailed('Invalid contact_dossier application input.');
        }
        $table = AA_Canonical_Schema::contact_dossier_applications_table_name();
        $this->assert_table_exists($table);
        $this->clear_error_state();
        $sql = $this->wpdb->prepare(
            "INSERT INTO `{$table}`
                (contact_container_id, is_active, created_at, updated_at)
             VALUES (%d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
                is_active = VALUES(is_active), updated_at = VALUES(updated_at)",
            $contact_container_id,
            $active ? 1 : 0,
            $now_utc,
            $now_utc
        );
        $result = $this->wpdb->query($sql);
        if ($result === false || $this->wpdb->last_error !== '') {
            throw new CanonicalSolutionPersistenceFailed('Failed to UPSERT contact_dossier application.');
        }
        $row = $this->find($contact_container_id);
        if ($row === null) {
            throw new CanonicalSolutionPersistenceFailed('contact_dossier application missing after upsert.');
        }

        return $row;
    }

    /** @param array<string,mixed>|null $row */
    private function map_row(?array $row): ?array {
        if ($row === null) {
            return null;
        }
        return [
            'contact_container_id' => (int) ($row['contact_container_id'] ?? 0),
            'is_active' => !empty($row['is_active']),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function clear_error_state(): void {
        if (isset($this->wpdb->last_error)) {
            $this->wpdb->last_error = '';
        }
    }

    private function assert_table_exists(string $table): void {
        $this->clear_error_state();
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($table))
        );
        if ($this->wpdb->last_error !== '') {
            throw new CanonicalSolutionPersistenceFailed('SHOW TABLES failed for contact_dossier applications.');
        }
        if ($found !== $table) {
            throw new CanonicalSolutionSchemaNotReady('contact_dossier applications table is not ready.');
        }
    }
}
