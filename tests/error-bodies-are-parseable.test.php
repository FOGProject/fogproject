<?php
/**
 * An error response carries a body a client can actually read.
 *
 * `breakHead()` echoes whatever `sendResponse()` was given, under a
 * Content-Type of `application/json`. For most of this router's history the
 * caught-exception path handed it `$e->getMessage()` -- a bare sentence --
 * so the body was a document no client could parse. jQuery leaves
 * `responseJSON` undefined, `notifyFromAPI()` falls into its
 * unreadable-response guard, and the user is told "The server answered 406
 * with no readable message" about a message that was right there.
 *
 * It was invisible because it degrades rather than breaks: something is
 * shown, it is just never the thing that would help. ADR 0031 made it
 * matter -- a refused delete is a routine, expected answer an admin needs to
 * read, not an exceptional one.
 *
 * `{"error": "..."}` is not a new shape. Five sendResponse() call sites in
 * Route already build exactly it, and fog.common.js reads
 * `xhr.responseJSON.error` first in the DataTables error handler. It is also
 * what makes the toast red: notifyFromAPI() types a body carrying `msg` as a
 * SUCCESS, which is never right for something reaching this path.
 *
 * The subtle half, and the reason this is driven rather than grepped:
 * sendResponse() does not always emit. With a result wrapper on the stack
 * (ADR 0011) it re-raises instead, and it does that by putting the BODY into
 * a RuntimeException's MESSAGE. So an inner refusal that built its own JSON
 * reaches _sendCaught() as a message that is really a document. Wrapping
 * that again buries every field inside a string. Both directions are pinned
 * below.
 *
 * Usage: php tests/error-bodies-are-parseable.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('error-bodies');

$t = new FogChecks();

// Raised so sendResponse() re-raises instead of ending the process, which is
// the only way to see what it was handed. The RuntimeException carries the
// body as its message and the status as its code.
FogTestHarness::setStatic('Route', '_rethrowDepth', 1);

$send = static function (\Exception $e) {
    $m = new \ReflectionMethod('FOG\Router\Route', '_sendCaught');
    $m->setAccessible(true);
    try {
        $m->invoke(null, $e);
    } catch (\RuntimeException $r) {
        return $r;
    }
    return null;
};

// --- a plain sentence is wrapped --------------------------------------------
$raised = $send(new \Exception('Cannot delete this storage group.', 409));
$t->check(
    'a caught exception still ends the response',
    null !== $raised
);
$body = null === $raised ? null : json_decode($raised->getMessage(), true);
$t->check(
    'the body parses as JSON -- the whole point',
    is_array($body)
);
$t->check(
    'the message is under `error`, which is what the UI reads and what '
    . 'colors the toast as a failure',
    is_array($body)
    && ($body['error'] ?? null) === 'Cannot delete this storage group.'
);
$t->check(
    'the body is not the bare sentence any more',
    null !== $raised && $raised->getMessage() !== 'Cannot delete this storage group.'
);
$t->check(
    'the status still rides along',
    null !== $raised && 409 === $raised->getCode()
);

// --- an already-encoded body is passed through ------------------------------
/*
 * _assertFilterKeys() sends {error, valid}: the refusal AND the list of
 * fields the caller could have used. Under a result wrapper that whole
 * document arrives here as an exception message. Re-wrapping it would leave
 * `valid` stringified inside `error`, so the caller keeps the refusal and
 * silently loses the half that says what to do instead.
 */
$inner = json_encode(
    ['error' => 'Unknown filter field(s)', 'valid' => ['name', 'ip']]
);
$raised = $send(new \Exception($inner, 400));
$body = null === $raised ? null : json_decode($raised->getMessage(), true);
$t->check(
    'an already-encoded body is not wrapped a second time',
    is_array($body) && ($body['error'] ?? null) === 'Unknown filter field(s)'
);
$t->check(
    'so the fields beside `error` survive',
    is_array($body) && ($body['valid'] ?? null) === ['name', 'ip']
);

// A message that merely looks numeric is NOT a body and must still be
// wrapped -- it decodes to an int, not an array, which is why the test is on
// shape rather than on whether json_decode succeeded.
$raised = $send(new \Exception('404', 400));
$body = null === $raised ? null : json_decode($raised->getMessage(), true);
$t->check(
    'a message that happens to be valid JSON scalar is still wrapped',
    is_array($body) && ($body['error'] ?? null) === '404'
);

/*
 * No sendResponse() call anywhere may hand over a bare message again.
 *
 * The gate that keeps this from rotting back: every call site either sends
 * no body, or sends something built by json_encode(). A future caller
 * passing _('...') straight through re-opens the exact hole, in one line,
 * with nothing else to notice it -- the response still works, it is just
 * unreadable again.
 */
$sources = array_merge(
    glob(dirname(__DIR__) . '/packages/web/src/*/*.php') ?: [],
    glob(dirname(__DIR__) . '/packages/web/lib/*/*.php') ?: []
);
$bare = [];
foreach ($sources as $file) {
    $src = file_get_contents($file);
    // The message argument spans lines, so match the call and take the two
    // lines after the status code.
    if (!preg_match_all(
        '#sendResponse\(\s*\n\s*[^\n]*\n(\s*[^\n]*\n\s*[^\n]*)#',
        $src,
        $m
    )) {
        continue;
    }
    foreach ($m[1] as $tail) {
        // A call with no message closes immediately after the code.
        if (preg_match('#^\s*\)\s*;#', $tail)) {
            continue;
        }
        if (false !== strpos($tail, 'json_encode')
            || false !== strpos($tail, '$msg')
            || false !== strpos($tail, '$body')
        ) {
            continue;
        }
        if (preg_match("#_\(#", $tail)) {
            $bare[] = basename($file) . ': ' . trim(explode("\n", $tail)[0]);
        }
    }
}
$t->check(
    'no sendResponse() passes a bare translated string as the body ('
    . (count($bare) ? implode('; ', $bare) : 'none') . ')',
    $bare === []
);

/*
 * A message carrying a byte JSON cannot represent must not vanish.
 *
 * json_encode() returns FALSE rather than throwing, and sendResponse() reads
 * false as "send no body" -- so without JSON_INVALID_UTF8_SUBSTITUTE one raw
 * byte turns a real error into an EMPTY response, which is worse than the
 * bare string this whole change replaces: the caller gets a status and
 * nothing else, and there is no log line saying why. A database error
 * quoting a binary column value is how it arrives.
 */
$raised = $send(new \Exception("Duplicate entry '" . chr(0xB5) . "' for key", 500));
// Not merely "non-empty": with encoding failed, sendResponse() falls back
// to the status code as the exception message, so the body is the string
// "500" and an emptiness test would pass while the message was gone.
$t->check(
    'a message with an unrepresentable byte still produces a real body',
    null !== $raised
    && (string)$raised->getMessage() !== (string)$raised->getCode()
);
$body = null === $raised ? null : json_decode($raised->getMessage(), true);
$t->check(
    'that body still parses, and keeps the readable part of the message',
    is_array($body)
    && false !== strpos((string)($body['error'] ?? ''), 'Duplicate entry')
    && false !== strpos((string)($body['error'] ?? ''), 'for key')
);

$t->finish();
