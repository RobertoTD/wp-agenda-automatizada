<?php
/**
 * Presentación dossier (acción Expediente) en tarjeta del shell.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Dossier_Shell_Presenter {

    /**
     * @param array<string,mixed>|null $cap_map
     * @return array{kind:string,enabled?:bool,reason?:string}|null
     */
    public static function card_action(?array $cap_map): ?array {
        if (!is_array($cap_map) || !isset($cap_map['dossier']) || !is_array($cap_map['dossier'])) {
            return null;
        }
        $state = $cap_map['dossier'];
        $status = isset($state['status']) ? (string) $state['status'] : '';

        if ($status === CanonicalCapabilityRecordReadState::STATUS_KNOWN_ABSENT) {
            return null;
        }
        if ($status === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED) {
            return ['kind' => 'error'];
        }
        if ($status !== CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE) {
            return null;
        }

        $value = isset($state['value']) ? (string) $state['value'] : '';
        if ($value === CanonicalDossierRecordsPageContributor::VALUE_ARCHIVE_DISABLED) {
            return [
                'kind' => 'action',
                'enabled' => false,
                'reason' => 'archive_disabled',
            ];
        }
        if ($value === CanonicalDossierRecordsPageContributor::VALUE_DOSSIER_RETIRING) {
            return [
                'kind' => 'action',
                'enabled' => false,
                'reason' => 'dossier_retiring',
            ];
        }
        if ($value === CanonicalDossierRecordsPageContributor::VALUE_READY) {
            return [
                'kind' => 'action',
                'enabled' => true,
            ];
        }

        return ['kind' => 'error'];
    }
}
