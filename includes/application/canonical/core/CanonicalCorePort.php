<?php
/** Puerto de persistencia del núcleo canónico libre de extensiones. */
defined('ABSPATH') or die('No direct access');

interface CanonicalCorePort {
    /** @return list<array{id:int,public_id:string,title:string,details:?string,created_at:string,updated_at:string}> */
    public function list_lists(): array;
    /** @return array{items:list<array>,page:int,per_page:int,total:int,total_pages:int}|null */
    public function list_lists_page(int $page, int $per_page): array;
    /** @return array{id:int,public_id:string,title:string,details:?string,created_at:string,updated_at:string}|null */
    public function find_list(int $list_id): ?array;
    /** @return list<array{id:int,public_id:string,container_id:int,title:string,details:?string,created_at:string,updated_at:string}> */
    public function list_records(int $list_id): array;
    /** @return array{items:list<array>,page:int,per_page:int,total:int,total_pages:int}|null */
    public function list_records_page(int $list_id, int $page, int $per_page): array;
    public function count_records(int $list_id): int;
    /** @return array{id:int,public_id:string,title:string,details:?string,created_at:string,updated_at:string} */
    public function create_list(AA_Canonical_Base_Fields $fields): array;
    /** @return array{id:int,public_id:string,title:string,details:?string,created_at:string,updated_at:string}|null */
    public function update_list(int $list_id, AA_Canonical_Base_Fields $fields): ?array;
    public function delete_list(int $list_id): bool;
    /** @return array{id:int,public_id:string,container_id:int,title:string,details:?string,created_at:string,updated_at:string}|null */
    public function create_record(int $list_id, AA_Canonical_Base_Fields $fields): ?array;
    /** @return array{id:int,public_id:string,container_id:int,title:string,details:?string,created_at:string,updated_at:string}|null */
    public function update_record(int $list_id, int $record_id, AA_Canonical_Base_Fields $fields): ?array;
    public function delete_record(int $list_id, int $record_id): bool;
}
