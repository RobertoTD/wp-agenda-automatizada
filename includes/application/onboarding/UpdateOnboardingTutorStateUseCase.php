<?php
/**
 * Update Onboarding Tutor State Use Case — actualización controlada del estado durable UX.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/onboarding/class-aa-onboarding-tutor-state-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/OnboardingTutorStateRepository.php';

final class UpdateOnboardingTutorStateUseCase {

    /** @var callable|null */
    private $now_resolver;

    /**
     * @param callable|null $now_resolver Debe devolver string datetime mysql.
     */
    public function __construct(?callable $now_resolver = null) {
        $this->now_resolver = $now_resolver;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $flow_id = isset($input['flow_id']) ? sanitize_key((string) $input['flow_id']) : '';

        if ($flow_id === '') {
            return $this->failure('missing_flow_id', 'Falta flow_id.');
        }

        $patch = $input['patch'] ?? null;

        if (!is_array($patch)) {
            return $this->failure('invalid_patch', 'Patch de tutor inválido.');
        }

        $current = OnboardingTutorStateRepository::find();
        $result = AA_Onboarding_Tutor_State_Policy::apply_flow_patch($current, $flow_id, $patch);

        if (empty($result['ok'])) {
            $error = $result['error'] ?? [];

            return $this->failure(
                (string) ($error['code'] ?? 'invalid_patch'),
                (string) ($error['message'] ?? 'No se pudo aplicar el patch del tutor.')
            );
        }

        $next_state = $result['state'] ?? AA_Onboarding_Tutor_State_Policy::empty_state();
        $next_state['flows'][$flow_id]['updated_at'] = $this->resolve_now();

        if (!OnboardingTutorStateRepository::save($next_state)) {
            return $this->failure('persist_failed', 'No se pudo guardar el estado del tutor.');
        }

        return [
            'success' => true,
            'data' => OnboardingTutorStateRepository::find(),
        ];
    }

    /**
     * @return string
     */
    private function resolve_now(): string {
        if ($this->now_resolver !== null) {
            $value = call_user_func($this->now_resolver);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        if (function_exists('current_time')) {
            return current_time('mysql');
        }

        return gmdate('Y-m-d H:i:s');
    }

    /**
     * @param string $code
     * @param string $message
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function failure(string $code, string $message): array {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
