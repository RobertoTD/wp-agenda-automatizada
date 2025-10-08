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
    register_setting('agenda_automatizada_settings', 'aa_work_days');
    register_setting('agenda_automatizada_settings', 'aa_start_time');
    register_setting('agenda_automatizada_settings', 'aa_end_time');
    register_setting('agenda_automatizada_settings', 'aa_slot_duration');
    register_setting('agenda_automatizada_settings', 'aa_future_window');

     register_setting('agenda_automatizada_settings', 'aa_google_email');
});

// Render de la página
function agenda_automatizada_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configuración de Agenda Automatizada</h1>
        <form method="post" action="options.php">
            <?php settings_fields('agenda_automatizada_settings'); ?>
            <?php do_settings_sections('agenda_automatizada_settings'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Días hábiles</th>
                    <td>
                        <?php
                        $days = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
                        $work_days = get_option('aa_work_days', []);
                        foreach ($days as $i => $day) {
                            $checked = in_array($i, (array)$work_days) ? 'checked' : '';
                            echo "<label><input type='checkbox' name='aa_work_days[]' value='$i' $checked> $day</label><br>";
                        }
                        ?>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Hora inicio</th>
                    <td><input type="time" name="aa_start_time" value="<?php echo esc_attr(get_option('aa_start_time', '09:00')); ?>"></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Hora fin</th>
                    <td><input type="time" name="aa_end_time" value="<?php echo esc_attr(get_option('aa_end_time', '18:00')); ?>"></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Duración de cita (minutos)</th>
                    <td><input type="number" name="aa_slot_duration" value="<?php echo esc_attr(get_option('aa_slot_duration', 30)); ?>"></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Ventana futura (días)</th>
                    <td><input type="number" name="aa_future_window" value="<?php echo esc_attr(get_option('aa_future_window', 15)); ?>"></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Google Calendar</th>
                    <td>
                        <?php 
                        $google_email = get_option('aa_google_email', '');
                        if ($google_email) {
                            echo "<p><strong>Sincronizado con: $google_email</strong></p>";
                            echo "<a href='" . esc_url(admin_url('admin-post.php?action=aa_disconnect_google')) . "' class='button'>Desconectar</a>";
                        } else {
                            // Backend local en pruebas
                            $backend_url = "http://localhost:3000/oauth/authorize";

                            // El state será el dominio actual de WordPress (dinámico)
                            $state = home_url();

                            // El redirect_uri apunta al admin-post de WP
                            $redirect_uri = admin_url('admin-post.php?action=aa_connect_google');

                            // Construir URL final
                            $auth_url = $backend_url 
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
