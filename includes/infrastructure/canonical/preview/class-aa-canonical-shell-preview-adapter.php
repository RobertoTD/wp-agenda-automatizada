<?php
/**
 * Canonical Shell Preview Adapter — TEMPORAL SB1-2B/SB1-3A.
 *
 * Dataset in-memory neutro. Cargar solo cuando AA_CANONICAL_SHELL_PREVIEW === true
 * y shell_mode=preview. No registrar en bootstrap productivo ni en el registry de producto.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Preview
 * @internal Remover al conectar un adaptador real de familia.
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
if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-container.php';
}
if (!class_exists('AA_Canonical_Record')) {
    require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-record.php';
}

/**
 * @internal
 */
final class AA_Canonical_Shell_Preview_Adapter implements CanonicalReadAdapter {

    public const VARIANT_KEY = 'demo';

    /** Contenedor con >15 registros. */
    public const CONTAINER_MANY_RECORDS = 1;

    /** Contenedor existente sin registros. */
    public const CONTAINER_EMPTY_RECORDS = 2;

    /** Contenedor con pocos registros y empates. */
    public const CONTAINER_TIE_RECORDS = 3;

    /** @var list<AA_Canonical_Container> */
    private $containers;

    /** @var array<int, list<AA_Canonical_Record>> */
    private $records_by_container;

    public function __construct() {
        $built = self::build_dataset();
        $this->containers = $built['containers'];
        $this->records_by_container = $built['records'];
    }

    /**
     * @return array{containers:list<AA_Canonical_Container>,records:array<int,list<AA_Canonical_Record>>}
     */
    private static function build_dataset(): array {
        $rows = [];
        $tie = '2026-03-01T15:00:00Z';
        $rows[] = new AA_Canonical_Container(3, self::VARIANT_KEY, 'Elemento gamma', 'Detalle gamma', $tie);
        $rows[] = new AA_Canonical_Container(2, self::VARIANT_KEY, 'Elemento beta', null, $tie);
        $rows[] = new AA_Canonical_Container(1, self::VARIANT_KEY, 'Elemento alpha', 'Detalle alpha', $tie);

        for ($i = 4; $i <= 18; $i++) {
            $day = 20 - ($i - 4);
            $stamp = sprintf('2026-03-%02dT09:00:00Z', $day);
            $details = null;
            if ($i === 7) {
                $details = null;
            } elseif ($i === 9) {
                $details = 'Nota moderada sobre la muestra número nueve para comprobar el truncado visual del detalle en la card del shell.';
            } else {
                $details = 'Nota ' . $i;
            }
            $rows[] = new AA_Canonical_Container(
                $i,
                self::VARIANT_KEY,
                'Muestra ' . $i,
                $details,
                $stamp
            );
        }

        $records = [];
        $records[self::CONTAINER_MANY_RECORDS] = [];
        for ($r = 1; $r <= 18; $r++) {
            $day = 25 - ($r - 1);
            if ($day < 1) {
                $day = 1;
            }
            $stamp = sprintf('2026-04-%02dT10:00:00Z', $day);
            $records[self::CONTAINER_MANY_RECORDS][] = new AA_Canonical_Record(
                $r,
                self::CONTAINER_MANY_RECORDS,
                'Registro ' . $r,
                ($r === 5) ? null : ('Detalle registro ' . $r),
                $stamp
            );
        }

        $records[self::CONTAINER_EMPTY_RECORDS] = [];

        $tie_records = '2026-04-10T12:00:00Z';
        $records[self::CONTAINER_TIE_RECORDS] = [
            new AA_Canonical_Record(203, self::CONTAINER_TIE_RECORDS, 'Registro gamma', 'G', $tie_records),
            new AA_Canonical_Record(202, self::CONTAINER_TIE_RECORDS, 'Registro beta', null, $tie_records),
            new AA_Canonical_Record(201, self::CONTAINER_TIE_RECORDS, 'Registro alpha', 'A', $tie_records),
        ];

        return [
            'containers' => $rows,
            'records' => $records,
        ];
    }

    public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage {
        $this->assert_variant_page($variant_key, $page, $per_page);

        $sorted = $this->containers;
        usort($sorted, static function (AA_Canonical_Container $a, AA_Canonical_Container $b): int {
            $cmp = $b->updated_at() <=> $a->updated_at();
            if ($cmp !== 0) {
                return $cmp;
            }
            return $b->id() <=> $a->id();
        });

        return $this->slice_containers($sorted, $page, $per_page);
    }

    public function get_container(string $variant_key, int $container_id): AA_Canonical_Container {
        if ($variant_key !== self::VARIANT_KEY) {
            throw new \InvalidArgumentException('[preview_variant] Unexpected variant_key.');
        }
        foreach ($this->containers as $container) {
            if ($container->id() === $container_id) {
                return $container;
            }
        }
        throw new CanonicalContainerNotFound($variant_key, $container_id);
    }

    public function list_records(
        string $variant_key,
        int $container_id,
        int $page,
        int $per_page
    ): CanonicalRecordsPage {
        $this->assert_variant_page($variant_key, $page, $per_page);
        $this->get_container($variant_key, $container_id);

        $records = $this->records_by_container[$container_id] ?? [];
        usort($records, static function (AA_Canonical_Record $a, AA_Canonical_Record $b): int {
            $cmp = $b->updated_at() <=> $a->updated_at();
            if ($cmp !== 0) {
                return $cmp;
            }
            return $b->id() <=> $a->id();
        });

        return $this->slice_records($records, $page, $per_page);
    }

    private function assert_variant_page(string $variant_key, int $page, int $per_page): void {
        if ($variant_key !== self::VARIANT_KEY) {
            throw new \InvalidArgumentException('[preview_variant] Unexpected variant_key.');
        }
        if ($page < 1) {
            throw new \InvalidArgumentException('[preview_page] Adapter expects page >= 1.');
        }
        if ($per_page < 1) {
            throw new \InvalidArgumentException('[preview_per_page] Adapter expects per_page >= 1.');
        }
    }

    /**
     * @param list<AA_Canonical_Container> $sorted
     */
    private function slice_containers(array $sorted, int $page, int $per_page): CanonicalPage {
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

    /**
     * @param list<AA_Canonical_Record> $sorted
     */
    private function slice_records(array $sorted, int $page, int $per_page): CanonicalRecordsPage {
        $total = count($sorted);
        if ($total === 0) {
            return new CanonicalRecordsPage([], 1, $per_page, 0, 0, false, false);
        }
        $total_pages = (int) ceil($total / $per_page);
        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $slice = array_slice($sorted, ($page - 1) * $per_page, $per_page);
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
}
