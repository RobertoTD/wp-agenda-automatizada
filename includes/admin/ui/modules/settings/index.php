<?php
/**
 * Settings Module - Configuration UI
 * 
 * This module handles:
 * - Display of settings form
 * - UI interactions for settings
 * - No business logic (data saving handled outside via WordPress Settings API)
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// Get data for form
$schedule = get_option('aa_schedule', []);
$days = [
    'monday'    => 'Lunes',
    'tuesday'   => 'Martes',
    'wednesday' => 'Miércoles',
    'thursday'  => 'Jueves',
    'friday'    => 'Viernes',
    'saturday'  => 'Sábado',
    'sunday'    => 'Domingo'
];
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Configuración de Agenda Automatizada</h2>
        
        <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" class="space-y-8">
            <?php settings_fields('agenda_automatizada_settings'); ?>
            <?php do_settings_sections('agenda_automatizada_settings'); ?>

            <!-- ========== SECCIÓN A: Disponibilidad por día ========== -->
            <section class="space-y-4">
                <header class="border-b pb-3">
                    <h3 class="text-lg font-semibold text-gray-800">📅 Horarios y Disponibilidad</h3>
                    <p class="text-sm text-gray-600 mt-1">Configura los días y horarios en que tu negocio está disponible para citas.</p>
                </header>
                <div class="space-y-3">
                    <?php foreach ($days as $key => $label): 
                        $enabled   = !empty($schedule[$key]['enabled']);
                        $intervals = $schedule[$key]['intervals'] ?? [];
                    ?>
                        <div class="aa-day-block border rounded p-4">
                            <div class="aa-day-toggle mb-3">
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" name="aa_schedule[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($enabled, true); ?> class="rounded">
                                    <span class="aa-day-name font-medium"><?php echo esc_html($label); ?></span>
                                </label>
                            </div>

                            <div class="day-intervals" data-day="<?php echo esc_attr($key); ?>" style="<?php echo $enabled ? '' : 'display:none;'; ?>">
                                <?php if (!empty($intervals)): ?>
                                    <?php foreach ($intervals as $i => $interval): ?>
                                        <div class="interval flex items-center gap-3 mb-2">
                                            <input type="time" name="aa_schedule[<?php echo esc_attr($key); ?>][intervals][<?php echo $i; ?>][start]" value="<?php echo esc_attr($interval['start']); ?>" class="aa-timepicker border rounded px-3 py-2">
                                            <span class="aa-interval-separator">—</span>
                                            <input type="time" name="aa_schedule[<?php echo esc_attr($key); ?>][intervals][<?php echo $i; ?>][end]" value="<?php echo esc_attr($interval['end']); ?>" class="aa-timepicker border rounded px-3 py-2">
                                            <button type="button" class="remove-interval px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600">Eliminar</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <button type="button" class="add-interval px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Añadir intervalo</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ========== SECCIÓN B: Servicios / Motivos ========== -->
            <section class="space-y-4">
                <header class="border-b pb-3">
                    <h3 class="text-lg font-semibold text-gray-800">🏷️ Tipos de Cita / Servicios</h3>
                    <p class="text-sm text-gray-600 mt-1">Agrega los tipos de servicios o motivos de cita que ofreces.</p>
                </header>
                <div id="aa-motivos-container" class="space-y-3">
                    <ul id="aa-motivos-list" class="space-y-2"></ul>

                    <div class="aa-motivo-add-row flex gap-2">
                        <input type="text" id="aa-motivo-input" placeholder="Ej: Corte de cabello" class="flex-1 border rounded px-3 py-2">
                        <button type="button" id="aa-add-motivo" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Agregar motivo</button>
                    </div>

                    <!-- Campo oculto donde se guarda el JSON real -->
                    <input type="hidden" name="aa_google_motivo" id="aa-google-motivo-hidden"
                        value='<?php echo esc_attr(get_option("aa_google_motivo", json_encode(["Cita general"]))); ?>'>
                </div>
            </section>

            <!-- ========== SECCIÓN C: Parámetros generales ========== -->
            <section class="space-y-4">
                <header class="border-b pb-3">
                    <h3 class="text-lg font-semibold text-gray-800">⏱️ Parámetros Generales</h3>
                    <p class="text-sm text-gray-600 mt-1">Configura la duración de las citas y la ventana de disponibilidad futura.</p>
                </header>
                <div class="space-y-4">
                    <div class="aa-field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aa_slot_duration">Duración de cita</label>
                        <select name="aa_slot_duration" id="aa_slot_duration" class="border rounded px-3 py-2 w-full max-w-xs">
                            <option value="30" <?php selected(get_option('aa_slot_duration', 30), 30); ?>>30 minutos</option>
                            <option value="60" <?php selected(get_option('aa_slot_duration', 30), 60); ?>>60 minutos</option>
                            <option value="90" <?php selected(get_option('aa_slot_duration', 30), 90); ?>>90 minutos</option>
                        </select>
                    </div>

                    <div class="aa-field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aa_future_window">Ventana futura</label>
                        <select name="aa_future_window" id="aa_future_window" class="border rounded px-3 py-2 w-full max-w-xs">
                            <option value="15" <?php selected(get_option('aa_future_window', 15), 15); ?>>15 días</option>
                            <option value="30" <?php selected(get_option('aa_future_window', 15), 30); ?>>30 días</option>
                            <option value="45" <?php selected(get_option('aa_future_window', 15), 45); ?>>45 días</option>
                            <option value="60" <?php selected(get_option('aa_future_window', 15), 60); ?>>60 días</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Hasta cuántos días en el futuro se pueden agendar citas.</p>
                    </div>

                    <div class="aa-field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aa_timezone">Zona horaria</label>
                        <select name="aa_timezone" id="aa_timezone" class="border rounded px-3 py-2 w-full max-w-xs">
                            <?php
                            $saved_tz = get_option('aa_timezone', 'America/Mexico_City');
                            $timezones = [
                                'America/Mexico_City' => 'México (CDMX) - GMT-6',
                                'America/Cancun' => 'Cancún - GMT-5',
                                'America/Tijuana' => 'Tijuana - GMT-8',
                                'America/Monterrey' => 'Monterrey - GMT-6',
                                'America/Bogota' => 'Colombia (Bogotá) - GMT-5',
                                'America/Lima' => 'Perú (Lima) - GMT-5',
                                'America/Argentina/Buenos_Aires' => 'Argentina (Buenos Aires) - GMT-3',
                                'America/Santiago' => 'Chile (Santiago) - GMT-3',
                                'America/New_York' => 'Estados Unidos (Este) - GMT-5',
                                'America/Los_Angeles' => 'Estados Unidos (Pacífico) - GMT-8',
                                'Europe/Madrid' => 'España (Madrid) - GMT+1',
                                'Europe/London' => 'Reino Unido (Londres) - GMT+0',
                            ];
                            foreach ($timezones as $value => $label) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($value),
                                    selected($saved_tz, $value, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Los horarios se ajustarán automáticamente a esta zona.</p>
                    </div>
                </div>
            </section>

            <!-- ========== SECCIÓN D: Datos del negocio ========== -->
            <section class="space-y-4">
                <header class="border-b pb-3">
                    <h3 class="text-lg font-semibold text-gray-800">🏢 Datos del Negocio</h3>
                    <p class="text-sm text-gray-600 mt-1">Información que aparecerá en las confirmaciones y recordatorios de cita.</p>
                </header>
                <div class="space-y-4">
                    <div class="aa-field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aa_business_name">Nombre del negocio</label>
                        <input type="text" name="aa_business_name" id="aa_business_name"
                               value="<?php echo esc_attr(get_option('aa_business_name', '')); ?>" 
                               placeholder="Ej: Salón de Belleza María"
                               class="border rounded px-3 py-2 w-full max-w-md">
                        <p class="text-xs text-gray-500 mt-1">Nombre que aparecerá en las confirmaciones de cita.</p>
                    </div>

                    <div class="aa-field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de citas</label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" name="aa_is_virtual" value="1" 
                                   id="aa-is-virtual-checkbox"
                                   <?php checked(get_option('aa_is_virtual', 0), 1); ?>
                                   class="rounded">
                            <span>Las citas son virtuales (sin dirección física)</span>
                        </label>
                    </div>

                    <div class="aa-field-group" id="aa-address-row">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aa-business-address">Dirección física</label>
                        <textarea name="aa_business_address" 
                                  id="aa-business-address" 
                                  rows="3" 
                                  placeholder="Ej: Av. Reforma 123, Col. Centro, CDMX"
                                  class="border rounded px-3 py-2 w-full max-w-md"><?php echo esc_textarea(get_option('aa_business_address', '')); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Dirección donde se realizarán las citas presenciales.</p>
                    </div>

                    <div class="aa-field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aa_whatsapp_number">WhatsApp del negocio</label>
                        <input type="tel" name="aa_whatsapp_number" id="aa_whatsapp_number"
                               value="<?php echo esc_attr(get_option('aa_whatsapp_number', '')); ?>" 
                               placeholder="521234567890"
                               pattern="[0-9]{10,15}"
                               class="border rounded px-3 py-2 w-full max-w-md">
                        <p class="text-xs text-gray-500 mt-1">Número con código de país sin espacios ni símbolos (Ej: 5215522992290).</p>
                    </div>
                </div>
            </section>

            <!-- ========== SECCIÓN E: Google Calendar ========== -->
            <section class="space-y-4">
                <header class="border-b pb-3">
                    <h3 class="text-lg font-semibold text-gray-800">📆 Google Calendar</h3>
                    <p class="text-sm text-gray-600 mt-1">Sincroniza tus citas con Google Calendar para evitar conflictos.</p>
                </header>
                <div class="aa-google-status">
                    <?php 
                    $google_email = get_option('aa_google_email', '');
                    $is_sync_invalid = SyncService::is_sync_invalid();

                    echo '<input type="hidden" name="aa_google_email" value="' . esc_attr($google_email) . '">';
                    
                    if ($google_email && !$is_sync_invalid) {
                        // Estado válido - conectado correctamente
                        echo '<div class="aa-google-connected p-4 bg-green-50 border border-green-200 rounded">';
                        echo "<p class='aa-google-email mb-2'><strong>✅ Sincronizado con:</strong> " . esc_html($google_email) . "</p>";
                        echo "<a href='" . esc_url(admin_url('admin-post.php?action=aa_disconnect_google')) . "' class='inline-block px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600'>Desconectar</a>";
                        echo '</div>';
                    } elseif ($google_email && $is_sync_invalid) {
                        // Token caducado o rechazado - requiere reconexión
                        echo '<div class="aa-google-error p-4 bg-yellow-50 border border-yellow-200 rounded">';
                        echo "<p class='aa-google-warning mb-2'>⚠️ Token caducado o conexión rechazada. Se requiere reconexión manual.</p>";
                        echo "<p class='mb-2'><strong>Email anterior:</strong> " . esc_html($google_email) . "</p>";
                        echo "<a href='" . esc_url(SyncService::get_auth_url()) . "' class='inline-block px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600'>Reconectar con Google</a>";
                        echo '</div>';
                    } else {
                        // No hay cuenta conectada
                        echo '<div class="aa-google-disconnected p-4 bg-gray-50 border border-gray-200 rounded">';
                        echo "<p class='mb-2'><strong>No sincronizado</strong></p>";
                        echo "<a href='" . esc_url(SyncService::get_auth_url()) . "' class='inline-block px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600'>Conectar con Google</a>";
                        echo '</div>';
                    }
                    ?>
                </div>
            </section>

            <div class="pt-4 border-t">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                    Guardar Configuración
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Load Flatpickr CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

<!-- Load module-specific JS -->
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'module.js'); ?>"></script>
