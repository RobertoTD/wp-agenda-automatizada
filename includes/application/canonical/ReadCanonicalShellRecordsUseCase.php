<?php
/**
 * Read Canonical Shell Records — Lectura tipada de registros vía gateway.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalShellManifest')) {
    require_once __DIR__ . '/CanonicalShellManifest.php';
}
if (!class_exists('CanonicalShellRecordsReadResult')) {
    require_once __DIR__ . '/CanonicalShellRecordsReadResult.php';
}
if (!class_exists('CanonicalReadGateway')) {
    require_once __DIR__ . '/CanonicalReadGateway.php';
}
if (!class_exists('CanonicalReadBindingNotFound')) {
    require_once __DIR__ . '/CanonicalReadBindingNotFound.php';
}
if (!class_exists('CanonicalContainerNotFound')) {
    require_once __DIR__ . '/CanonicalContainerNotFound.php';
}

final class ReadCanonicalShellRecordsUseCase {

    /** @var CanonicalReadGateway */
    private $gateway;

    public function __construct(CanonicalReadGateway $gateway) {
        $this->gateway = $gateway;
    }

    public function execute(
        CanonicalShellManifest $manifest,
        int $container_id,
        int $page
    ): CanonicalShellRecordsReadResult {
        try {
            $container = $this->gateway->get_container($manifest->identity(), $container_id);
            $records_page = $this->gateway->list_records(
                $manifest->identity(),
                $container_id,
                $page
            );
        } catch (CanonicalReadBindingNotFound $e) {
            return CanonicalShellRecordsReadResult::read_adapter_pending($manifest);
        } catch (CanonicalContainerNotFound $e) {
            return CanonicalShellRecordsReadResult::container_not_found($manifest);
        } catch (\InvalidArgumentException $e) {
            if (strpos($e->getMessage(), '[invalid_page_contract]') === 0) {
                return CanonicalShellRecordsReadResult::contract_error($manifest);
            }
            throw $e;
        }

        if ($records_page->total() === 0) {
            return CanonicalShellRecordsReadResult::empty_page($manifest, $container, $records_page);
        }

        return CanonicalShellRecordsReadResult::resolved_page($manifest, $container, $records_page);
    }
}
