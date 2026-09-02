<?php
/**
 * Canonical Shell Preview Adapter — TEMPORAL SB1-2B.
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
if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-container.php';
}

/**
 * @internal
 */
final class AA_Canonical_Shell_Preview_Adapter implements CanonicalReadAdapter {

    public const VARIANT_KEY = 'demo';

    /** @var list<AA_Canonical_Container> */
    private $containers;

    public function __construct() {
        $this->containers = self::build_dataset();
    }

    /**
     * @return list<AA_Canonical_Container>
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

        return $rows;
    }

    public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage {
        if ($variant_key !== self::VARIANT_KEY) {
            throw new \InvalidArgumentException('[preview_variant] Unexpected variant_key.');
        }
        if ($page < 1) {
            throw new \InvalidArgumentException('[preview_page] Adapter expects page >= 1.');
        }
        if ($per_page < 1) {
            throw new \InvalidArgumentException('[preview_per_page] Adapter expects per_page >= 1.');
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
