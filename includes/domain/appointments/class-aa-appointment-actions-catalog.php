<?php
/**
 * Appointment Actions Catalog — definición versionada de la lista del sistema de citas.
 *
 * Catálogo de producto (no editable por el usuario). Sin WordPress ni SQL.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Appointment_Actions_Catalog {

    public const SOURCE_CATEGORY = 'agenda_app';

    public const LIST_ORIGIN_KEY = 'appointment_actions';

    public const LIST_TITLE = 'Acciones de citas';

    /**
     * Versión del seed hacia DB común (MC1).
     * Bumpear cuando cambie la definición canónica de la lista.
     */
    public const SEED_VERSION = '1';

    /**
     * @return array<string,mixed>
     */
    public static function list_definition(): array {
        return [
            'title' => self::LIST_TITLE,
            'description' => '',
            'owner_type' => 'developer',
            'source_category' => self::SOURCE_CATEGORY,
            'origin_key' => self::LIST_ORIGIN_KEY,
            'managed_by' => 'developer',
            'status' => 'active',
            'importance' => 0,
            'position' => 0,
        ];
    }
}
