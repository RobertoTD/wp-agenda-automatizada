<?php
/**
 * AC — agenda magic-link token format (C2 / C1 base64url length).
 *
 *   php tests/domain/auth/test-agenda-access-token-format-ac.php
 *
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$root = dirname(__DIR__, 3);
require_once $root . '/includes/domain/auth/class-aa-agenda-access-token-format.php';

$passed = 0;
$total  = 0;

function ac(string $label, bool $ok, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($ok) {
        $passed++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
}

$valid = str_repeat('A', 43);
ac('exact 43 base64url accepted', AA_Agenda_Access_Token_Format::is_valid($valid));
ac('sanitize keeps valid', AA_Agenda_Access_Token_Format::sanitize('  ' . $valid . '  ') === $valid);

ac('too short rejected', !AA_Agenda_Access_Token_Format::is_valid(str_repeat('A', 42)));
ac('too long rejected', !AA_Agenda_Access_Token_Format::is_valid(str_repeat('A', 44)));
ac('invalid charset rejected', !AA_Agenda_Access_Token_Format::is_valid(str_repeat('+', 43)));
ac('empty rejected', AA_Agenda_Access_Token_Format::sanitize('') === '');
ac('LENGTH constant is 43', AA_Agenda_Access_Token_Format::LENGTH === 43);

echo "\n{$passed}/{$total} passed\n";
exit($passed === $total ? 0 : 1);
