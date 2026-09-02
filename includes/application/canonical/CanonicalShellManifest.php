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
if (!class_exists('AA_Canonical_Variant_Definition')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-variant-definition.php';
}

final class CanonicalShellManifest {

    /** @var CanonicalReadIdentity */
    private $identity;

    /** @var AA_Canonical_Family_Definition */
    private $family;

    /** @var AA_Canonical_Variant_Definition */
    private $variant;

    public function __construct(
        CanonicalReadIdentity $identity,
        AA_Canonical_Family_Definition $family,
        AA_Canonical_Variant_Definition $variant
    ) {
        if ($identity->family_key() !== $family->key()) {
            throw new \InvalidArgumentException(
                '[invalid_manifest] Identity family_key does not match family definition.'
            );
        }
        if ($identity->variant_key() !== $variant->key()) {
            throw new \InvalidArgumentException(
                '[invalid_manifest] Identity variant_key does not match variant definition.'
            );
        }
        if ($variant->family_key() !== $family->key()) {
            throw new \InvalidArgumentException(
                '[invalid_manifest] Variant does not belong to the given family.'
            );
        }

        $this->identity = $identity;
        $this->family = $family;
        $this->variant = $variant;
    }

    public function identity(): CanonicalReadIdentity {
        return $this->identity;
    }

    public function family(): AA_Canonical_Family_Definition {
        return $this->family;
    }

    public function variant(): AA_Canonical_Variant_Definition {
        return $this->variant;
    }

    public function family_label(): string {
        return $this->family->label();
    }

    public function variant_label(): string {
        return $this->variant->label();
    }

    public function qualified_key(): string {
        return $this->identity->qualified_key();
    }
}
