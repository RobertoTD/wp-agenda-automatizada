<?php
/**
 * Expedientes Module — parent-entity skeleton (Cycle D).
 *
 * Neutral roots only. Cycle E fills loading / empty / grid via JS.
 * No business logic; data operations go through ExpedientesAjax.
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$aa_expedientes_module_url = admin_url('admin-post.php?action=aa_iframe_content&module=expedientes');
$aa_expedientes_ajax_url = admin_url('admin-ajax.php');
$aa_expedientes_nonce = wp_create_nonce(
    class_exists('ExpedientesAjax') ? ExpedientesAjax::NONCE_ACTION : 'aa_expedientes_nonce'
);
$aa_expedientes_list_action = class_exists('ExpedientesAjax')
    ? ExpedientesAjax::ACTION_LIST
    : 'aa_list_expedientes';
$aa_expedientes_create_action = class_exists('ExpedientesAjax')
    ? ExpedientesAjax::ACTION_CREATE
    : 'aa_create_expediente';
?>

<div
    id="aa-expedientes-root"
    class="max-w-5xl mx-auto py-2"
    data-aa-page-title="Expedientes"
>
    <div id="aa-expedientes-list-root">
        <div
            id="aa-expedientes-grid"
            class="aa-expedientes-grid"
            aria-live="polite"
        ></div>
    </div>
</div>

<script>
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = <?php echo wp_json_encode($aa_expedientes_ajax_url); ?>;
    }

    window.AA_EXPEDIENTES_DATA = {
        ajaxUrl: window.ajaxurl || <?php echo wp_json_encode($aa_expedientes_ajax_url); ?>,
        nonce: <?php echo wp_json_encode($aa_expedientes_nonce); ?>,
        moduleBaseUrl: <?php echo wp_json_encode($aa_expedientes_module_url); ?>,
        actions: {
            list: <?php echo wp_json_encode($aa_expedientes_list_action); ?>,
            create: <?php echo wp_json_encode($aa_expedientes_create_action); ?>
        }
    };
</script>
