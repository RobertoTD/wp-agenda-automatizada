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
if (!class_exists('CanonicalRecordsPage')) {
    require_once dirname(__DIR__, 3) . '/includes/application/canonical/CanonicalRecordsPage.php';
}
if (!class_exists('CanonicalContainerNotFound')) {
    require_once dirname(__DIR__, 3) . '/includes/application/canonical/CanonicalContainerNotFound.php';
}
if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 3) . '/includes/domain/canonical/class-aa-canonical-container.php';
}
if (!class_exists('AA_Canonical_Record')) {
    require_once dirname(__DIR__, 3) . '/includes/domain/canonical/class-aa-canonical-record.php';
}

final class CanonicalFixtureReadAdapter implements CanonicalReadAdapter {

    /** @var string */
    private $family_key;

    /** @var list<AA_Canonical_Container> */
    private $containers;

    /** @var array<int, list<AA_Canonical_Record>> */
    private $records_by_container;

    /**
     * @param list<AA_Canonical_Container>           $containers
     * @param array<int, list<AA_Canonical_Record>> $records_by_container
     */
    public function __construct(string $family_key, array $containers, array $records_by_container = []) {
        $this->family_key = $family_key;
        $this->containers = array_values($containers);
        $this->records_by_container = $records_by_container;
    }

    /**
     * @return list<AA_Canonical_Container>
     */
    public static function build_alpha_dataset(): array {
        $rows = [];
        $tie = '2026-01-10T12:00:00Z';
        $rows[] = new AA_Canonical_Container(3, 'Elemento gamma', 'Detalle gamma', $tie);
        $rows[] = new AA_Canonical_Container(2, 'Elemento beta', null, $tie);
        $rows[] = new AA_Canonical_Container(1, 'Elemento alpha', 'Detalle alpha', $tie);

        for ($i = 4; $i <= 18; $i++) {
            $day = 20 - ($i - 4);
            $stamp = sprintf('2026-01-%02dT08:00:00Z', $day);
            $rows[] = new AA_Canonical_Container(
                $i,
                'Muestra ' . $i,
                ($i === 7) ? null : ('Nota ' . $i),
                $stamp
            );
        }

        return $rows;
    }

    /**
     * @return list<AA_Canonical_Container>
     */
    public static function build_beta_dataset(): array {
        return [
            new AA_Canonical_Container(101, 'Catalogo uno', 'Primero', '2026-02-02T10:00:00Z'),
            new AA_Canonical_Container(100, 'Catalogo dos', null, '2026-02-01T10:00:00Z'),
        ];
    }

    /**
     * Dataset de registros para container_id=1 (>15) y container_id=2 vacío.
     *
     * @return array<int, list<AA_Canonical_Record>>
     */
    public static function build_alpha_records_dataset(): array {
        $many = [];
        for ($r = 1; $r <= 18; $r++) {
            $day = 28 - ($r - 1);
            if ($day < 1) {
                $day = 1;
            }
            $many[] = new AA_Canonical_Record(
                $r,
                1,
                'Entrada ' . $r,
                ($r === 4) ? null : ('Nota ' . $r),
                sprintf('2026-02-%02dT11:00:00Z', $day)
            );
        }

        $tie = '2026-02-15T09:00:00Z';
        $ties = [
            new AA_Canonical_Record(33, 3, 'Tie gamma', 'g', $tie),
            new AA_Canonical_Record(32, 3, 'Tie beta', null, $tie),
            new AA_Canonical_Record(31, 3, 'Tie alpha', 'a', $tie),
        ];

        return [
            1 => $many,
            2 => [],
            3 => $ties,
        ];
    }

    public function list_containers(int $page, int $per_page): CanonicalPage {
        $this->assert_page($page, $per_page);

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
        $slice = array_slice($sorted, ($page - 1) * $per_page, $per_page);
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

    public function get_container(int $container_id): AA_Canonical_Container {
        foreach ($this->containers as $container) {
            if ($container->id() === $container_id) {
                return $container;
            }
        }
        throw new CanonicalContainerNotFound($this->family_key, $container_id);
    }

    public function list_records(
        int $container_id,
        int $page,
        int $per_page
    ): CanonicalRecordsPage {
        $this->assert_page($page, $per_page);
        $this->get_container($container_id);

        $records = array_values($this->records_by_container[$container_id] ?? []);
        usort($records, static function (AA_Canonical_Record $a, AA_Canonical_Record $b): int {
            $cmp = $b->updated_at() <=> $a->updated_at();
            if ($cmp !== 0) {
                return $cmp;
            }
            return $b->id() <=> $a->id();
        });

        $total = count($records);
        if ($total === 0) {
            return new CanonicalRecordsPage([], 1, $per_page, 0, 0, false, false);
        }
        $total_pages = (int) ceil($total / $per_page);
        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $slice = array_slice($records, ($page - 1) * $per_page, $per_page);
        return new CanonicalRecordsPage(
            $slice,
            $page,
            $per_page,
            $total,
            $total_pages,
            $page > 1,
            $page < $total_pages
        );
    }

    private function assert_page(int $page, int $per_page): void {
        if ($page < 1) {
            throw new \InvalidArgumentException('[fixture_page] Adapter expects page >= 1.');
        }
        if ($per_page < 1) {
            throw new \InvalidArgumentException('[fixture_per_page] Adapter expects per_page >= 1.');
        }
    }
}
