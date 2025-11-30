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
                            <option value="90" <?php selected(get_option('aa_slot_duration', 30), 90); ?>>90 minutos</option>
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
                        $is_sync_invalid = SyncService::is_sync_invalid();

                        echo '<input type="hidden" name="aa_google_email" value="' . esc_attr($google_email) . '">';
                        
                        if ($google_email && !$is_sync_invalid) {
                            // Estado válido - conectado correctamente
                            echo "<p><strong>✅ Sincronizado con: $google_email</strong></p>";
                            echo "<a href='" . esc_url(admin_url('admin-post.php?action=aa_disconnect_google')) . "' class='button'>Desconectar</a>";
                        } elseif ($google_email && $is_sync_invalid) {
                            // Token caducado o rechazado - requiere reconexión
                            echo "<p style='color: #d63638; font-weight: bold;'>⚠️ Token caducado o conexión rechazada. Se requiere reconexión manual.</p>";
                            echo "<p><strong>Email anterior: $google_email</strong></p>";
                            echo "<a href='" . esc_url(SyncService::get_auth_url()) . "' class='button button-primary'>Reconectar con Google</a>";
                        } else {
                            // No hay cuenta conectada
                            echo "<p><strong>No sincronizado</strong></p>";
                            echo "<a href='" . esc_url(SyncService::get_auth_url()) . "' class='button button-primary'>Conectar con Google</a>";
                        }
                        ?>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <!-- 🔹 Sección de gestión de asistentes -->
        <hr style="margin: 40px 0;">
        <?php aa_render_asistentes_section(); ?>

    </div>
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

            echo '<div class="updated"><p>✅ Asistente creado y notificado por correo.</p></div>';
        } else {
            echo '<div class="error"><p>❌ Error: '.$user_id->get_error_message().'</p></div>';
        }
    }

    if (isset($_POST['aa_inhabilitar_asistente'])) {
        // Verificar nonce de seguridad
        check_admin_referer('aa_inhabilitar_asistente_nonce');

        $id = intval($_POST['aa_user_id']);
        if ($id) {
            wp_update_user(['ID' => $id, 'role' => '']); // sin rol = inhabilitado
            echo '<div class="updated"><p>✅ Asistente inhabilitado correctamente.</p></div>';
        }
    }

    // Obtener lista de asistentes
    $asistentes = get_users(['role' => 'aa_asistente']);
    ?>
    
    <h2>👥 Gestión de Asistentes</h2>
    
    <h3>Crear nuevo asistente</h3>
    <form method="post" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px; max-width: 600px;">
        <?php wp_nonce_field('aa_crear_asistente_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="aa_nombre">Nombre completo</label></th>
                <td><input type="text" id="aa_nombre" name="aa_nombre" required style="width: 100%;"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aa_email">Correo electrónico</label></th>
                <td><input type="email" id="aa_email" name="aa_email" required style="width: 100%;"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aa_password">Contraseña</label></th>
                <td>
                    <input type="text" id="aa_password" name="aa_password" required style="width: 100%;">
                    <p class="description">El asistente recibirá esta contraseña por correo.</p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="aa_crear_asistente" class="button button-primary" value="Crear Asistente">
        </p>
    </form>

    <h3 style="margin-top: 40px;">Asistentes activos</h3>
    <?php if (empty($asistentes)): ?>
        <p>No hay asistentes registrados.</p>
    <?php else: ?>
        <table class="widefat" style="max-width: 800px;">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Fecha de creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($asistentes as $user): ?>
                    <tr>
                        <td><?php echo esc_html($user->display_name); ?></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($user->user_registered))); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('aa_inhabilitar_asistente_nonce'); ?>
                                <input type="hidden" name="aa_user_id" value="<?php echo esc_attr($user->ID); ?>">
                                <input type="submit" name="aa_inhabilitar_asistente" class="button" value="Inhabilitar" 
                                       onclick="return confirm('¿Estás seguro de inhabilitar este asistente?');">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <?php
}