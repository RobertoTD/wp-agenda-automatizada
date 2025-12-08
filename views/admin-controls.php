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
    <div class="wrap aa-settings-wrap">
        <header class="aa-page-header">
            <h1>Configuración de Agenda Automatizada</h1>
        </header>

        <form method="post" action="options.php" class="aa-settings-form">
            <?php settings_fields('agenda_automatizada_settings'); ?>
            <?php do_settings_sections('agenda_automatizada_settings'); ?>

            <div class="aa-sections-container">

                <!-- ========== SECCIÓN A: Disponibilidad por día ========== -->
                <section class="aa-section aa-section-schedule">
                    <header class="aa-section-header">
                        <h2>📅 Horarios y Disponibilidad</h2>
                        <p>Configura los días y horarios en que tu negocio está disponible para citas.</p>
                    </header>
                    <div class="aa-section-body">
                        <div class="aa-days-list">
                            <?php foreach ($days as $key => $label): 
                                $enabled   = !empty($schedule[$key]['enabled']);
                                $intervals = $schedule[$key]['intervals'] ?? [];
                            ?>
                                <div class="aa-day-block">
                                    <div class="aa-day-toggle">
                                        <label>
                                            <input type="checkbox" name="aa_schedule[<?php echo $key; ?>][enabled]" value="1" <?php checked($enabled, true); ?>>
                                            <span class="aa-day-name"><?php echo $label; ?></span>
                                        </label>
                                    </div>

                                    <div class="day-intervals" data-day="<?php echo $key; ?>" style="<?php echo $enabled ? '' : 'display:none;'; ?>">
                                        <?php if (!empty($intervals)): ?>
                                            <?php foreach ($intervals as $i => $interval): ?>
                                                <div class="interval">
                                                    <input type="time" name="aa_schedule[<?php echo $key; ?>][intervals][<?php echo $i; ?>][start]" value="<?php echo esc_attr($interval['start']); ?>">
                                                    <span class="aa-interval-separator">—</span>
                                                    <input type="time" name="aa_schedule[<?php echo $key; ?>][intervals][<?php echo $i; ?>][end]" value="<?php echo esc_attr($interval['end']); ?>">
                                                    <button type="button" class="remove-interval button">Eliminar</button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <button type="button" class="add-interval button">Añadir intervalo</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- ========== SECCIÓN B: Servicios / Motivos ========== -->
                <section class="aa-section aa-section-services">
                    <header class="aa-section-header">
                        <h2>🏷️ Tipos de Cita / Servicios</h2>
                        <p>Agrega los tipos de servicios o motivos de cita que ofreces.</p>
                    </header>
                    <div class="aa-section-body">
                        <div id="aa-motivos-container" class="aa-motivos-wrapper">
                            <ul id="aa-motivos-list" class="aa-motivos-list"></ul>

                            <div class="aa-motivo-add-row">
                                <input type="text" id="aa-motivo-input" placeholder="Ej: Corte de cabello">
                                <button type="button" id="aa-add-motivo" class="button">Agregar motivo</button>
                            </div>

                            <!-- Campo oculto donde se guarda el JSON real -->
                            <input type="hidden" name="aa_google_motivo" id="aa-google-motivo-hidden"
                                value='<?php echo esc_attr(get_option("aa_google_motivo", json_encode(["Cita general"]))); ?>'>
                        </div>
                    </div>
                </section>

                <!-- ========== SECCIÓN C: Parámetros generales ========== -->
                <section class="aa-section aa-section-params">
                    <header class="aa-section-header">
                        <h2>⏱️ Parámetros Generales</h2>
                        <p>Configura la duración de las citas y la ventana de disponibilidad futura.</p>
                    </header>
                    <div class="aa-section-body">
                        <div class="aa-field-group">
                            <label class="aa-field-label" for="aa_slot_duration">Duración de cita</label>
                            <div class="aa-field-control">
                                <select name="aa_slot_duration" id="aa_slot_duration">
                                    <option value="30" <?php selected(get_option('aa_slot_duration', 30), 30); ?>>30 minutos</option>
                                    <option value="60" <?php selected(get_option('aa_slot_duration', 30), 60); ?>>60 minutos</option>
                                    <option value="90" <?php selected(get_option('aa_slot_duration', 30), 90); ?>>90 minutos</option>
                                </select>
                            </div>
                        </div>

                        <div class="aa-field-group">
                            <label class="aa-field-label" for="aa_future_window">Ventana futura</label>
                            <div class="aa-field-control">
                                <select name="aa_future_window" id="aa_future_window">
                                    <option value="15" <?php selected(get_option('aa_future_window', 15), 15); ?>>15 días</option>
                                    <option value="30" <?php selected(get_option('aa_future_window', 15), 30); ?>>30 días</option>
                                    <option value="45" <?php selected(get_option('aa_future_window', 15), 45); ?>>45 días</option>
                                    <option value="60" <?php selected(get_option('aa_future_window', 15), 60); ?>>60 días</option>
                                </select>
                                <p class="aa-field-hint">Hasta cuántos días en el futuro se pueden agendar citas.</p>
                            </div>
                        </div>

                        <div class="aa-field-group">
                            <label class="aa-field-label" for="aa_timezone">Zona horaria</label>
                            <div class="aa-field-control">
                                <select name="aa_timezone" id="aa_timezone">
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
                                <p class="aa-field-hint">Los horarios se ajustarán automáticamente a esta zona.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========== SECCIÓN D: Datos del negocio ========== -->
                <section class="aa-section aa-section-business">
                    <header class="aa-section-header">
                        <h2>🏢 Datos del Negocio</h2>
                        <p>Información que aparecerá en las confirmaciones y recordatorios de cita.</p>
                    </header>
                    <div class="aa-section-body">
                        <div class="aa-field-group">
                            <label class="aa-field-label" for="aa_business_name">Nombre del negocio</label>
                            <div class="aa-field-control">
                                <input type="text" name="aa_business_name" id="aa_business_name"
                                       value="<?php echo esc_attr(get_option('aa_business_name', '')); ?>" 
                                       placeholder="Ej: Salón de Belleza María">
                                <p class="aa-field-hint">Nombre que aparecerá en las confirmaciones de cita.</p>
                            </div>
                        </div>

                        <div class="aa-field-group">
                            <label class="aa-field-label">Tipo de citas</label>
                            <div class="aa-field-control">
                                <label class="aa-checkbox-label">
                                    <input type="checkbox" name="aa_is_virtual" value="1" 
                                           id="aa-is-virtual-checkbox"
                                           <?php checked(get_option('aa_is_virtual', 0), 1); ?>>
                                    <span>Las citas son virtuales (sin dirección física)</span>
                                </label>
                            </div>
                        </div>

                        <div class="aa-field-group" id="aa-address-row">
                            <label class="aa-field-label" for="aa-business-address">Dirección física</label>
                            <div class="aa-field-control">
                                <textarea name="aa_business_address" 
                                          id="aa-business-address" 
                                          rows="3" 
                                          placeholder="Ej: Av. Reforma 123, Col. Centro, CDMX"><?php echo esc_textarea(get_option('aa_business_address', '')); ?></textarea>
                                <p class="aa-field-hint">Dirección donde se realizarán las citas presenciales.</p>
                            </div>
                        </div>

                        <div class="aa-field-group">
                            <label class="aa-field-label" for="aa_whatsapp_number">WhatsApp del negocio</label>
                            <div class="aa-field-control">
                                <input type="tel" name="aa_whatsapp_number" id="aa_whatsapp_number"
                                       value="<?php echo esc_attr(get_option('aa_whatsapp_number', '')); ?>" 
                                       placeholder="521234567890"
                                       pattern="[0-9]{10,15}">
                                <p class="aa-field-hint">Número con código de país sin espacios ni símbolos (Ej: 5215522992290).</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========== SECCIÓN E: Google Calendar ========== -->
                <section class="aa-section aa-section-google">
                    <header class="aa-section-header">
                        <h2>📆 Google Calendar</h2>
                        <p>Sincroniza tus citas con Google Calendar para evitar conflictos.</p>
                    </header>
                    <div class="aa-section-body">
                        <div class="aa-google-status">
                            <?php 
                            $google_email = get_option('aa_google_email', '');
                            $is_sync_invalid = SyncService::is_sync_invalid();

                            echo '<input type="hidden" name="aa_google_email" value="' . esc_attr($google_email) . '">';
                            
                            if ($google_email && !$is_sync_invalid) {
                                // Estado válido - conectado correctamente
                                echo '<div class="aa-google-connected">';
                                echo "<p class='aa-google-email'><strong>✅ Sincronizado con:</strong> $google_email</p>";
                                echo "<a href='" . esc_url(admin_url('admin-post.php?action=aa_disconnect_google')) . "' class='button aa-btn-disconnect'>Desconectar</a>";
                                echo '</div>';
                            } elseif ($google_email && $is_sync_invalid) {
                                // Token caducado o rechazado - requiere reconexión
                                echo '<div class="aa-google-error">';
                                echo "<p class='aa-google-warning'>⚠️ Token caducado o conexión rechazada. Se requiere reconexión manual.</p>";
                                echo "<p><strong>Email anterior:</strong> $google_email</p>";
                                echo "<a href='" . esc_url(SyncService::get_auth_url()) . "' class='button button-primary aa-btn-reconnect'>Reconectar con Google</a>";
                                echo '</div>';
                            } else {
                                // No hay cuenta conectada
                                echo '<div class="aa-google-disconnected">';
                                echo "<p><strong>No sincronizado</strong></p>";
                                echo "<a href='" . esc_url(SyncService::get_auth_url()) . "' class='button button-primary aa-btn-connect'>Conectar con Google</a>";
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </section>

            </div><!-- /.aa-sections-container -->

            <div class="aa-form-actions">
                <?php submit_button('Guardar Configuración', 'primary', 'submit', false); ?>
            </div>
        </form>

        <!-- ========== SECCIÓN F: Asistentes ========== -->
        <section class="aa-section aa-section-assistants">
            <?php aa_render_asistentes_section(); ?>
        </section>

    </div><!-- /.wrap -->
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
    
    <header class="aa-section-header">
        <h2>👥 Gestión de Asistentes</h2>
        <p>Crea y administra las cuentas de los asistentes que pueden gestionar citas.</p>
    </header>

    <div class="aa-section-body">
        <!-- Formulario crear asistente -->
        <div class="aa-subsection aa-create-assistant">
            <h3 class="aa-subsection-title">Crear nuevo asistente</h3>
            <form method="post" class="aa-assistant-form">
                <?php wp_nonce_field('aa_crear_asistente_nonce'); ?>
                
                <div class="aa-field-group">
                    <label class="aa-field-label" for="aa_nombre">Nombre completo</label>
                    <div class="aa-field-control">
                        <input type="text" id="aa_nombre" name="aa_nombre" required placeholder="Ej: María García">
                    </div>
                </div>

                <div class="aa-field-group">
                    <label class="aa-field-label" for="aa_email">Correo electrónico</label>
                    <div class="aa-field-control">
                        <input type="email" id="aa_email" name="aa_email" required placeholder="correo@ejemplo.com">
                    </div>
                </div>

                <div class="aa-field-group">
                    <label class="aa-field-label" for="aa_password">Contraseña</label>
                    <div class="aa-field-control">
                        <input type="text" id="aa_password" name="aa_password" required placeholder="Contraseña inicial">
                        <p class="aa-field-hint">El asistente recibirá esta contraseña por correo.</p>
                    </div>
                </div>

                <div class="aa-form-actions">
                    <input type="submit" name="aa_crear_asistente" class="button button-primary" value="Crear Asistente">
                </div>
            </form>
        </div>

        <!-- Lista de asistentes activos -->
        <div class="aa-subsection aa-assistant-list">
            <h3 class="aa-subsection-title">Asistentes activos</h3>
            
            <?php if (empty($asistentes)): ?>
                <p class="aa-empty-state">No hay asistentes registrados.</p>
            <?php else: ?>
                <div class="aa-assistants-grid">
                    <?php foreach ($asistentes as $user): ?>
                        <div class="aa-assistant-card">
                            <div class="aa-assistant-info">
                                <span class="aa-assistant-name"><?php echo esc_html($user->display_name); ?></span>
                                <span class="aa-assistant-email"><?php echo esc_html($user->user_email); ?></span>
                                <span class="aa-assistant-date">Creado: <?php echo esc_html(date('d/m/Y', strtotime($user->user_registered))); ?></span>
                            </div>
                            <div class="aa-assistant-actions">
                                <form method="post">
                                    <?php wp_nonce_field('aa_inhabilitar_asistente_nonce'); ?>
                                    <input type="hidden" name="aa_user_id" value="<?php echo esc_attr($user->ID); ?>">
                                    <input type="submit" name="aa_inhabilitar_asistente" class="button aa-btn-disable" value="Inhabilitar" 
                                           onclick="return confirm('¿Estás seguro de inhabilitar este asistente?');">
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
}