<?php
/**
 * Canonical Page — Resultado tipado de una consulta de lectura de contenedores.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-container.php';
}

final class CanonicalPage {

    /** @var list<AA_Canonical_Container> */
    private $items;

    /** @var int */
    private $page;

    /** @var int */
    private $per_page;

    /** @var int */
    private $total;

    /** @var int */
    private $total_pages;

    /** @var bool */
    private $has_previous;

    /** @var bool */
    private $has_next;

    /**
     * @param list<AA_Canonical_Container> $items
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
            if (!$item instanceof AA_Canonical_Container) {
                throw new \InvalidArgumentException('[invalid_items] CanonicalPage items must be AA_Canonical_Container.');
            }
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('[invalid_page] page must be >= 1.');
        }
        if ($per_page < 1) {
            throw new \InvalidArgumentException('[invalid_per_page] per_page must be >= 1.');
        }
        if ($total < 0) {
            throw new \InvalidArgumentException('[invalid_total] total must be >= 0.');
        }
        if ($total_pages < 0) {
            throw new \InvalidArgumentException('[invalid_total_pages] total_pages must be >= 0.');
        }
        if (count($items) > $per_page) {
            throw new \InvalidArgumentException('[invalid_items] items exceed per_page.');
        }

        if ($total === 0) {
            if ($page !== 1 || $total_pages !== 0 || $items !== [] || $has_previous || $has_next) {
                throw new \InvalidArgumentException('[invalid_empty_page] Empty result invariants violated.');
            }
        } else {
            $expected_pages = (int) ceil($total / $per_page);
            if ($total_pages !== $expected_pages) {
                throw new \InvalidArgumentException('[invalid_total_pages] total_pages does not match total/per_page.');
            }
            if ($page > $total_pages) {
                throw new \InvalidArgumentException('[invalid_page] page exceeds total_pages.');
            }
            if ($has_previous !== ($page > 1) || $has_next !== ($page < $total_pages)) {
                throw new \InvalidArgumentException('[invalid_flags] has_previous/has_next inconsistent.');
            }
        }

        $this->items = array_values($items);
        $this->page = $page;
        $this->per_page = $per_page;
        $this->total = $total;
        $this->total_pages = $total_pages;
        $this->has_previous = $has_previous;
        $this->has_next = $has_next;
    }

    /** @return list<AA_Canonical_Container> */
    public function items(): array {
        return $this->items;
    }

    public function page(): int {
        return $this->page;
    }

    public function per_page(): int {
        return $this->per_page;
    }

    public function total(): int {
        return $this->total;
    }

    public function total_pages(): int {
        return $this->total_pages;
    }

    public function has_previous(): bool {
        return $this->has_previous;
    }

    public function has_next(): bool {
        return $this->has_next;
    }
}
