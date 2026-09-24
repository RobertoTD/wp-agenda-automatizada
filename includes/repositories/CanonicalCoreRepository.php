<?php
/** SQL del núcleo canónico: listas y registros, sin semántica vertical. */
defined('ABSPATH') or die('No direct access');

if (!interface_exists('CanonicalCorePort')) require_once dirname(__DIR__) . '/application/canonical/core/CanonicalCorePort.php';
if (!class_exists('CanonicalCorePersistenceFailed')) require_once dirname(__DIR__) . '/application/canonical/core/CanonicalCorePersistenceFailed.php';
if (!class_exists('CanonicalCoreMutationUncertain')) require_once dirname(__DIR__) . '/application/canonical/core/CanonicalCoreMutationUncertain.php';

final class CanonicalCoreRepository implements CanonicalCorePort {
    /** @var wpdb */
    private $wpdb;
    public function __construct($database = null) {
        if ($database === null) { global $wpdb; $database = $wpdb; }
        if (!is_object($database)) throw new \InvalidArgumentException('wpdb is required.');
        $this->wpdb = $database;
    }
    private function lists_table(): string { return $this->wpdb->prefix . 'aa_canonical_containers'; }
    private function records_table(): string { return $this->wpdb->prefix . 'aa_canonical_records'; }

    public function list_lists(): array {
        return $this->rows('SELECT id,public_id,title,details,created_at,updated_at FROM `' . $this->lists_table() . '` ORDER BY updated_at DESC,id DESC');
    }
    public function list_lists_page(int $page, int $per_page): array { return $this->page($this->lists_table(), null, $page, $per_page); }
    public function find_list(int $list_id): ?array {
        return $this->row($this->wpdb->prepare('SELECT id,public_id,title,details,created_at,updated_at FROM `' . $this->lists_table() . '` WHERE id=%d', $list_id));
    }
    public function list_records(int $list_id): array {
        return $this->rows($this->wpdb->prepare('SELECT id,public_id,container_id,title,details,created_at,updated_at FROM `' . $this->records_table() . '` WHERE container_id=%d ORDER BY updated_at DESC,id DESC', $list_id));
    }
    public function list_records_page(int $list_id, int $page, int $per_page): array {
        if ($this->find_list($list_id) === null) return null;
        return $this->page($this->records_table(), $list_id, $page, $per_page);
    }

    public function create_list(AA_Canonical_Base_Fields $fields): array {
        $table = $this->lists_table();
        $ok = $this->query($this->wpdb->prepare("INSERT INTO `{$table}` (public_id,title,details,created_at,updated_at) VALUES (UUID(),%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP())", $fields->title(), $fields->details()));
        if ($ok === false || (int) $this->wpdb->insert_id < 1) throw new CanonicalCorePersistenceFailed('create_list failed');
        $row = $this->find_list((int) $this->wpdb->insert_id);
        if ($row === null) throw new CanonicalCoreMutationUncertain('created list cannot be confirmed');
        return $row;
    }
    public function update_list(int $list_id, AA_Canonical_Base_Fields $fields): ?array {
        if ($this->find_list($list_id) === null) return null;
        $table = $this->lists_table();
        $ok = $this->query($this->wpdb->prepare("UPDATE `{$table}` SET title=%s,details=%s,updated_at=UTC_TIMESTAMP() WHERE id=%d", $fields->title(), $fields->details(), $list_id));
        if ($ok === false) throw new CanonicalCorePersistenceFailed('update_list failed');
        $row = $this->find_list($list_id);
        if ($row === null) throw new CanonicalCoreMutationUncertain('updated list cannot be confirmed');
        return $row;
    }
    public function delete_list(int $list_id): bool {
        $table = $this->lists_table();
        $result = $this->query($this->wpdb->prepare("DELETE FROM `{$table}` WHERE id=%d", $list_id));
        if ($result === false) throw new CanonicalCorePersistenceFailed('delete_list failed');
        return (int) $result === 1;
    }

    public function create_record(int $list_id, AA_Canonical_Base_Fields $fields): ?array {
        return $this->transaction(function () use ($list_id, $fields) {
            if (!$this->list_exists_for_update($list_id)) return null;
            $table = $this->records_table();
            $ok = $this->query($this->wpdb->prepare("INSERT INTO `{$table}` (public_id,container_id,title,details,created_at,updated_at) VALUES (UUID(),%d,%s,%s,UTC_TIMESTAMP(),UTC_TIMESTAMP())", $list_id, $fields->title(), $fields->details()));
            if ($ok === false || (int) $this->wpdb->insert_id < 1) throw new CanonicalCorePersistenceFailed('create_record failed');
            $record_id = (int) $this->wpdb->insert_id;
            $this->touch_list($list_id);
            return $record_id;
        }, function ($record_id) use ($list_id) {
            if ($record_id === null) return null;
            $row = $this->find_record($list_id, $record_id);
            if ($row === null) throw new CanonicalCoreMutationUncertain('created record cannot be confirmed');
            return $row;
        });
    }
    public function update_record(int $list_id, int $record_id, AA_Canonical_Base_Fields $fields): ?array {
        return $this->transaction(function () use ($list_id, $record_id, $fields) {
            if (!$this->list_exists_for_update($list_id) || $this->find_record($list_id, $record_id) === null) return null;
            $table = $this->records_table();
            if ($this->query($this->wpdb->prepare("UPDATE `{$table}` SET title=%s,details=%s,updated_at=UTC_TIMESTAMP() WHERE id=%d AND container_id=%d", $fields->title(), $fields->details(), $record_id, $list_id)) === false) throw new CanonicalCorePersistenceFailed('update_record failed');
            $this->touch_list($list_id);
            return $record_id;
        }, function ($confirmed_id) use ($list_id) {
            if ($confirmed_id === null) return null;
            $row = $this->find_record($list_id, $confirmed_id);
            if ($row === null) throw new CanonicalCoreMutationUncertain('updated record cannot be confirmed');
            return $row;
        });
    }
    public function delete_record(int $list_id, int $record_id): bool {
        return $this->transaction(function () use ($list_id, $record_id) {
            if (!$this->list_exists_for_update($list_id)) return false;
            $table = $this->records_table();
            $deleted = $this->query($this->wpdb->prepare("DELETE FROM `{$table}` WHERE id=%d AND container_id=%d", $record_id, $list_id));
            if ($deleted === false) throw new CanonicalCorePersistenceFailed('delete_record failed');
            if ((int) $deleted !== 1) return false;
            $this->touch_list($list_id);
            return true;
        }, static function ($result) { return $result; });
    }

    private function page(string $table, ?int $list_id, int $page, int $per_page): array {
        if ($page < 1 || $per_page < 1) throw new \InvalidArgumentException('invalid pagination');
        $where = $list_id === null ? '' : $this->wpdb->prepare(' WHERE container_id=%d', $list_id);
        $total = (int) $this->value("SELECT COUNT(*) FROM `{$table}`{$where}");
        $pages = $total === 0 ? 0 : (int) ceil($total / $per_page);
        $page = $total === 0 ? 1 : min($page, $pages);
        $offset = ($page - 1) * $per_page;
        $columns = $list_id === null ? 'id,public_id,title,details,created_at,updated_at' : 'id,public_id,container_id,title,details,created_at,updated_at';
        $items = $this->rows("SELECT {$columns} FROM `{$table}`{$where} ORDER BY updated_at DESC,id DESC LIMIT " . (int) $per_page . ' OFFSET ' . (int) $offset);
        return ['items' => $items, 'page' => $page, 'per_page' => $per_page, 'total' => $total, 'total_pages' => $pages];
    }
    private function find_record(int $list_id, int $record_id): ?array { return $this->row($this->wpdb->prepare('SELECT id,public_id,container_id,title,details,created_at,updated_at FROM `' . $this->records_table() . '` WHERE id=%d AND container_id=%d', $record_id, $list_id)); }
    private function list_exists_for_update(int $list_id): bool { return $this->value($this->wpdb->prepare('SELECT id FROM `' . $this->lists_table() . '` WHERE id=%d FOR UPDATE', $list_id)) !== null; }
    private function touch_list(int $list_id): void { if ($this->query($this->wpdb->prepare('UPDATE `' . $this->lists_table() . '` SET updated_at=UTC_TIMESTAMP() WHERE id=%d', $list_id)) === false) throw new CanonicalCorePersistenceFailed('touch_list failed'); }
    private function transaction(callable $work, callable $confirm) { if ($this->query('START TRANSACTION') === false) throw new CanonicalCorePersistenceFailed('transaction start failed'); try { $result = $work(); if ($this->query('COMMIT') === false) throw new CanonicalCoreMutationUncertain('commit outcome unknown'); return $confirm($result); } catch (\Throwable $e) { $this->query('ROLLBACK'); throw $e; } }
    private function rows(string $sql): array { $rows = $this->wpdb->get_results($sql, ARRAY_A); if ($rows === false || $this->wpdb->last_error !== '') throw new CanonicalCorePersistenceFailed('read failed'); return $rows; }
    private function row(string $sql): ?array { $row = $this->wpdb->get_row($sql, ARRAY_A); if ($this->wpdb->last_error !== '') throw new CanonicalCorePersistenceFailed('read failed'); return is_array($row) ? $row : null; }
    private function value(string $sql) { $value = $this->wpdb->get_var($sql); if ($this->wpdb->last_error !== '') throw new CanonicalCorePersistenceFailed('read failed'); return $value; }
    private function query(string $sql) { $this->wpdb->last_error = ''; $result = $this->wpdb->query($sql); return $result; }
}
