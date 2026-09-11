<?php
/**
 * Canonical Capability Defaults Lifecycle — seeds de defaults sin sobrescritura.
 *
 * Solo inserta filas ausentes para capacidades is_ready. Nunca UPDATE de is_enabled.
 * A1b: amount ready → insert-if-missing de finance/amount (DEFAULTS_VERSION=2).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Defaults_Lifecycle {

    public const DEFAULTS_VERSION = 2;

    public const OPTION_VERSION = 'aa_canonical_capability_defaults_version';

    public const OPTION_LAST_ERROR = 'aa_canonical_capability_defaults_last_error';

    private const MIN_DB_VERSION = '23';

    /** Después del catálogo de familias (25). */
    public const ADMIN_INIT_PRIORITY = 26;

    /** @var callable|null */
    private static $ensure_override = null;

    public static function register(string $main_plugin_file): void {
        add_action('admin_init', [__CLASS__, 'maybe_ensure'], self::ADMIN_INIT_PRIORITY);
    }

    /**
     * @internal Solo tests.
     */
    public static function set_ensure_override_for_tests(?callable $override): void {
        self::$ensure_override = $override;
    }

    public static function maybe_ensure(): void {
        if (self::should_skip()) {
            return;
        }

        try {
            self::run_ensure();
            update_option(self::OPTION_VERSION, (string) self::DEFAULTS_VERSION);
            delete_option(self::OPTION_LAST_ERROR);
        } catch (\Throwable $e) {
            error_log('[AA_Canonical_Capability_Defaults_Lifecycle] ' . $e->getMessage());
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

        $family_catalog = (string) get_option(
            AA_Canonical_Family_Catalog_Lifecycle::OPTION_VERSION,
            '0'
        );
        if (version_compare(
            $family_catalog,
            (string) AA_Canonical_Family_Catalog_Lifecycle::CATALOG_VERSION,
            '<'
        )) {
            return true;
        }

        $stored = (string) get_option(self::OPTION_VERSION, '0');

        return version_compare($stored, (string) self::DEFAULTS_VERSION, '>=');
    }

    /**
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    private static function run_ensure(): void {
        if (self::$ensure_override !== null) {
            call_user_func(self::$ensure_override);
            return;
        }

        AA_Canonical_Core_Bootstrap::bootstrap();
        $capability_registry = AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
        $repository = new CanonicalCapabilityConfigRepository();
        $repository->assert_schema_ready();

        foreach (self::declared_seeds() as $seed) {
            if (!$capability_registry->has($seed['capability_key'])) {
                continue;
            }
            $definition = $capability_registry->get($seed['capability_key']);
            if (!$definition->is_ready()) {
                continue;
            }

            $family_id = $repository->resolve_family_id($seed['family_key']);
            if ($family_id === null) {
                continue;
            }

            $repository->insert_family_default_if_missing(
                $family_id,
                $seed['capability_key'],
                $seed['is_enabled']
            );
        }
    }

    /**
     * Seeds declarados. Con amount ready (A1b), el ensure inserta finance/amount solo si falta.
     *
     * @return list<array{family_key:string,capability_key:string,is_enabled:bool}>
     */
    public static function declared_seeds(): array {
        return [
            [
                'family_key' => 'finance',
                'capability_key' => 'amount',
                'is_enabled' => true,
            ],
        ];
    }
}
