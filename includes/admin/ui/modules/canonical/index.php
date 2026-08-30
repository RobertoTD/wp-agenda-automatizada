<?php
/**
 * Canonical Module — Despachador canónico ligero por familia.
 *
 * Despacha hacia el submódulo específico de familia o hacia el fallback neutral.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Modules\Canonical
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$allowed_family_templates = [
    'finance' => __DIR__ . '/finance/index.php',
];

$is_valid_family = isset($aa_canonical_family)
    && ($aa_canonical_family instanceof AA_Canonical_Family_Definition);

$family_key = $is_valid_family ? $aa_canonical_family->key() : null;

if ($family_key !== null && isset($allowed_family_templates[$family_key])) {
    require $allowed_family_templates[$family_key];
    return;
}

require __DIR__ . '/_fallback.php';
