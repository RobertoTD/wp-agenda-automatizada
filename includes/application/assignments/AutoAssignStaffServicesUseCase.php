<?php
/**
 * Auto Assign Staff Services Use Case
 *
 * Asigna automáticamente vínculos staff-servicio al crear staff o servicio,
 * cuando la preferencia global aa_auto_assign_staff_services está activa.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';

final class AutoAssignStaffServicesUseCase {

    /** @var callable(): bool */
    private $is_enabled_resolver;

    /** @var callable(): array<int> */
    private $list_assignable_service_ids;

    /** @var callable(): array<int> */
    private $list_active_staff_ids;

    /** @var callable(int, int): string */
    private $ensure_link;

    /** @var callable(int): bool */
    private $is_service_assignable;

    /**
     * @param callable|null $is_enabled_resolver
     * @param callable|null $list_assignable_service_ids
     * @param callable|null $list_active_staff_ids
     * @param callable|null $ensure_link (int $staff_id, int $service_id) => 'created'|'skipped'|'failed'
     * @param callable|null $is_service_assignable
     */
    public function __construct(
        ?callable $is_enabled_resolver = null,
        ?callable $list_assignable_service_ids = null,
        ?callable $list_active_staff_ids = null,
        ?callable $ensure_link = null,
        ?callable $is_service_assignable = null
    ) {
        $this->is_enabled_resolver = $is_enabled_resolver ?? static function (): bool {
            return (int) get_option('aa_auto_assign_staff_services', 0) === 1;
        };
        $this->list_assignable_service_ids = $list_assignable_service_ids
            ?? [AssignmentsRepository::class, 'list_assignable_service_ids'];
        $this->list_active_staff_ids = $list_active_staff_ids
            ?? [AssignmentsRepository::class, 'list_active_staff_ids'];
        $this->ensure_link = $ensure_link
            ?? [AssignmentsRepository::class, 'ensure_staff_service_link'];
        $this->is_service_assignable = $is_service_assignable
            ?? [AssignmentsRepository::class, 'is_assignable_service'];
    }

    /**
     * @param array<string,mixed> $params trigger, staff_id|service_id
     * @return array{enabled:bool,created:int,skipped:int,errors:array<int,string>}
     */
    public function execute(array $params): array {
        if (!($this->is_enabled_resolver)()) {
            return $this->build_result(false, 0, 0, []);
        }

        $trigger = isset($params['trigger']) ? (string) $params['trigger'] : '';

        if ($trigger === 'staff_created') {
            return $this->handle_staff_created($params);
        }

        if ($trigger === 'service_created') {
            return $this->handle_service_created($params);
        }

        return $this->build_result(true, 0, 0, ['invalid_trigger']);
    }

    /**
     * @param array<string,mixed> $params
     * @return array{enabled:bool,created:int,skipped:int,errors:array<int,string>}
     */
    private function handle_staff_created(array $params): array {
        $staff_id = isset($params['staff_id']) ? (int) $params['staff_id'] : 0;

        if ($staff_id <= 0) {
            return $this->build_result(true, 0, 0, ['invalid_staff_id']);
        }

        $service_ids = ($this->list_assignable_service_ids)();

        return $this->assign_links(
            static function () use ($staff_id, $service_ids): array {
                $pairs = [];

                foreach ($service_ids as $service_id) {
                    $pairs[] = [$staff_id, (int) $service_id];
                }

                return $pairs;
            }
        );
    }

    /**
     * @param array<string,mixed> $params
     * @return array{enabled:bool,created:int,skipped:int,errors:array<int,string>}
     */
    private function handle_service_created(array $params): array {
        $service_id = isset($params['service_id']) ? (int) $params['service_id'] : 0;

        if ($service_id <= 0) {
            return $this->build_result(true, 0, 0, ['invalid_service_id']);
        }

        if (!($this->is_service_assignable)($service_id)) {
            return $this->build_result(true, 0, 0, []);
        }

        $staff_ids = ($this->list_active_staff_ids)();

        return $this->assign_links(
            static function () use ($staff_ids, $service_id): array {
                $pairs = [];

                foreach ($staff_ids as $staff_id) {
                    $pairs[] = [(int) $staff_id, $service_id];
                }

                return $pairs;
            }
        );
    }

    /**
     * @param callable(): array<int, array{0:int,1:int}> $pairs_provider
     * @return array{enabled:bool,created:int,skipped:int,errors:array<int,string>}
     */
    private function assign_links(callable $pairs_provider): array {
        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($pairs_provider() as $pair) {
            $staff_id = (int) ($pair[0] ?? 0);
            $service_id = (int) ($pair[1] ?? 0);

            if ($staff_id <= 0 || $service_id <= 0) {
                $errors[] = 'invalid_pair';
                continue;
            }

            $outcome = ($this->ensure_link)($staff_id, $service_id);

            if ($outcome === 'created') {
                $created++;
                continue;
            }

            if ($outcome === 'skipped') {
                $skipped++;
                continue;
            }

            $errors[] = 'staff_' . $staff_id . '_service_' . $service_id;
            error_log(
                '[AutoAssignStaffServicesUseCase] Failed to ensure staff-service link: '
                . 'staff=' . $staff_id . ', service=' . $service_id
            );
        }

        return $this->build_result(true, $created, $skipped, $errors);
    }

    /**
     * @param array<int,string> $errors
     * @return array{enabled:bool,created:int,skipped:int,errors:array<int,string>}
     */
    private function build_result(bool $enabled, int $created, int $skipped, array $errors): array {
        return [
            'enabled' => $enabled,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}
