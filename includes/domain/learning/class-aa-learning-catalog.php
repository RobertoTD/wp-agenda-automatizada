<?php
/**
 * Learning Catalog — definiciones base versionadas de recomendaciones.
 *
 * Catálogo de producto (no editable por el usuario en esta etapa).
 * Sin WordPress, SQL ni URLs resueltas; navigation es destino conceptual para Ciclo C.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Learning_Catalog {

    public const COMPLETION_AUTO = 'auto';

    public const COMPLETION_MANUAL = 'manual';

    /**
     * @return array<string,array<string,mixed>> Indexado por recommendation key.
     */
    public static function definitions(): array {
        $items = [
            self::item(
                'connect_google_calendar',
                'Conecta Google Calendar',
                'Sincroniza tus citas con Google Calendar para recibir recordatorios y reducir olvidos.',
                -10,
                1,
                self::COMPLETION_AUTO,
                'google_connected',
                [
                    'module' => 'settings',
                    'setup_focus' => 'google_calendar',
                    'fragment' => 'aa-google-calendar-root',
                ]
            ),
            self::item(
                'complete_business_data',
                'Completa los datos de tu negocio',
                'Añade el nombre y la dirección de tu negocio para personalizar la experiencia.',
                -5,
                1,
                self::COMPLETION_AUTO,
                'business_data_complete',
                [
                    'module' => 'settings',
                    'setup_focus' => null,
                    'fragment' => null,
                ]
            ),
            self::item(
                'configure_services',
                'Configura tus servicios',
                'Define qué servicios ofreces antes de abrir horarios o agendar citas.',
                0,
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
                10,
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
                20,
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
                30,
                1,
                self::COMPLETION_AUTO,
                'has_registered_client',
                [
                    'module' => 'clients',
                    'setup_focus' => 'clients',
                    'fragment' => 'aa-clients-grid',
                ]
            ),
            self::item(
                'install_pwa',
                'Instala la app en tu dispositivo',
                'Añade DEOIA Citas a la pantalla de inicio para abrir la agenda más rápido.',
                100,
                2,
                self::COMPLETION_MANUAL,
                null,
                [
                    'module' => null,
                    'setup_focus' => null,
                    'fragment' => null,
                ]
            ),
            self::item(
                'learn_basic_flow',
                'Aprende el flujo básico',
                'Recorre resumen, agenda y asignaciones para familiarizarte con la app.',
                110,
                2,
                self::COMPLETION_MANUAL,
                null,
                [
                    'module' => 'dashboard',
                    'setup_focus' => null,
                    'fragment' => null,
                ]
            ),
            self::item(
                'review_agenda',
                'Revisa tu agenda del día',
                'Consulta las citas de hoy en el módulo Agenda cuando empieces a operar.',
                120,
                2,
                self::COMPLETION_MANUAL,
                null,
                [
                    'module' => 'calendar',
                    'setup_focus' => null,
                    'fragment' => null,
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
     * @param array<string,string|null> $navigation
     * @param bool        $active
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
        bool $active = true
    ): array {
        return [
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
    }
}
