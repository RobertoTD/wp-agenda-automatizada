<?php
/**
 * Canonical Read Gateway — Orquestación de lectura canónica de contenedores.
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
if (!class_exists('CanonicalReadBindingNotFound')) {
    require_once __DIR__ . '/CanonicalReadBindingNotFound.php';
}
if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-container.php';
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
        $result = $adapter->list_containers($identity->variant_key(), $page, $per_page);

        $this->assert_valid_page($result, $identity->variant_key());

        return $result;
    }

    private function assert_valid_page(CanonicalPage $page, string $expected_variant_key): void {
        if ($page->per_page() !== self::PAGE_SIZE) {
            throw new \InvalidArgumentException('[invalid_page_contract] per_page must be 15.');
        }

        $items = $page->items();
        if (count($items) > self::PAGE_SIZE) {
            throw new \InvalidArgumentException('[invalid_page_contract] Too many items.');
        }

        $previous = null;
        foreach ($items as $item) {
            if (!$item instanceof AA_Canonical_Container) {
                throw new \InvalidArgumentException('[invalid_page_contract] Invalid item type.');
            }
            if ($item->variant_key() !== $expected_variant_key) {
                throw new \InvalidArgumentException('[invalid_page_contract] variant_key mismatch.');
            }
            if ($previous !== null) {
                $cmp = $item->updated_at() <=> $previous->updated_at();
                if ($cmp > 0) {
                    throw new \InvalidArgumentException('[invalid_page_contract] Items not ordered by updated_at DESC.');
                }
                if ($cmp === 0 && $item->id() >= $previous->id()) {
                    throw new \InvalidArgumentException('[invalid_page_contract] Items not ordered by id DESC on tie.');
                }
            }
            $previous = $item;
        }

        // CanonicalPage constructor already enforces empty/range flag invariants.
        // Re-check empty/out-of-range semantics expected from adapter.
        if ($page->total() === 0) {
            if (
                $page->page() !== 1
                || $page->total_pages() !== 0
                || $items !== []
                || $page->has_previous()
                || $page->has_next()
            ) {
                throw new \InvalidArgumentException('[invalid_page_contract] Empty page contract violated.');
            }
            return;
        }

        $expected_pages = (int) ceil($page->total() / self::PAGE_SIZE);
        if ($page->total_pages() !== $expected_pages) {
            throw new \InvalidArgumentException('[invalid_page_contract] total_pages mismatch.');
        }
        if ($page->page() > $page->total_pages()) {
            throw new \InvalidArgumentException('[invalid_page_contract] page exceeds total_pages.');
        }
        if ($page->has_previous() !== ($page->page() > 1)) {
            throw new \InvalidArgumentException('[invalid_page_contract] has_previous mismatch.');
        }
        if ($page->has_next() !== ($page->page() < $page->total_pages())) {
            throw new \InvalidArgumentException('[invalid_page_contract] has_next mismatch.');
        }
    }
}
