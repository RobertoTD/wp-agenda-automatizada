<?php
/**
 * Initial Setup Seed Definition — constantes del cliente de prueba inicial.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Setup
 */

defined('ABSPATH') or die('No direct access');

final class AA_Initial_Setup_Seed_Definition {

    public const SEED_VERSION = '2';

    /** @deprecated Agendas que ya completaron MC1; no reciben backfill. */
    public const LEGACY_SEED_VERSION = '1';

    public const CLIENT_NAME = 'Cliente de Prueba';

    public const CLIENT_PHONE_RAW = '5555555555';

    public const CLIENT_PHONE_CANONICAL = '525555555555';

    public const SERVICE_NAME = 'Consulta general';

    public const STAFF_NAME = 'Personal de prueba';

    public const AREA_NAME = 'Zona general';
}
