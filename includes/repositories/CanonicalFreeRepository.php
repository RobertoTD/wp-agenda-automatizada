<?php
/** SQL puro del canon libre v2: listas y registros, sin familia. */
defined('ABSPATH') or die('No direct access');

final class CanonicalFreeRepository {
    private $wpdb;
    public function __construct($wpdb = null) { global $wpdb; $this->wpdb = $wpdb ?: $wpdb; }
    private function containers(): string { return $this->wpdb->prefix . 'aa_canonical_containers'; }
    private function records_table(): string { return $this->wpdb->prefix . 'aa_canonical_records'; }
    private function now(): string { return gmdate('Y-m-d H:i:s'); }
    private function uuid(): string { return wp_generate_uuid4(); }
    public function lists(): array {
        $rows = $this->wpdb->get_results("SELECT id,title,details,updated_at FROM `{$this->containers()}` ORDER BY updated_at DESC,id DESC", ARRAY_A);
        if ($rows === false) { throw new RuntimeException('canonical_read_failed'); }
        return $rows;
    }
    public function list(int $id): ?array {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT id,title,details,updated_at FROM `{$this->containers()}` WHERE id=%d", $id), ARRAY_A) ?: null;
    }
    public function records(int $container_id): array {
        $rows = $this->wpdb->get_results($this->wpdb->prepare("SELECT id,title,details,updated_at FROM `{$this->records_table()}` WHERE container_id=%d ORDER BY updated_at DESC,id DESC", $container_id), ARRAY_A);
        if ($rows === false) { throw new RuntimeException('canonical_read_failed'); }
        return $rows;
    }
    public function save_list(?int $id, string $title, ?string $details): int {
        $now=$this->now();
        if ($id !== null) { $ok=$this->wpdb->update($this->containers(), ['title'=>$title,'details'=>$details,'updated_at'=>$now], ['id'=>$id], ['%s','%s','%s'], ['%d']); if ($ok===false || $ok===0 && $this->list($id)===null) throw new RuntimeException('canonical_write_failed'); return $id; }
        $ok=$this->wpdb->insert($this->containers(), ['public_id'=>$this->uuid(),'title'=>$title,'details'=>$details,'created_at'=>$now,'updated_at'=>$now], ['%s','%s','%s','%s','%s']); if (!$ok) throw new RuntimeException('canonical_write_failed'); return (int)$this->wpdb->insert_id;
    }
    public function save_record(?int $id, int $container_id, string $title, ?string $details): int {
        $now=$this->now(); if ($this->list($container_id)===null) throw new RuntimeException('container_not_found');
        if ($id !== null) { $ok=$this->wpdb->update($this->records_table(), ['title'=>$title,'details'=>$details,'updated_at'=>$now], ['id'=>$id,'container_id'=>$container_id], ['%s','%s','%s'], ['%d','%d']); if ($ok===false || $ok===0 && !$this->record_exists($id,$container_id)) throw new RuntimeException('canonical_write_failed'); }
        else { $ok=$this->wpdb->insert($this->records_table(), ['public_id'=>$this->uuid(),'container_id'=>$container_id,'title'=>$title,'details'=>$details,'created_at'=>$now,'updated_at'=>$now], ['%s','%d','%s','%s','%s','%s']); if (!$ok) throw new RuntimeException('canonical_write_failed'); $id=(int)$this->wpdb->insert_id; }
        $this->wpdb->update($this->containers(), ['updated_at'=>$now], ['id'=>$container_id], ['%s'], ['%d']); return $id;
    }
    private function record_exists(int $id,int $container): bool { return (bool)$this->wpdb->get_var($this->wpdb->prepare("SELECT 1 FROM `{$this->records_table()}` WHERE id=%d AND container_id=%d",$id,$container)); }
    public function delete_list(int $id): void { $this->wpdb->delete($this->records_table(), ['container_id'=>$id], ['%d']); if (!$this->wpdb->delete($this->containers(), ['id'=>$id], ['%d'])) throw new RuntimeException('canonical_delete_failed'); }
    public function delete_record(int $id,int $container): void { if (!$this->wpdb->delete($this->records_table(), ['id'=>$id,'container_id'=>$container], ['%d','%d'])) throw new RuntimeException('canonical_delete_failed'); $this->wpdb->update($this->containers(), ['updated_at'=>$this->now()], ['id'=>$container], ['%s'], ['%d']); }
}
