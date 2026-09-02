<?php
/**
 * Canonical Fixture Read Adapter — Solo tests. Ordena el conjunto completo y luego pagina.
 *
 * @package WP_Agenda_Automatizada
 */

if (!interface_exists('CanonicalReadAdapter')) {
    require_once dirname(__DIR__, 3) . '/includes/application/canonical/CanonicalReadAdapter.php';
}
if (!class_exists('CanonicalPage')) {
    require_once dirname(__DIR__, 3) . '/includes/application/canonical/CanonicalPage.php';
}
if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 3) . '/includes/domain/canonical/class-aa-canonical-container.php';
}

final class CanonicalFixtureReadAdapter implements CanonicalReadAdapter {

    /** @var string */
    private $variant_key;

    /** @var list<AA_Canonical_Container> */
    private $containers;

    /**
     * @param list<AA_Canonical_Container> $containers
     */
    public function __construct(string $variant_key, array $containers) {
        $this->variant_key = $variant_key;
        $this->containers = array_values($containers);
    }

    /**
     * Dataset neutral >15 ítems para variant "alpha" (tests).
     *
     * @return list<AA_Canonical_Container>
     */
    public static function build_alpha_dataset(): array {
        $rows = [];
        // Shared timestamp group to exercise id DESC tie-break (ids 3,2,1 same instant).
        $tie = '2026-01-10T12:00:00Z';
        $rows[] = new AA_Canonical_Container(3, 'alpha', 'Elemento gamma', 'Detalle gamma', $tie);
        $rows[] = new AA_Canonical_Container(2, 'alpha', 'Elemento beta', null, $tie);
        $rows[] = new AA_Canonical_Container(1, 'alpha', 'Elemento alpha', 'Detalle alpha', $tie);

        for ($i = 4; $i <= 18; $i++) {
            $day = 20 - ($i - 4); // 20..6
            $stamp = sprintf('2026-01-%02dT08:00:00Z', $day);
            $rows[] = new AA_Canonical_Container(
                $i,
                'alpha',
                'Muestra ' . $i,
                ($i === 7) ? null : ('Nota ' . $i),
                $stamp
            );
        }

        return $rows;
    }

    /**
     * Segundo dataset para variant "beta".
     *
     * @return list<AA_Canonical_Container>
     */
    public static function build_beta_dataset(): array {
        return [
            new AA_Canonical_Container(101, 'beta', 'Catalogo uno', 'Primero', '2026-02-02T10:00:00Z'),
            new AA_Canonical_Container(100, 'beta', 'Catalogo dos', null, '2026-02-01T10:00:00Z'),
        ];
    }

    public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage {
        if ($variant_key !== $this->variant_key) {
            throw new \InvalidArgumentException('[fixture_variant] Unexpected variant_key.');
        }
        if ($page < 1) {
            throw new \InvalidArgumentException('[fixture_page] Adapter expects page >= 1.');
        }
        if ($per_page < 1) {
            throw new \InvalidArgumentException('[fixture_per_page] Adapter expects per_page >= 1.');
        }

        $sorted = $this->containers;
        usort($sorted, static function (AA_Canonical_Container $a, AA_Canonical_Container $b): int {
            $cmp = $b->updated_at() <=> $a->updated_at();
            if ($cmp !== 0) {
                return $cmp;
            }
            return $b->id() <=> $a->id();
        });

        $total = count($sorted);
        if ($total === 0) {
            return new CanonicalPage([], 1, $per_page, 0, 0, false, false);
        }

        $total_pages = (int) ceil($total / $per_page);
        if ($page > $total_pages) {
            $page = $total_pages;
        }

        $offset = ($page - 1) * $per_page;
        $slice = array_slice($sorted, $offset, $per_page);

        return new CanonicalPage(
            $slice,
            $page,
            $per_page,
            $total,
            $total_pages,
            $page > 1,
            $page < $total_pages
        );
    }
}
