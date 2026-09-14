<?php
/**
 * Avance HMAC de mandatos de purge (accept/seal/status).
 *
 * Intención persistida ANTES del HTTP. Sin TX SQL abierta. Presupuesto:
 * un accept (+ status de esa tanda) y seal cuando toque. expected_batch_count
 * puede ser 0 (cierre de recepción de lista vacía).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalPurgeRunsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalPurgeRunsRepository.php';
}
if (!class_exists('CanonicalPurgeInventoryItemsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalPurgeInventoryItemsRepository.php';
}
if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
    require_once dirname(__DIR__, 3) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
}
if (!class_exists('CanonicalPurgeRemoteAdvanceResult')) {
    require_once __DIR__ . '/CanonicalPurgeRemoteAdvanceResult.php';
}

final class CanonicalPurgeRemoteMandateAdvancer {

    /** @var CanonicalPurgeRunsRepository */
    private $runs;

    /** @var CanonicalPurgeInventoryItemsRepository */
    private $inventory;

    /** @var AA_Expediente_Attachments_Backend_Client */
    private $client;

    public function __construct(
        CanonicalPurgeRunsRepository $runs,
        CanonicalPurgeInventoryItemsRepository $inventory,
        AA_Expediente_Attachments_Backend_Client $client
    ) {
        $this->runs = $runs;
        $this->inventory = $inventory;
        $this->client = $client;
    }

    /**
     * @param array<string, mixed> $run
     */
    public function advance(array $run): CanonicalPurgeRemoteAdvanceResult {
        $purge_run_id = (int) ($run['id'] ?? 0);
        $mandate_id = (string) ($run['mandate_id'] ?? '');
        $prepared = (int) ($run['prepared_batch_count'] ?? 0);
        $now = gmdate('Y-m-d H:i:s');

        if ($purge_run_id < 1 || $mandate_id === '') {
            return CanonicalPurgeRemoteAdvanceResult::intervention('mandate_missing');
        }

        $last_accepted = CanonicalPurgeRunsRepository::nullable_int($run['last_accepted_batch_seq'] ?? null);
        $next_seq = $last_accepted === null ? 0 : $last_accepted + 1;

        if ($next_seq < $prepared) {
            $credited = $this->credit_or_accept_batch($run, $next_seq, $mandate_id, $now);
            if ($credited !== null) {
                return $credited;
            }
            $run = $this->runs->find_by_id($purge_run_id);
            if ($run === null) {
                return CanonicalPurgeRemoteAdvanceResult::persistence_failed();
            }
            $last_accepted = CanonicalPurgeRunsRepository::nullable_int($run['last_accepted_batch_seq'] ?? null);
            $next_seq = $last_accepted === null ? 0 : $last_accepted + 1;
            if ($next_seq < $prepared) {
                return CanonicalPurgeRemoteAdvanceResult::incomplete();
            }
        }

        return $this->seal_or_recover($run, $mandate_id, $prepared, $now);
    }

    /**
     * @param array<string, mixed> $run
     */
    private function credit_or_accept_batch(
        array $run,
        int $batch_seq,
        string $mandate_id,
        string $now
    ): ?CanonicalPurgeRemoteAdvanceResult {
        $purge_run_id = (int) $run['id'];
        $local = $this->inventory->list_prepared_batch($purge_run_id, $batch_seq);
        if ($local === []) {
            return CanonicalPurgeRemoteAdvanceResult::intervention('prepared_batch_missing');
        }

        $intent_seq = CanonicalPurgeRunsRepository::nullable_int($run['accept_intent_batch_seq'] ?? null);
        $unknown = $intent_seq !== null && $intent_seq === $batch_seq;

        if ($unknown) {
            $status = $this->client->get_delete_mandate_status([
                'mandate_id' => $mandate_id,
                'batch_seq' => $batch_seq,
            ]);
            if (($status['ok'] ?? false) === true) {
                $remote_items = $this->status_credit_items($status);
                if ($remote_items !== null) {
                    if (!$this->items_match_local($local, $remote_items)) {
                        return CanonicalPurgeRemoteAdvanceResult::intervention('remote_identity_mismatch');
                    }
                    $this->runs->credit_last_accepted_batch($purge_run_id, $batch_seq, $now);

                    return null;
                }
                if (($status['result']['found'] ?? null) === false
                    || ($status['result']['batch_found'] ?? null) === false
                ) {
                    return $this->dispatch_accept($run, $batch_seq, $mandate_id, $local, $now, false);
                }
            }
            if (($status['ok'] ?? false) !== true) {
                return $this->hmac_failed_result($status);
            }

            return $this->dispatch_accept($run, $batch_seq, $mandate_id, $local, $now, false);
        }

        return $this->dispatch_accept($run, $batch_seq, $mandate_id, $local, $now, true);
    }

    /**
     * @param array<string, mixed> $run
     * @param list<array<string, mixed>> $local
     */
    private function dispatch_accept(
        array $run,
        int $batch_seq,
        string $mandate_id,
        array $local,
        string $now,
        bool $persist_intent
    ): ?CanonicalPurgeRemoteAdvanceResult {
        $purge_run_id = (int) $run['id'];

        if ($persist_intent) {
            $this->runs->persist_accept_intent($purge_run_id, $batch_seq, $now);
        }

        $accepted = $this->client->accept_delete_batch([
            'mandate_id' => $mandate_id,
            'batch_seq' => $batch_seq,
            'items' => $this->accept_items_from_local($local),
        ]);
        if (($accepted['ok'] ?? false) !== true) {
            return $this->hmac_failed_result($accepted);
        }

        $remote_items = is_array($accepted['result']['items'] ?? null) ? $accepted['result']['items'] : null;
        if ($remote_items === null || !$this->items_match_local($local, $remote_items)) {
            return CanonicalPurgeRemoteAdvanceResult::intervention('remote_identity_mismatch');
        }

        $this->runs->credit_last_accepted_batch($purge_run_id, $batch_seq, $now);

        return null;
    }

    /**
     * @param array<string, mixed> $run
     */
    private function seal_or_recover(
        array $run,
        string $mandate_id,
        int $expected_batch_count,
        string $now
    ): CanonicalPurgeRemoteAdvanceResult {
        $purge_run_id = (int) $run['id'];
        $seal_intent = CanonicalPurgeRunsRepository::nullable_string($run['seal_intent_at'] ?? null);

        if ($seal_intent !== null) {
            $status = $this->client->get_delete_mandate_status([
                'mandate_id' => $mandate_id,
            ]);
            if (($status['ok'] ?? false) !== true) {
                return $this->hmac_failed_result($status);
            }
            if ($this->is_structurally_sealed($status)) {
                $this->runs->mark_sealed($purge_run_id, $now);

                return CanonicalPurgeRemoteAdvanceResult::sealed();
            }
        } else {
            $this->runs->persist_seal_intent($purge_run_id, $now);
        }

        $sealed = $this->client->seal_delete_mandate([
            'mandate_id' => $mandate_id,
            'expected_batch_count' => $expected_batch_count,
        ]);
        if (($sealed['ok'] ?? false) !== true) {
            return $this->hmac_failed_result($sealed);
        }
        if (!$this->is_structurally_sealed($sealed)) {
            return CanonicalPurgeRemoteAdvanceResult::intervention('seal_not_structural');
        }

        $this->runs->mark_sealed($purge_run_id, $now);

        return CanonicalPurgeRemoteAdvanceResult::sealed();
    }

    /**
     * @param list<array<string, mixed>> $local
     * @return list<array<string, mixed>>
     */
    private function accept_items_from_local(array $local): array {
        $items = [];
        foreach ($local as $row) {
            $item = [
                'upload_operation_id' => (string) $row['upload_operation_id'],
                'wp_record_id' => (int) $row['wp_record_id'],
                'content_sha256' => (string) $row['content_sha256'],
                'byte_size' => (int) $row['byte_size'],
            ];
            if (isset($row['storage_path']) && is_string($row['storage_path']) && $row['storage_path'] !== '') {
                $item['storage_path'] = $row['storage_path'];
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $local
     * @param list<array<string, mixed>> $remote
     */
    private function items_match_local(array $local, array $remote): bool {
        if (count($local) !== count($remote)) {
            return false;
        }

        $by_op = [];
        foreach ($remote as $item) {
            if (!is_array($item)) {
                return false;
            }
            $op = strtolower((string) ($item['upload_operation_id'] ?? ''));
            if ($op === '' || isset($by_op[$op])) {
                return false;
            }
            $by_op[$op] = $item;
        }

        foreach ($local as $row) {
            $op = strtolower((string) ($row['upload_operation_id'] ?? ''));
            if (!isset($by_op[$op])) {
                return false;
            }
            $remote_item = $by_op[$op];
            if ((int) ($remote_item['wp_record_id'] ?? 0) !== (int) $row['wp_record_id']) {
                return false;
            }
            if (strtolower((string) ($remote_item['content_sha256'] ?? '')) !== strtolower((string) $row['content_sha256'])) {
                return false;
            }
            if ((int) ($remote_item['byte_size'] ?? 0) !== (int) $row['byte_size']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $hmac
     * @return list<array<string, mixed>>|null
     */
    private function status_credit_items(array $hmac): ?array {
        if (($hmac['ok'] ?? false) !== true) {
            return null;
        }
        $result = $hmac['result'] ?? [];
        if (empty($result['can_credit_batch'])) {
            return null;
        }
        if (isset($result['batch']['items']) && is_array($result['batch']['items'])) {
            return $result['batch']['items'];
        }
        if (!empty($result['inventory_page_complete']) && isset($result['items']) && is_array($result['items'])) {
            return $result['items'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $hmac
     */
    private function is_structurally_sealed(array $hmac): bool {
        if (($hmac['ok'] ?? false) !== true) {
            return false;
        }
        if (($hmac['retire_authorized'] ?? false) !== true) {
            return false;
        }
        $result = $hmac['result'] ?? [];

        return ($result['inventory_status'] ?? '') === 'sealed'
            && ($result['structural_retire_authorized'] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $hmac
     */
    private function hmac_failed_result(array $hmac): CanonicalPurgeRemoteAdvanceResult {
        $class = (string) ($hmac['failure_class'] ?? '');
        $code = (string) ($hmac['code'] ?? '');
        if ($class === 'malformed' || $code === 'expediente_attachments_invalid_response') {
            return CanonicalPurgeRemoteAdvanceResult::intervention('remote_payload_invalid');
        }

        return CanonicalPurgeRemoteAdvanceResult::incomplete();
    }
}
