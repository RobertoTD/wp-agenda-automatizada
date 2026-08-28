<?php
/**
 * Solicita magic-link de agenda vía HMAC (C3B).
 *
 * Una sola llamada a /agenda/access/request; transient anti doble clic por blog.
 * El handler debe haber verificado gate + nonce antes de execute().
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-agenda-access-backend-client.php';

final class RequestAgendaAccessLinkUseCase {

    public const TRANSIENT_TTL_SECONDS = 60;

    public const STATUS_ATTEMPTED = 'attempted';
    public const STATUS_SKIPPED_TRANSIENT = 'skipped_transient';

    /** @var AA_Agenda_Access_Backend_Client */
    private $client;

    /** @var int */
    private $request_calls = 0;

    public function __construct(?AA_Agenda_Access_Backend_Client $client = null) {
        $this->client = $client ?? new AA_Agenda_Access_Backend_Client();
    }

    public static function transient_key(): string {
        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;

        return 'aa_agenda_access_req_' . $blog_id;
    }

    /**
     * @return int Backend request() invocations in this instance (tests).
     */
    public function get_request_call_count(): int {
        return $this->request_calls;
    }

    /**
     * @return array{status: string, hmac_calls: int}
     */
    public function execute(): array {
        $key = self::transient_key();

        if (get_transient($key)) {
            return [
                'status'     => self::STATUS_SKIPPED_TRANSIENT,
                'hmac_calls' => 0,
            ];
        }

        set_transient($key, '1', self::TRANSIENT_TTL_SECONDS);

        $this->request_calls++;
        // Outcome ignored: UI is always neutral; C3A is authoritative.
        $this->client->request();

        return [
            'status'     => self::STATUS_ATTEMPTED,
            'hmac_calls' => $this->request_calls,
        ];
    }
}
