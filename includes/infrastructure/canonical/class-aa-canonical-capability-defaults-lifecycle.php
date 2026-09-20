<?php
/**
 * Canonical Capability Defaults Lifecycle — seeds de repertorio sin sobrescritura.
 *
 * Solo inserta filas ausentes para capacidades is_ready. Nunca UPDATE de is_default.
 * A1b: amount ready → insert-if-missing de finance/amount (DEFAULTS_VERSION=2).
 * Paso 5: images ready + DEFAULTS_VERSION=3 → insert-if-missing de la matriz §12.1
 * (archive default on; finance/catalog/contact default off). Sin tocar listas ya persistidas.
 * Phone: DEFAULTS_VERSION=4 → contact/phone default off (insert-if-missing).
 * WhatsApp: DEFAULTS_VERSION=5 → contact/whatsapp default on (insert-if-missing; solo listas nuevas).
 * Email: DEFAULTS_VERSION=6 → contact/email default off (insert-if-missing).
 * Dossier: DEFAULTS_VERSION=7 → contact/dossier default off (insert-if-missing).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Defaults_Lifecycle {

    public const DEFAULTS_VERSION = 7;

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

            $repository->insert_family_capability_if_missing(
                $family_id,
                $seed['capability_key'],
                $seed['is_default']
            );
        }
    }

    /**
     * Seeds declarados. Ensure solo inserta filas de capacidades is_ready.
     * amount (A1b) + images (Paso 5 / DEFAULTS_VERSION=3): matriz §12.1.
     * phone (DEFAULTS_VERSION=4): solo contact, default off.
     * whatsapp (DEFAULTS_VERSION=5): solo contact, default on (listas nuevas).
     * email (DEFAULTS_VERSION=6): solo contact, default off.
     *
     * @return list<array{family_key:string,capability_key:string,is_default:bool}>
     */
    public static function declared_seeds(): array {
        return [
            [
                'family_key' => 'finance',
                'capability_key' => 'amount',
                'is_default' => true,
            ],
            [
                'family_key' => 'archive',
                'capability_key' => 'images',
                'is_default' => true,
            ],
            [
                'family_key' => 'finance',
                'capability_key' => 'images',
                'is_default' => false,
            ],
            [
                'family_key' => 'catalog',
                'capability_key' => 'images',
                'is_default' => false,
            ],
            [
                'family_key' => 'contact',
                'capability_key' => 'images',
                'is_default' => false,
            ],
            [
                'family_key' => 'contact',
                'capability_key' => 'phone',
                'is_default' => false,
            ],
            [
                'family_key' => 'contact',
                'capability_key' => 'whatsapp',
                'is_default' => true,
            ],
            [
                'family_key' => 'contact',
                'capability_key' => 'email',
                'is_default' => false,
            ],
        ];
    }
}
