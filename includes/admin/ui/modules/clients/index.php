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
?>

<div class="max-w-5xl mx-auto py-2">

    <!-- Lista de clientes -->
    <div id="aa-clients-list-root" class="<?php echo $aa_clients_is_expediente ? 'hidden' : ''; ?>">
        <div class="bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden">
            <div class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white rounded-t-xl">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Clientes</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Crea, busca o edita clientes</p>
                    </div>
                </div>
            </div>

            <div class="p-3 transition-all duration-200">
                <div id="aa-clients-grid" class="aa-clients-grid"></div>
            </div>
        </div>
    </div>

    <!-- Vista individual de expediente (contenido vía JS) -->
    <div
        id="aa-expediente-root"
        class="<?php echo $aa_clients_is_expediente ? '' : 'hidden'; ?>"
        aria-live="polite"
    ></div>

</div>

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
        ajaxUrl: window.ajaxurl || <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        actions: {
            getCliente: <?php echo wp_json_encode(class_exists('ClientsAjax') ? ClientsAjax::ACTION_GET_CLIENTE : 'aa_get_cliente'); ?>
            <?php if ($aa_clients_is_expediente) : ?>,
            listRegistros: <?php echo wp_json_encode(class_exists('ExpedienteRegistrosAjax') ? ExpedienteRegistrosAjax::ACTION_LIST : 'aa_list_expediente_registros'); ?>,
            createRegistro: <?php echo wp_json_encode(class_exists('ExpedienteRegistrosAjax') ? ExpedienteRegistrosAjax::ACTION_CREATE : 'aa_create_expediente_registro'); ?>,
            updateRegistro: <?php echo wp_json_encode(class_exists('ExpedienteRegistrosAjax') ? ExpedienteRegistrosAjax::ACTION_UPDATE : 'aa_update_expediente_registro'); ?>,
            attachRegistro: <?php echo wp_json_encode(class_exists('ExpedienteAdjuntosAjax') ? ExpedienteAdjuntosAjax::ACTION_ATTACH : 'aa_attach_expediente_registro'); ?>,
            signAdjuntoRead: <?php echo wp_json_encode(class_exists('ExpedienteAdjuntosAjax') ? ExpedienteAdjuntosAjax::ACTION_SIGN_READ : 'aa_sign_expediente_adjunto_read'); ?>
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
<?php if ($aa_clients_is_expediente) :
    $expediente_registros_js = plugin_dir_url(__FILE__) . 'expediente-registros.js';
    ?>
<script src="<?php echo esc_url($expediente_registros_js . '?ver=' . rawurlencode($clients_module_ver)); ?>" defer></script>
<?php endif; ?>
