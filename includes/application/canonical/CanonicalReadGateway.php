<?php
/**
 * Canonical Read Gateway — Orquestación de lectura canónica (contenedores y registros).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalReadIdentity')) {
    require_once __DIR__ . '/CanonicalReadIdentity.php';
}
if (!interface_exists('CanonicalReadAdapterResolver')) {
    require_once __DIR__ . '/CanonicalReadAdapterResolver.php';
}
if (!interface_exists('CanonicalReadAdapter')) {
    require_once __DIR__ . '/CanonicalReadAdapter.php';
}
if (!class_exists('CanonicalPage')) {
    require_once __DIR__ . '/CanonicalPage.php';
}
if (!class_exists('CanonicalRecordsPage')) {
    require_once __DIR__ . '/CanonicalRecordsPage.php';
}
if (!class_exists('CanonicalPagination')) {
    require_once __DIR__ . '/CanonicalPagination.php';
}
if (!class_exists('CanonicalReadBindingNotFound')) {
    require_once __DIR__ . '/CanonicalReadBindingNotFound.php';
}
if (!class_exists('CanonicalContainerNotFound')) {
    require_once __DIR__ . '/CanonicalContainerNotFound.php';
}
if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-container.php';
}
if (!class_exists('AA_Canonical_Record')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-record.php';
}

final class CanonicalReadGateway {

    public const PAGE_SIZE = 15;

    /** @var CanonicalReadAdapterResolver */
    private $resolver;

    public function __construct(CanonicalReadAdapterResolver $resolver) {
        $this->resolver = $resolver;
    }

    /**
     * @throws CanonicalReadBindingNotFound
     * @throws \InvalidArgumentException Si la página del adaptador viola el contrato.
     */
    public function list_containers(CanonicalReadIdentity $identity, int $page): CanonicalPage {
        if ($page < 1) {
            $page = 1;
        }
        $per_page = self::PAGE_SIZE;

        $adapter = $this->resolver->require($identity);
        $result = $adapter->list_containers($page, $per_page);

        $this->assert_pagination_meta($result->pagination(), count($result->items()));

        $previous = null;
        foreach ($result->items() as $item) {
            if (!$item instanceof AA_Canonical_Container) {
                throw new \InvalidArgumentException('[invalid_page_contract] Invalid item type.');
            }
            $this->assert_order_desc($previous, $item->updated_at(), $item->id());
            $previous = [$item->updated_at(), $item->id()];
        }

        return $result;
    }

    /**
     * @throws CanonicalReadBindingNotFound
     * @throws CanonicalContainerNotFound
     * @throws \InvalidArgumentException
     */
    public function get_container(
        CanonicalReadIdentity $identity,
        int $container_id
    ): AA_Canonical_Container {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_page_contract] container_id must be positive.');
        }

        $adapter = $this->resolver->require($identity);
        $container = $adapter->get_container($container_id);

        if (!$container instanceof AA_Canonical_Container) {
            throw new \InvalidArgumentException('[invalid_page_contract] Invalid container type.');
        }
        if ($container->id() !== $container_id) {
            throw new \InvalidArgumentException('[invalid_page_contract] container id mismatch.');
        }

        return $container;
    }

    /**
     * @throws CanonicalReadBindingNotFound
     * @throws CanonicalContainerNotFound
     * @throws \InvalidArgumentException
     */
    public function list_records(
        CanonicalReadIdentity $identity,
        int $container_id,
        int $page
    ): CanonicalRecordsPage {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_page_contract] container_id must be positive.');
        }
        if ($page < 1) {
            $page = 1;
        }
        $per_page = self::PAGE_SIZE;

        $adapter = $this->resolver->require($identity);
        // Ensure missing parent is never represented as an empty page.
        $this->get_container($identity, $container_id);

        $result = $adapter->list_records(
            $container_id,
            $page,
            $per_page
        );

        $this->assert_pagination_meta($result->pagination(), count($result->items()));

        $previous = null;
        foreach ($result->items() as $item) {
            if (!$item instanceof AA_Canonical_Record) {
                throw new \InvalidArgumentException('[invalid_page_contract] Invalid item type.');
            }
            if ($item->container_id() !== $container_id) {
                throw new \InvalidArgumentException('[invalid_page_contract] container_id mismatch.');
            }
            $this->assert_order_desc($previous, $item->updated_at(), $item->id());
            $previous = [$item->updated_at(), $item->id()];
        }

        return $result;
    }

    private function assert_pagination_meta(CanonicalPagination $meta, int $item_count): void {
        if ($meta->per_page() !== self::PAGE_SIZE) {
            throw new \InvalidArgumentException('[invalid_page_contract] per_page must be 15.');
        }
        if ($item_count > self::PAGE_SIZE) {
            throw new \InvalidArgumentException('[invalid_page_contract] Too many items.');
        }

        if ($meta->total() === 0) {
            if (
                $meta->page() !== 1
                || $meta->total_pages() !== 0
                || $item_count !== 0
                || $meta->has_previous()
                || $meta->has_next()
            ) {
                throw new \InvalidArgumentException('[invalid_page_contract] Empty page contract violated.');
            }
            return;
        }

        $expected_pages = (int) ceil($meta->total() / self::PAGE_SIZE);
        if ($meta->total_pages() !== $expected_pages) {
            throw new \InvalidArgumentException('[invalid_page_contract] total_pages mismatch.');
        }
        if ($meta->page() > $meta->total_pages()) {
            throw new \InvalidArgumentException('[invalid_page_contract] page exceeds total_pages.');
        }
        if ($meta->has_previous() !== ($meta->page() > 1)) {
            throw new \InvalidArgumentException('[invalid_page_contract] has_previous mismatch.');
        }
        if ($meta->has_next() !== ($meta->page() < $meta->total_pages())) {
            throw new \InvalidArgumentException('[invalid_page_contract] has_next mismatch.');
        }
    }

    /**
     * @param array{0:\DateTimeImmutable,1:int}|null $previous
     */
    private function assert_order_desc(?array $previous, \DateTimeImmutable $updated_at, int $id): void {
        if ($previous === null) {
            return;
        }
        $cmp = $updated_at <=> $previous[0];
        if ($cmp > 0) {
            throw new \InvalidArgumentException('[invalid_page_contract] Items not ordered by updated_at DESC.');
        }
        if ($cmp === 0 && $id >= $previous[1]) {
            throw new \InvalidArgumentException('[invalid_page_contract] Items not ordered by id DESC on tie.');
        }
    }
}
