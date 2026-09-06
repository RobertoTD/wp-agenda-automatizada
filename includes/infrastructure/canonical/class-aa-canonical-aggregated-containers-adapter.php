<?php
/**
 * Canonical Aggregated Containers Adapter — Lectura SQL multi-familia vía repositorio.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!interface_exists('CanonicalAggregatedContainersPort')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalAggregatedContainersPort.php';
}
if (!class_exists('CanonicalAggregatedContainerItem')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalAggregatedContainerItem.php';
}
if (!class_exists('CanonicalAggregatedContainersPage')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalAggregatedContainersPage.php';
}
if (!class_exists('CanonicalReadGateway')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadGateway.php';
}
if (!class_exists('CanonicalReadPersistenceFailed')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadPersistenceFailed.php';
}
if (!class_exists('AA_Canonical_Instant')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-instant.php';
}
if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-container.php';
}
if (!class_exists('CanonicalRelationalRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/CanonicalRelationalRepository.php';
}
if (!class_exists('CanonicalRelationalQueryFailed')) {
    require_once dirname(__DIR__, 2) . '/repositories/CanonicalRelationalQueryFailed.php';
}

final class AA_Canonical_Aggregated_Containers_Adapter implements CanonicalAggregatedContainersPort {

    /** @var CanonicalRelationalRepository */
    private $repository;

    public function __construct(CanonicalRelationalRepository $repository) {
        $this->repository = $repository;
    }

    public function list_containers(
        array $family_keys,
        array $labels_by_key,
        int $page,
        int $per_page
    ): CanonicalAggregatedContainersPage {
        if ($page < 1) {
            throw new \InvalidArgumentException('[invalid_page_contract] page must be >= 1.');
        }
        if ($per_page !== CanonicalReadGateway::PAGE_SIZE) {
            throw new \InvalidArgumentException('[invalid_page_contract] per_page must match gateway PAGE_SIZE.');
        }
        if ($family_keys === []) {
            throw new \InvalidArgumentException(
                '[invalid_page_contract] family_keys must not be empty for aggregated queries.'
            );
        }

        $family_ids = [];
        $id_to_key = [];
        foreach ($family_keys as $key) {
            if (!is_string($key) || $key === '') {
                throw new \InvalidArgumentException('[invalid_family_key] Invalid family_key in aggregated list.');
            }
            if (!isset($labels_by_key[$key]) || !is_string($labels_by_key[$key]) || $labels_by_key[$key] === '') {
                throw new \InvalidArgumentException('[invalid_family_key] Missing label for family_key.');
            }
            try {
                $family_id = $this->repository->resolve_family_id($key);
            } catch (CanonicalRelationalQueryFailed $e) {
                throw $this->map_query_failed($e);
            }
            if ($family_id === null) {
                continue;
            }
            $family_ids[] = $family_id;
            $id_to_key[$family_id] = $key;
        }

        if ($family_ids === []) {
            return new CanonicalAggregatedContainersPage([], 1, $per_page, 0, 0, false, false);
        }

        try {
            $total = $this->repository->count_containers_in_families($family_ids);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_query_failed($e);
        }

        if ($total === 0) {
            return new CanonicalAggregatedContainersPage([], 1, $per_page, 0, 0, false, false);
        }

        $total_pages = (int) ceil($total / $per_page);
        if ($page > $total_pages) {
            $page = $total_pages;
        }

        try {
            $rows = $this->repository->list_containers_in_families($family_ids, $page, $per_page);
        } catch (CanonicalRelationalQueryFailed $e) {
            throw $this->map_query_failed($e);
        }

        $items = [];
        $previous = null;
        foreach ($rows as $row) {
            $family_key = (string) ($row['family_key'] ?? '');
            if ($family_key === '' || !isset($labels_by_key[$family_key])) {
                continue;
            }
            $container = $this->map_container($row);
            $this->assert_order_desc($previous, $container->updated_at(), $container->id());
            $previous = [$container->updated_at(), $container->id()];
            $items[] = new CanonicalAggregatedContainerItem(
                $container,
                $family_key,
                (string) $labels_by_key[$family_key]
            );
        }

        return new CanonicalAggregatedContainersPage(
            $items,
            $page,
            $per_page,
            $total,
            $total_pages,
            $page > 1,
            $page < $total_pages
        );
    }

    /**
     * @param array{id:int,title:string,details:?string,updated_at:string} $row
     */
    private function map_container(array $row): AA_Canonical_Container {
        return new AA_Canonical_Container(
            (int) $row['id'],
            (string) $row['title'],
            $row['details'],
            $this->parse_updated_at((string) $row['updated_at'])
        );
    }

    private function parse_updated_at(string $mysql_utc): \DateTimeImmutable {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysql_utc, new \DateTimeZone('UTC'));
        if (!$parsed instanceof \DateTimeImmutable) {
            throw new CanonicalReadPersistenceFailed(
                CanonicalReadPersistenceFailed::REASON_SQL,
                'Invalid updated_at in aggregated container row.'
            );
        }

        return AA_Canonical_Instant::from($parsed)->to_datetime();
    }

    /**
     * @param array{0:\DateTimeImmutable,1:int}|null $previous
     */
    private function assert_order_desc(?array $previous, \DateTimeImmutable $updated_at, int $id): void {
        if ($previous === null) {
            return;
        }
        $prev_at = $previous[0];
        $prev_id = $previous[1];
        if ($updated_at < $prev_at) {
            return;
        }
        if ($updated_at == $prev_at && $id < $prev_id) {
            return;
        }
        throw new \InvalidArgumentException('[invalid_page_contract] Aggregated containers are out of order.');
    }

    private function map_query_failed(CanonicalRelationalQueryFailed $e): CanonicalReadPersistenceFailed {
        return new CanonicalReadPersistenceFailed(
            CanonicalReadPersistenceFailed::REASON_SQL,
            $e->getMessage()
        );
    }
}
