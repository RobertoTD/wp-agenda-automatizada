<?php
/**
 * Shared helpers for Training application use cases.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-training-backend-client.php';

trait AA_Training_Use_Case_Support {

    /** @var AA_Training_Backend_Client|null */
    private $client;

    private function resolve_client(): AA_Training_Backend_Client {
        if ($this->client instanceof AA_Training_Backend_Client) {
            return $this->client;
        }

        return new AA_Training_Backend_Client();
    }

    /**
     * @return array{success: false, error: array{code: string, message: string}}|null
     */
    private function guard_client_secret(): ?array {
        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->failure('training_backend_not_configured', '');
        }

        return null;
    }

    /**
     * @param array{ok?: bool, code?: string, error?: string, result?: array<string,mixed>} $backend
     * @return array{success: true, data: array<string,mixed>}|array{success: false, error: array{code: string, message: string}}
     */
    private function map_backend_result(array $backend): array {
        if (!empty($backend['ok']) && isset($backend['result']) && is_array($backend['result'])) {
            return [
                'success' => true,
                'data'    => $backend['result'],
            ];
        }

        $code = isset($backend['code']) && is_string($backend['code']) && $backend['code'] !== ''
            ? $backend['code']
            : 'training_backend_error';

        return $this->failure($code, '');
    }

    /**
     * @return array{success: false, error: array{code: string, message: string}}
     */
    private function failure(string $code, string $message): array {
        return [
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];
    }
}
