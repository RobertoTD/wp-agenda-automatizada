<?php
/**
 * Shell access decision constants (free by default; subscribed only when
 * the backend conclusively confirms subscription_active).
 */

defined('ABSPATH') or die('No direct access');

final class AA_Shell_Access {

    public const ACCESS_FREE       = 'free';
    public const ACCESS_LEGAL_GATE = 'legal_gate';
    public const ACCESS_FULL       = 'full';

    /** Admitted on the result type; bootstrap never emits it (sync resolve). */
    public const REASON_PENDING = 'pending';

    public const REASON_MISSING_CREDENTIALS  = 'missing_credentials';
    public const REASON_INSTALLATION_MISSING = 'installation_missing';
    public const REASON_NO_SUBSCRIPTION      = 'no_subscription';
    public const REASON_CREDENTIALS_INVALID  = 'credentials_invalid';
    public const REASON_TRANSPORT_ERROR      = 'transport_error';
    public const REASON_DOCUMENTS_PENDING    = 'documents_pending';
    public const REASON_DOCUMENTS_ACCEPTED   = 'documents_accepted';
    public const REASON_UNKNOWN              = 'unknown';

    /** @var list<string> */
    public const LEGAL_PENDING_STATUSES = [
        'needs_terms',
        'needs_privacy_and_terms',
        'privacy_required',
        'provisioning_request_missing',
    ];
}
