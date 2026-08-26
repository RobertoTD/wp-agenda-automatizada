<?php
/**
 * Resolves and validates the WP user for an agenda magic-link consume result.
 *
 * Fail-closed: id/email mismatch, missing membership, or missing capability ⇒ reject.
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('No direct access');

final class AA_Agenda_Access_User_Policy {

    public const CODE_OK = 'ok';
    public const CODE_USER_NOT_FOUND = 'user_not_found';
    public const CODE_EMAIL_MISMATCH = 'email_mismatch';
    public const CODE_NOT_MEMBER = 'not_member';
    public const CODE_CAPABILITY = 'capability';
    public const CODE_INVALID_PAYLOAD = 'invalid_payload';

    /**
     * @param array{email?: mixed, wp_user_id?: mixed} $payload Backend 200 body fields.
     * @param int                                       $blog_id Current blog id.
     * @return array{ok: true, user_id: int}|array{ok: false, code: string}
     */
    public function resolve(array $payload, int $blog_id): array {
        $email = $this->canonicalize_email($payload['email'] ?? null);
        if ($email === '') {
            return ['ok' => false, 'code' => self::CODE_INVALID_PAYLOAD];
        }

        $wp_user_id = $this->normalize_wp_user_id($payload['wp_user_id'] ?? null);

        if ($wp_user_id !== null) {
            $user = get_user_by('id', $wp_user_id);
            if (!$user instanceof WP_User) {
                return ['ok' => false, 'code' => self::CODE_USER_NOT_FOUND];
            }
            if ($this->canonicalize_email($user->user_email) !== $email) {
                return ['ok' => false, 'code' => self::CODE_EMAIL_MISMATCH];
            }
        } else {
            $user = get_user_by('email', $email);
            if (!$user instanceof WP_User) {
                return ['ok' => false, 'code' => self::CODE_USER_NOT_FOUND];
            }
        }

        $user_id = (int) $user->ID;
        if ($user_id <= 0) {
            return ['ok' => false, 'code' => self::CODE_USER_NOT_FOUND];
        }

        if ($blog_id > 0 && function_exists('is_user_member_of_blog')) {
            if (!is_user_member_of_blog($user_id, $blog_id)) {
                return ['ok' => false, 'code' => self::CODE_NOT_MEMBER];
            }
        }

        if (!user_can($user_id, 'manage_options')) {
            return ['ok' => false, 'code' => self::CODE_CAPABILITY];
        }

        return ['ok' => true, 'user_id' => $user_id];
    }

    /**
     * @param mixed $value
     */
    private function canonicalize_email($value): string {
        if (!is_string($value)) {
            return '';
        }
        $email = strtolower(trim($value));
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }
        return $email;
    }

    /**
     * @param mixed $value
     */
    private function normalize_wp_user_id($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value) && !ctype_digit($value)) {
            return null;
        }
        $n = (int) $value;
        return $n > 0 ? $n : null;
    }
}
