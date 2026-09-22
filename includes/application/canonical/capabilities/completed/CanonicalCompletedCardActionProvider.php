<?php
/** Acción de card de la capability `completed`. */
defined('ABSPATH') or die('No direct access');

final class CanonicalCompletedCardActionProvider implements CanonicalCapabilityCardActionProvider {
    public const KEY = 'completed';

    public function capability_key(): string { return self::KEY; }

    public function actions_for_record(int $record_id, array $record_capabilities): array {
        if ($record_id < 1 || !isset($record_capabilities[self::KEY]) || !is_array($record_capabilities[self::KEY])) {
            return [];
        }
        $state = $record_capabilities[self::KEY];
        $status = isset($state['status']) ? (string) $state['status'] : '';
        $value = '';
        if ($status === 'known_value') {
            $value = isset($state['value']) ? (string) $state['value'] : '';
        } elseif ($status === 'known_fields' && isset($state['fields']) && is_array($state['fields'])) {
            $value = isset($state['fields']['completed']) ? (string) $state['fields']['completed'] : '';
        } else {
            return [];
        }
        if ($value !== '0' && $value !== '1') {
            return [];
        }
        $is_completed = ($value === '1');
        return [new CanonicalCapabilityCardAction(
            self::KEY,
            'toggle',
            $is_completed ? 'Marcar como pendiente' : 'Completar',
            ['record_id' => $record_id, 'completed' => $is_completed ? 0 : 1]
        )];
    }
}
