<?php
/**
 * View: Portal de citas virtuales
 *
 * Renderiza dentro del tema activo (header/footer).
 * Resuelve join_token → reserva → attendance_type → enlace final.
 * Muestra countdown + botón "Entrar a la cita" en la ventana habilitada.
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

get_header();

global $wpdb;

// ──────────────────────────────────────────
// 1. Leer y validar token
// ──────────────────────────────────────────
$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

if ($token === '') {
    echo '<div class="aa-join-portal"><p>Enlace inválido (falta token).</p></div>';
    get_footer();
    return;
}

// ──────────────────────────────────────────
// 2. Buscar reserva por join_token
// ──────────────────────────────────────────
$reservas_table = $wpdb->prefix . 'aa_reservas';
$reserva = $wpdb->get_row($wpdb->prepare(
    "SELECT id, servicio, fecha, duracion, estado, virtual_link, join_token
     FROM $reservas_table WHERE join_token = %s LIMIT 1",
    $token
), ARRAY_A);

if (!$reserva) {
    echo '<div class="aa-join-portal"><p>Token inválido o expirado.</p></div>';
    get_footer();
    return;
}

// ──────────────────────────────────────────
// 3. Resolver attendance_type / virtual_channel
// ──────────────────────────────────────────
$attendance_type = null;
$virtual_channel = null;
$servicio_raw = $reserva['servicio'] ?? '';

if (is_numeric($servicio_raw)) {
    $services_table = $wpdb->prefix . 'aa_services';
    $service_row = $wpdb->get_row($wpdb->prepare(
        "SELECT attendance_type, virtual_channel FROM $services_table WHERE id = %d LIMIT 1",
        intval($servicio_raw)
    ), ARRAY_A);
    if ($service_row) {
        $attendance_type = isset($service_row['attendance_type']) && $service_row['attendance_type'] !== '' ? $service_row['attendance_type'] : null;
        $virtual_channel = isset($service_row['virtual_channel']) && $service_row['virtual_channel'] !== '' ? $service_row['virtual_channel'] : null;
    }
}

if ($attendance_type !== 'virtual') {
    echo '<div class="aa-join-portal"><p>Esta cita no es virtual.</p></div>';
    get_footer();
    return;
}

// ──────────────────────────────────────────
// 4. Resolver enlace final según canal
// ──────────────────────────────────────────
$virtual_link = isset($reserva['virtual_link']) && $reserva['virtual_link'] !== '' ? $reserva['virtual_link'] : null;
$whatsapp = get_option('aa_whatsapp_number', '');
$join_url = null;
$link_pending = false;

if ($virtual_channel === 'whatsapp') {
    $phone = preg_replace('/\D/', '', $whatsapp);
    $msg = sprintf(
        'Hola, quiero unirme a mi cita virtual. Reserva #%d — %s',
        intval($reserva['id']),
        esc_attr($reserva['fecha'])
    );
    $join_url = 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
} elseif ($virtual_channel === 'google_meet' || $virtual_channel === 'custom_link') {
    if ($virtual_link) {
        $join_url = $virtual_link;
    } else {
        $link_pending = true;
    }
}

// ──────────────────────────────────────────
// 5. Calcular timestamps para countdown
// ──────────────────────────────────────────
$tz_string = get_option('aa_timezone', 'America/Mexico_City');
try {
    $tz = new DateTimeZone($tz_string);
} catch (Exception $e) {
    $tz = new DateTimeZone('America/Mexico_City');
}

$start_dt = new DateTime($reserva['fecha'], $tz);
$duracion = intval($reserva['duracion'] ?: 60);
$end_dt = clone $start_dt;
$end_dt->modify("+{$duracion} minutes");

$open_dt = clone $start_dt;
$open_dt->modify('-10 minutes');
$close_dt = clone $end_dt;
$close_dt->modify('+15 minutes');

$start_ms = $start_dt->getTimestamp() * 1000;
$open_ms  = $open_dt->getTimestamp() * 1000;
$close_ms = $close_dt->getTimestamp() * 1000;

$fecha_legible = $start_dt->format('d/m/Y H:i');

// ──────────────────────────────────────────
// 6. Render HTML
// ──────────────────────────────────────────
?>
<div class="aa-join-portal" style="max-width:480px;margin:40px auto;padding:24px;text-align:center;font-family:sans-serif;">

    <h2 style="margin-bottom:4px;">Cita Virtual</h2>
    <p style="color:#555;margin-top:0;">
        <?php echo esc_html($fecha_legible); ?>
        &nbsp;·&nbsp;<?php echo intval($duracion); ?> min
    </p>

    <?php if ($reserva['estado'] === 'cancelled'): ?>
        <p style="color:#b91c1c;">Esta cita fue cancelada.</p>
    <?php elseif ($link_pending): ?>
        <p style="color:#92400e;">Aún no está disponible el enlace de la cita. Vuelve a intentar más tarde.</p>
    <?php else: ?>
        <div id="aa-join-status" style="margin:16px 0;font-size:14px;color:#555;"></div>
        <div id="aa-join-countdown" style="font-size:28px;font-weight:700;margin:12px 0;font-variant-numeric:tabular-nums;"></div>
        <a  id="aa-join-btn"
            href="<?php echo esc_url($join_url ?? '#'); ?>"
            target="_blank"
            rel="noopener noreferrer"
            style="display:none;inline-size:fit-content;margin:0 auto;padding:12px 28px;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:16px;">
            Entrar a la cita
        </a>
    <?php endif; ?>
</div>

<?php if (!$link_pending && $reserva['estado'] !== 'cancelled' && $join_url): ?>
<script>
(function() {
    var openMs  = <?php echo $open_ms; ?>;
    var startMs = <?php echo $start_ms; ?>;
    var closeMs = <?php echo $close_ms; ?>;
    var statusEl    = document.getElementById('aa-join-status');
    var countdownEl = document.getElementById('aa-join-countdown');
    var btnEl       = document.getElementById('aa-join-btn');

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function formatDiff(ms) {
        var s = Math.floor(ms / 1000);
        var h = Math.floor(s / 3600); s %= 3600;
        var m = Math.floor(s / 60);   s %= 60;
        if (h > 0) return pad(h) + ':' + pad(m) + ':' + pad(s);
        return pad(m) + ':' + pad(s);
    }

    function tick() {
        var now = Date.now();

        if (now < openMs) {
            statusEl.textContent = 'La sala se abre en';
            countdownEl.textContent = formatDiff(openMs - now);
            btnEl.style.display = 'none';
        } else if (now <= closeMs) {
            statusEl.textContent = 'La cita está en curso';
            countdownEl.textContent = '';
            btnEl.style.display = '';
        } else {
            statusEl.textContent = 'La ventana para unirse ha terminado.';
            countdownEl.textContent = '';
            btnEl.style.display = 'none';
        }
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
<?php endif; ?>

<?php get_footer(); ?>
