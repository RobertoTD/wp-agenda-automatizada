<?php
/**
 * Canonical Aggregated Container Item — Contenedor con familia asociada (alcance multi-familia).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-container.php';
}

final class CanonicalAggregatedContainerItem {

    /** @var AA_Canonical_Container */
    private $container;

    /** @var string */
    private $family_key;

    /** @var string */
    private $family_label;

    public function __construct(
        AA_Canonical_Container $container,
        string $family_key,
        string $family_label
    ) {
        $key = trim($family_key);
        if ($key === '') {
            throw new \InvalidArgumentException('[invalid_family_key] Aggregated item requires family_key.');
        }
        $label = trim($family_label);
        if ($label === '') {
            throw new \InvalidArgumentException('[invalid_family_label] Aggregated item requires family_label.');
        }

        $this->container = $container;
        $this->family_key = $key;
        $this->family_label = $label;
    }

    public function container(): AA_Canonical_Container {
        return $this->container;
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function family_label(): string {
        return $this->family_label;
    }
}
