<?php
/**
 * Consume agenda magic-link token and establish a persistent WP session (C2).
 *
 * Exactly one backend consume call; no automatic retries.
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/auth/class-aa-agenda-access-token-format.php';
require_once dirname(__DIR__, 2) . '/domain/auth/class-aa-agenda-access-user-policy.php';
require_once dirname(__DIR__, 2) . '/domain/auth/class-aa-agenda-access-redirect-policy.php';
require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-agenda-access-backend-client.php';
require_once dirname(__DIR__, 2) . '/infrastructure/auth/class-aa-agenda-access-session-issuer.php';

final class ConsumeAgendaAccessTokenUseCase {

    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    public const ERROR_INVALID_TOKEN = 'invalid_token';
    public const ERROR_INVALID_OR_EXPIRED = 'invalid_or_expired';
    public const ERROR_UNAVAILABLE = 'unavailable';
    public const ERROR_USER_REJECTED = 'user_rejected';

    /** @var AA_Agenda_Access_Backend_Client */
    private $client;

    /** @var AA_Agenda_Access_User_Policy */
    private $user_policy;

    /** @var AA_Agenda_Access_Redirect_Policy */
    private $redirect_policy;

    /** @var AA_Agenda_Access_Session_Issuer */
    private $session_issuer;

    /** @var int */
    private $consume_calls = 0;

    public function __construct(
        ?AA_Agenda_Access_Backend_Client $client = null,
        ?AA_Agenda_Access_User_Policy $user_policy = null,
        ?AA_Agenda_Access_Redirect_Policy $redirect_policy = null,
        ?AA_Agenda_Access_Session_Issuer $session_issuer = null
    ) {
        $this->client          = $client ?? new AA_Agenda_Access_Backend_Client();
        $this->user_policy     = $user_policy ?? new AA_Agenda_Access_User_Policy();
        $this->redirect_policy = $redirect_policy ?? new AA_Agenda_Access_Redirect_Policy();
        $this->session_issuer  = $session_issuer ?? new AA_Agenda_Access_Session_Issuer();
    }

    /**
     * @return int Number of backend consume attempts in this instance (tests).
     */
    public function get_consume_call_count(): int {
        return $this->consume_calls;
    }

    /**
     * @return array{
     *     status: 'success',
     *     user_id: int,
     *     redirect_url: string
     * }|array{
     *     status: 'error',
     *     code: string,
     *     redirect_url: string,
     *     soft_success?: bool
     * }
     */
    public function execute(string $token): array {
        $token = AA_Agenda_Access_Token_Format::sanitize($token);
        if ($token === '') {
            return $this->error(self::ERROR_INVALID_TOKEN);
        }

        $this->consume_calls++;
        $remote = $this->client->consume($token);

        if (empty($remote['ok'])) {
            $code = isset($remote['code']) ? (string) $remote['code'] : AA_Agenda_Access_Backend_Client::CODE_UNAVAILABLE;
            if ($code === AA_Agenda_Access_Backend_Client::CODE_INVALID_OR_EXPIRED) {
                return $this->error_or_soft_success(self::ERROR_INVALID_OR_EXPIRED);
            }
            return $this->error_or_soft_success(self::ERROR_UNAVAILABLE);
        }

        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        $resolved = $this->user_policy->resolve(
            [
                'email'      => $remote['email'] ?? '',
                'wp_user_id' => $remote['wp_user_id'] ?? null,
            ],
            $blog_id
        );

        if (empty($resolved['ok'])) {
            return $this->error(self::ERROR_USER_REJECTED);
        }

        $user_id = (int) $resolved['user_id'];
        $purpose = isset($remote['purpose']) && is_string($remote['purpose']) ? $remote['purpose'] : '';

        $current_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        if ($current_id > 0 && $current_id !== $user_id && function_exists('wp_logout')) {
            wp_logout();
        }

        $this->session_issuer->establish($user_id);

        $redirect = $this->redirect_policy->success_url($purpose);
        if (!$this->redirect_policy->is_allowlisted_success_url($redirect)) {
            $redirect = $this->redirect_policy->calendar_url();
        }

        return [
            'status'       => self::STATUS_SUCCESS,
            'user_id'      => $user_id,
            'redirect_url' => $redirect,
        ];
    }

    /**
     * When consume fails but the visitor already has a valid admin session on this blog,
     * send them to the app instead of the login error screen (double-click / already used).
     *
     * @return array{status: 'error', code: string, redirect_url: string, soft_success?: bool}
     */
    private function error_or_soft_success(string $code): array {
        if ($this->current_user_may_use_app()) {
            return [
                'status'       => self::STATUS_ERROR,
                'code'         => $code,
                'redirect_url' => $this->redirect_policy->calendar_url(),
                'soft_success' => true,
            ];
        }
        return $this->error($code);
    }

    /**
     * @return array{status: 'error', code: string, redirect_url: string}
     */
    private function error(string $code): array {
        return [
            'status'       => self::STATUS_ERROR,
            'code'         => $code,
            'redirect_url' => $this->redirect_policy->error_login_url(),
        ];
    }

    private function current_user_may_use_app(): bool {
        if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
            return false;
        }
        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }
        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        if ($blog_id > 0 && function_exists('is_user_member_of_blog') && !is_user_member_of_blog($user_id, $blog_id)) {
            return false;
        }
        return user_can($user_id, 'manage_options');
    }
}
