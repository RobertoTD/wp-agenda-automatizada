<?php
/**
 * Canonical Module — Pantalla canónica vacía de presentación.
 *
 * Muestra el contexto resuelto de familia y variante desde el núcleo canónico.
 * No contiene lógica de negocio, formularios, CRUD, AJAX ni assets financieros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Modules\Canonical
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

/** @var AA_Canonical_Family_Definition $aa_canonical_family */
/** @var AA_Canonical_Variant_Definition $aa_canonical_variant */

$family_label = isset($aa_canonical_family) ? $aa_canonical_family->label() : 'Finanzas';
$family_key = isset($aa_canonical_family) ? $aa_canonical_family->key() : 'finance';
$variant_label = isset($aa_canonical_variant) ? $aa_canonical_variant->label() : 'General';
$variant_key = isset($aa_canonical_variant) ? $aa_canonical_variant->key() : 'general';
$qualified_key = isset($aa_canonical_variant) ? $aa_canonical_variant->qualified_key() : ($family_key . '.' . $variant_key);
?>

<div
    id="aa-canonical-root"
    class="max-w-5xl mx-auto py-2"
    data-aa-page-title="<?php echo esc_attr($family_label); ?>"
    data-aa-canonical-family="<?php echo esc_attr($family_key); ?>"
    data-aa-canonical-variant="<?php echo esc_attr($variant_key); ?>"
    data-aa-canonical-qualified="<?php echo esc_attr($qualified_key); ?>"
>
    <!-- Encabezado de contexto canónico -->
    <div class="bg-white rounded-xl shadow border border-gray-200 p-6 mb-4">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 font-bold text-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 leading-tight">
                        <?php echo esc_html($family_label); ?>
                    </h2>
                    <p class="text-sm text-gray-500">
                        Variante: <span class="font-medium text-gray-700"><?php echo esc_html($variant_label); ?></span>
                        <span class="text-gray-300 mx-1.5">•</span>
                        Clave: <code class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded font-mono"><?php echo esc_html($qualified_key); ?></code>
                    </p>
                </div>
            </div>
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    <?php echo esc_html($variant_label); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Estado vacío neutral -->
    <div class="bg-white rounded-xl shadow border border-gray-200 p-12 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 text-gray-400 mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
        </div>
        <h3 class="text-base font-semibold text-gray-900 mb-1">
            Espacio de <?php echo esc_html($family_label); ?>
        </h3>
        <p class="text-sm text-gray-500 max-w-md mx-auto">
            La estructura canónica para la familia <?php echo esc_html($family_label); ?> (variante <?php echo esc_html($variant_label); ?>) está activa y lista.
        </p>
    </div>
</div>
