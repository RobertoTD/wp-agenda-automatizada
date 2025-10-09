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

                        echo '<input type="hidden" name="aa_google_email" value="' . esc_attr($google_email) . '">';
                        
                        
                        if ($google_email) {
                            echo "<p><strong>Sincronizado con: $google_email</strong></p>";
                            echo "<a href='" . esc_url(admin_url('admin-post.php?action=aa_disconnect_google')) . "' class='button'>Desconectar</a>";
                        } else {
                            $backend_url  = "http://localhost:3000/oauth/authorize";
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
