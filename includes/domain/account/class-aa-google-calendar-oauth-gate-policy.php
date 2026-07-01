<?php
/**
 * Google Calendar OAuth gate policy.
 *
 * Pure rule: whether to show Freemium consent before opening OAuth on first connect.
 * Does not read WordPress options — callers pass facts.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Google_Calendar_Oauth_Gate_Policy {

    /**
     * True when the installation is not yet linked to a DEOIA account (no HMAC secret).
     *
     * @param bool $has_client_secret Whether aa_client_secret is stored locally.
     */
    public static function requires_freemium_consent_before_oauth(bool $has_client_secret): bool {
        return !$has_client_secret;
    }
}
