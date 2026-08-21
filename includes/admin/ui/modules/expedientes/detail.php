<?php
/**
 * Expedientes — vista de detalle de un expediente padre real.
 *
 * Espera $aa_expediente_detail ya resuelto por el router (GetExpedienteUseCase).
 * Registros SSR read-only + FAB "Nuevo registro" (create vía AJAX acotado).
 * Sin buscador, menú vacío ni controlador legacy de registros.
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$aa_detail = (isset($aa_expediente_detail) && is_array($aa_expediente_detail))
    ? $aa_expediente_detail
    : [];

$aa_detail_id = (int) ($aa_detail['id'] ?? 0);
$aa_detail_title = (string) ($aa_detail['title'] ?? '');
$aa_detail_description_raw = $aa_detail['description'] ?? null;
$aa_detail_has_description = is_string($aa_detail_description_raw)
    && trim($aa_detail_description_raw) !== '';
$aa_detail_description = $aa_detail_has_description
    ? (string) $aa_detail_description_raw
    : 'Sin descripción';
$aa_detail_category = $aa_detail['category'] ?? [];
$aa_detail_category_name = is_array($aa_detail_category)
    ? (string) ($aa_detail_category['name'] ?? '')
    : '';
if ($aa_detail_category_name === '') {
    $aa_detail_category_name = '—';
}

$aa_detail_created_raw = (string) ($aa_detail['created_at'] ?? '');
$aa_detail_created_display = '—';
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $aa_detail_created_raw, $aa_detail_created_match)) {
    $aa_detail_created_display = $aa_detail_created_match[3]
        . '/' . $aa_detail_created_match[2]
        . '/' . $aa_detail_created_match[1];
} elseif ($aa_detail_created_raw !== '') {
    $aa_detail_created_display = $aa_detail_created_raw;
}

$aa_records_view = (isset($aa_expediente_records_view) && is_array($aa_expediente_records_view))
    ? $aa_expediente_records_view
    : [
        'records' => [],
        'page' => 1,
        'per_page' => 15,
        'total' => 0,
        'total_pages' => 0,
        'has_previous' => false,
        'has_next' => false,
        'prev_url' => '',
        'next_url' => '',
    ];
$aa_records_items = is_array($aa_records_view['records'] ?? null) ? $aa_records_view['records'] : [];
$aa_records_total = (int) ($aa_records_view['total'] ?? 0);
$aa_records_page = (int) ($aa_records_view['page'] ?? 1);
$aa_records_total_pages = (int) ($aa_records_view['total_pages'] ?? 0);
$aa_records_prev_url = (string) ($aa_records_view['prev_url'] ?? '');
$aa_records_next_url = (string) ($aa_records_view['next_url'] ?? '');
$aa_records_has_previous = !empty($aa_records_view['has_previous']) && $aa_records_prev_url !== '';
$aa_records_has_next = !empty($aa_records_view['has_next']) && $aa_records_next_url !== '';

$aa_detail_can_create = $aa_detail_id > 0;
$aa_detail_ajax_url = admin_url('admin-ajax.php');
$aa_detail_create_action = (class_exists('ExpedienteRegistrosByExpedienteAjax')
    && defined('ExpedienteRegistrosByExpedienteAjax::ACTION_CREATE'))
    ? ExpedienteRegistrosByExpedienteAjax::ACTION_CREATE
    : 'aa_create_expediente_registro_for_expediente';
$aa_detail_list_action = (class_exists('ExpedienteRegistrosByExpedienteAjax')
    && defined('ExpedienteRegistrosByExpedienteAjax::ACTION_LIST'))
    ? ExpedienteRegistrosByExpedienteAjax::ACTION_LIST
    : 'aa_list_expediente_registros_for_expediente';
$aa_detail_attach_action = (class_exists('ExpedienteAdjuntosByExpedienteAjax')
    && defined('ExpedienteAdjuntosByExpedienteAjax::ACTION_ATTACH'))
    ? ExpedienteAdjuntosByExpedienteAjax::ACTION_ATTACH
    : 'aa_attach_expediente_adjunto_for_expediente';
$aa_detail_sign_read_action = (class_exists('ExpedienteAdjuntosByExpedienteAjax')
    && defined('ExpedienteAdjuntosByExpedienteAjax::ACTION_SIGN_READ'))
    ? ExpedienteAdjuntosByExpedienteAjax::ACTION_SIGN_READ
    : 'aa_sign_expediente_adjunto_read_for_expediente';
$aa_detail_delete_adjunto_action = (class_exists('ExpedienteAdjuntosByExpedienteAjax')
    && defined('ExpedienteAdjuntosByExpedienteAjax::ACTION_DELETE'))
    ? ExpedienteAdjuntosByExpedienteAjax::ACTION_DELETE
    : 'aa_delete_expediente_adjunto_for_expediente';
$aa_detail_update_action = (class_exists('ExpedienteRegistrosByExpedienteAjax')
    && defined('ExpedienteRegistrosByExpedienteAjax::ACTION_UPDATE'))
    ? ExpedienteRegistrosByExpedienteAjax::ACTION_UPDATE
    : 'aa_update_expediente_registro_for_expediente';
$aa_detail_create_nonce_action = (class_exists('ExpedienteRegistrosByExpedienteAjax')
    && defined('ExpedienteRegistrosByExpedienteAjax::NONCE_ACTION'))
    ? ExpedienteRegistrosByExpedienteAjax::NONCE_ACTION
    : 'aa_expediente_registros_by_expediente_nonce';
$aa_detail_create_nonce = $aa_detail_can_create
    ? wp_create_nonce($aa_detail_create_nonce_action)
    : '';
$aa_detail_success_url = $aa_detail_can_create
    ? add_query_arg(
        [
            'action' => 'aa_iframe_content',
            'module' => 'expedientes',
            'view' => 'detail',
            'expediente_id' => (string) $aa_detail_id,
        ],
        admin_url('admin-post.php')
    )
    : '';
$aa_detail_scope_key = $aa_detail_can_create
    ? ('expediente:' . (string) $aa_detail_id)
    : '';
?>

<div
    id="aa-expediente-detail-root"
    class="max-w-5xl mx-auto py-2"
    data-aa-page-title="Expediente"
    <?php if ($aa_detail_id > 0) : ?>data-expediente-id="<?php echo esc_attr((string) $aa_detail_id); ?>"<?php endif; ?>
>
    <div class="aa-expediente-detail-panel aa-expediente-panel bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden">
        <div id="aa-expediente-detail-header" class="px-4 py-5 bg-white rounded-t-xl">
            <div class="flex items-center min-w-0">
                <span class="flex items-center justify-center w-8 h-8 text-gray-600 shrink-0" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                </span>
                <h3 class="min-w-0 truncate text-lg font-semibold text-gray-600">
                    <?php echo esc_html($aa_detail_title !== '' ? $aa_detail_title : 'Sin título'); ?>
                </h3>
            </div>
        </div>
        <div class="p-4 aa-expediente-detail-body">
            <p class="aa-expediente-detail-description text-sm text-gray-600 mb-3">
                <?php echo esc_html($aa_detail_description); ?>
            </p>
            <div class="aa-expediente-detail-meta space-y-1 text-sm font-medium text-gray-600">
                <div>
                    <span class="font-semibold">Categoría:</span>
                    <span class="text-gray-500"><?php echo esc_html($aa_detail_category_name); ?></span>
                </div>
                <div>
                    <span class="font-semibold">Creado:</span>
                    <span class="text-gray-500"><?php echo esc_html($aa_detail_created_display); ?></span>
                </div>
            </div>
            <div
                id="aa-expediente-detail-registros"
                class="aa-expediente-detail-registros mt-4"
                <?php if ($aa_detail_id > 0) : ?>data-expediente-id="<?php echo esc_attr((string) $aa_detail_id); ?>"<?php endif; ?>
            >
                <h4 class="aa-expediente-detail-registros-title text-base font-semibold text-gray-700 mb-2">Registros</h4>
                <div id="aa-expediente-detail-registros-ssr">
                    <?php if (count($aa_records_items) === 0) : ?>
                        <p class="text-sm text-gray-500 aa-expediente-registros-empty">Aún no hay registros en este expediente</p>
                    <?php else : ?>
                        <div class="aa-expediente-registros-list">
                            <?php foreach ($aa_records_items as $aa_record) : ?>
                                <?php include dirname(__DIR__, 2) . '/shared/expediente-record-readonly.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div
                    id="aa-expediente-detail-registros-live"
                    class="hidden"
                    hidden
                    aria-hidden="true"
                    aria-live="polite"
                ></div>

                <?php if ($aa_records_total_pages > 1) : ?>
                    <nav class="aa-expediente-detail-pagination mt-3" aria-label="Paginación de registros del expediente">
                        <?php if ($aa_records_has_previous) : ?>
                            <a href="<?php echo esc_url($aa_records_prev_url); ?>" class="aa-expediente-detail-pagination-link aa-expediente-detail-pagination-prev">← Anterior</a>
                        <?php endif; ?>
                        <span class="aa-expediente-detail-pagination-current text-sm text-gray-500">
                            Página <?php echo esc_html((string) $aa_records_page); ?>
                            de <?php echo esc_html((string) $aa_records_total_pages); ?>
                            (<?php echo esc_html((string) $aa_records_total); ?>)
                        </span>
                        <?php if ($aa_records_has_next) : ?>
                            <a href="<?php echo esc_url($aa_records_next_url); ?>" class="aa-expediente-detail-pagination-link aa-expediente-detail-pagination-next">Siguiente →</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($aa_detail_can_create) : ?>
<div id="aa-expediente-detail-fab-stack" class="aa-expediente-detail-fab-stack fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <button
        type="button"
        id="aa-expediente-detail-new-registro"
        class="aa-expediente-detail-fab inline-flex items-center gap-2 px-4 py-3 text-base font-bold text-white bg-violet-600 hover:bg-violet-700 active:bg-violet-800 rounded-full shadow-lg shadow-violet-600/30 hover:shadow-xl hover:shadow-violet-600/35 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-violet-500/40"
        aria-label="Nuevo registro"
        data-expediente-detail-tool="create-registro"
    >
        <span>Nuevo registro</span>
    </button>
</div>

<script>
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = <?php echo wp_json_encode($aa_detail_ajax_url); ?>;
    }

    window.AA_EXPEDIENTE_DETAIL_DATA = {
        ajaxUrl: window.ajaxurl || <?php echo wp_json_encode($aa_detail_ajax_url); ?>,
        nonce: <?php echo wp_json_encode($aa_detail_create_nonce); ?>,
        action: <?php echo wp_json_encode($aa_detail_create_action); ?>,
        expedienteId: <?php echo wp_json_encode((string) $aa_detail_id); ?>,
        successUrl: <?php echo wp_json_encode($aa_detail_success_url); ?>,
        scopeKey: <?php echo wp_json_encode($aa_detail_scope_key); ?>,
        recordsPage: <?php echo wp_json_encode((int) $aa_records_page); ?>,
        actions: {
            listRegistros: <?php echo wp_json_encode($aa_detail_list_action); ?>,
            createRegistro: <?php echo wp_json_encode($aa_detail_create_action); ?>,
            updateRegistro: <?php echo wp_json_encode($aa_detail_update_action); ?>,
            attachRegistro: <?php echo wp_json_encode($aa_detail_attach_action); ?>,
            signAdjuntoRead: <?php echo wp_json_encode($aa_detail_sign_read_action); ?>,
            deleteAdjunto: <?php echo wp_json_encode($aa_detail_delete_adjunto_action); ?>
        },
        capabilities: {
            createRegistro: true,
            updateRegistro: true,
            deleteRegistro: false,
            attach: true,
            signRead: true,
            deleteAdjunto: true
        }
    };
</script>
<?php
$aa_detail_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
$aa_detail_registros_js = dirname(plugin_dir_url(__FILE__)) . '/clients/expediente-registros.js';
$aa_detail_adapter_js = plugin_dir_url(__FILE__) . 'expediente-registros-canonical-adapter.js';
$aa_detail_mount_js = plugin_dir_url(__FILE__) . 'expediente-registros-canonical-mount.js';
$aa_detail_create_js = plugin_dir_url(__FILE__) . 'expediente-registro-create-modal.js';
?>
<script src="<?php echo esc_url($aa_detail_registros_js . '?ver=' . rawurlencode($aa_detail_ver)); ?>" defer></script>
<script src="<?php echo esc_url($aa_detail_adapter_js . '?ver=' . rawurlencode($aa_detail_ver)); ?>" defer></script>
<script src="<?php echo esc_url($aa_detail_mount_js . '?ver=' . rawurlencode($aa_detail_ver)); ?>" defer></script>
<script src="<?php echo esc_url($aa_detail_create_js . '?ver=' . rawurlencode($aa_detail_ver)); ?>" defer></script>
<?php endif; ?>
