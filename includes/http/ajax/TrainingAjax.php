<?php
/**
 * Training AJAX — admin bridge to TrainingEnrollment/Consent/Content use cases (C8A1b).
 *
 * Browser talks only to admin-ajax.php. HMAC stays server-side via C8A1a client.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-training-backend-client.php';
require_once dirname(__DIR__, 2) . '/application/training/TrainingEnrollmentUseCase.php';
require_once dirname(__DIR__, 2) . '/application/training/TrainingConsentUseCase.php';
require_once dirname(__DIR__, 2) . '/application/training/TrainingContentUseCase.php';
require_once dirname(__DIR__, 2) . '/application/training/TrainingProgressUseCase.php';

class TrainingAjax {

    public const NONCE_ACTION = 'aa_training_nonce';

    public const ACTION_GET_STATUS           = 'aa_get_training_status';
    public const ACTION_ENROLL              = 'aa_enroll_training';
    public const ACTION_UNSUBSCRIBE         = 'aa_unsubscribe_training';
    public const ACTION_GET_CONSENT_STATUS  = 'aa_get_training_consent_status';
    public const ACTION_ACCEPT_CONSENT      = 'aa_accept_training_consent';
    public const ACTION_REVOKE_CONSENT      = 'aa_revoke_training_consent';
    public const ACTION_GET_COURSE          = 'aa_get_training_course';
    public const ACTION_GET_LESSON          = 'aa_get_training_lesson';
    public const ACTION_MARK_LESSON_OPENED  = 'aa_mark_training_lesson_opened';
    public const ACTION_MARK_LESSON_COMPLETED = 'aa_mark_training_lesson_completed';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_GET_STATUS, [__CLASS__, 'handle_get_status']);
        add_action('wp_ajax_' . self::ACTION_ENROLL, [__CLASS__, 'handle_enroll']);
        add_action('wp_ajax_' . self::ACTION_UNSUBSCRIBE, [__CLASS__, 'handle_unsubscribe']);
        add_action('wp_ajax_' . self::ACTION_GET_CONSENT_STATUS, [__CLASS__, 'handle_get_consent_status']);
        add_action('wp_ajax_' . self::ACTION_ACCEPT_CONSENT, [__CLASS__, 'handle_accept_consent']);
        add_action('wp_ajax_' . self::ACTION_REVOKE_CONSENT, [__CLASS__, 'handle_revoke_consent']);
        add_action('wp_ajax_' . self::ACTION_GET_COURSE, [__CLASS__, 'handle_get_course']);
        add_action('wp_ajax_' . self::ACTION_GET_LESSON, [__CLASS__, 'handle_get_lesson']);
        add_action('wp_ajax_' . self::ACTION_MARK_LESSON_OPENED, [__CLASS__, 'handle_mark_lesson_opened']);
        add_action('wp_ajax_' . self::ACTION_MARK_LESSON_COMPLETED, [__CLASS__, 'handle_mark_lesson_completed']);
    }

    public static function handle_get_status(): void {
        self::authorize();
        self::respond(static::resolveEnrollmentUseCase()->get_status());
    }

    public static function handle_enroll(): void {
        self::authorize();
        self::respond(static::resolveEnrollmentUseCase()->enroll());
    }

    public static function handle_unsubscribe(): void {
        self::authorize();
        self::respond(static::resolveEnrollmentUseCase()->unsubscribe());
    }

    public static function handle_get_consent_status(): void {
        self::authorize();
        self::respond(static::resolveConsentUseCase()->get_status());
    }

    public static function handle_accept_consent(): void {
        self::authorize();
        self::respond(static::resolveConsentUseCase()->accept());
    }

    public static function handle_revoke_consent(): void {
        self::authorize();
        self::respond(static::resolveConsentUseCase()->revoke());
    }

    public static function handle_get_course(): void {
        self::authorize();
        self::respond(static::resolveContentUseCase()->get_course());
    }

    public static function handle_get_lesson(): void {
        self::authorize();

        $lesson_key = isset($_POST['lessonKey'])
            ? sanitize_text_field(wp_unslash((string) $_POST['lessonKey']))
            : '';

        self::respond(static::resolveContentUseCase()->get_lesson($lesson_key));
    }

    public static function handle_mark_lesson_opened(): void {
        self::authorize();
        self::respond(static::resolveProgressUseCase()->mark_opened(self::read_lesson_key()));
    }

    public static function handle_mark_lesson_completed(): void {
        self::authorize();
        self::respond(static::resolveProgressUseCase()->mark_completed(self::read_lesson_key()));
    }

    /**
     * Reads only lessonKey from the AJAX request. Ignores identity and quiz fields.
     */
    private static function read_lesson_key(): string {
        return isset($_POST['lessonKey'])
            ? sanitize_text_field(wp_unslash((string) $_POST['lessonKey']))
            : '';
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code'    => 'training_forbidden',
                'message' => 'Permisos insuficientes.',
            ], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');
    }

    /**
     * Maps use-case result to a single WP AJAX envelope (no nested success/data).
     *
     * @param array{success?: bool, data?: array<string,mixed>, error?: array{code?: string, message?: string}} $result
     */
    private static function respond(array $result): void {
        if (!empty($result['success'])) {
            $data = isset($result['data']) && is_array($result['data']) ? $result['data'] : [];
            wp_send_json_success($data);
        }

        $error   = isset($result['error']) && is_array($result['error']) ? $result['error'] : [];
        $code    = isset($error['code']) && is_string($error['code']) && $error['code'] !== ''
            ? $error['code']
            : 'training_backend_error';
        $message = isset($error['message']) && is_string($error['message'])
            ? $error['message']
            : '';

        wp_send_json_error([
            'code'    => $code,
            'message' => $message,
        ]);
    }

    protected static function resolveEnrollmentUseCase(): TrainingEnrollmentUseCase {
        return new TrainingEnrollmentUseCase();
    }

    protected static function resolveConsentUseCase(): TrainingConsentUseCase {
        return new TrainingConsentUseCase();
    }

    protected static function resolveContentUseCase(): TrainingContentUseCase {
        return new TrainingContentUseCase();
    }

    protected static function resolveProgressUseCase(): TrainingProgressUseCase {
        return new TrainingProgressUseCase();
    }
}
