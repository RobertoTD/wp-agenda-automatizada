<?php
/**
 * Comando de captura de inventario de purge canónico (IMG-5 inc. 2).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalPurgeRunsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalPurgeRunsRepository.php';
}

final class CaptureCanonicalPurgeInventoryCommand {

    /** @var string */
    private $scope;

    /** @var int */
    private $target_id;

    /** @var int */
    private $container_id;

    /** @var string */
    private $family_key;

    /** @var int */
    private $max_pages;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $scope,
        int $target_id,
        int $container_id,
        string $family_key,
        int $max_pages = 1
    ) {
        if ($scope !== CanonicalPurgeRunsRepository::SCOPE_RECORD
            && $scope !== CanonicalPurgeRunsRepository::SCOPE_CONTAINER
        ) {
            throw new \InvalidArgumentException('[invalid_purge_scope] scope must be record or container.');
        }
        if ($target_id < 1) {
            throw new \InvalidArgumentException('[invalid_purge_target] target_id must be positive.');
        }
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_purge_container] container_id must be positive.');
        }
        if ($scope === CanonicalPurgeRunsRepository::SCOPE_CONTAINER && $target_id !== $container_id) {
            throw new \InvalidArgumentException('[invalid_purge_container] container scope target_id must equal container_id.');
        }
        $family = trim($family_key);
        if ($family === '') {
            throw new \InvalidArgumentException('[invalid_purge_family] family_key required.');
        }
        if ($max_pages < 1) {
            throw new \InvalidArgumentException('[invalid_purge_max_pages] max_pages must be >= 1.');
        }

        $this->scope = $scope;
        $this->target_id = $target_id;
        $this->container_id = $container_id;
        $this->family_key = $family;
        $this->max_pages = $max_pages;
    }

    public function scope(): string {
        return $this->scope;
    }

    public function target_id(): int {
        return $this->target_id;
    }

    public function container_id(): int {
        return $this->container_id;
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function max_pages(): int {
        return $this->max_pages;
    }
}
