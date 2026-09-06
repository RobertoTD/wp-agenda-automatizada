<?php
/**
 * Read Canonical Shell All Containers — Lectura agregada de listas de familias enabled.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalAggregatedContainersPage')) {
    require_once __DIR__ . '/CanonicalAggregatedContainersPage.php';
}
if (!interface_exists('CanonicalAggregatedContainersPort')) {
    require_once __DIR__ . '/CanonicalAggregatedContainersPort.php';
}
if (!class_exists('CanonicalShellAggregatedReadResult')) {
    require_once __DIR__ . '/CanonicalShellAggregatedReadResult.php';
}
if (!class_exists('CanonicalReadGateway')) {
    require_once __DIR__ . '/CanonicalReadGateway.php';
}
if (!class_exists('CanonicalReadPersistenceFailed')) {
    require_once __DIR__ . '/CanonicalReadPersistenceFailed.php';
}
if (!class_exists('AA_Canonical_Family_Definition')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-family-definition.php';
}

final class ReadCanonicalShellAllContainersUseCase {

    /** @var CanonicalAggregatedContainersPort */
    private $port;

    public function __construct(CanonicalAggregatedContainersPort $port) {
        $this->port = $port;
    }

    /**
     * @param list<AA_Canonical_Family_Definition> $enabled_families
     */
    public function execute(array $enabled_families, int $page): CanonicalShellAggregatedReadResult {
        if ($page < 1) {
            $page = 1;
        }
        $per_page = CanonicalReadGateway::PAGE_SIZE;

        $keys = [];
        $labels = [];
        foreach ($enabled_families as $family) {
            if (!$family instanceof AA_Canonical_Family_Definition) {
                return CanonicalShellAggregatedReadResult::contract_error();
            }
            $key = $family->key();
            $keys[] = $key;
            $labels[$key] = $family->label();
        }

        if ($keys === []) {
            return CanonicalShellAggregatedReadResult::empty_page(
                new CanonicalAggregatedContainersPage([], 1, $per_page, 0, 0, false, false)
            );
        }

        try {
            $canonical_page = $this->port->list_containers($keys, $labels, $page, $per_page);
        } catch (CanonicalReadPersistenceFailed $e) {
            return CanonicalShellAggregatedReadResult::contract_error();
        } catch (\InvalidArgumentException $e) {
            if (strpos($e->getMessage(), '[invalid_page_contract]') === 0
                || strpos($e->getMessage(), '[invalid_family_key]') === 0
            ) {
                return CanonicalShellAggregatedReadResult::contract_error();
            }
            throw $e;
        }

        if ($canonical_page->total() === 0) {
            return CanonicalShellAggregatedReadResult::empty_page($canonical_page);
        }

        return CanonicalShellAggregatedReadResult::resolved_page($canonical_page);
    }
}
