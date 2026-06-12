<?php
/**
 * Task List Governance Policy — editabilidad y archivado de listas (dominio puro).
 *
 * Sin WordPress ni SQL. Extensible para overrides explícitos futuros.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__) . '/executable/class-aa-executable-contract.php';

final class AA_Task_List_Governance_Policy {

    public const MANAGED_BY_USER = 'user';

    /**
     * @param array<string,mixed> $list Fila o payload con status, source_category y managed_by.
     */
    public function can_edit_list(array $list): bool {
        return $this->is_user_managed_active_list($list);
    }

    /**
     * @param array<string,mixed> $list
     */
    public function can_archive_list(array $list): bool {
        return $this->is_user_managed_active_list($list);
    }

    /**
     * @param array<string,mixed> $list
     */
    public function can_restore_archived_tasks(array $list): bool {
        return $this->is_user_managed_active_list($list);
    }

    /**
     * @param array<string,mixed> $list
     */
    public function can_delete_list(array $list): bool {
        return $this->is_user_list($list);
    }

    /**
     * @param array<string,mixed> $list
     */
    private function is_user_managed_active_list(array $list): bool {
        if (!$this->is_active_list($list)) {
            return false;
        }

        return $this->is_user_list($list);
    }

    /**
     * @param array<string,mixed> $list
     */
    private function is_user_list(array $list): bool {
        return $this->resolve_source_category($list) === AA_Executable_Contract::SOURCE_CATEGORY_USER
            && $this->resolve_managed_by($list) === self::MANAGED_BY_USER;
    }

    /**
     * @param array<string,mixed> $list
     */
    private function is_active_list(array $list): bool {
        $status = is_string($list['status'] ?? null)
            ? strtolower(trim((string) $list['status']))
            : '';

        if ($status === '') {
            return true;
        }

        return $status === AA_Executable_Contract::LIST_STATUS_ACTIVE;
    }

    /**
     * @param array<string,mixed> $list
     */
    private function resolve_source_category(array $list): string {
        $category = is_string($list['source_category'] ?? null)
            ? strtolower(trim((string) $list['source_category']))
            : '';

        if ($category !== '') {
            return $category;
        }

        return AA_Executable_Contract::SOURCE_CATEGORY_USER;
    }

    /**
     * @param array<string,mixed> $list
     */
    private function resolve_managed_by(array $list): string {
        $managed_by = is_string($list['managed_by'] ?? null)
            ? strtolower(trim((string) $list['managed_by']))
            : '';

        if ($managed_by === '') {
            return self::MANAGED_BY_USER;
        }

        return $managed_by;
    }
}
