<?php
/**
 * AC Test — CanonicalCreateRecordCommand (SB1-5B2).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-create-record-command-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/application/canonical/CanonicalCreateRecordCommand.php';

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }
    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

try {
    new CanonicalCreateRecordCommand(0, 'T', null);
    ac_assert('Zero container throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Zero container throws', strpos($e->getMessage(), '[invalid_container_id]') === 0);
}

try {
    new CanonicalCreateRecordCommand(-3, 'T', null);
    ac_assert('Negative container throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Negative container throws', strpos($e->getMessage(), '[invalid_container_id]') === 0);
}

try {
    new CanonicalCreateRecordCommand(1, '', null);
    ac_assert('Empty title throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Empty title throws', strpos($e->getMessage(), '[invalid_title]') === 0);
}

try {
    new CanonicalCreateRecordCommand(1, '   ', null);
    ac_assert('Whitespace title throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Whitespace title throws', strpos($e->getMessage(), '[invalid_title]') === 0);
}

$ok200 = new CanonicalCreateRecordCommand(1, str_repeat('á', 200), null);
ac_assert('Title 200 multibyte accepted', $ok200->title() === str_repeat('á', 200));

try {
    new CanonicalCreateRecordCommand(1, str_repeat('a', 201), null);
    ac_assert('Title 201 throws', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Title 201 throws', strpos($e->getMessage(), '[title_too_long]') === 0);
}

$utf = new CanonicalCreateRecordCommand(7, 'Café 日本語', " line1\nline2 ");
ac_assert('UTF-8 title preserved', $utf->title() === 'Café 日本語');
ac_assert('Details keep internal newlines', $utf->details() === "line1\nline2");
ac_assert('Container id preserved', $utf->container_id() === 7);

$empty_details = new CanonicalCreateRecordCommand(1, 'Reg', '');
ac_assert('Empty details → null', $empty_details->details() === null);

$ws_details = new CanonicalCreateRecordCommand(1, 'Reg', "  \n  ");
ac_assert('Whitespace-only details → null', $ws_details->details() === null);

ac_assert('MAX_TITLE_LENGTH is 200', CanonicalCreateRecordCommand::MAX_TITLE_LENGTH === 200);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
