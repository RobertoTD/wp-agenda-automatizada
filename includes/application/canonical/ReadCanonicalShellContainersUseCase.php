<?php
/**
 * Read Canonical Shell Containers — Lectura tipada vía gateway.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalShellManifest')) {
    require_once __DIR__ . '/CanonicalShellManifest.php';
}
if (!class_exists('CanonicalShellReadResult')) {
    require_once __DIR__ . '/CanonicalShellReadResult.php';
}
if (!class_exists('CanonicalReadGateway')) {
    require_once __DIR__ . '/CanonicalReadGateway.php';
}
if (!class_exists('CanonicalReadBindingNotFound')) {
    require_once __DIR__ . '/CanonicalReadBindingNotFound.php';
}

final class ReadCanonicalShellContainersUseCase {

    /** @var CanonicalReadGateway */
    private $gateway;

    public function __construct(CanonicalReadGateway $gateway) {
        $this->gateway = $gateway;
    }

    public function execute(CanonicalShellManifest $manifest, int $page): CanonicalShellReadResult {
        try {
            $canonical_page = $this->gateway->list_containers($manifest->identity(), $page);
        } catch (CanonicalReadBindingNotFound $e) {
            return CanonicalShellReadResult::read_adapter_pending($manifest);
        } catch (\InvalidArgumentException $e) {
            if (strpos($e->getMessage(), '[invalid_page_contract]') === 0) {
                return CanonicalShellReadResult::contract_error($manifest);
            }
            throw $e;
        }

        if ($canonical_page->total() === 0) {
            return CanonicalShellReadResult::empty_page($manifest, $canonical_page);
        }

        return CanonicalShellReadResult::resolved_page($manifest, $canonical_page);
    }
}
