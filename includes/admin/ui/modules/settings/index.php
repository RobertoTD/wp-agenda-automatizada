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

<div class="max-w-5xl mx-auto py-2">
    
    <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
        <?php settings_fields('agenda_automatizada_settings'); ?>
        <?php do_settings_sections('agenda_automatizada_settings'); ?>

        <!-- ═══════════════════════════════════════════════════════════════
             SECCIÓN: Horarios y Disponibilidad
        ═══════════════════════════════════════════════════════════════ -->
        <details class="bg-white rounded-xl shadow border border-gray-200 mb-6 overflow-hidden group">
            <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 text-blue-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Horarios y Disponibilidad Fija</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Define los días y horarios de atención (semanal)</p>
                        </div>
                    </div>
                    <svg class="aa-chevron w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </summary>
            
            <div class="p-3 transition-all duration-200">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="aa_service_schedule">
                        Nombre del servicio con disponibilidad fija
                    </label>
                    <input type="text" name="aa_service_schedule" id="aa_service_schedule"
                           value="<?php echo esc_attr(get_option('aa_service_schedule', '')); ?>" 
                           placeholder="Ej: Informes rápidos"
                           class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-shadow placeholder:text-gray-400">
                    <p class="text-xs text-gray-500 mt-1.5">Este servicio usará el horario fijo.</p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="aa_staff_schedule">
                        Nombre del Personal
                    </label>
                    <input type="text" name="aa_staff_schedule" id="aa_staff_schedule"
                           value="<?php echo esc_attr(get_option('aa_staff_schedule', '')); ?>" 
                           placeholder="Ej: Juan Pérez"
                           class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-shadow placeholder:text-gray-400">
                    <p class="text-xs text-gray-500 mt-1.5">Persona que atiende en el siguiente horario fijo.</p>
                </div>
                
                <div class="grid gap-3">
                    <?php foreach ($days as $key => $label): 
                        $enabled   = !empty($schedule[$key]['enabled']);
                        $intervals = $schedule[$key]['intervals'] ?? [];
                    ?>
                    <div class="aa-day-block group rounded-lg border border-gray-200 bg-gray-50/50 hover:border-blue-200 hover:bg-blue-50/30 transition-all duration-200 overflow-hidden <?php echo $enabled ? 'border-blue-200 bg-blue-50/40' : ''; ?>">
                        <div class="px-4 py-3 flex items-center justify-between">
                            <label class="flex items-center gap-3 cursor-pointer flex-1">
                                <div class="relative">
                                    <input type="checkbox" 
                                           name="aa_schedule[<?php echo esc_attr($key); ?>][enabled]" 
                                           value="1" 
                                           <?php checked($enabled, true); ?> 
                                           class="peer sr-only">
                                    <div class="w-11 h-6 bg-gray-300 peer-checked:bg-blue-500 rounded-full transition-colors duration-200"></div>
                                    <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform duration-200 peer-checked:translate-x-5"></div>
                                </div>
                                <span class="aa-day-name font-medium text-gray-700 select-none"><?php echo esc_html($label); ?></span>
                            </label>
                            
                            <?php if ($enabled && !empty($intervals)): ?>
                            <span class="text-xs text-gray-500 bg-white px-2 py-1 rounded-full border border-gray-200">
                                <?php echo count($intervals); ?> intervalo<?php echo count($intervals) > 1 ? 's' : ''; ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="day-intervals border-t border-gray-200 bg-white px-3 py-4 rounded-b-lg" 
                             data-day="<?php echo esc_attr($key); ?>" 
                             style="<?php echo $enabled ? '' : 'display:none;'; ?>">
                            
                            <div class="space-y-3">
                                <?php if (!empty($intervals)): ?>
                                    <?php foreach ($intervals as $i => $interval): ?>
                                    <div class="interval flex flex-wrap items-center gap-2 sm:gap-3" data-day="<?php echo esc_attr($key); ?>" data-index="<?php echo $i; ?>">
                                        <div class="aa-time-input-wrapper flex items-center gap-1">
                                            <input type="time" 
                                                   name="aa_schedule[<?php echo esc_attr($key); ?>][intervals][<?php echo $i; ?>][start]" 
                                                   value="<?php echo esc_attr($interval['start']); ?>" 
                                                   step="1800"
                                                   class="aa-timepicker-mobile w-[7rem] px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow">
                                        </div>
                                        <span class="text-gray-400 font-light">-</span>
                                        <div class="aa-time-input-wrapper flex items-center gap-1">
                                            <input type="time" 
                                                   name="aa_schedule[<?php echo esc_attr($key); ?>][intervals][<?php echo $i; ?>][end]" 
                                                   value="<?php echo esc_attr($interval['end']); ?>" 
                                                   step="1800"
                                                   class="aa-timepicker-mobile w-[7rem] px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow">
                                        </div>
                                        <button type="button" 
                                                class="remove-interval ml-auto text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Eliminar intervalo">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" 
                                    class="add-interval mt-3 inline-flex items-center gap-2 px-3 py-1.5 text-sm text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Añadir intervalo
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>

        <!-- ═══════════════════════════════════════════════════════════════
             GRID: Servicios + Parámetros (2 columnas en desktop)
        ═══════════════════════════════════════════════════════════════ -->
        <div class="grid lg:grid-cols-2 gap-6 mb-6">

            <!-- SECCIÓN: Tipos de Cita / Servicios -->
            <details class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden group">
                <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Tipos de Cita</h3>
                                <p class="text-sm text-gray-500 mt-0.5">Servicios que ofreces</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </summary>
                
                <div class="p-6 transition-all duration-200" id="aa-motivos-container">
                    <ul id="aa-motivos-list" class="space-y-2 mb-4"></ul>

                    <div class="flex gap-2">
                        <input type="text" 
                               id="aa-motivo-input" 
                               placeholder="Ej: Corte de cabello" 
                               class="flex-1 px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow placeholder:text-gray-400">
                        <button type="button" 
                                id="aa-add-motivo" 
                                class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 transition-colors shadow-sm">
                            Agregar
                        </button>
                    </div>

                    <input type="hidden" name="aa_google_motivo" id="aa-google-motivo-hidden"
                        value='<?php echo esc_attr(get_option("aa_google_motivo", json_encode(["Cita general"]))); ?>'>
                </div>
            </details>

            <!-- SECCIÓN: Parámetros Generales -->
            <details class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden group">
                <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Parámetros</h3>
                                <p class="text-sm text-gray-500 mt-0.5">Duración y ventana de citas</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </summary>
                
                <div class="p-6 space-y-5 transition-all duration-200">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="aa_slot_duration">
                            Duración de cada cita
                        </label>
                        <select name="aa_slot_duration" id="aa_slot_duration" 
                                class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-white transition-shadow">
                            <option value="30" <?php selected(get_option('aa_slot_duration', 30), 30); ?>>30 minutos</option>
                            <option value="60" <?php selected(get_option('aa_slot_duration', 30), 60); ?>>60 minutos</option>
                            <option value="90" <?php selected(get_option('aa_slot_duration', 30), 90); ?>>90 minutos</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="aa_future_window">
                            Ventana de disponibilidad
                        </label>
                        <select name="aa_future_window" id="aa_future_window" 
                                class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-white transition-shadow">
                            <option value="15" <?php selected(get_option('aa_future_window', 15), 15); ?>>15 días</option>
                            <option value="30" <?php selected(get_option('aa_future_window', 15), 30); ?>>30 días</option>
                            <option value="45" <?php selected(get_option('aa_future_window', 15), 45); ?>>45 días</option>
                            <option value="60" <?php selected(get_option('aa_future_window', 15), 60); ?>>60 días</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1.5">Límite para agendar citas en el futuro</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="aa_timezone">
                            Zona horaria
                        </label>
                        <select name="aa_timezone" id="aa_timezone" 
                                class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-white transition-shadow">
                            <?php
                            $saved_tz = get_option('aa_timezone', 'America/Mexico_City');
                            $timezones = [
                                'America/Mexico_City' => 'México (CDMX) - GMT-6',
                                'America/Cancun' => 'Cancún - GMT-5',
                                'America/Tijuana' => 'Tijuana - GMT-8',
                                'America/Monterrey' => 'Monterrey - GMT-6',
                                'America/Bogota' => 'Colombia (Bogotá) - GMT-5',
                                'America/Lima' => 'Perú (Lima) - GMT-5',
                                'America/Argentina/Buenos_Aires' => 'Argentina - GMT-3',
                                'America/Santiago' => 'Chile (Santiago) - GMT-3',
                                'America/New_York' => 'EE.UU. (Este) - GMT-5',
                                'America/Los_Angeles' => 'EE.UU. (Pacífico) - GMT-8',
                                'Europe/Madrid' => 'España (Madrid) - GMT+1',
                                'Europe/London' => 'Reino Unido - GMT+0',
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
                    </div>
                </div>
            </details>

        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             SECCIÓN: Datos del Negocio
        ═══════════════════════════════════════════════════════════════ -->
        <details class="bg-white rounded-xl shadow border border-gray-200 mb-6 overflow-hidden group">
            <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 text-blue-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Datos del Negocio</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Información para confirmaciones y recordatorios</p>
                        </div>
                    </div>
                    <svg class="aa-chevron w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </summary>
            
            <div class="p-3 transition-all duration-200">
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="aa_business_name">
                            Nombre del negocio
                        </label>
                        <input type="text" name="aa_business_name" id="aa_business_name"
                               value="<?php echo esc_attr(get_option('aa_business_name', '')); ?>" 
                               placeholder="Ej: Salón de Belleza María"
                               class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-shadow placeholder:text-gray-400">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="aa_whatsapp_number">
                            WhatsApp del negocio
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                            </span>
                            <input type="tel" name="aa_whatsapp_number" id="aa_whatsapp_number"
                                   value="<?php echo esc_attr(get_option('aa_whatsapp_number', '')); ?>" 
                                   placeholder="521234567890"
                                   pattern="[0-9]{10,15}"
                                   class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-shadow placeholder:text-gray-400">
                        </div>
                        <p class="text-xs text-gray-500 mt-1.5">Código de país + número, sin espacios</p>
                    </div>

                    <div class="md:col-span-2">
                        <div class="flex items-center gap-3 mb-4 p-3 bg-gray-50 rounded-lg">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="aa_is_virtual" value="1" 
                                       id="aa-is-virtual-checkbox"
                                       <?php checked(get_option('aa_is_virtual', 0), 1); ?>
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-300 peer-checked:bg-emerald-500 rounded-full transition-colors"></div>
                                <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-5"></div>
                            </label>
                            <span class="text-sm text-gray-700">Las citas son virtuales (sin dirección física)</span>
                        </div>
                    </div>

                    <div class="md:col-span-2" id="aa-address-row">
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="aa-business-address">
                            Dirección física
                        </label>
                        <textarea name="aa_business_address" 
                                  id="aa-business-address" 
                                  rows="2" 
                                  placeholder="Ej: Av. Reforma 123, Col. Centro, CDMX"
                                  class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-shadow placeholder:text-gray-400 resize-none"><?php echo esc_textarea(get_option('aa_business_address', '')); ?></textarea>
                    </div>
                </div>
            </div>
        </details>

        <!-- ═══════════════════════════════════════════════════════════════
             SECCIÓN: Google Calendar
        ═══════════════════════════════════════════════════════════════ -->
        <details class="bg-white rounded-xl shadow border border-gray-200 mb-6 overflow-hidden group">
            <summary class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer list-none">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 text-blue-600">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm-8 4H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Google Calendar</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Sincroniza citas para evitar conflictos</p>
                        </div>
                    </div>
                    <svg class="aa-chevron w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </summary>
            
            <div class="p-3 transition-all duration-200">
                <?php 
                $google_email = get_option('aa_google_email', '');
                $is_sync_invalid = SyncService::is_sync_invalid();

                echo '<input type="hidden" name="aa_google_email" value="' . esc_attr($google_email) . '">';
                
                if ($google_email && !$is_sync_invalid): ?>
                    <!-- Estado: Conectado -->
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 flex-shrink-0">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-emerald-700">Sincronizado</p>
                                <p class="text-sm text-emerald-600 truncate"><?php echo esc_html($google_email); ?></p>
                            </div>
                        </div>
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=aa_disconnect_google')); ?>" 
                           class="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors whitespace-nowrap ml-auto">
                            Desconectar
                        </a>
                    </div>
                <?php elseif ($google_email && $is_sync_invalid): ?>
                    <!-- Estado: Requiere reconexión -->
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
                        <div class="flex items-start gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 flex-shrink-0 mt-0.5">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </span>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-amber-800">Reconexión requerida</p>
                                <p class="text-sm text-amber-700 mt-1">El token ha caducado. Reconecta para seguir sincronizando.</p>
                                <p class="text-xs text-amber-600 mt-2">Cuenta anterior: <?php echo esc_html($google_email); ?></p>
                                <a href="<?php echo esc_url(SyncService::get_auth_url()); ?>" 
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                    </svg>
                                    Reconectar con Google
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Estado: No conectado -->
                    <div class="text-center py-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h4 class="text-base font-medium text-gray-900 mb-1">Sin sincronización</h4>
                        <p class="text-sm text-gray-500 mb-4 max-w-sm mx-auto">
                            Conecta tu cuenta de Google Calendar para sincronizar tus citas automáticamente
                        </p>
                        <a href="<?php echo esc_url(SyncService::get_auth_url()); ?>" 
                           target="_blank"
                           rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition-colors shadow-sm">
                            <svg class="w-5 h-5" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            Conectar con Google
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </details>

        <!-- ═══════════════════════════════════════════════════════════════
             BOTÓN GUARDAR
        ═══════════════════════════════════════════════════════════════ -->
        <div class="-mx-4 lg:w-screen lg:relative lg:left-1/2 lg:-translate-x-1/2 lg:mx-0 px-4 lg:px-[calc((100vw-80rem)/2+1rem)] py-4 mt-6 flex justify-end">
            <button type="submit" 
                    class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg border border-gray-300 transition-all shadow-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Guardar Configuración
            </button>
        </div>

    </form>
</div>

<!-- Time picker logic handled in module.js -->

<!-- Module JS -->
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'module.js'); ?>"></script>
