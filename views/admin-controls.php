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
        'aa_render_settings_iframe_page',
        'dashicons-calendar-alt'
    );
});

// Render iframe container for settings page
function aa_render_settings_iframe_page() {
    $iframe_url = admin_url('admin-post.php?action=aa_iframe_content&module=dashboard');
    ?>
    <div class="wrap aa-iframe-wrapper" style="margin: 0; padding: 0;">
        <iframe 
            id="aa-settings-iframe"
            src="<?php echo esc_url($iframe_url); ?>"
            style="width: 100%; border: none; display: block;"
            scrolling="no"
        ></iframe>
    </div>
    <?php
}

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
    register_setting('agenda_automatizada_settings', 'aa_service_schedule', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('agenda_automatizada_settings', 'aa_staff_schedule', ['sanitize_callback' => 'sanitize_text_field']);
});

// Note: Settings page UI has been moved to includes/admin/ui/modules/settings/
// This file now only contains backend logic (register_setting, admin_post handlers, etc.)

// Procesar retorno del OAuth callback y canjear secretos server-to-server
add_action('admin_post_aa_connect_google', function() {
    if (!current_user_can('manage_options')) return;

    $success = isset($_GET['success']) ? $_GET['success'] : '';

    if ($success !== 'true') {
        wp_redirect(admin_url('admin.php?page=agenda-automatizada-settings'));
        exit;
    }

    $flow_id        = isset($_GET['flow_id']) ? sanitize_text_field($_GET['flow_id']) : '';
    $provision_code = isset($_GET['provision_code']) ? sanitize_text_field($_GET['provision_code']) : '';

    if (empty($flow_id) || empty($provision_code)) {
        wp_redirect(admin_url('admin.php?page=agenda-automatizada-settings'));
        exit;
    }

    $result = SyncService::redeem_secrets($flow_id, $provision_code);

    if (!is_wp_error($result) && !empty($result['email']) && !empty($result['client_secret'])) {
        $webhook_token = isset($result['webhook_token']) ? $result['webhook_token'] : '';
        SyncService::handle_oauth_success($result['email'], $result['client_secret'], $webhook_token);
    } else {
        $err_msg = is_wp_error($result) ? $result->get_error_message() : 'Error desconocido al canjear credenciales';
        error_log('[WP Agenda] OAuth redeem failed: ' . $err_msg);
    }

    wp_redirect(admin_url('admin.php?page=agenda-automatizada-settings'));
    exit;
});

// Desconectar Google
add_action('admin_post_aa_disconnect_google', function() {
    if (!current_user_can('manage_options')) return;

    // Notificar al backend sobre la desconexión (no rompe el flujo si falla)
    SyncService::notify_backend_disconnect_google();

    // Eliminar opciones de Google
    delete_option('aa_google_email');
    
    // Opcional: marcar sincronización como inválida
    update_option('aa_estado_gsync', 'disconnected');
    
    wp_redirect(admin_url('admin-post.php?action=aa_iframe_content&module=settings'));
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

// Iframe auto-resize handler (parent window)
add_action('admin_footer', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'toplevel_page_agenda-automatizada-settings') {
        return;
    }
    ?>
    <script>
        (function () {
            'use strict';

            const iframe = document.getElementById('aa-settings-iframe');
            if (!iframe) return;

            let lastContentHeight = 0;
            let lastAppliedHeight = 0;

            function getAvailableHeight() {
                const rect = iframe.getBoundingClientRect();
                const top = rect.top;
                return Math.max(200, Math.floor(window.innerHeight - top - 16));
            }

            function applyHeight(contentHeight) {
                const available = getAvailableHeight();
                const target = Math.max(contentHeight, available);
                if (target !== lastAppliedHeight) {
                    iframe.style.height = target + 'px';
                    lastAppliedHeight = target;
                }
                return { available: available, applied: lastAppliedHeight };
            }

            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin) return;

                if (event.data && event.data.type === 'aa-iframe-resize') {
                    lastContentHeight = event.data.height;
                    applyHeight(lastContentHeight);
                }
            });

            window.addEventListener('resize', function () {
                if (lastContentHeight > 0) {
                    applyHeight(lastContentHeight);
                }
            });

            iframe.addEventListener('load', function () {
                lastContentHeight = 0;
                const availableHeight = getAvailableHeight();
                iframe.style.height = availableHeight + 'px';
                lastAppliedHeight = availableHeight;
            });
        })();
    </script>
    <?php
});
