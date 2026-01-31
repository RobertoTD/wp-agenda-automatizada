<?php
/**
 * Shared Header - Minimal app shell with clear hierarchy
 * 
 * Architecture:
 * - Single compact row (~44px)
 * - Left: Subtle identifier
 * - Center: Horizontal text navigation (no icons, no heavy backgrounds)
 * - Right: Utilities (notifications as ghost button)
 * 
 * Design principles (from Design Brief):
 * - Neutrals as base, color only as accent
 * - Clear hierarchy
 * - Reduce UI clutter
 * - Navigation should not compete with content
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// Navigation items
$nav_items = [
    'calendar' => 'Calendario',
    'clients' => 'Clientes', 
    'assignments' => 'Asignaciones',
    'settings' => 'Configuración',
];
?>
<header class="aa-app-header">
    <div class="aa-header-inner">
        <!-- Left: Subtle identifier -->
        <div class="aa-header-brand">
            <span class="aa-brand-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </span>
        </div>
        
        <!-- Center: Navigation -->
        <nav class="aa-header-nav" role="navigation" aria-label="Navegación principal">
            <?php foreach ($nav_items as $module => $label): 
                $is_active = isset($active_module) && $active_module === $module;
                $url = admin_url('admin-post.php?action=aa_iframe_content&module=' . $module);
            ?>
            <a href="<?php echo esc_url($url); ?>" 
               class="aa-nav-item <?php echo $is_active ? 'is-active' : ''; ?>"
               <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html($label); ?>
            </a>
            <?php endforeach; ?>
        </nav>
        
        <!-- Right: Utilities -->
        <div class="aa-header-utils">
            <!-- Notifications -->
            <div class="aa-notifications-wrapper">
                <button 
                    id="aa-btn-notifications" 
                    type="button"
                    class="aa-util-btn"
                    aria-label="Notificaciones"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span id="aa-notifications-badge" class="aa-badge" aria-live="polite">0</span>
                </button>
                
                <!-- Notifications Popover -->
                <div id="aa-notifications-popover" class="aa-popover aa-notifications-popover hidden" role="dialog" aria-label="Panel de notificaciones">
                    <div class="aa-popover-header">
                        <span class="aa-popover-title">Notificaciones</span>
                        <button 
                            id="aa-btn-close-notifications" 
                            type="button"
                            class="aa-popover-close"
                            aria-label="Cerrar"
                        >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="aa-notifications-content">
                        <!-- Content rendered by notifications.js -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
/* ============================================
   App Shell Header - Design Brief Compliant
   ============================================ */

.aa-app-header {
    background-color: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    z-index: 100;
}

.aa-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 44px;
    padding: 0 16px;
    max-width: 100%;
}

/* Brand - Subtle identifier */
.aa-header-brand {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.aa-brand-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    opacity: 0.7;
}

/* Navigation - Center, horizontal, text-only */
.aa-header-nav {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0 auto;
}

.aa-nav-item {
    position: relative;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 500;
    color: #6b7280;
    text-decoration: none;
    border-radius: 6px;
    transition: color 150ms ease, background-color 150ms ease;
}

.aa-nav-item:hover {
    color: #374151;
    background-color: #f9fafb;
}

.aa-nav-item:focus-visible {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

.aa-nav-item.is-active {
    color: #111827;
    background-color: #f3f4f6;
}

/* Active indicator - subtle underline */
.aa-nav-item.is-active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 24px;
    height: 2px;
    background-color: #3b82f6;
    border-radius: 1px;
}

/* Utilities - Right side */
.aa-header-utils {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

/* Utility button - Ghost style */
.aa-util-btn {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    background: transparent;
    border: none;
    border-radius: 6px;
    color: #9ca3af;
    cursor: pointer;
    transition: color 150ms ease, background-color 150ms ease;
}

.aa-util-btn:hover {
    color: #6b7280;
    background-color: #f3f4f6;
}

.aa-util-btn:focus-visible {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

.aa-util-btn:active {
    background-color: #e5e7eb;
    color: #374151;
}

/* Badge - Notification count */
.aa-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    min-width: 14px;
    height: 14px;
    padding: 0 4px;
    font-size: 10px;
    font-weight: 600;
    line-height: 14px;
    text-align: center;
    color: #6b7280;
    background: transparent;
    border-radius: 7px;
}

/* Show badge only when there are notifications */
.aa-badge:not(:empty):not([data-count="0"]) {
    color: #ffffff;
    background-color: #ef4444;
}

/* Hide badge when empty or zero */
.aa-badge:empty,
.aa-badge[data-count="0"] {
    display: none;
}

/* Notifications wrapper */
.aa-notifications-wrapper {
    position: relative;
}

/* Popover - Notifications panel */
.aa-notifications-popover {
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    width: 320px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    z-index: 200;
}

.aa-notifications-popover.hidden {
    display: none;
}

.aa-popover-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #f3f4f6;
}

.aa-popover-title {
    font-size: 13px;
    font-weight: 600;
    color: #111827;
}

.aa-popover-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    padding: 0;
    background: transparent;
    border: none;
    border-radius: 4px;
    color: #9ca3af;
    cursor: pointer;
    transition: color 150ms ease, background-color 150ms ease;
}

.aa-popover-close:hover {
    color: #6b7280;
    background-color: #f3f4f6;
}

.aa-notifications-content {
    padding: 12px 16px;
    max-height: 320px;
    overflow-y: auto;
}

/* Responsive adjustments */
@media (max-width: 480px) {
    .aa-header-inner {
        padding: 0 12px;
    }
    
    .aa-nav-item {
        padding: 8px 10px;
        font-size: 12px;
    }
    
    .aa-brand-icon {
        display: none;
    }
}
</style>
