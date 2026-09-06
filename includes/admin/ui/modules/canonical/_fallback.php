<?php
/**
 * Canonical Fallback View — Estado neutral para familias sin template específico o contexto ausente.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Modules\Canonical
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$family_label = (isset($aa_canonical_family) && $aa_canonical_family instanceof AA_Canonical_Family_Definition)
    ? $aa_canonical_family->label()
    : 'Módulo Canónico';

$family_key = (isset($aa_canonical_family) && $aa_canonical_family instanceof AA_Canonical_Family_Definition)
    ? $aa_canonical_family->key()
    : 'canonical';
?>

<div
    id="aa-canonical-root"
    class="max-w-5xl mx-auto py-2"
    data-aa-page-title="<?php echo esc_attr($family_label); ?>"
    data-aa-canonical-family="<?php echo esc_attr($family_key); ?>"
>
    <!-- Encabezado de contexto canónico neutral -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
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
                        Clave: <code class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded font-mono"><?php echo esc_html($family_key); ?></code>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Estado vacío neutral -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 text-gray-400 mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
        </div>
        <h3 class="text-base font-semibold text-gray-900 mb-1">
            Espacio de <?php echo esc_html($family_label); ?>
        </h3>
        <p class="text-sm text-gray-500 max-w-md mx-auto">
            La estructura canónica para la familia <?php echo esc_html($family_label); ?> está activa y lista.
        </p>
    </div>
</div>
