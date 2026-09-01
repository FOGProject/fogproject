<?php
/**
 * Pins the mass-edit endpoint's gates, in order.
 *
 * A handler that edits an arbitrary set of hosts in one statement is the
 * shape where a missing check is worth the most: the same request that
 * changes twelve hosts changes four hundred, and a scope bound that is
 * merely LATE is a scope bound that never ran on the request that mattered.
 *
 * Four gates, and the order between them is the assertion, not just their
 * presence:
 *
 *   1. checkAuthAndCSRF() -- saveGroup was reachable with a session cookie
 *      alone, from any origin, because the router's CSRF gate keys off a
 *      *Post/*Ajax suffix and that method had neither (ADR 0038 decision
 *      16a). This one is named massEditPost, so the router covers it too,
 *      and the explicit call is the belt to that braces.
 *   2. requirePageObjectScopeMass('host', ...) -- BEFORE any write. One id
 *      outside the caller's sites denies the whole request rather than
 *      quietly editing the rest.
 *   3. The resolution runs through MassEdit, which fails closed. The
 *      endpoint must not do its own reading of the posted actions.
 *   4. columnUpdates() is given the CORE spec, which is the whitelist. A
 *      key the spec does not name cannot reach a column whatever the
 *      request says, and passing the merged core+plugin map here instead
 *      would hand plugin keys direct write access to `hosts`.
 *
 * Plus the two invariants ADR 0021 decision 11 fixes for a bulk action: one
 * audit header rather than one per host, and a guard against issuing an
 * UPDATE with no assignments.
 *
 * Source-anchored, for the reason set out in
 * tests/task-creation-resolves-assignments.test.php: MassEdit's behavior is
 * covered directly by tests/mass-edit-fails-closed.test.php, and reaching
 * massEditPost() needs a configured FOG, a session, a CSRF token and a
 * schema, at which point the test is an install rehearsal rather than a
 * gate. What is left is exactly what source text can answer -- is the check
 * there, and is it before the write.
 *
 * Usage: php tests/mass-edit-endpoint-is-gated.test.php
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

$root = dirname(__DIR__);
$path = $root . '/packages/web/src/Pages/HostManagement.php';
$source = @file_get_contents($path);
if (false === $source) {
    fwrite(STDERR, "FAIL: cannot read $path\n");
    exit(1);
}
$source = (string)$source;

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

/**
 * The body of one method, so an assertion about what massEditPost() does
 * cannot be satisfied by an identical line in a neighboring handler -- of
 * which this file has many, several carrying the very calls being checked.
 */
$methodBody = static function ($src, $signature) {
    $start = strpos($src, $signature);
    if (false === $start) {
        return null;
    }
    $open = strpos($src, '{', $start);
    if (false === $open) {
        return null;
    }
    $depth = 0;
    $len = strlen($src);
    for ($i = $open; $i < $len; $i++) {
        if ('{' === $src[$i]) {
            $depth++;
        } elseif ('}' === $src[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($src, $open, $i - $open + 1);
            }
        }
    }
    return null;
};

$body = $methodBody($source, 'public function massEditPost()');
$check('massEditPost() is still findable', null !== $body);
if (null === $body) {
    fwrite(STDERR, "FAIL: massEditPost() moved or was renamed; this test is\n");
    fwrite(STDERR, "      pinning nothing until its signature is updated.\n");
    exit(1);
}

$at = static function ($needle) use ($body) {
    return strpos($body, $needle);
};

$auth = $at('self::checkAuthAndCSRF();');
$scope = $at("Authorization::requirePageObjectScopeMass('host', \$hosts);");
$resolve = $at('$resolved = MassEdit::resolve(');
$write = $at("->update(['id' => \$hosts], '', \$updates);");
$hook = $at("'HOST_MASSEDIT_APPLY',");

$check('it checks auth and CSRF', false !== $auth);
$check('it bounds the hosts to the caller\'s site scope', false !== $scope);
$check('it resolves through MassEdit', false !== $resolve);
$check('it writes the host columns in one statement', false !== $write);

// The ORDER is the point. A scope check after the write is not a scope
// check; it is an audit trail for damage already done.
$check(
    'the scope bound comes before the write',
    false !== $scope && false !== $write && $scope < $write
);
$check(
    'the scope bound comes before the plugin apply hook',
    false !== $scope && false !== $hook && $scope < $hook
);
$check(
    'auth and CSRF come before everything',
    false !== $auth && false !== $scope && $auth < $scope
);

// The whitelist. columnUpdates() must be handed the CORE spec: handing it
// the merged core+plugin map would let a plugin key write straight into a
// `hosts` column, which is exactly what HOST_MASSEDIT_APPLY exists to avoid.
$check(
    'only core fields can reach a hosts column',
    false !== strpos(
        $body,
        'MassEdit::columnUpdates($resolved, $coreFields)'
    )
);
$check(
    'plugins get their own keys only, already resolved',
    false !== strpos($body, '$forPlugins = array_intersect_key(')
);

// An UPDATE with no assignments is either a syntax error or a statement
// whose WHERE is the only part left. The guard is not decoration.
$check(
    'the write is guarded on there being something to write',
    false !== strpos($body, 'if (count($updates) > 0) {')
);
// A submission that changes nothing is refused rather than reported as a
// success: a form that says "12 hosts updated" having changed nothing is
// how somebody concludes the edit landed and moves on.
$check(
    'a submission that touches no field is refused',
    false !== strpos($body, 'if (count($touched) < 1) {')
);

// ADR 0021 decision 11: ONE header for one authorized action, with
// affectedCount -- never a header per host.
$check(
    'it records exactly one audit header',
    1 === substr_count($body, 'Audit::record(')
);
$check(
    'the audit header carries affectedCount',
    false !== strpos($body, "'affectedCount' => \$affected,")
);
$check(
    'the audit record is not inside a loop over hosts',
    false === strpos($body, 'foreach ($hosts')
);

// The endpoint must not re-read the posted actions itself: MassEdit is the
// only thing that decides what an action means, and a second reader is how
// the sentinel comes back.
$check(
    'the endpoint does not interpret posted actions itself',
    false === stripos($body, "'NULL'")
        && false === strpos($body, "strcasecmp")
);

if (count($failures)) {
    fwrite(STDERR, "FAIL: the mass-edit endpoint lost a gate:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  mass-edit endpoint is gated: %d checks\n", $checks);
exit(0);
