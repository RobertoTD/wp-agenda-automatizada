<?php
/**
 * Canonical Read Identity — Identidad tipada del flujo de lectura canónica.
 *
 * Application: family_key validado. Sin URL, HTTP ni registry.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Key')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-key.php';
}

final class CanonicalReadIdentity {

    /** @var string */
    private $family_key;

    public function __construct(string $family_key) {
        $this->family_key = AA_Canonical_Key::assert_valid($family_key, 'family_key');
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function qualified_key(): string {
        return $this->family_key;
    }
}
