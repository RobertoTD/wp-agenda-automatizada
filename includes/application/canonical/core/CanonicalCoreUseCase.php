<?php
/** Operaciones universales de listas y registros; no conoce Family ni capabilities. */
defined('ABSPATH') or die('No direct access');

if (!interface_exists('CanonicalCorePort')) require_once __DIR__ . '/CanonicalCorePort.php';
if (!class_exists('CanonicalCorePersistenceFailed')) require_once __DIR__ . '/CanonicalCorePersistenceFailed.php';
if (!class_exists('CanonicalCoreMutationUncertain')) require_once __DIR__ . '/CanonicalCoreMutationUncertain.php';
if (!class_exists('CanonicalCoreMutationResult')) require_once __DIR__ . '/CanonicalCoreMutationResult.php';
if (!class_exists('AA_Canonical_Base_Fields')) require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-base-fields.php';

final class CanonicalCoreUseCase {
    public const PAGE_SIZE = 15;
    /** @var CanonicalCorePort */
    private $port;
    public function __construct(CanonicalCorePort $port) { $this->port = $port; }

    public function lists(): array { return $this->port->list_lists(); }
    public function lists_page(int $page): array { return $this->port->list_lists_page(max(1, $page), self::PAGE_SIZE); }
    public function list(int $list_id): ?array { return $this->port->find_list(self::positive($list_id, 'list_id')); }
    public function records(int $list_id): array { return $this->port->list_records(self::positive($list_id, 'list_id')); }
    public function records_page(int $list_id, int $page): array { return $this->port->list_records_page(self::positive($list_id, 'list_id'), max(1, $page), self::PAGE_SIZE); }

    public function create_list(AA_Canonical_Base_Fields $fields): CanonicalCoreMutationResult { return $this->mutate(function () use ($fields) { return $this->port->create_list($fields); }); }
    public function update_list(int $list_id, AA_Canonical_Base_Fields $fields): CanonicalCoreMutationResult { return $this->mutate(function () use ($list_id, $fields) { return $this->port->update_list(self::positive($list_id, 'list_id'), $fields); }); }
    public function delete_list(int $list_id): CanonicalCoreMutationResult { return $this->mutate(function () use ($list_id) { $id = self::positive($list_id, 'list_id'); return $this->port->delete_list($id) ? ['id' => $id] : null; }); }
    public function create_record(int $list_id, AA_Canonical_Base_Fields $fields): CanonicalCoreMutationResult { return $this->mutate(function () use ($list_id, $fields) { return $this->port->create_record(self::positive($list_id, 'list_id'), $fields); }); }
    public function update_record(int $list_id, int $record_id, AA_Canonical_Base_Fields $fields): CanonicalCoreMutationResult { return $this->mutate(function () use ($list_id, $record_id, $fields) { return $this->port->update_record(self::positive($list_id, 'list_id'), self::positive($record_id, 'record_id'), $fields); }); }
    public function delete_record(int $list_id, int $record_id): CanonicalCoreMutationResult { return $this->mutate(function () use ($list_id, $record_id) { $list_id = self::positive($list_id, 'list_id'); $record_id = self::positive($record_id, 'record_id'); return $this->port->delete_record($list_id, $record_id) ? ['id' => $record_id, 'container_id' => $list_id] : null; }); }

    private function mutate(callable $operation): CanonicalCoreMutationResult {
        try { $resource = $operation(); return $resource === null ? CanonicalCoreMutationResult::not_found() : CanonicalCoreMutationResult::confirmed($resource); }
        catch (CanonicalCoreMutationUncertain $e) { return CanonicalCoreMutationResult::uncertain(); }
        catch (CanonicalCorePersistenceFailed $e) { return CanonicalCoreMutationResult::persistence_failed(); }
    }
    private static function positive(int $value, string $label): int { if ($value < 1) throw new \InvalidArgumentException('[invalid_' . $label . '] must be positive.'); return $value; }
}
