<?php
/**
 * Training lesson progress mutations (opened / completed). C9A4.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/AA_Training_Use_Case_Support.php';

class TrainingProgressUseCase {
    use AA_Training_Use_Case_Support;

    /**
     * @param AA_Training_Backend_Client|null $client
     */
    public function __construct(?AA_Training_Backend_Client $client = null) {
        $this->client = $client;
    }

    /**
     * @param string $lesson_key
     * @return array{success: true, data: array{lesson_key: string, progress: array{opened: bool, completed: bool}}}|array{success: false, error: array{code: string, message: string}}
     */
    public function mark_opened($lesson_key): array {
        return $this->run_progress_mutation('mark_lesson_opened', $lesson_key);
    }

    /**
     * @param string $lesson_key
     * @return array{success: true, data: array{lesson_key: string, progress: array{opened: bool, completed: bool}}}|array{success: false, error: array{code: string, message: string}}
     */
    public function mark_completed($lesson_key): array {
        return $this->run_progress_mutation('mark_lesson_completed', $lesson_key);
    }

    /**
     * @param 'mark_lesson_opened'|'mark_lesson_completed' $method
     * @param string                                       $lesson_key
     * @return array{success: true, data: array{lesson_key: string, progress: array{opened: bool, completed: bool}}}|array{success: false, error: array{code: string, message: string}}
     */
    private function run_progress_mutation(string $method, $lesson_key): array {
        $guard = $this->guard_client_secret();
        if ($guard !== null) {
            return $guard;
        }

        $client = $this->resolve_client();
        if (!$client->is_valid_lesson_key($lesson_key)) {
            return $this->failure('training_content_lesson_key_invalid', '');
        }

        $mapped = $this->map_backend_result($client->{$method}($lesson_key));
        if (empty($mapped['success']) || !isset($mapped['data']) || !is_array($mapped['data'])) {
            return $mapped;
        }

        return [
            'success' => true,
            'data'    => $this->normalize_progress_payload($mapped['data'], (string) $lesson_key),
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @param string              $fallback_lesson_key
     * @return array{lesson_key: string, progress: array{opened: bool, completed: bool}}
     */
    private function normalize_progress_payload(array $data, string $fallback_lesson_key): array {
        $lesson_key = isset($data['lesson_key']) && is_string($data['lesson_key']) && $data['lesson_key'] !== ''
            ? $data['lesson_key']
            : $fallback_lesson_key;

        $raw_progress = isset($data['progress']) && is_array($data['progress'])
            ? $data['progress']
            : [];

        return [
            'lesson_key' => $lesson_key,
            'progress'   => [
                'opened'    => !empty($raw_progress['opened']),
                'completed' => !empty($raw_progress['completed']),
            ],
        ];
    }
}
