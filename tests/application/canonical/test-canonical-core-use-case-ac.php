<?php
define('ABSPATH', __DIR__ . '/');
$root = dirname(__DIR__, 3);
require_once $root . '/includes/domain/canonical/class-aa-canonical-base-fields.php';
require_once $root . '/includes/application/canonical/core/CanonicalCorePort.php';
require_once $root . '/includes/application/canonical/core/CanonicalCorePersistenceFailed.php';
require_once $root . '/includes/application/canonical/core/CanonicalCoreMutationUncertain.php';
require_once $root . '/includes/application/canonical/core/CanonicalCoreMutationResult.php';
require_once $root . '/includes/application/canonical/core/CanonicalCoreUseCase.php';

function core_assert($label, $condition): void { if (!$condition) { fwrite(STDERR, "[FAIL] {$label}\n"); exit(1); } echo "[ OK ] {$label}\n"; }

final class CanonicalCoreFakePort implements CanonicalCorePort {
    public $mode = 'ok';
    public $page_args = [];
    public function list_lists(): array { return []; }
    public function list_lists_page(int $page, int $per_page): array { $this->page_args = [$page, $per_page]; return ['items' => [], 'page' => $page, 'per_page' => $per_page, 'total' => 0, 'total_pages' => 0]; }
    public function find_list(int $list_id): ?array { return $list_id === 1 ? ['id' => 1] : null; }
    public function list_records(int $list_id): array { return []; }
    public function list_records_page(int $list_id, int $page, int $per_page): array { return $list_id === 1 ? ['items' => [], 'page' => $page, 'per_page' => $per_page, 'total' => 0, 'total_pages' => 0] : null; }
    public function create_list(AA_Canonical_Base_Fields $fields): array { return $this->outcome(['id' => 7, 'title' => $fields->title()]); }
    public function update_list(int $list_id, AA_Canonical_Base_Fields $fields): ?array { return $list_id === 1 ? $this->outcome(['id' => 1]) : null; }
    public function delete_list(int $list_id): bool { return $list_id === 1; }
    public function create_record(int $list_id, AA_Canonical_Base_Fields $fields): ?array { return $list_id === 1 ? $this->outcome(['id' => 8, 'container_id' => 1]) : null; }
    public function update_record(int $list_id, int $record_id, AA_Canonical_Base_Fields $fields): ?array { return $list_id === 1 && $record_id === 2 ? $this->outcome(['id' => 2, 'container_id' => 1]) : null; }
    public function delete_record(int $list_id, int $record_id): bool { return $list_id === 1 && $record_id === 2; }
    private function outcome(array $row): array { if ($this->mode === 'failed') throw new CanonicalCorePersistenceFailed(); if ($this->mode === 'uncertain') throw new CanonicalCoreMutationUncertain(); return $row; }
}

$port = new CanonicalCoreFakePort();
$use_case = new CanonicalCoreUseCase($port);
$fields = new AA_Canonical_Base_Fields('  Lista base  ', "  detalle\n ");
core_assert('normaliza title y details universales', $fields->title() === 'Lista base' && $fields->details() === 'detalle');
core_assert('create lista confirmado', $use_case->create_list($fields)->state() === CanonicalCoreMutationResult::CONFIRMED);
core_assert('update lista inexistente', $use_case->update_list(99, $fields)->state() === CanonicalCoreMutationResult::NOT_FOUND);
core_assert('delete lista confirmado', $use_case->delete_list(1)->state() === CanonicalCoreMutationResult::CONFIRMED);
core_assert('create record confirma pertenencia', $use_case->create_record(1, $fields)->resource()['container_id'] === 1);
core_assert('update record inexistente', $use_case->update_record(1, 99, $fields)->state() === CanonicalCoreMutationResult::NOT_FOUND);
$port->mode = 'failed';
core_assert('fallo de persistencia es explícito', $use_case->create_list($fields)->state() === CanonicalCoreMutationResult::PERSISTENCE_FAILED);
$port->mode = 'uncertain';
core_assert('resultado incierto es explícito', $use_case->create_list($fields)->state() === CanonicalCoreMutationResult::UNCERTAIN);
$port->mode = 'ok';
$use_case->lists_page(0);
core_assert('paginación normaliza página y fija tamaño', $port->page_args === [1, CanonicalCoreUseCase::PAGE_SIZE]);

echo "--- Resumen: 9/9 ---\n";
