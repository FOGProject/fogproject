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
// The call is wrapped in `if (!...)` now, so the statement no longer ends in
// `;` right after the arguments -- the write itself is unchanged, and this
// anchors on the arguments rather than on the punctuation around them.
$write = $at("->update(['id' => \$hosts], '', \$updates)");
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

// --- The whitelist itself -------------------------------------------------
//
// massEditCoreFields() is what decides which columns a mass edit can reach,
// so the shape of its entries is a gate too: a spec missing `field` is
// silently skipped by columnUpdates(), and a spec missing `empty` clears an
// int column to '' -- which stores 0 on a permissive server and errors on a
// strict one. Neither shows up as a failing request.
$spec = $methodBody($source, 'private function massEditCoreFields()');
$check('massEditCoreFields() is still findable', null !== $spec);
$spec = (string)$spec;

// Every column ADR 0038's disposition table sends to mass edit. Named one by
// one rather than counted, because "there are 14 of them" stays green when
// the wrong 14 are present.
$expected = [
    'image', 'kernel', 'kernelArgs', 'kernelDevice', 'init', 'biosexit',
    'efiexit', 'productKey', 'printerLevel', 'useAD', 'enforce', 'ADDomain',
    'ADOU', 'ADUser', 'ADPass',
];
$missing = [];
foreach ($expected as $key) {
    if (false === strpos($spec, "'" . $key . "' => [")) {
        $missing[] = $key;
    }
}
$check(
    'the whitelist carries every column ADR 0038 sends to mass edit ('
    . implode(', ', $missing) . ')',
    0 === count($missing)
);

// hostBuilding is copied by the persistentgroups trigger and written by
// nothing. It must not be revived by being listed in a new form.
$check(
    'the dead `building` column is not in the whitelist',
    false === strpos($spec, "'building'")
);

// Each entry must be complete. Counting the parts is what catches an entry
// added by copy-paste with a key renamed and an `empty` left behind.
$entries = preg_match_all("/'[A-Za-z]+' => \[/", $spec);
$fields = substr_count($spec, "'field' =>");
$empties = substr_count($spec, "'empty' =>");
$labels = substr_count($spec, "'label' =>");
$kinds = substr_count($spec, "'kind' =>");
$check(
    'every whitelist entry declares field, empty, label and kind',
    $entries > 0
        && $fields === $entries
        && $empties === $entries
        && $labels === $entries
        && $kinds === $entries
);

// The credential fields must be marked, because the form reads `secret` to
// decide it has no read path. ADPass and productKey both match
// Redaction::CREDENTIAL_PATTERN, so an unmarked one would be rendered back
// into a form that is editing hundreds of hosts at once.
$check(
    'the AD password is marked secret',
    1 === preg_match(
        "/'ADPass' => \[[^\]]*'secret' => true/s",
        $spec
    )
);
$check(
    'the product key is marked secret',
    1 === preg_match(
        "/'productKey' => \[[^\]]*'secret' => true/s",
        $spec
    )
);

// No 32-asterisk placeholder anywhere near the mass edit. That is the group
// page's pattern (GroupManagement.php:878) and ADR 0038 decision 11 rejects
// it: a fake value rendered into a form has to be matched back out at every
// call site that ever touches it.
$check(
    'the mass edit does not use the 32-asterisk password placeholder',
    false === strpos($spec, '*{32}')
        && false === strpos($body, '*{32}')
);

// --- The row-backed half --------------------------------------------------
//
// Auto-logout is not a `hosts` column; it is one row per host in its own
// table, written delete-then-insert. The row fields must be resolved
// SEPARATELY from the column fields, so a row key can never reach
// columnUpdates().
$rows = $methodBody($source, 'private function massEditRowFields()');
$check('massEditRowFields() is still findable', null !== $rows);
$rows = (string)$rows;
$check(
    'the row-backed fields include auto-logout',
    false !== strpos($rows, "'autologout' => [")
);

$rowKeys = strpos($body, 'massEditRowFields()');
$colUpdates = strpos($body, 'columnUpdates(');
$applyRows = strpos($body, 'massEditApplyRows(');
$check('the endpoint asks for the row-backed fields', false !== $rowKeys);
$check('the endpoint applies the row-backed half', false !== $applyRows);

// The load-bearing one: columnUpdates() is handed $coreFields, and the row
// fields are never merged into the key list it resolves from. If a row key
// ever reached the column spec the write would be an UPDATE against a column
// that does not exist.
$check(
    'row-backed keys are never merged into the column key list',
    1 === preg_match(
        '/\$keys = array_values\(\s*array_unique\(\s*array_merge\(\s*'
        . 'array_keys\(\$coreFields\),\s*array_keys\(\$pluginFields\)/s',
        $body
    )
);

// Scope is checked before the row-backed write too, not only before the
// UPDATE. Two separate write paths, one boundary.
$check(
    'the row-backed write happens after the scope check',
    false !== $scope && false !== $applyRows && $scope < $applyRows
);

// A row-backed-only submission must still count as work. Without this the
// "nothing was set to change" refusal fires on a row-backed-only edit and
// the operator is told their submission was empty when it was not.
$check(
    'the touched list includes the row-backed instructions',
    1 === preg_match(
        '/\$touched = array_merge\(\s*MassEdit::touched\(\$resolved\),'
        . '\s*MassEdit::touched\(\$resolvedRows\)/s',
        $body
    )
);

$apply = $methodBody($source, 'private function massEditApplyRows(');
$check('massEditApplyRows() is still findable', null !== $apply);
$apply = (string)$apply;

// CLEAR is the delete with no insert -- no row IS the absence of an override.
// The tell that this is wrong is an insert that runs unconditionally, which
// would turn CLEAR into "set to zero" for auto-logout.
$check(
    'the row arm inserts only on SET',
    1 === substr_count($apply, 'MassEdit::SET === ')
        && 1 === substr_count($apply, 'insertBatch(')
);
$check(
    'the row arm deletes on both SET and CLEAR',
    1 === substr_count($apply, 'Route::deletemass(')
        && 1 === substr_count($apply, 'MassEdit::LEAVE !== ')
);

// One statement per field regardless of selection size, same reason the
// column half is one UPDATE. A per-host loop here is the shape ADR 0038
// decision 4 is about.
$check(
    'the row arm does not write one host at a time',
    false === strpos($apply, '->save()')
        && false === strpos($apply, 'foreach ($resolved')
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
