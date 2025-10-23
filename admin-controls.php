<?php
if (!defined('ABSPATH')) exit;

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

// Registrar settings
add_action('admin_init', function() {
    register_setting('agenda_automatizada_settings', 'aa_schedule');
    register_setting('agenda_automatizada_settings', 'aa_slot_duration');
    register_setting('agenda_automatizada_settings', 'aa_future_window');
    register_setting('agenda_automatizada_settings', 'aa_google_email');
    register_setting('agenda_automatizada_settings', 'aa_google_motivo');
    register_setting('agenda_automatizada_settings', 'aa_timezone');
    register_setting('agenda_automatizada_settings', 'aa_business_name'); // 🔹 Nombre del negocio
    register_setting('agenda_automatizada_settings', 'aa_business_address'); // 🔹 Dirección física
    register_setting('agenda_automatizada_settings', 'aa_is_virtual'); // 🔹 Citas virtuales
    register_setting('agenda_automatizada_settings', 'aa_whatsapp_number'); // 🔹 WhatsApp
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
    <div class="wrap">
        <h1>Configuración de Agenda Automatizada</h1>
        <form method="post" action="options.php">
            <?php settings_fields('agenda_automatizada_settings'); ?>
            <?php do_settings_sections('agenda_automatizada_settings'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Disponibilidad por día</th>
                    <td>
                        <?php foreach ($days as $key => $label): 
                            $enabled   = !empty($schedule[$key]['enabled']);
                            $intervals = $schedule[$key]['intervals'] ?? [];
                        ?>
                            <div class="aa-day-block" style="margin-bottom:15px;">
                                <label>
                                    <input type="checkbox" name="aa_schedule[<?php echo $key; ?>][enabled]" value="1" <?php checked($enabled, true); ?>>
                                    <?php echo $label; ?>
                                </label>

                                <div class="day-intervals" data-day="<?php echo $key; ?>" style="<?php echo $enabled ? '' : 'display:none;'; ?> margin-left:20px;">
                                    <?php if (!empty($intervals)): ?>
                                        <?php foreach ($intervals as $i => $interval): ?>
                                            <div class="interval">
                                                <input type="time" name="aa_schedule[<?php echo $key; ?>][intervals][<?php echo $i; ?>][start]" value="<?php echo esc_attr($interval['start']); ?>">
                                                <input type="time" name="aa_schedule[<?php echo $key; ?>][intervals][<?php echo $i; ?>][end]" value="<?php echo esc_attr($interval['end']); ?>">
                                                <button type="button" class="remove-interval button">Eliminar</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <button type="button" class="add-interval button">Añadir intervalo</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <!-- Motivos de la cita -->
                <tr valign="top">
                <th scope="row">Motivos de la cita</th>
                <td>
                    <div id="aa-motivos-container">
                        <ul id="aa-motivos-list"></ul>

                        <div style="margin-top:10px; display:flex; gap:8px; align-items:center;">
                            <input type="text" id="aa-motivo-input" placeholder="Ej: Corte de cabello" style="width:250px;">
                            <button type="button" id="aa-add-motivo" class="button">Agregar motivo</button>
                        </div>

                        <!-- Campo oculto donde se guarda el JSON real -->
                        <input type="hidden" name="aa_google_motivo" id="aa-google-motivo-hidden"
                            value='<?php echo esc_attr(get_option("aa_google_motivo", json_encode(["Cita general"]))); ?>'>
                    </div>

                    <p class="description">Agrega los tipos de servicios o motivos de cita (uno por uno).</p>
                </td>
                </tr>
                <!-- Duración de cita -->
                <tr valign="top">
                    <th scope="row">Duración de cita (minutos)</th>
                    <td>
                        <select name="aa_slot_duration">
                            <option value="30" <?php selected(get_option('aa_slot_duration', 30), 30); ?>>30 minutos</option>
                            <option value="60" <?php selected(get_option('aa_slot_duration', 30), 60); ?>>60 minutos</option>
                        </select>
                    </td>
                </tr>
                    <!-- Ventana futura --> 
                <tr valign="top">
                    <th scope="row">Ventana futura (días)</th>
                    <td>
                        <select name="aa_future_window">
                            <option value="15" <?php selected(get_option('aa_future_window', 15), 15); ?>>15 días</option>
                            <option value="30" <?php selected(get_option('aa_future_window', 15), 30); ?>>30 días</option>
                            <option value="45" <?php selected(get_option('aa_future_window', 15), 45); ?>>45 días</option>
                            <option value="60" <?php selected(get_option('aa_future_window', 15), 60); ?>>60 días</option>
                        </select>
                    </td>
                </tr>
                <!-- 🔹 Nueva fila: Zona horaria -->
                <tr valign="top">
                    <th scope="row">Zona horaria del negocio</th>
                    <td>
                        <select name="aa_timezone">
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
                        <p class="description">Selecciona la zona horaria donde opera tu negocio. Los horarios se ajustarán automáticamente.</p>
                    </td>
                </tr>
                <!-- 🔹 Nombre del negocio -->
                <tr valign="top">
                    <th scope="row">Nombre del negocio</th>
                    <td>
                        <input type="text" name="aa_business_name" 
                               value="<?php echo esc_attr(get_option('aa_business_name', '')); ?>" 
                               style="width: 100%; max-width: 400px;" 
                               placeholder="Ej: Salón de Belleza María">
                        <p class="description">Nombre que aparecerá en las confirmaciones de cita.</p>
                    </td>
                </tr>

                <!-- 🔹 Citas virtuales -->
                <tr valign="top">
                    <th scope="row">Tipo de citas</th>
                    <td>
                        <label>
                            <input type="checkbox" name="aa_is_virtual" value="1" 
                                   id="aa-is-virtual-checkbox"
                                   <?php checked(get_option('aa_is_virtual', 0), 1); ?>>
                            Las citas son virtuales (sin dirección física)
                        </label>
                    </td>
                </tr>

                <!-- 🔹 Dirección física -->
                <tr valign="top" id="aa-address-row">
                    <th scope="row">Dirección física</th>
                    <td>
                        <textarea name="aa_business_address" 
                                  id="aa-business-address" 
                                  rows="3" 
                                  style="width: 100%; max-width: 400px;"
                                  placeholder="Ej: Av. Reforma 123, Col. Centro, CDMX"><?php echo esc_textarea(get_option('aa_business_address', '')); ?></textarea>
                        <p class="description">Dirección donde se realizarán las citas presenciales.</p>
                    </td>
                </tr>

                <!-- 🔹 WhatsApp del negocio -->
                <tr valign="top">
                    <th scope="row">WhatsApp del negocio</th>
                    <td>
                        <input type="tel" name="aa_whatsapp_number" 
                               value="<?php echo esc_attr(get_option('aa_whatsapp_number', '')); ?>" 
                               style="width: 100%; max-width: 300px;" 
                               placeholder="521234567890"
                               pattern="[0-9]{10,15}">
                        <p class="description">Número con código de país sin espacios ni símbolos (Ej: 5215522992290).</p>
                    </td>
                </tr>

                <!-- Google Calendar -->    
                <tr valign="top">
                    <th scope="row">Google Calendar</th>
                    <td>
                        <?php 
                        $google_email = get_option('aa_google_email', '');

                        echo '<input type="hidden" name="aa_google_email" value="' . esc_attr($google_email) . '">';
                        
                        
                        if ($google_email) {
                            echo "<p><strong>Sincronizado con: $google_email</strong></p>";
                            echo "<a href='" . esc_url(admin_url('admin-post.php?action=aa_disconnect_google')) . "' class='button'>Desconectar</a>";
                        } else {
                            $backend_url = AA_API_BASE_URL . "/oauth/authorize";
                            $state        = home_url();
                            $redirect_uri = admin_url('admin-post.php?action=aa_connect_google');
                            $auth_url     = $backend_url 
                                            . "?state=" . urlencode($state) 
                                            . "&redirect_uri=" . urlencode($redirect_uri);

                            echo "<p><strong>No sincronizado</strong></p>";
                            echo "<a href='" . esc_url($auth_url) . "' class='button button-primary'>Conectar con Google</a>";
                        }
                        ?>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Guardar el email de Google al volver del backend
add_action('admin_post_aa_connect_google', function() {
    if (!current_user_can('manage_options')) return;

    if (!empty($_GET['email'])) {
        update_option('aa_google_email', sanitize_email($_GET['email']));
    }

     // ✅ Guardar el client_secret si viene en la redirección
    if (!empty($_GET['client_secret'])) {
        update_option('aa_client_secret', sanitize_text_field($_GET['client_secret']));
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
