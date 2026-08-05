<?php
/**
 * Blocking legal gate screen — replaces the admin shell until legal acceptances
 * are recorded or an informative blocking state is resolved.
 *
 * Expected in scope: $legal_gate_view (array from GetLegalGateStatusUseCase result).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 3) . '/domain/legal/class-aa-agenda-terms-consent.php';
require_once dirname(__DIR__, 3) . '/domain/legal/class-aa-agenda-privacy-consent.php';

if (!isset($legal_gate_view) || !is_array($legal_gate_view)) {
    wp_die('Estado legal no disponible', 'Error', ['response' => 500]);
}

$success = !empty($legal_gate_view['success']);
$data    = isset($legal_gate_view['data']) && is_array($legal_gate_view['data'])
    ? $legal_gate_view['data']
    : [];
$error   = isset($legal_gate_view['error']) && is_array($legal_gate_view['error'])
    ? $legal_gate_view['error']
    : [];

$status = $success
    ? (string) ($data['status'] ?? '')
    : 'error';
$can_accept_terms = !empty($data['can_accept_terms']);
$can_accept_dual  = !empty($data['can_accept_privacy_and_terms']);

$terms_doc  = isset($data['terms_document']) && is_array($data['terms_document'])
    ? $data['terms_document']
    : null;
$privacy_doc = isset($data['privacy_document']) && is_array($data['privacy_document'])
    ? $data['privacy_document']
    : null;

$terms_version   = is_array($terms_doc) ? (string) ($terms_doc['version'] ?? '') : '';
$terms_url       = is_array($terms_doc) && !empty($terms_doc['human_url'])
    ? (string) $terms_doc['human_url']
    : AA_Agenda_Terms_Consent::HUMAN_URL;
$privacy_version = is_array($privacy_doc) ? (string) ($privacy_doc['version'] ?? '') : '';
$privacy_url     = is_array($privacy_doc) ? (string) ($privacy_doc['human_url'] ?? '') : '';

$title = 'Acceso pendiente';
$lead  = 'No pudimos verificar el estado legal de esta instalación.';
$show_terms_accept = false;
$show_dual_accept  = false;

if ($status === 'needs_terms') {
    $title = 'Acepta los Términos y Condiciones';
    $lead  = 'Para continuar usando DEOIA en esta instalación debes aceptar los Términos y Condiciones vigentes, incluido el Anexo de Encargo de Tratamiento.';
    $show_terms_accept = $can_accept_terms;
} elseif ($status === 'needs_privacy_and_terms') {
    $title = 'Acepta la Privacidad y los Términos';
    $lead  = 'Para continuar usando DEOIA en esta instalación debes aceptar el Aviso de Privacidad y los Términos y Condiciones vigentes, incluido el Anexo de Encargo de Tratamiento.';
    $show_dual_accept = $can_accept_dual;
} elseif ($status === 'privacy_required') {
    $title = 'Falta la aceptación de privacidad';
    $lead  = 'No encontramos evidencia de aceptación de la Política de Privacidad vinculada a esta instalación. No es posible completar el acceso desde aquí. Contacta a soporte o reintenta más tarde.';
} elseif ($status === 'provisioning_request_missing') {
    $title = 'Instalación sin solicitud de alta';
    $lead  = 'No encontramos la solicitud de aprovisionamiento necesaria para registrar la aceptación. Contacta a soporte o reintenta más tarde.';
} elseif (!$success) {
    $title = 'No se pudo verificar el acceso';
    $lead  = (string) ($error['message'] ?? 'Hubo un problema al consultar el servidor. Inténtalo de nuevo.');
}

$plugin_url = defined('AA_PLUGIN_URL') ? AA_PLUGIN_URL : plugin_dir_url(dirname(__DIR__, 3) . '/wp-agenda-automatizada.php');
$plugin_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
$css_url    = esc_url($plugin_url . 'includes/admin/ui/assets/css/admin.css?ver=' . rawurlencode($plugin_ver));
$js_url     = esc_url($plugin_url . 'includes/admin/ui/legal-gate/legal-gate.js?ver=' . rawurlencode($plugin_ver));

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEOIA — <?php echo esc_html($title); ?></title>
    <link rel="stylesheet" href="<?php echo $css_url; ?>">
    <style>
        body.aa-legal-gate-body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgb(240, 240, 241);
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }
        .aa-legal-gate {
            width: min(36rem, calc(100% - 2rem));
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.75rem 1.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .aa-legal-gate h1 {
            margin: 0 0 0.75rem;
            font-size: 1.35rem;
            font-weight: 600;
            color: #111827;
        }
        .aa-legal-gate p {
            margin: 0 0 1rem;
            color: #374151;
            line-height: 1.5;
            font-size: 0.95rem;
        }
        .aa-legal-gate a {
            color: #4f46e5;
            text-decoration: underline;
        }
        .aa-legal-gate__consent {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            margin: 1.25rem 0;
        }
        .aa-legal-gate__consent input {
            margin-top: 0.2rem;
        }
        .aa-legal-gate__consent label {
            font-size: 0.9rem;
            color: #1f2937;
            line-height: 1.45;
        }
        .aa-legal-gate__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }
        .aa-legal-gate__btn {
            appearance: none;
            border: 0;
            border-radius: 0.5rem;
            padding: 0.65rem 1.1rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }
        .aa-legal-gate__btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .aa-legal-gate__btn--primary {
            background: #4f46e5;
            color: #fff;
        }
        .aa-legal-gate__btn--secondary {
            background: #f3f4f6;
            color: #111827;
        }
        .aa-legal-gate__error {
            display: none;
            margin-top: 1rem;
            padding: 0.75rem 0.9rem;
            border-radius: 0.5rem;
            background: #fef2f2;
            color: #991b1b;
            font-size: 0.9rem;
        }
        .aa-legal-gate__error.is-visible {
            display: block;
        }
        .aa-legal-gate__note {
            margin-top: 1rem;
            font-size: 0.85rem;
            color: #6b7280;
        }
    </style>
</head>
<body class="aa-legal-gate-body">
    <main class="aa-legal-gate" id="aa-legal-gate-root" role="main" aria-live="polite"
        data-status="<?php echo esc_attr($status); ?>"
        data-can-accept="<?php echo ($show_terms_accept || $show_dual_accept) ? '1' : '0'; ?>"
        data-terms-version="<?php echo esc_attr($terms_version); ?>"
        data-privacy-version="<?php echo esc_attr($privacy_version); ?>"
    >
        <h1><?php echo esc_html($title); ?></h1>
        <p><?php echo esc_html($lead); ?></p>

        <?php if ($status === 'needs_terms') : ?>
            <p>
                <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener noreferrer">
                    Leer los Términos y Condiciones
                </a>
                <?php if ($terms_version !== '') : ?>
                    <span> (versión <?php echo esc_html($terms_version); ?>)</span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($status === 'needs_privacy_and_terms') : ?>
            <?php if ($privacy_url !== '') : ?>
                <p>
                    <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener noreferrer" id="aa-legal-gate-privacy-link">
                        Consulta el Aviso de Privacidad Integral
                    </a>
                    <?php if ($privacy_version !== '') : ?>
                        <span> (versión <?php echo esc_html($privacy_version); ?>)</span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <p>
                <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener noreferrer" id="aa-legal-gate-terms-link">
                    Leer los Términos y Condiciones
                </a>
                <?php if ($terms_version !== '') : ?>
                    <span> (versión <?php echo esc_html($terms_version); ?>)</span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($show_dual_accept) : ?>
            <div class="aa-legal-gate__consent">
                <input type="checkbox" id="aa-legal-gate-privacy-consent" name="privacy_consent" value="1">
                <label for="aa-legal-gate-privacy-consent"><?php echo esc_html(AA_Agenda_Privacy_Consent::TEXT); ?></label>
            </div>
            <div class="aa-legal-gate__consent">
                <input type="checkbox" id="aa-legal-gate-consent" name="terms_consent" value="1">
                <label for="aa-legal-gate-consent"><?php echo esc_html(AA_Agenda_Terms_Consent::TEXT); ?></label>
            </div>
            <div class="aa-legal-gate__actions">
                <button type="button" class="aa-legal-gate__btn aa-legal-gate__btn--primary" id="aa-legal-gate-accept" disabled>
                    Aceptar y continuar
                </button>
            </div>
        <?php elseif ($show_terms_accept) : ?>
            <div class="aa-legal-gate__consent">
                <input type="checkbox" id="aa-legal-gate-consent" name="terms_consent" value="1">
                <label for="aa-legal-gate-consent"><?php echo esc_html(AA_Agenda_Terms_Consent::TEXT); ?></label>
            </div>
            <div class="aa-legal-gate__actions">
                <button type="button" class="aa-legal-gate__btn aa-legal-gate__btn--primary" id="aa-legal-gate-accept" disabled>
                    Aceptar y continuar
                </button>
            </div>
        <?php elseif ($status === 'needs_terms' || $status === 'needs_privacy_and_terms') : ?>
            <p class="aa-legal-gate__note">
                Un administrador de la instalación debe aceptar los documentos legales para habilitar el acceso.
            </p>
            <div class="aa-legal-gate__actions">
                <button type="button" class="aa-legal-gate__btn aa-legal-gate__btn--secondary" id="aa-legal-gate-retry">
                    Reintentar
                </button>
            </div>
        <?php else : ?>
            <div class="aa-legal-gate__actions">
                <button type="button" class="aa-legal-gate__btn aa-legal-gate__btn--secondary" id="aa-legal-gate-retry">
                    Reintentar
                </button>
            </div>
        <?php endif; ?>

        <div class="aa-legal-gate__error" id="aa-legal-gate-error" role="alert"></div>
    </main>

    <script>
    window.AA_LEGAL_GATE_DATA = {
        ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        statusAction: 'aa_get_legal_gate_status',
        acceptAction: 'aa_accept_agenda_terms',
        acceptDualAction: 'aa_accept_agenda_privacy_and_terms',
        nonce: <?php echo wp_json_encode(wp_create_nonce('aa_legal_gate_nonce')); ?>,
        termsVersion: <?php echo wp_json_encode($terms_version); ?>,
        privacyVersion: <?php echo wp_json_encode($privacy_version); ?>,
        canAccept: <?php echo $show_terms_accept ? 'true' : 'false'; ?>,
        canAcceptDual: <?php echo $show_dual_accept ? 'true' : 'false'; ?>,
        initialStatus: <?php echo wp_json_encode($status); ?>
    };
    </script>
    <script src="<?php echo $js_url; ?>"></script>
</body>
</html>
<?php
die();
