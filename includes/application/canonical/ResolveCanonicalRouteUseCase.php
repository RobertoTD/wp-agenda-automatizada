<?php
/**
 * Resolve Canonical Route Use Case — Validación y resolución de ruta canónica.
 *
 * Capa de aplicación: recibe datos limpios y un registro canónico inyectado.
 * No depende de $_GET, WordPress ni HTTP.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Key')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-key.php';
}
if (!class_exists('AA_Canonical_Family_Definition')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-family-definition.php';
}
if (!class_exists('AA_Canonical_Variant_Definition')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-variant-definition.php';
}
if (!class_exists('AA_Canonical_Registry')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-registry.php';
}

final class ResolveCanonicalRouteUseCase {

    /** @var AA_Canonical_Registry */
    private $registry;

    public function __construct(AA_Canonical_Registry $registry) {
        $this->registry = $registry;
    }

    /**
     * @param array{family_key?:mixed, variant_key?:mixed} $input
     * @return array{success:true, data:array{family:AA_Canonical_Family_Definition, variant:AA_Canonical_Variant_Definition, used_default_variant:bool}}|array{success:false, error:array{code:string, message:string}}
     */
    public function execute(array $input): array {
        if (!$this->registry->is_frozen()) {
            return $this->fail('canonical_unavailable', 'El núcleo canónico no está disponible.');
        }

        // 1. Validar family_key
        if (!array_key_exists('family_key', $input) || $input['family_key'] === null) {
            return $this->fail('missing_family', 'Familia canónica requerida.');
        }

        $raw_family = $input['family_key'];
        if (!is_string($raw_family) || $raw_family === '' || !AA_Canonical_Key::is_valid($raw_family)) {
            return $this->fail('invalid_family_key', 'Clave de familia no válida.');
        }
        $family_key = $raw_family;

        // 2. Resolver familia en el registro
        if (!$this->registry->has_family($family_key)) {
            return $this->fail('unknown_family', sprintf('Familia canónica "%s" no encontrada.', $family_key));
        }
        $family = $this->registry->family($family_key);

        // 3. Validar y resolver variant_key
        $used_default_variant = false;
        if (!array_key_exists('variant_key', $input) || $input['variant_key'] === null) {
            $variant_key = $family->default_variant_key();
            $used_default_variant = true;
        } else {
            $raw_variant = $input['variant_key'];
            if (!is_string($raw_variant) || $raw_variant === '' || !AA_Canonical_Key::is_valid($raw_variant)) {
                return $this->fail('invalid_variant_key', 'Clave de variante no válida.');
            }
            $variant_key = $raw_variant;
        }

        // 4. Resolver variante en el registro
        if (!$this->registry->has_variant($family_key, $variant_key)) {
            return $this->fail(
                'unknown_variant',
                sprintf('Variante canónica "%s" no encontrada para la familia "%s".', $variant_key, $family_key)
            );
        }
        $variant = $this->registry->variant($family_key, $variant_key);

        return [
            'success' => true,
            'data' => [
                'family' => $family,
                'variant' => $variant,
                'used_default_variant' => $used_default_variant,
            ],
        ];
    }

    /**
     * @return array{success:false, error:array{code:string, message:string}}
     */
    private function fail(string $code, string $message): array {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
