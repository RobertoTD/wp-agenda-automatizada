<?php
/**
 * Executive Actions Policy — filtra acciones permitidas en Propuesta ejecutiva (MC1).
 *
 * Dominio puro: whitelist sobre AA_Executable_Visible_Actions_Policy.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__) . '/executable/class-aa-executable-contract.php';
require_once dirname(__DIR__) . '/executable/class-aa-executable-visible-actions-policy.php';

final class AA_Executive_Actions_Policy {

    /**
     * @param array<string,mixed> $item Executable-like item (primary_action, capabilities, status).
     * @param array<string,mixed> $context view, bucket_key, source.
     * @return list<array<string,mixed>>
     */
    public static function resolve(array $item, array $context = []): array {
        $visible_actions = AA_Executable_Visible_Actions_Policy::resolve($item, $context);
        $filtered = [];

        foreach ($visible_actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            if (!self::is_executive_action($action)) {
                continue;
            }

            $filtered[] = $action;
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $action
     */
    private static function is_executive_action(array $action): bool {
        $key = strtolower(trim((string) ($action['key'] ?? '')));
        $category = strtolower(trim((string) ($action['category'] ?? '')));
        $type = strtolower(trim((string) ($action['type'] ?? '')));

        if ($key === 'defer' || $key === 'reactivate' || $key === 'reopen') {
            return false;
        }

        if ($category === AA_Executable_Visible_Actions_Policy::CATEGORY_MECHANICAL) {
            return $type === AA_Executable_Contract::ACTION_NAVIGATE
                || $type === AA_Executable_Contract::ACTION_HANDLER;
        }

        if ($category === AA_Executable_Visible_Actions_Policy::CATEGORY_DECLARATIVE) {
            return $key === 'complete';
        }

        if ($category === AA_Executable_Visible_Actions_Policy::CATEGORY_INTENT) {
            return $key === 'dismiss';
        }

        return false;
    }
}
