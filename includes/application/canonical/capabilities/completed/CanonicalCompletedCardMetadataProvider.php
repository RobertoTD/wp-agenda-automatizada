<?php
/** Metadata de card de la capability `completed`. */
defined('ABSPATH') or die('No direct access');

final class CanonicalCompletedCardMetadataProvider implements CanonicalCapabilityCardMetadataProvider {
    public const KEY = 'completed';

    public function capability_key(): string { return self::KEY; }

    public function metadata_for_record(int $record_id, array $record_capabilities): array {
        if ($record_id < 1 || !isset($record_capabilities[self::KEY]) || !is_array($record_capabilities[self::KEY])) {
            return [];
        }
        $state = $record_capabilities[self::KEY];
        if (($state['status'] ?? '') !== CanonicalCapabilityRecordReadState::STATUS_KNOWN_FIELDS
            || !isset($state['fields']) || !is_array($state['fields'])
            || ($state['fields']['completed'] ?? null) !== '1'
            || !isset($state['fields']['completed_at']) || !is_string($state['fields']['completed_at'])
        ) {
            return [];
        }
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $state['fields']['completed_at'],
            new \DateTimeZone('UTC')
        );
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return [];
        }
        return [new CanonicalCapabilityCardMetadata(self::KEY, 'completed_at', 'Completada el', $date)];
    }
}
