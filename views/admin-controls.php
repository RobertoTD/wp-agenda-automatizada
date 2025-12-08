<?php
if (!defined('ABSPATH')) exit;

// 🔹 Banner de notificación global cuando la sincronización está inválida
add_action('admin_notices', function() {
    // Solo mostrar en páginas del plugin para administradores
    if (!current_user_can('manage_options')) return;
    
    if (SyncService::is_sync_invalid()) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>⚠️ Sincronización de Google Calendar detenida</strong><br>
                El token de autenticación ha caducado o fue rechazado. 
                <a href="<?php echo esc_url(admin_url('admin.php?page=agenda-automatizada-settings')); ?>">
                    Dirígete a la configuración para reconectar tu cuenta de Google.
                </a>
            </p>
        </div>
        <?php
    }
});

// Crear página de configuración en el menú de WordPress
add_action('admin_menu', function() {
    add_menu_page(
        'Agenda Automatizada',
        'Agenda Automatizada',
        'manage_options',
        'agenda-automatizada-settings',
        'agenda_automatizada_render_settings_page',
        'dashicons-calendar-alt'
    );
});

// 🔹 Submenú visible para el admin: Panel del Asistente
add_action('admin_menu', function () {
    // Si es administrador, agregar pestaña del asistente debajo del menú principal
    if (current_user_can('administrator')) {
        add_submenu_page(
            'agenda-automatizada-settings',
            'Panel del Asistente',
            'Panel del Asistente',
            'administrator',
            'aa_asistant_panel',
            'aa_render_asistant_panel'
        );
    }

    // 🔹 Menú principal exclusivo para el rol aa_asistente
    if (current_user_can('aa_asistente')) {
        add_menu_page(
            'Panel del Asistente',
            'Panel del Asistente',
            'read', // ✅ Cambiar de 'aa_asistente' a 'read' para que WordPress lo permita
            'aa_asistant_panel',
            'aa_render_asistant_panel',
            'dashicons-calendar-alt',
            30
        );
    }
});


// Registrar settings
add_action('admin_init', function() {
    register_setting('agenda_automatizada_settings', 'aa_schedule');
    register_setting('agenda_automatizada_settings', 'aa_slot_duration');
    register_setting('agenda_automatizada_settings', 'aa_future_window');
    register_setting('agenda_automatizada_settings', 'aa_google_email');
    register_setting('agenda_automatizada_settings', 'aa_google_motivo');
    register_setting('agenda_automatizada_settings', 'aa_timezone');
    register_setting('agenda_automatizada_settings', 'aa_business_name');
    register_setting('agenda_automatizada_settings', 'aa_business_address');
    register_setting('agenda_automatizada_settings', 'aa_is_virtual');
    register_setting('agenda_automatizada_settings', 'aa_whatsapp_number');
});

// Render de la página
function agenda_automatizada_render_settings_page() {
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
    <!-- AA Root Wrapper -->
    <div class="aa-root">

        <div class="wrap aa-settings-wrap bg-gray-50 min-h-screen -ml-5 p-4 md:p-8">
            
            <!-- Page Header -->
            <header class="aa-page-header mb-8 pb-6 border-b border-gray-200">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                        <span class="text-white text-2xl">⚙️</span>
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Configuración</h1>
                        <p class="text-gray-500 text-sm mt-1">Administra los ajustes de tu sistema de citas</p>
                    </div>
                </div>
            </header>

            <form method="post" action="options.php" class="aa-settings-form">
                <?php settings_fields('agenda_automatizada_settings'); ?>
                <?php do_settings_sections('agenda_automatizada_settings'); ?>

                <div class="aa-sections-container space-y-4">

                    <!-- ========== ACCORDION: Horarios y Disponibilidad ========== -->
                    <div class="aa-accordion is-open bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <button type="button" class="aa-accordion-header flex justify-between items-center w-full py-4 px-6 text-left hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center text-xl">📅</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Horarios y Disponibilidad</h2>
                                    <p class="text-sm text-gray-500 font-normal">Configura los días y horarios de atención</p>
                                </div>
                            </div>
                            <span class="aa-accordion-icon text-gray-400 transition-transform duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </button>
                        <div class="aa-accordion-body">
                            <div class="px-6 pb-6">
                                <div class="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                                    <?php foreach ($days as $key => $label): 
                                        $enabled   = !empty($schedule[$key]['enabled']);
                                        $intervals = $schedule[$key]['intervals'] ?? [];
                                    ?>
                                        <div class="aa-day-block p-4 hover:bg-gray-50 transition-colors bg-white">
                                            <div class="aa-day-toggle flex items-center justify-between">
                                                <label class="flex items-center gap-3 cursor-pointer">
                                                    <input type="checkbox" 
                                                           name="aa_schedule[<?php echo $key; ?>][enabled]" 
                                                           value="1" 
                                                           <?php checked($enabled, true); ?>
                                                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                                    <span class="aa-day-name font-medium text-gray-800"><?php echo $label; ?></span>
                                                </label>
                                                <?php if ($enabled && !empty($intervals)): ?>
                                                    <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded-full"><?php echo count($intervals); ?> intervalo(s)</span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="day-intervals mt-4 ml-8 space-y-3" data-day="<?php echo $key; ?>" style="<?php echo $enabled ? '' : 'display:none;'; ?>">
                                                <?php if (!empty($intervals)): ?>
                                                    <?php foreach ($intervals as $i => $interval): ?>
                                                        <div class="interval flex items-center gap-3 flex-wrap bg-gray-50 p-3 rounded-lg">
                                                            <input type="time" 
                                                                   name="aa_schedule[<?php echo $key; ?>][intervals][<?php echo $i; ?>][start]" 
                                                                   value="<?php echo esc_attr($interval['start']); ?>"
                                                                   class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                                                            <span class="aa-interval-separator text-gray-400 font-medium">→</span>
                                                            <input type="time" 
                                                                   name="aa_schedule[<?php echo $key; ?>][intervals][<?php echo $i; ?>][end]" 
                                                                   value="<?php echo esc_attr($interval['end']); ?>"
                                                                   class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                                                            <button type="button" class="remove-interval text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition-colors" title="Eliminar">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <button type="button" class="add-interval inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 text-sm font-medium py-2 px-3 hover:bg-blue-50 rounded-lg transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                    Añadir intervalo
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========== ACCORDION: Servicios / Motivos ========== -->
                    <div class="aa-accordion bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <button type="button" class="aa-accordion-header flex justify-between items-center w-full py-4 px-6 text-left hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center text-xl">🏷️</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Tipos de Cita / Servicios</h2>
                                    <p class="text-sm text-gray-500 font-normal">Define los servicios que ofreces</p>
                                </div>
                            </div>
                            <span class="aa-accordion-icon text-gray-400 transition-transform duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </button>
                        <div class="aa-accordion-body">
                            <div class="px-6 pb-6">
                                <div id="aa-motivos-container" class="aa-motivos-wrapper">
                                    <ul id="aa-motivos-list" class="aa-motivos-list space-y-2 mb-4"></ul>

                                    <div class="aa-motivo-add-row flex gap-3 flex-wrap">
                                        <input type="text" 
                                               id="aa-motivo-input" 
                                               placeholder="Ej: Corte de cabello"
                                               class="flex-1 min-w-[200px]">
                                        <button type="button" 
                                                id="aa-add-motivo" 
                                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Agregar
                                        </button>
                                    </div>

                                    <input type="hidden" name="aa_google_motivo" id="aa-google-motivo-hidden"
                                        value='<?php echo esc_attr(get_option("aa_google_motivo", json_encode(["Cita general"]))); ?>'>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========== ACCORDION: Parámetros Generales ========== -->
                    <div class="aa-accordion bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <button type="button" class="aa-accordion-header flex justify-between items-center w-full py-4 px-6 text-left hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-xl">⏱️</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Parámetros Generales</h2>
                                    <p class="text-sm text-gray-500 font-normal">Duración, ventana y zona horaria</p>
                                </div>
                            </div>
                            <span class="aa-accordion-icon text-gray-400 transition-transform duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </button>
                        <div class="aa-accordion-body">
                            <div class="px-6 pb-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    
                                    <!-- Duración -->
                                    <div class="space-y-2 p-4 bg-gray-50 rounded-xl">
                                        <label class="block text-sm font-medium text-gray-700" for="aa_slot_duration">Duración de cita</label>
                                        <select name="aa_slot_duration" id="aa_slot_duration" class="w-full">
                                            <option value="30" <?php selected(get_option('aa_slot_duration', 30), 30); ?>>30 minutos</option>
                                            <option value="60" <?php selected(get_option('aa_slot_duration', 30), 60); ?>>60 minutos</option>
                                            <option value="90" <?php selected(get_option('aa_slot_duration', 30), 90); ?>>90 minutos</option>
                                        </select>
                                    </div>

                                    <!-- Ventana futura -->
                                    <div class="space-y-2 p-4 bg-gray-50 rounded-xl">
                                        <label class="block text-sm font-medium text-gray-700" for="aa_future_window">Ventana futura</label>
                                        <select name="aa_future_window" id="aa_future_window" class="w-full">
                                            <option value="15" <?php selected(get_option('aa_future_window', 15), 15); ?>>15 días</option>
                                            <option value="30" <?php selected(get_option('aa_future_window', 15), 30); ?>>30 días</option>
                                            <option value="45" <?php selected(get_option('aa_future_window', 15), 45); ?>>45 días</option>
                                            <option value="60" <?php selected(get_option('aa_future_window', 15), 60); ?>>60 días</option>
                                        </select>
                                        <p class="text-xs text-gray-400">Días en el futuro para agendar</p>
                                    </div>

                                    <!-- Zona horaria -->
                                    <div class="space-y-2 p-4 bg-gray-50 rounded-xl">
                                        <label class="block text-sm font-medium text-gray-700" for="aa_timezone">Zona horaria</label>
                                        <select name="aa_timezone" id="aa_timezone" class="w-full">
                                            <?php
                                            $saved_tz = get_option('aa_timezone', 'America/Mexico_City');
                                            $timezones = [
                                                'America/Mexico_City' => 'México (CDMX)',
                                                'America/Cancun' => 'Cancún',
                                                'America/Tijuana' => 'Tijuana',
                                                'America/Monterrey' => 'Monterrey',
                                                'America/Bogota' => 'Colombia',
                                                'America/Lima' => 'Perú',
                                                'America/Argentina/Buenos_Aires' => 'Argentina',
                                                'America/Santiago' => 'Chile',
                                                'America/New_York' => 'USA Este',
                                                'America/Los_Angeles' => 'USA Pacífico',
                                                'Europe/Madrid' => 'España',
                                                'Europe/London' => 'Reino Unido',
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
                            </div>
                        </div>
                    </div>

                    <!-- ========== ACCORDION: Datos del Negocio ========== -->
                    <div class="aa-accordion bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <button type="button" class="aa-accordion-header flex justify-between items-center w-full py-4 px-6 text-left hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center text-xl">🏢</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Datos del Negocio</h2>
                                    <p class="text-sm text-gray-500 font-normal">Nombre, dirección y contacto</p>
                                </div>
                            </div>
                            <span class="aa-accordion-icon text-gray-400 transition-transform duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </button>
                        <div class="aa-accordion-body">
                            <div class="px-6 pb-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    
                                    <!-- Nombre del negocio -->
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-gray-700" for="aa_business_name">Nombre del negocio</label>
                                        <input type="text" name="aa_business_name" id="aa_business_name"
                                               value="<?php echo esc_attr(get_option('aa_business_name', '')); ?>" 
                                               placeholder="Ej: Salón de Belleza María"
                                               class="w-full">
                                    </div>

                                    <!-- WhatsApp -->
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-gray-700" for="aa_whatsapp_number">WhatsApp del negocio</label>
                                        <input type="tel" name="aa_whatsapp_number" id="aa_whatsapp_number"
                                               value="<?php echo esc_attr(get_option('aa_whatsapp_number', '')); ?>" 
                                               placeholder="521234567890"
                                               pattern="[0-9]{10,15}"
                                               class="w-full">
                                        <p class="text-xs text-gray-400">Con código de país, sin espacios</p>
                                    </div>

                                    <!-- Tipo de citas (checkbox) -->
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-gray-700">Modalidad</label>
                                        <label class="aa-checkbox-label inline-flex items-center gap-3 cursor-pointer p-4 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors w-full">
                                            <input type="checkbox" name="aa_is_virtual" value="1" 
                                                   id="aa-is-virtual-checkbox"
                                                   <?php checked(get_option('aa_is_virtual', 0), 1); ?>
                                                   class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                            <span class="text-sm text-gray-700">Citas virtuales (sin dirección física)</span>
                                        </label>
                                    </div>

                                    <!-- Dirección física -->
                                    <div class="space-y-2" id="aa-address-row">
                                        <label class="block text-sm font-medium text-gray-700" for="aa-business-address">Dirección física</label>
                                        <textarea name="aa_business_address" 
                                                  id="aa-business-address" 
                                                  rows="3" 
                                                  placeholder="Ej: Av. Reforma 123, Col. Centro, CDMX"
                                                  class="w-full resize-none"><?php echo esc_textarea(get_option('aa_business_address', '')); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========== ACCORDION: Google Calendar ========== -->
                    <div class="aa-accordion bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <button type="button" class="aa-accordion-header flex justify-between items-center w-full py-4 px-6 text-left hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center text-xl">📆</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Google Calendar</h2>
                                    <p class="text-sm text-gray-500 font-normal">Sincronización con tu calendario</p>
                                </div>
                            </div>
                            <span class="aa-accordion-icon text-gray-400 transition-transform duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </button>
                        <div class="aa-accordion-body">
                            <div class="px-6 pb-6">
                                <?php 
                                $google_email = get_option('aa_google_email', '');
                                $is_sync_invalid = SyncService::is_sync_invalid();
                                ?>
                                <input type="hidden" name="aa_google_email" value="<?php echo esc_attr($google_email); ?>">
                                
                                <?php if ($google_email && !$is_sync_invalid): ?>
                                    <!-- Estado: Conectado -->
                                    <div class="aa-google-connected flex items-center justify-between flex-wrap gap-4 p-4 bg-green-50 rounded-xl border border-green-100">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-green-800">Conectado correctamente</p>
                                                <p class="text-sm text-green-700"><?php echo esc_html($google_email); ?></p>
                                            </div>
                                        </div>
                                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=aa_disconnect_google')); ?>" 
                                           class="aa-btn-disconnect inline-flex items-center px-4 py-2 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                            Desconectar
                                        </a>
                                    </div>
                                <?php elseif ($google_email && $is_sync_invalid): ?>
                                    <!-- Estado: Error / Reconexión requerida -->
                                    <div class="aa-google-error">
                                        <div class="flex items-start gap-4 p-4 bg-amber-50 border border-amber-200 rounded-xl mb-4">
                                            <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                <span class="text-amber-600 text-lg">⚠️</span>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-amber-800">Reconexión requerida</p>
                                                <p class="text-sm text-amber-700 mt-1">El token ha caducado o fue rechazado.</p>
                                                <p class="text-xs text-amber-600 mt-2">Email anterior: <?php echo esc_html($google_email); ?></p>
                                            </div>
                                        </div>
                                        <a href="<?php echo esc_url(SyncService::get_auth_url()); ?>" 
                                           class="aa-btn-reconnect inline-flex items-center gap-2 px-5 py-3 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors shadow-sm">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                            </svg>
                                            Reconectar con Google
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <!-- Estado: No conectado -->
                                    <div class="aa-google-disconnected text-center py-8">
                                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <span class="text-4xl">📅</span>
                                        </div>
                                        <p class="text-gray-900 font-medium mb-2">No hay cuenta conectada</p>
                                        <p class="text-gray-500 text-sm mb-6">Conecta tu Google Calendar para sincronizar citas</p>
                                        <a href="<?php echo esc_url(SyncService::get_auth_url()); ?>" 
                                           class="aa-btn-connect inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors shadow-sm">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                            </svg>
                                            Conectar con Google
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div><!-- /.aa-sections-container -->

                <!-- Submit Button -->
                <div class="aa-form-actions mt-8 flex justify-end sticky bottom-4">
                    <button type="submit" name="submit" 
                            class="inline-flex items-center gap-2 px-8 py-4 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-all shadow-lg hover:shadow-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Guardar Configuración
                    </button>
                </div>
            </form>

            <!-- ========== SECCIÓN: Asistentes ========== -->
            <div class="mt-8">
                <?php aa_render_asistentes_section(); ?>
            </div>

        </div><!-- /.wrap -->

        <!-- Accordion Toggle Script -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.aa-accordion-header').forEach(function(header) {
                header.addEventListener('click', function() {
                    var accordion = this.closest('.aa-accordion');
                    accordion.classList.toggle('is-open');
                });
            });
        });
        </script>

    </div><!-- /.aa-root -->
    <?php
}

// Guardar el email de Google al volver del backend
add_action('admin_post_aa_connect_google', function() {
    if (!current_user_can('manage_options')) return;

    $email = !empty($_GET['email']) ? $_GET['email'] : '';
    $secret = !empty($_GET['client_secret']) ? $_GET['client_secret'] : '';

    if ($email && $secret) {
        // Usar el servicio para manejar el éxito del OAuth
        SyncService::handle_oauth_success($email, $secret);
    }

    wp_redirect(admin_url('admin.php?page=agenda-automatizada-settings'));
    exit;
});

// Desconectar Google
add_action('admin_post_aa_disconnect_google', function() {
    if (!current_user_can('manage_options')) return;

    delete_option('aa_google_email');
    wp_redirect(admin_url('admin.php?page=agenda-automatizada-settings'));
    exit;
});


// creacion de asistentes 
// ------------------------------------------------
// --------------------------------

// 🔹 Sección para gestión de asistentes
function aa_render_asistentes_section() {
    if (!current_user_can('administrator')) return;

    if (isset($_POST['aa_crear_asistente'])) {
        // Verificar nonce de seguridad
        check_admin_referer('aa_crear_asistente_nonce');

        $nombre = sanitize_text_field($_POST['aa_nombre']);
        $email = sanitize_email($_POST['aa_email']);
        $password = sanitize_text_field($_POST['aa_password']);

        // Crear usuario de WordPress
        $user_id = wp_create_user($email, $password, $email);
        if (!is_wp_error($user_id)) {
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $nombre,
                'role' => 'aa_asistente'
            ]);

            // Enviar correo de bienvenida
            $subject = 'Acceso a tu panel de asistente';
            $message = "Hola $nombre,\n\n".
                       "Se te ha creado una cuenta para acceder al panel de asistente.\n\n".
                       "Usuario: $email\n".
                       "Contraseña: $password\n".
                       "Accede aquí: " . wp_login_url() . "\n\n".
                       "Por seguridad, cambia tu contraseña al iniciar sesión.";
            wp_mail($email, $subject, $message);

            echo '<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800 text-sm">✅ Asistente creado y notificado por correo.</div>';
        } else {
            echo '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm">❌ Error: '.$user_id->get_error_message().'</div>';
        }
    }

    if (isset($_POST['aa_inhabilitar_asistente'])) {
        // Verificar nonce de seguridad
        check_admin_referer('aa_inhabilitar_asistente_nonce');

        $id = intval($_POST['aa_user_id']);
        if ($id) {
            wp_update_user(['ID' => $id, 'role' => '']); // sin rol = inhabilitado
            echo '<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800 text-sm">✅ Asistente inhabilitado correctamente.</div>';
        }
    }

    // Obtener lista de asistentes
    $asistentes = get_users(['role' => 'aa_asistente']);
    ?>
    
    <!-- ========== ACCORDION: Asistentes ========== -->
    <div class="aa-accordion bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <button type="button" class="aa-accordion-header flex justify-between items-center w-full py-4 px-6 text-left hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center text-xl">👥</span>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Gestión de Asistentes</h2>
                    <p class="text-sm text-gray-500 font-normal">Crea y administra cuentas de asistentes</p>
                </div>
            </div>
            <span class="aa-accordion-icon text-gray-400 transition-transform duration-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </span>
        </button>
        <div class="aa-accordion-body">
            <div class="px-6 pb-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Formulario crear asistente -->
                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </span>
                            Crear nuevo asistente
                        </h3>
                        <form method="post" class="aa-assistant-form space-y-4">
                            <?php wp_nonce_field('aa_crear_asistente_nonce'); ?>
                            
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700" for="aa_nombre">Nombre completo</label>
                                <input type="text" id="aa_nombre" name="aa_nombre" required 
                                       placeholder="Ej: María García"
                                       class="w-full">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700" for="aa_email">Correo electrónico</label>
                                <input type="email" id="aa_email" name="aa_email" required 
                                       placeholder="correo@ejemplo.com"
                                       class="w-full">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700" for="aa_password">Contraseña inicial</label>
                                <input type="text" id="aa_password" name="aa_password" required 
                                       placeholder="Contraseña segura"
                                       class="w-full">
                                <p class="text-xs text-gray-400">Se enviará por correo al asistente.</p>
                            </div>

                            <button type="submit" name="aa_crear_asistente" 
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Crear Asistente
                            </button>
                        </form>
                    </div>

                    <!-- Lista de asistentes activos -->
                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">👤</span>
                            Asistentes activos
                            <?php if (!empty($asistentes)): ?>
                                <span class="ml-auto text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded-full font-medium"><?php echo count($asistentes); ?></span>
                            <?php endif; ?>
                        </h3>
                        
                        <?php if (empty($asistentes)): ?>
                            <div class="text-center py-8">
                                <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <span class="text-gray-400 text-2xl">👤</span>
                                </div>
                                <p class="aa-empty-state text-gray-500 text-sm">No hay asistentes registrados</p>
                                <p class="text-gray-400 text-xs mt-1">Crea uno usando el formulario</p>
                            </div>
                        <?php else: ?>
                            <div class="aa-assistants-grid space-y-3">
                                <?php foreach ($asistentes as $user): ?>
                                    <div class="aa-assistant-card flex items-center justify-between p-3 bg-white rounded-xl border border-gray-100 hover:border-gray-200 transition-colors">
                                        <div class="aa-assistant-info flex items-center gap-3">
                                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center">
                                                <span class="text-white font-medium text-sm">
                                                    <?php echo strtoupper(substr($user->display_name, 0, 2)); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="aa-assistant-name block text-sm font-medium text-gray-900"><?php echo esc_html($user->display_name); ?></span>
                                                <span class="aa-assistant-email block text-xs text-gray-500"><?php echo esc_html($user->user_email); ?></span>
                                            </div>
                                        </div>
                                        <div class="aa-assistant-actions">
                                            <form method="post">
                                                <?php wp_nonce_field('aa_inhabilitar_asistente_nonce'); ?>
                                                <input type="hidden" name="aa_user_id" value="<?php echo esc_attr($user->ID); ?>">
                                                <button type="submit" name="aa_inhabilitar_asistente" 
                                                        class="aa-btn-disable text-xs text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-colors font-medium"
                                                        onclick="return confirm('¿Estás seguro de inhabilitar este asistente?');">
                                                    Inhabilitar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}