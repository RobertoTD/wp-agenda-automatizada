<?php
/**
 * Canonical Shell Manifest — Contexto canónico completo e inmutable para el shell.
 *
 * Labels se derivan de las definiciones; no se almacenan como copia configurable.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalReadIdentity')) {
    require_once __DIR__ . '/CanonicalReadIdentity.php';
}
if (!class_exists('AA_Canonical_Family_Definition')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-family-definition.php';
}

final class CanonicalShellManifest {

    /** @var CanonicalReadIdentity */
    private $identity;

    /** @var AA_Canonical_Family_Definition */
    private $family;

    public function __construct(
        CanonicalReadIdentity $identity,
        AA_Canonical_Family_Definition $family
    ) {
        if ($identity->family_key() !== $family->key()) {
            throw new \InvalidArgumentException(
                '[invalid_manifest] Identity family_key does not match family definition.'
            );
        }

        $this->identity = $identity;
        $this->family = $family;
    }

    public function identity(): CanonicalReadIdentity {
        return $this->identity;
    }

    public function family(): AA_Canonical_Family_Definition {
        return $this->family;
    }

    public function family_label(): string {
        return $this->family->label();
    }

    public function qualified_key(): string {
        return $this->identity->qualified_key();
    }
}
