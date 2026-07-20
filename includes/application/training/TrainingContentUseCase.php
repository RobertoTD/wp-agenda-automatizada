<?php
/**
 * Training course manifest + rendered lesson use cases.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/AA_Training_Use_Case_Support.php';

final class TrainingContentUseCase {
    use AA_Training_Use_Case_Support;

    /**
     * @param AA_Training_Backend_Client|null $client
     */
    public function __construct(?AA_Training_Backend_Client $client = null) {
        $this->client = $client;
    }

    /**
     * @return array{success: true, data: array<string,mixed>}|array{success: false, error: array{code: string, message: string}}
     */
    public function get_course(): array {
        $guard = $this->guard_client_secret();
        if ($guard !== null) {
            return $guard;
        }

        return $this->map_backend_result($this->resolve_client()->get_course());
    }

    /**
     * @param string $lesson_key
     * @return array{success: true, data: array<string,mixed>}|array{success: false, error: array{code: string, message: string}}
     */
    public function get_lesson($lesson_key): array {
        $guard = $this->guard_client_secret();
        if ($guard !== null) {
            return $guard;
        }

        $client = $this->resolve_client();
        if (!$client->is_valid_lesson_key($lesson_key)) {
            return $this->failure('training_content_lesson_key_invalid', '');
        }

        return $this->map_backend_result($client->get_lesson($lesson_key));
    }
}
