<?php
/**
 * Training enrollment status + enroll/unsubscribe use cases.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/AA_Training_Use_Case_Support.php';

final class TrainingEnrollmentUseCase {
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
    public function get_status(): array {
        $guard = $this->guard_client_secret();
        if ($guard !== null) {
            return $guard;
        }

        return $this->map_backend_result($this->resolve_client()->get_status());
    }

    /**
     * @return array{success: true, data: array<string,mixed>}|array{success: false, error: array{code: string, message: string}}
     */
    public function enroll(): array {
        $guard = $this->guard_client_secret();
        if ($guard !== null) {
            return $guard;
        }

        return $this->map_backend_result($this->resolve_client()->enroll());
    }

    /**
     * @return array{success: true, data: array<string,mixed>}|array{success: false, error: array{code: string, message: string}}
     */
    public function unsubscribe(): array {
        $guard = $this->guard_client_secret();
        if ($guard !== null) {
            return $guard;
        }

        return $this->map_backend_result($this->resolve_client()->unsubscribe());
    }
}
