<?php
/**
 * Canonical Family Catalog Lifecycle — Provisioning versionado de aa_canonical_families.
 *
 * Corre en admin_init después de AA_Schema::maybe_migrate. No toca DB_VERSION.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Core_Bootstrap')) {
    require_once __DIR__ . '/class-aa-canonical-core-bootstrap.php';
}
if (!class_exists('AA_Canonical_Family_Provisioner')) {
    require_once __DIR__ . '/class-aa-canonical-family-provisioner.php';
}
if (!class_exists('CanonicalFamilyProvisioningFailed')) {
    require_once __DIR__ . '/CanonicalFamilyProvisioningFailed.php';
}

final class AA_Canonical_Family_Catalog_Lifecycle {

    public const CATALOG_VERSION = 3;

    public const OPTION_VERSION = 'aa_canonical_family_catalog_version';

    public const OPTION_LAST_ERROR = 'aa_canonical_family_catalog_last_error';

    private const MIN_DB_VERSION = '21';

    /** Prioridad admin_init: después de AA_Schema::maybe_migrate (default 10). */
    public const ADMIN_INIT_PRIORITY = 25;

    /** @var callable|null Override solo para tests. */
    private static $provision_override = null;

    /**
     * @param string $main_plugin_file Path absoluto del archivo principal del plugin.
     */
    public static function register(string $main_plugin_file): void {
        add_action('admin_init', [__CLASS__, 'maybe_provision'], self::ADMIN_INIT_PRIORITY);
    }

    /**
     * @internal Solo tests.
     *
     * @param callable|null $override Debe lanzar o completar sin retorno significativo.
     */
    public static function set_provision_override_for_tests(?callable $override): void {
        self::$provision_override = $override;
    }

    public static function maybe_provision(): void {
        if (self::should_skip()) {
            return;
        }

        try {
            self::run_provision();
            update_option(self::OPTION_VERSION, (string) self::CATALOG_VERSION);
            delete_option(self::OPTION_LAST_ERROR);
        } catch (\Throwable $e) {
            error_log('[AA_Canonical_Family_Catalog_Lifecycle] ' . $e->getMessage());
            update_option(self::OPTION_LAST_ERROR, $e->getMessage());
        }
    }

    private static function should_skip(): bool {
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return true;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }

        $db_version = (string) get_option('aa_db_version', '0');
        if (version_compare($db_version, self::MIN_DB_VERSION, '<')) {
            return true;
        }

        $stored = (string) get_option(self::OPTION_VERSION, '0');

        return version_compare($stored, (string) self::CATALOG_VERSION, '>=');
    }

    /**
     * @throws CanonicalFamilyProvisioningFailed
     */
    private static function run_provision(): void {
        if (self::$provision_override !== null) {
            call_user_func(self::$provision_override);
            return;
        }

        $registry = AA_Canonical_Core_Bootstrap::bootstrap();
        $provisioner = new AA_Canonical_Family_Provisioner();
        $provisioner->ensure_declared_families($registry);
    }
}
