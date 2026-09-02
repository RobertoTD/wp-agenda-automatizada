<?php
/**
 * Canonical Records Page — Resultado tipado de lectura paginada de registros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Record')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-record.php';
}
if (!class_exists('CanonicalPagination')) {
    require_once __DIR__ . '/CanonicalPagination.php';
}

final class CanonicalRecordsPage {

    /** @var list<AA_Canonical_Record> */
    private $items;

    /** @var CanonicalPagination */
    private $pagination;

    /**
     * @param list<AA_Canonical_Record> $items
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
            if (!$item instanceof AA_Canonical_Record) {
                throw new \InvalidArgumentException('[invalid_items] CanonicalRecordsPage items must be AA_Canonical_Record.');
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

    /** @return list<AA_Canonical_Record> */
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
