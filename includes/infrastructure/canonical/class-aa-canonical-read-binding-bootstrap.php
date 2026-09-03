<?php
/**
 * Canonical Read Binding Bootstrap — Registro explícito de adaptadores productivos (SB1-4B).
 *
 * Sin URL, sin registry de rutas, sin preview.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Read_Binding_Registry')) {
    require_once __DIR__ . '/class-aa-canonical-read-binding-registry.php';
}
if (!class_exists('CanonicalReadIdentity')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadIdentity.php';
}

final class AA_Canonical_Read_Binding_Bootstrap {

    public static function register_productive(AA_Canonical_Read_Binding_Registry $registry): void {
        require_once __DIR__ . '/finance/class-aa-finance-canonical-read-adapter.php';

        $registry->register(
            new CanonicalReadIdentity('finance', 'general'),
            new AA_Finance_Canonical_Read_Adapter()
        );
    }
}
