<?php
/**
 * Canonical Relational Read Adapter — Lectura SQL universal vía repositorio.
 *
 * Ligado en construcción a una CanonicalReadIdentity (el puerto no recibe family_key).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Relational
 */

defined('ABSPATH') or die('No direct access');

if (!interface_exists('CanonicalReadAdapter')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalReadAdapter.php';
}
if (!class_exists('CanonicalPage')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalPage.php';
}
if (!class_exists('CanonicalRecordsPage')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalRecordsPage.php';
}
if (!class_exists('CanonicalContainerNotFound')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalContainerNotFound.php';
}
if (!class_exists('CanonicalReadPersistenceFailed')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalReadPersistenceFailed.php';
}
if (!class_exists('CanonicalReadGateway')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalReadGateway.php';
}
if (!class_exists('CanonicalReadIdentity')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalReadIdentity.php';
}
if (!class_exists('AA_Canonical_Instant')) {
    require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-instant.php';
}
if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-container.php';
}
if (!class_exists('AA_Canonical_Record')) {
    require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-record.php';
}
if (!class_exists('CanonicalRelationalRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRelationalRepository.php';
}
if (!class_exists('CanonicalRelationalQueryFailed')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRelationalQueryFailed.php';
}

final class AA_Canonical_Relational_Read_Adapter implements CanonicalReadAdapter {

    /** @var CanonicalRelationalRepository */
    private $repository;

    /** @var CanonicalReadIdentity */
    private $bound_identity;

    /** @var CanonicalRecordsFilter|null */
    private $records_filter;

    public function __construct(
        CanonicalRelationalRepository $repository,
        CanonicalReadIdentity $bound_identity,
        ?CanonicalRecordsFilter $records_filter = null
    ) {
        $this->repository = $repository;
        $this->bound_identity = $bound_identity;
        $this->records_filter = $records_filter;
    }

    public function list_containers(int $page, int $per_page): CanonicalPage {
        $this->assert_page_contract($page, $per_page);

        $family_id = $this->require_family_id();

        try {
            $total = $this->repository->count_containers($family_id);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_query_failed($e);
        }

        if ($total === 0) {
            return new CanonicalPage([], 1, $per_page, 0, 0, false, false);
        }

        $total_pages = (int) ceil($total / $per_page);
        if ($page > $total_pages) {
            $page = $total_pages;
        }

        try {
            $rows = $this->repository->list_containers($family_id, $page, $per_page);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_query_failed($e);
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->map_container($row);
        }

        return new CanonicalPage(
            $items,
            $page,
            $per_page,
            $total,
            $total_pages,
            $page > 1,
            $page < $total_pages
        );
    }

    public function get_container(int $container_id): AA_Canonical_Container {
        $container_id = $this->assert_positive_id($container_id, 'container_id');
        $family_id = $this->require_family_id();

        try {
            $row = $this->repository->find_container($family_id, $container_id);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_query_failed($e);
        }

        if ($row === null) {
            throw new CanonicalContainerNotFound($this->bound_identity->family_key(), $container_id);
        }

        return $this->map_container($row);
    }

    public function list_records(
        int $container_id,
        int $page,
        int $per_page
    ): CanonicalRecordsPage {
        $this->assert_page_contract($page, $per_page);
        $container_id = $this->assert_positive_id($container_id, 'container_id');

        $this->get_container($container_id);

        try {
            $total = $this->records_filter === null
                ? $this->repository->count_records($container_id)
                : $this->records_filter->count($this->repository, $container_id);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_query_failed($e);
        }

        if ($total === 0) {
            return new CanonicalRecordsPage([], 1, $per_page, 0, 0, false, false);
        }

        $total_pages = (int) ceil($total / $per_page);
        if ($page > $total_pages) {
            $page = $total_pages;
        }

        try {
            $rows = $this->records_filter === null
                ? $this->repository->list_records($container_id, $page, $per_page)
                : $this->records_filter->list($this->repository, $container_id, $page, $per_page);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_query_failed($e);
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->map_record($row);
        }

        return new CanonicalRecordsPage(
            $items,
            $page,
            $per_page,
            $total,
            $total_pages,
            $page > 1,
            $page < $total_pages
        );
    }

    private function require_family_id(): int {
        try {
            $family_id = $this->repository->resolve_family_id($this->bound_identity->family_key());
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_query_failed($e);
        }

        if ($family_id === null) {
            throw new CanonicalReadPersistenceFailed(
                CanonicalReadPersistenceFailed::REASON_FAMILY_NOT_PROVISIONED,
                $this->bound_identity->family_key()
            );
        }

        return $family_id;
    }

    private function assert_page_contract(int $page, int $per_page): void {
        if ($page < 1) {
            throw new \InvalidArgumentException('[invalid_page_contract] page must be >= 1.');
        }
        if ($per_page !== CanonicalReadGateway::PAGE_SIZE) {
            throw new \InvalidArgumentException('[invalid_page_contract] per_page must match gateway PAGE_SIZE.');
        }
    }

    private function assert_positive_id(int $id, string $label): int {
        if ($id < 1) {
            throw new \InvalidArgumentException('[invalid_page_contract] ' . $label . ' must be positive.');
        }

        return $id;
    }

    private function map_query_failed(CanonicalRelationalQueryFailed $e): CanonicalReadPersistenceFailed {
        if ($e->code_key() === CanonicalRelationalQueryFailed::CODE_FAMILY_NOT_PROVISIONED) {
            return new CanonicalReadPersistenceFailed(
                CanonicalReadPersistenceFailed::REASON_FAMILY_NOT_PROVISIONED,
                $e->getMessage()
            );
        }

        return new CanonicalReadPersistenceFailed(
            CanonicalReadPersistenceFailed::REASON_SQL,
            $e->getMessage()
        );
    }

    /**
     * @param array{id:int,public_id:string,family_id:int,title:string,details:?string,created_at:string,updated_at:string} $row
     */
    private function map_container(array $row): AA_Canonical_Container {
        return new AA_Canonical_Container(
            (int) $row['id'],
            (string) $row['title'],
            $row['details'],
            $this->mysql_utc_to_instant((string) $row['updated_at'])
        );
    }

    /**
     * @param array{id:int,public_id:string,container_id:int,title:string,details:?string,created_at:string,updated_at:string} $row
     */
    private function map_record(array $row): AA_Canonical_Record {
        return new AA_Canonical_Record(
            (int) $row['id'],
            (int) $row['container_id'],
            (string) $row['title'],
            $row['details'],
            $this->mysql_utc_to_instant((string) $row['updated_at'])
        );
    }

    private function mysql_utc_to_instant(string $mysql_utc): \DateTimeImmutable {
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $mysql_utc)) {
            throw new CanonicalReadPersistenceFailed(
                CanonicalReadPersistenceFailed::REASON_SQL,
                'Invalid UTC timestamp format.'
            );
        }

        $parsed = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $mysql_utc,
            new \DateTimeZone('UTC')
        );
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $parsed === false
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        ) {
            throw new CanonicalReadPersistenceFailed(
                CanonicalReadPersistenceFailed::REASON_SQL,
                'UTC timestamp parse failed.'
            );
        }

        if ($parsed->format('Y-m-d H:i:s') !== $mysql_utc) {
            throw new CanonicalReadPersistenceFailed(
                CanonicalReadPersistenceFailed::REASON_SQL,
                'UTC timestamp round-trip failed.'
            );
        }

        return AA_Canonical_Instant::from($parsed)->to_datetime();
    }
}
