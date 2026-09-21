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
        if (($state['status'] ?? '') !== 'known_value') {
            return [];
        }
        $value = isset($state['value']) ? (string) $state['value'] : '';
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
