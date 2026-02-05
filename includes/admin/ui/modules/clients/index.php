<?php
/**
 * Clients Module - Clients Management UI
 * 
 * This module handles:
 * - Display of clients list
 * - UI for adding/editing clients
 * - No business logic (data operations handled via AJAX)
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>

<div class="max-w-5xl mx-auto py-2">
    
    <!-- ═══════════════════════════════════════════════════════════════
         SECCIÓN: Clientes
    ═══════════════════════════════════════════════════════════════ -->
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

<script>
    // Garantizar ajaxurl global para el iframe
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
    
    // Nonces para operaciones de clientes
    window.AA_CLIENTS_NONCES = {
        crear_cliente: '<?php echo wp_create_nonce('aa_crear_cliente'); ?>',
        editar_cliente: '<?php echo wp_create_nonce('aa_editar_cliente'); ?>'
    };
</script>

<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'clients-module.js'); ?>" defer></script>
