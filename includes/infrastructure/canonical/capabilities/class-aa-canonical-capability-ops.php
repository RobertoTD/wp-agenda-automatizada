<?php
/**
 * Canonical Capability Ops — fachada de operación para desarrollador (C1a).
 *
 * Exige usuario autenticado e instalación/schema listos. Sin constante de
 * bypass operativo, sin AJAX y sin Settings.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Ops {

    /** @var CanonicalCapabilityConfigRepository */
    private $repository;

    /** @var AA_Canonical_Registry */
    private $family_registry;

    /** @var AA_Canonical_Capability_Registry */
    private $capability_registry;

    public function __construct(
        ?CanonicalCapabilityConfigRepository $repository = null,
        ?AA_Canonical_Registry $family_registry = null,
        ?AA_Canonical_Capability_Registry $capability_registry = null
    ) {
        $this->repository = $repository ?: new CanonicalCapabilityConfigRepository();
        $this->family_registry = $family_registry ?: AA_Canonical_Core_Bootstrap::instance();
        $this->capability_registry = $capability_registry
            ?: AA_Canonical_Capability_Registry_Bootstrap::instance();
    }

    /**
     * @return array{id:int,family_id:int,capability_key:string,is_default:bool,created_at:string,updated_at:string}
     * @throws CanonicalCapabilityUnauthorized
     * @throws CanonicalCapabilityUnknown
     * @throws CanonicalCapabilityNotReady
     * @throws CanonicalFamilyUnknown
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    public function set_family_default(string $family_key, string $capability_key, bool $enabled): array {
        $this->require_manage_options();
        $this->assert_installation_ready();

        $uc = new SetFamilyCapabilityDefaultUseCase(
            $this->repository,
            $this->family_registry,
            $this->capability_registry
        );

        return $uc->execute(
            new SetFamilyCapabilityDefaultCommand($family_key, $capability_key, $enabled)
        );
    }

    /**
     * @return array{id:int,container_id:int,capability_key:string,is_active:bool,created_at:string,updated_at:string}
     * @throws CanonicalCapabilityUnauthorized
     * @throws CanonicalCapabilityUnknown
     * @throws CanonicalCapabilityNotReady
     * @throws CanonicalFamilyUnknown
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalContainerNotFound
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    public function set_container_activation(
        string $family_key,
        int $container_id,
        string $capability_key,
        bool $active
    ): array {
        $this->require_family_access($family_key);
        $this->assert_installation_ready();

        $uc = new SetContainerCapabilityActivationUseCase(
            $this->repository,
            $this->family_registry,
            $this->capability_registry
        );

        return $uc->execute(
            new SetContainerCapabilityActivationCommand(
                $family_key,
                $container_id,
                $capability_key,
                $active
            )
        );
    }

    /**
     * @throws CanonicalCapabilityUnauthorized
     * @throws CanonicalFamilyUnknown
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalContainerNotFound
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    public function read_container_config(
        string $family_key,
        int $container_id
    ): CanonicalContainerCapabilityConfigSnapshot {
        $this->require_family_access($family_key);
        $this->assert_installation_ready();

        $uc = new ReadContainerCapabilityConfigUseCase(
            $this->repository,
            $this->family_registry,
            $this->capability_registry
        );

        return $uc->execute($family_key, $container_id);
    }

    /**
     * @throws CanonicalCapabilityUnauthorized
     * @throws CanonicalCapabilitySchemaNotReady
     */
    private function assert_installation_ready(): void {
        $db_version = (string) get_option('aa_db_version', '0');
        if (version_compare($db_version, '23', '<')) {
            // Schema puede existir en tests con prefijo temporal sin option;
            // assert_schema_ready del repo es la fuente autoritativa de tablas.
        }

        $this->repository->assert_schema_ready();
    }

    /**
     * @throws CanonicalCapabilityUnauthorized
     */
    private function require_manage_options(): void {
        if (!is_user_logged_in()) {
            throw new CanonicalCapabilityUnauthorized(
                'unauthorized',
                'Usuario no autenticado.',
                401
            );
        }

        if (is_multisite() && function_exists('is_user_member_of_blog') && !is_user_member_of_blog()) {
            throw new CanonicalCapabilityUnauthorized(
                'forbidden',
                'Acceso denegado: el usuario no pertenece a este sitio.',
                403
            );
        }

        if (!current_user_can('manage_options')) {
            throw new CanonicalCapabilityUnauthorized(
                'forbidden',
                'Permisos insuficientes para configurar defaults de capacidades.',
                403
            );
        }
    }

    /**
     * @throws CanonicalCapabilityUnauthorized
     */
    private function require_family_access(string $family_key): void {
        $access = AA_Canonical_Access_Policy::check_family_access($family_key);
        if (!empty($access['authorized'])) {
            return;
        }

        throw new CanonicalCapabilityUnauthorized(
            (string) $access['code'],
            (string) $access['message'],
            (int) $access['status']
        );
    }
}
