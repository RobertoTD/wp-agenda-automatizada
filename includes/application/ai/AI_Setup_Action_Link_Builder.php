<?php
/**
 * AI Setup Action Link Builder
 *
 * Construye actions de navegación para bloqueos de setup del chat AI.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Setup_Action_Link_Builder {

    private const ACTION_CONFIG = [
        'assignments_staff' => [
            'label' => 'Ir a profesionales',
            'focus' => 'staff',
            'hash'  => '#aa-staff-root',
        ],
        'assignments_services' => [
            'label' => 'Ir a Servicios',
            'focus' => 'services',
            'hash'  => '#aa-services-root',
        ],
        'assignments_areas' => [
            'label'  => 'Ir a Zonas de atención',
            'module' => 'assignments',
            'focus'  => 'areas',
            'hash'   => '#aa-areas-root',
        ],
        'assignments_staff_services' => [
            'label'  => 'Asignar servicios al personal',
            'module' => 'assignments',
            'focus'  => 'staff_services',
            'hash'   => '#aa-staff-root',
        ],
        'clients_create' => [
            'label'  => 'Ir a Clientes',
            'module' => 'clients',
            'focus'  => 'clients',
            'hash'   => '#aa-clients-grid',
        ],
    ];

    /**
     * @param array<int, array<string,mixed>> $missing_setup
     * @return array<int, array<string,string>>
     */
    public function build_actions(array $missing_setup): array {
        $actions = [];

        foreach ($missing_setup as $item) {
            if (!is_array($item)) {
                continue;
            }

            $action_key = isset($item['action_key']) ? (string) $item['action_key'] : '';
            $action = $this->build_action_for_key($action_key);
            if ($action !== null) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * @return array{key:string,label:string,url:string}|null
     */
    public function build_action_for_key(string $action_key): ?array {
        if (!isset(self::ACTION_CONFIG[$action_key])) {
            return null;
        }

        $config = self::ACTION_CONFIG[$action_key];
        $url = add_query_arg(
            [
                'action'      => 'aa_iframe_content',
                'module'      => $config['module'] ?? 'assignments',
                'setup_focus' => $config['focus'],
            ],
            admin_url('admin-post.php')
        );
        $url .= $config['hash'];

        return [
            'key'   => $action_key,
            'label' => $config['label'],
            'url'   => $url,
        ];
    }
}
