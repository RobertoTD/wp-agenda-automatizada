<?php
/**
 * Canonical Shell Write Ajax Support — preparación común del transporte de mutaciones canónicas (SB1-5C1).
 *
 * Centraliza exclusivamente la preparación que los seis endpoints de escritura repetían:
 * resolución de la identidad declarada, Access Policy, provisioning/enablement, construcción
 * del write gateway por petición y parseo de enteros positivos.
 *
 * Límites: no lee la superglobal de la petición, no ejecuta SQL, no instancia repositories,
 * no conoce comandos ni operaciones CRUD, no abre transacciones, no genera redirects y no
 * emite JSON. Rechaza mediante CanonicalShellWriteAjaxRejection; la respuesta la decide
 * siempre el endpoint.
 *
 * Es soporte de transporte WordPress. La futura API reutilizará Application, no este helper.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalShellWriteAjaxSupport {

    /**
     * Resuelve la identidad declarada, aplica la Access Policy y comprueba provisioning y enablement.
     *
     * Conserva el orden observable de los endpoints: núcleo → ruta → acceso → provisioning → enablement.
     * Devuelve las definiciones autorizadas; la identidad y el manifest los sigue construyendo el
     * endpoint en su punto actual, después de validar y construir su command.
     *
     * @param string $family_key Clave de familia ya saneada por el endpoint.
     * @return array{family: AA_Canonical_Family_Definition}
     * @throws CanonicalShellWriteAjaxRejection unknown_identity 404 | unauthorized 401 | forbidden 403
     *                                          | schema_not_ready 503 | enablement_unavailable 500
     *                                          | family_not_provisioned 409 | family_disabled 409
     */
    public static function authorize_identity(string $family_key): array {
        self::require_dependencies();

        try {
            $registry = AA_Canonical_Core_Bootstrap::instance();
        } catch (\Throwable $e) {
            throw new CanonicalShellWriteAjaxRejection(
                'enablement_unavailable',
                'El núcleo canónico no está disponible.',
                500
            );
        }

        $route = (new ResolveCanonicalRouteUseCase($registry))->execute([
            'family_key' => $family_key,
        ]);

        if (!$route['success']) {
            $code = (string) ($route['error']['code'] ?? '');
            if ($code === 'unknown_family'
                || $code === 'invalid_family_key'
                || $code === 'missing_family'
            ) {
                throw new CanonicalShellWriteAjaxRejection(
                    'unknown_identity',
                    'Familia no reconocida.',
                    404
                );
            }
            throw new CanonicalShellWriteAjaxRejection(
                'enablement_unavailable',
                'El núcleo canónico no está disponible.',
                500
            );
        }

        /** @var AA_Canonical_Family_Definition $family */
        $family = $route['data']['family'];
        $resolved_family_key = $family->key();

        $access = AA_Canonical_Access_Policy::check_family_access($resolved_family_key);
        if (!$access['authorized']) {
            $code = ($access['code'] === AA_Canonical_Access_Policy::CODE_UNAUTHORIZED)
                ? 'unauthorized'
                : 'forbidden';
            throw new CanonicalShellWriteAjaxRejection(
                $code,
                (string) $access['message'],
                (int) $access['status']
            );
        }

        try {
            $snapshot = (new ReadCanonicalFamilyEnablementUseCase(
                new AA_Canonical_Family_Enablement_Store()
            ))->execute($registry);
        } catch (CanonicalFamilyEnablementSchemaNotReady $e) {
            throw new CanonicalShellWriteAjaxRejection(
                'schema_not_ready',
                'El esquema canónico no está listo.',
                503
            );
        } catch (CanonicalFamilyEnablementPersistenceFailed $e) {
            throw new CanonicalShellWriteAjaxRejection(
                'enablement_unavailable',
                'No se pudo consultar el estado de habilitación.',
                500
            );
        } catch (\Throwable $e) {
            throw new CanonicalShellWriteAjaxRejection(
                'enablement_unavailable',
                'No se pudo consultar el estado de habilitación.',
                500
            );
        }

        if (!$snapshot->is_provisioned($resolved_family_key)) {
            throw new CanonicalShellWriteAjaxRejection(
                'family_not_provisioned',
                'Esta familia aún no está provisionada.',
                409
            );
        }
        if (!$snapshot->is_enabled($resolved_family_key)) {
            throw new CanonicalShellWriteAjaxRejection(
                'family_disabled',
                'Este tipo de registro está desactivado.',
                409
            );
        }

        return [
            'family' => $family,
        ];
    }

    /**
     * Construye el gateway de escritura de esta petición: registry nuevo + bootstrap productivo.
     *
     * El registry es local a la construcción; no hay registry estático ni compartido entre
     * peticiones. Se conserva la segunda lectura de enablement que hace el bootstrap.
     *
     * @throws CanonicalShellWriteAjaxRejection schema_not_ready 503 | enablement_unavailable 500
     *                                          | persistence_failed 500
     */
    public static function build_write_gateway(): CanonicalWriteGateway {
        self::require_dependencies();

        $write_registry = new AA_Canonical_Write_Binding_Registry();
        try {
            AA_Canonical_Write_Binding_Bootstrap::register_productive($write_registry);
        } catch (CanonicalFamilyEnablementSchemaNotReady $e) {
            throw new CanonicalShellWriteAjaxRejection(
                'schema_not_ready',
                'El esquema canónico no está listo.',
                503
            );
        } catch (CanonicalFamilyEnablementPersistenceFailed $e) {
            throw new CanonicalShellWriteAjaxRejection(
                'enablement_unavailable',
                'No se pudo consultar el estado de habilitación.',
                500
            );
        } catch (\Throwable $e) {
            throw new CanonicalShellWriteAjaxRejection(
                'persistence_failed',
                'No se pudo preparar la escritura canónica.',
                500
            );
        }

        return new CanonicalWriteGateway($write_registry);
    }

    /**
     * Parseo compartido de enteros positivos. Cada endpoint decide cuándo llamarlo y qué
     * código y mensaje emitir cuando obtiene null.
     *
     * @param mixed $raw
     */
    public static function parse_positive_int($raw): ?int {
        if (is_int($raw)) {
            return $raw >= 1 ? $raw : null;
        }
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        if (!ctype_digit($raw)) {
            return null;
        }
        $value = (int) $raw;
        return $value >= 1 ? $value : null;
    }

    private static function require_dependencies(): void {
        if (!class_exists('CanonicalShellWriteAjaxRejection')) {
            require_once __DIR__ . '/CanonicalShellWriteAjaxRejection.php';
        }
        if (!class_exists('AA_Canonical_Access_Policy')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-canonical-access-policy.php';
        }
        if (!class_exists('AA_Canonical_Core_Bootstrap')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
        }
        if (!class_exists('ResolveCanonicalRouteUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/ResolveCanonicalRouteUseCase.php';
        }
        if (!class_exists('CanonicalFamilyEnablementSchemaNotReady')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalFamilyEnablementSchemaNotReady.php';
        }
        if (!class_exists('CanonicalFamilyEnablementPersistenceFailed')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalFamilyEnablementPersistenceFailed.php';
        }
        if (!class_exists('ReadCanonicalFamilyEnablementUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/ReadCanonicalFamilyEnablementUseCase.php';
        }
        if (!class_exists('AA_Canonical_Family_Enablement_Store')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/canonical/class-aa-canonical-family-enablement-store.php';
        }
        if (!class_exists('AA_Canonical_Write_Binding_Registry')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/canonical/class-aa-canonical-write-binding-registry.php';
        }
        if (!class_exists('AA_Canonical_Write_Binding_Bootstrap')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/canonical/class-aa-canonical-write-binding-bootstrap.php';
        }
        if (!class_exists('CanonicalWriteGateway')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalWriteGateway.php';
        }
    }
}
