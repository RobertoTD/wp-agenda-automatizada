<?php
/**
 * Canonical Aggregated Containers Page — Página tipada del listado multi-familia.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalAggregatedContainerItem')) {
    require_once __DIR__ . '/CanonicalAggregatedContainerItem.php';
}
if (!class_exists('CanonicalPagination')) {
    require_once __DIR__ . '/CanonicalPagination.php';
}

final class CanonicalAggregatedContainersPage {

    /** @var list<CanonicalAggregatedContainerItem> */
    private $items;

    /** @var CanonicalPagination */
    private $pagination;

    /**
     * @param list<CanonicalAggregatedContainerItem> $items
     */
    public function __construct(
        array $items,
        int $page,
        int $per_page,
        int $total,
        int $total_pages,
        bool $has_previous,
        bool $has_next
    ) {
        foreach ($items as $item) {
            if (!$item instanceof CanonicalAggregatedContainerItem) {
                throw new \InvalidArgumentException(
                    '[invalid_items] Aggregated page items must be CanonicalAggregatedContainerItem.'
                );
            }
        }

        $normalized = array_values($items);
        $this->pagination = new CanonicalPagination(
            $page,
            $per_page,
            $total,
            $total_pages,
            $has_previous,
            $has_next,
            count($normalized)
        );
        $this->items = $normalized;
    }

    /** @return list<CanonicalAggregatedContainerItem> */
    public function items(): array {
        return $this->items;
    }

    public function pagination(): CanonicalPagination {
        return $this->pagination;
    }

    public function page(): int {
        return $this->pagination->page();
    }

    public function per_page(): int {
        return $this->pagination->per_page();
    }

    public function total(): int {
        return $this->pagination->total();
    }

    public function total_pages(): int {
        return $this->pagination->total_pages();
    }

    public function has_previous(): bool {
        return $this->pagination->has_previous();
    }

    public function has_next(): bool {
        return $this->pagination->has_next();
    }
}
