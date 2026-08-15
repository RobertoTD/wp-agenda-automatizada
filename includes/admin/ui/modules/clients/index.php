<?php
/**
 * Clients Module - Clients Management UI
 *
 * Views:
 * - list (default): search / create / edit clients
 * - expediente: individual empty expediente shell for a client
 *
 * No business logic (data operations handled via AJAX).
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$aa_clients_view_raw = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : '';
$aa_clients_view = ($aa_clients_view_raw === 'expediente') ? 'expediente' : 'list';

$aa_clients_client_id = isset($_GET['client_id']) ? absint($_GET['client_id']) : 0;
if ($aa_clients_view === 'expediente' && $aa_clients_client_id < 1) {
    $aa_clients_view = 'list';
    $aa_clients_client_id = 0;
}

$aa_clients_list_url = admin_url('admin-post.php?action=aa_iframe_content&module=clients');
$aa_clients_expediente_url = '';
if ($aa_clients_view === 'expediente' && $aa_clients_client_id > 0) {
    $aa_clients_expediente_url = add_query_arg(
        [
            'view' => 'expediente',
            'client_id' => $aa_clients_client_id,
        ],
        $aa_clients_list_url
    );
}

$aa_clients_is_expediente = ($aa_clients_view === 'expediente');

// UX flag only — never authority. Always starts false (fail-closed). The async
// shell-access projection flips it to true only on a live `full` confirmation;
// the server URL/AJAX gates enforce the real rule.
$aa_expediente_access_allowed = false;
?>

<div class="max-w-5xl mx-auto py-2">

    <!-- Lista de clientes (tools en shared/header junto a #aa-page-title) -->
    <div id="aa-clients-list-root" class="<?php echo $aa_clients_is_expediente ? 'hidden' : ''; ?>">
        <div id="aa-clients-grid" class="aa-clients-grid"></div>
    </div>

    <!-- Vista individual de expediente (contenido vía JS) -->
    <div
        id="aa-expediente-root"
        class="<?php echo $aa_clients_is_expediente ? '' : 'hidden'; ?>"
        aria-live="polite"
        <?php if ($aa_clients_is_expediente) : ?>data-aa-page-title="Expediente"<?php endif; ?>
    ></div>

</div>

<?php if (!$aa_clients_is_expediente) : ?>
<div id="aa-clients-fab-stack" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <button
        type="button"
        id="aa-clients-new-client"
        class="inline-flex items-center gap-2 px-4 py-3 text-base font-bold text-white bg-violet-600 hover:bg-violet-700 active:bg-violet-800 rounded-full shadow-lg shadow-violet-600/30 hover:shadow-xl hover:shadow-violet-600/35 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-violet-500/40"
        aria-label="Nuevo cliente"
        data-clients-tool="create-client"
    >
        <span>+ Nuevo cliente</span>
    </button>
</div>
<?php else : ?>
<div id="aa-expediente-fab-stack" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <button
        type="button"
        id="aa-expediente-new-registro"
        class="inline-flex items-center gap-2 px-4 py-3 text-base font-bold text-white bg-violet-600 hover:bg-violet-700 active:bg-violet-800 rounded-full shadow-lg shadow-violet-600/30 hover:shadow-xl hover:shadow-violet-600/35 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-violet-500/40"
        aria-label="Registro de expediente"
        data-expediente-tool="create-registro"
    >
        <span>+ Registro de expediente</span>
    </button>
</div>
<?php endif; ?>

<script>
    // Garantizar ajaxurl global para el iframe
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    }

    // Nonces para operaciones de clientes (merge para no sobrescribir si layout ya definió el objeto)
    window.AA_CLIENTS_NONCES = Object.assign(window.AA_CLIENTS_NONCES || {}, {
        crear_cliente: <?php echo wp_json_encode(wp_create_nonce('aa_crear_cliente')); ?>,
        editar_cliente: <?php echo wp_json_encode(wp_create_nonce('aa_editar_cliente')); ?>,
        search_clientes: <?php echo wp_json_encode(wp_create_nonce('aa_search_clientes')); ?>,
        get_cliente: <?php echo wp_json_encode(wp_create_nonce(class_exists('ClientsAjax') ? ClientsAjax::NONCE_ACTION : 'aa_get_cliente')); ?>
    });

    window.AA_CLIENTS_DATA = {
        view: <?php echo wp_json_encode($aa_clients_view); ?>,
        clientId: <?php echo (int) $aa_clients_client_id; ?>,
        listUrl: <?php echo wp_json_encode($aa_clients_list_url); ?>,
        expedienteUrl: <?php echo wp_json_encode($aa_clients_expediente_url); ?>,
        moduleBaseUrl: <?php echo wp_json_encode($aa_clients_list_url); ?>,
        expedienteAccessAllowed: <?php echo $aa_expediente_access_allowed ? 'true' : 'false'; ?>,
        ajaxUrl: window.ajaxurl || <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        actions: {
            getCliente: <?php echo wp_json_encode(class_exists('ClientsAjax') ? ClientsAjax::ACTION_GET_CLIENTE : 'aa_get_cliente'); ?>
            <?php if ($aa_clients_is_expediente) : ?>,
            listRegistros: <?php echo wp_json_encode(class_exists('ExpedienteRegistrosAjax') ? ExpedienteRegistrosAjax::ACTION_LIST : 'aa_list_expediente_registros'); ?>,
            createRegistro: <?php echo wp_json_encode(class_exists('ExpedienteRegistrosAjax') ? ExpedienteRegistrosAjax::ACTION_CREATE : 'aa_create_expediente_registro'); ?>,
            updateRegistro: <?php echo wp_json_encode(class_exists('ExpedienteRegistrosAjax') ? ExpedienteRegistrosAjax::ACTION_UPDATE : 'aa_update_expediente_registro'); ?>,
            deleteRegistro: <?php echo wp_json_encode(class_exists('ExpedienteRegistrosAjax') ? ExpedienteRegistrosAjax::ACTION_DELETE : 'aa_delete_expediente_registro'); ?>,
            attachRegistro: <?php echo wp_json_encode(class_exists('ExpedienteAdjuntosAjax') ? ExpedienteAdjuntosAjax::ACTION_ATTACH : 'aa_attach_expediente_registro'); ?>,
            signAdjuntoRead: <?php echo wp_json_encode(class_exists('ExpedienteAdjuntosAjax') ? ExpedienteAdjuntosAjax::ACTION_SIGN_READ : 'aa_sign_expediente_adjunto_read'); ?>,
            deleteAdjunto: <?php echo wp_json_encode(class_exists('ExpedienteAdjuntosAjax') ? ExpedienteAdjuntosAjax::ACTION_DELETE : 'aa_delete_expediente_adjunto'); ?>
            <?php endif; ?>
        }
    };
    <?php if ($aa_clients_is_expediente) : ?>
    window.AA_CLIENTS_NONCES = Object.assign(window.AA_CLIENTS_NONCES || {}, {
        expediente_registros: <?php echo wp_json_encode(wp_create_nonce(class_exists('ExpedienteRegistrosAjax') ? ExpedienteRegistrosAjax::NONCE_ACTION : 'aa_expediente_registros_nonce')); ?>
    });
    <?php endif; ?>
</script>

<?php
$clients_module_js = plugin_dir_url(__FILE__) . 'clients-module.js';
$clients_module_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
?>
<script src="<?php echo esc_url($clients_module_js . '?ver=' . rawurlencode($clients_module_ver)); ?>" defer></script>
<?php
$client_card_longpress_js = plugin_dir_url(__FILE__) . 'client-card-longpress-module.js';
?>
<script src="<?php echo esc_url($client_card_longpress_js . '?ver=' . rawurlencode($clients_module_ver)); ?>" defer></script>
<?php if ($aa_clients_is_expediente) :
    $executable_options_menu_placement_js = AA_PLUGIN_URL . 'includes/admin/ui/modules/learning/executable-options-menu-placement.js';
    $expediente_registros_js = plugin_dir_url(__FILE__) . 'expediente-registros.js';
    ?>
<script src="<?php echo esc_url($executable_options_menu_placement_js . '?ver=' . rawurlencode($clients_module_ver)); ?>" defer></script>
<script src="<?php echo esc_url($expediente_registros_js . '?ver=' . rawurlencode($clients_module_ver)); ?>" defer></script>
<?php endif; ?>
