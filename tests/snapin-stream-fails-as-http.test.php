<?php
/**
 * A failed snapin download fails as a download, not as a 200 with prose.
 *
 * snapins.file.php streams the snapin binary. FOGClient's constructor catches
 * every exception and prints the message, and nothing on that path ever set an
 * HTTP status -- so a storage-node or FTP failure answered 200 with
 * "cannot connect to the storage node" AS THE FILE. The client saved that,
 * hashed it, and reported "Hash does not match" with the same wrong SHA-512
 * every time, which reads as a corrupt snapin forever (forum 18253, found by a
 * missing /home/fogproject breaking vsftpd's chdir).
 *
 * So every coded failure on the download path goes through _fail(), which sets
 * the status before it throws. The status itself cannot be observed from the
 * CLI -- http_response_code() needs headers that are not yet sent, and the
 * suite has already written to stdout -- so what is pinned here is that no
 * throw on that path can bypass the helper. That is the property a future edit
 * breaks.
 *
 * Usage: php tests/snapin-stream-fails-as-http.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('snapin-stream-fails-as-http');

use FOG\Agent\Snapins;

$t = new FogChecks();

/*
 * 1. The helper exists, and it carries the caller's code onto the exception
 *    so the REST router (Route::_sendCaught, which reads getCode()) keeps
 *    answering exactly as it did before.
 */
$caught = null;
try {
    FogTestHarness::callStatic(
        Snapins::class,
        '_fail',
        ['cannot connect to the storage node', 503]
    );
} catch (\Throwable $e) {
    // \Throwable, not \RuntimeException: when the helper is absent the
    // harness raises a ReflectionException, and this has to report that as a
    // failed check rather than die with a fatal and no verdict.
    $caught = $e;
}
$t->check('_fail() throws a RuntimeException', $caught instanceof \RuntimeException);
$t->check(
    '_fail() keeps the message',
    $caught && $caught->getMessage() === 'cannot connect to the storage node'
);
$t->check('_fail() keeps the code, for Route::_sendCaught', $caught && 503 === $caught->getCode());

/*
 * 2. The gate. stream() serves the file and _node() is only ever called by
 *    stream(), so a bare coded throw in either one answers 200 with prose
 *    again. Read the real line ranges rather than grepping the whole file,
 *    because the other methods on this class ARE reached through the REST
 *    router, where throwing is correct.
 */
$src = file(dirname(__DIR__) . '/packages/web/src/Agent/Snapins.php');
$t->check('the source is readable', is_array($src) && count($src) > 0);

foreach (['stream', '_node'] as $method) {
    $r = new \ReflectionMethod(Snapins::class, $method);
    $body = implode(
        '',
        array_slice($src, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1)
    );
    $t->check(
        "$method() raises no exception of its own, it calls _fail()",
        false === strpos($body, 'throw new')
    );
    $t->check(
        "$method() routes its failures through _fail()",
        false !== strpos($body, 'self::_fail(')
    );
}

/*
 * 3. _fail() is the only thing that sets a status here. If a second writer
 *    appears, the last one wins and the reason becomes a guess.
 */
$whole = implode('', $src);
$t->check(
    'http_response_code() is called in exactly one place',
    1 === substr_count($whole, 'http_response_code(')
);

$t->finish();
