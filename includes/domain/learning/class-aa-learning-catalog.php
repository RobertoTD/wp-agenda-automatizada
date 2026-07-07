<?php
/**
 * Learning Catalog — definiciones base versionadas de recomendaciones.
 *
 * Catálogo de producto (no editable por el usuario en esta etapa).
 * Sin WordPress, SQL ni URLs resueltas; action/navigation son intención conceptual.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Learning_Catalog {

    public const COMPLETION_AUTO = 'auto';

    public const COMPLETION_MANUAL = 'manual';

    /**
     * Versión del catálogo para seed/sync hacia DB común (MC13O-D3).
     * Bumpear cuando cambien definiciones activas del catálogo.
     */
    public const SEED_VERSION = '4';

    /**
     * @return list<string> Keys de definiciones activas del catálogo.
     */
    public static function active_definition_keys(): array {
        $keys = [];

        foreach (self::definitions() as $key => $definition) {
            if (empty($definition['active'])) {
                continue;
            }

            $keys[] = isset($definition['key']) ? (string) $definition['key'] : (string) $key;
        }

        return $keys;
    }

    /**
     * @return array<string,array<string,mixed>> Indexado por recommendation key.
     */
    public static function definitions(): array {
        $items = [
            self::item(
                'connect_google_calendar',
                'Conecta Google Calendar',
                'Sincroniza tus citas con Google Calendar para recibir recordatorios y reducir olvidos.',
                100,
                1,
                self::COMPLETION_AUTO,
                'google_connected',
                [
                    'module' => 'settings',
                    'setup_focus' => 'google_calendar',
                    'fragment' => 'aa-google-calendar-root',
                ],
                true,
                [
                    'aging_days' => 30,
                    'dismiss_hours' => 24,
                ]
            ),
            self::item(
                'complete_business_data',
                'Completa los datos de tu negocio',
                'Añade el nombre y la ubicación de tu negocio para personalizar la experiencia.',
                90,
                1,
                self::COMPLETION_AUTO,
                'business_data_complete',
                [
                    'module' => 'settings',
                    'setup_focus' => 'business_data',
                    'fragment' => 'aa-business-data-root',
                ],
                true,
                [
                    'aging_days' => 7,
                    'dismiss_hours' => 24,
                ]
            ),
            self::item(
                'configure_services',
                'Configura tus servicios',
                'Define qué servicios ofreces antes de abrir horarios o agendar citas.',
                80,
                1,
                self::COMPLETION_AUTO,
                'has_active_service',
                [
                    'module' => 'assignments',
                    'setup_focus' => 'services',
                    'fragment' => 'aa-services-root',
                ]
            ),
            self::item(
                'configure_areas',
                'Configura zonas de atención',
                'Crea las áreas o consultorios donde se prestarán los servicios.',
                70,
                1,
                self::COMPLETION_AUTO,
                'has_active_area',
                [
                    'module' => 'assignments',
                    'setup_focus' => 'areas',
                    'fragment' => 'aa-areas-root',
                ]
            ),
            self::item(
                'configure_staff',
                'Configura personal y servicios',
                'Registra personal activo y asígnale al menos un servicio.',
                60,
                1,
                self::COMPLETION_AUTO,
                'has_staff_with_service',
                [
                    'module' => 'assignments',
                    'setup_focus' => 'staff',
                    'fragment' => 'aa-staff-root',
                ]
            ),
            self::item(
                'create_first_client',
                'Crea tu primer cliente',
                'Agrega al menos un cliente para poder agendar con datos de contacto.',
                50,
                1,
                self::COMPLETION_AUTO,
                'has_registered_client',
                [
                    'module' => 'clients',
                    'setup_focus' => 'clients',
                    'fragment' => 'aa-clients-grid',
                ],
                true,
                [
                    'aging_days' => 7,
                    'dismiss_hours' => 24,
                ]
            ),
            self::item(
                'install_pwa',
                'Instala la app en tu dispositivo',
                'Añade DEOIA Citas a la pantalla de inicio para abrir la agenda más rápido.',
                110,
                1,
                self::COMPLETION_MANUAL,
                null,
                [
                    'module' => null,
                    'setup_focus' => null,
                    'fragment' => null,
                ],
                true,
                [
                    'dismiss_hours' => 48,
                ],
                [
                    'type' => 'handler',
                    'handler' => 'pwa.install',
                    'label' => 'Instalar',
                ]
            ),
        ];

        $indexed = [];

        foreach ($items as $item) {
            $indexed[$item['key']] = $item;
        }

        return $indexed;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function all(): array {
        return array_values(self::definitions());
    }

    /**
     * @param string $key
     * @return array<string,mixed>|null
     */
    public static function get(string $key): ?array {
        $definitions = self::definitions();

        return $definitions[$key] ?? null;
    }

    /**
     * @param string      $key
     * @param string      $title
     * @param string      $description
     * @param int         $importance
     * @param int         $default_list
     * @param string      $completion_type
     * @param string|null $completion_fact
     * @param array<string,string|null>      $navigation
     * @param bool                           $active
     * @param array<string,mixed>            $rules Opcional: aging_days, dismiss_hours (enteros >= 1).
     * @param array<string,mixed>|null|false $action Acción primaria opcional. Si es false, se usa navigation como legacy adapter.
     * @return array<string,mixed>
     */
    private static function item(
        string $key,
        string $title,
        string $description,
        int $importance,
        int $default_list,
        string $completion_type,
        ?string $completion_fact,
        array $navigation,
        bool $active = true,
        array $rules = [],
        $action = false
    ): array {
        $item = [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'importance' => $importance,
            'default_list' => $default_list,
            'active' => $active,
            'completion_type' => $completion_type,
            'completion_fact' => $completion_fact,
            'navigation' => $navigation,
        ];

        if ($action !== false) {
            $item['action'] = is_array($action) ? $action : null;
        }

        $aging_days = self::normalize_rule_int($rules['aging_days'] ?? null);
        if ($aging_days !== null) {
            $item['aging_days'] = $aging_days;
        }

        $dismiss_hours = self::normalize_rule_int($rules['dismiss_hours'] ?? null);
        if ($dismiss_hours !== null) {
            $item['dismiss_hours'] = $dismiss_hours;
        }

        return $item;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_rule_int($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        if ($normalized < 1) {
            return null;
        }

        return $normalized;
    }
}
