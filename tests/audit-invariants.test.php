<?php
/**
 * The audit trail's structural promises, pinned.
 *
 * Four things ADR 0021 decided that are easy to undo by accident, because
 * undoing any of them looks like ordinary tidying and none of them fails
 * loudly:
 *
 *   1. A DELETE WRITES NO CHANGE ROWS. Decision 7, and the sharpest of the
 *      HARD constraints: a delete has a "before" for every column, so uniform
 *      before/after recording would dump every credential a host carries --
 *      ADPass, ADPassLegacy, productKey, sec_tok, prev_sec_tok -- into a
 *      table built to be read. The header says what was destroyed and by
 *      whom; a per-column inventory of a deleted host is a credential dump
 *      wearing an audit badge. This is enforced by destroy() simply not
 *      calling the writer, which is exactly the kind of absence somebody
 *      "fixes" for symmetry.
 *   2. VALUES GO THROUGH Redaction. A caller that filters for itself is how
 *      a credential reaches a log, twice in one week, in two subsystems
 *      (58483d6 and #1261/#1262). So Audit::changes() must be the only
 *      construction site for an AuditChange, and it must consult Redaction.
 *   3. NEITHER TABLE HAS A WRITE ROUTE. Decision 8. FOG cannot promise
 *      tamper-proof -- the web tier holds GRANT ALL -- but it can promise
 *      that nothing in its own API edits or deletes an audit row. Adding
 *      either class to Route::$validClasses would hand out generic CRUD on
 *      both, silently, because that list is what generates the routes.
 *   4. A LOG TABLE DOES NOT LOG ITSELF. save() and destroy() call
 *      logHistory(); without the guard every audited action would also write
 *      a history row, doubling the volume of the table audit exists to
 *      replace.
 *
 * Textual, and DB-free, because all four are properties of the source.
 *
 * Usage: php tests/audit-invariants.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
chdir($root);

$failures = [];
$checks = 0;

/**
 * The source of one method, from its signature to the next one.
 *
 * Crude on purpose: a brace matcher would be more precise and would also be
 * the thing that breaks. The next `    public function` / `    private
 * function` at class indent is a reliable enough end marker for the two
 * methods this test reads.
 *
 * @param string $src  the file source
 * @param string $sig  the signature to find, e.g. 'public function destroy('
 *
 * @return string|null
 */
function methodBody($src, $sig)
{
    $start = strpos($src, $sig);
    if (false === $start) {
        return null;
    }
    $after = $start + strlen($sig);
    $next = preg_match(
        '#\n    (?:public|private|protected) function #',
        $src,
        $m,
        PREG_OFFSET_CAPTURE,
        $after
    ) ? $m[0][1] : strlen($src);

    return substr($src, $start, $next - $start);
}

$controller = (string) @file_get_contents(
    $web . '/lib/fog/fogcontroller.class.php'
);
$audit = (string) @file_get_contents($web . '/lib/fog/audit.class.php');
$route = (string) @file_get_contents($web . '/lib/router/route.class.php');

foreach ([$controller, $audit, $route] as $needed) {
    if ('' === $needed) {
        fwrite(STDERR, "FAIL: could not read a source file\n");
        exit(1);
    }
}

/*
 * 1. destroy() writes no change rows, and save() does.
 */
$destroy = methodBody($controller, 'public function destroy(');
$save = methodBody($controller, 'public function save()');

$checks++;
if (null === $destroy || null === $save) {
    $failures[] = 'could not locate FOGController::save()/destroy(); this '
        . 'test cannot check what it cannot find';
} else {
    $checks++;
    if (false !== strpos($destroy, '_auditChanges')
        || false !== strpos($destroy, 'Audit::changes')
    ) {
        $failures[] = 'FOGController::destroy() writes auditChange rows. A '
            . 'delete has a before for EVERY column, so this puts every '
            . 'credential a host carries into the audit table (ADR 0021 '
            . 'Decision 7). The header already records what was destroyed.';
    }
    $checks++;
    if (false === strpos($save, '_auditChanges')) {
        $failures[] = 'FOGController::save() no longer writes auditChange '
            . 'rows, so the audit trail records that something changed and '
            . 'never what.';
    }
}

/*
 * 2. Audit::changes() is the only construction site, and it redacts.
 */
$files = array_filter(
    explode("\n", (string) shell_exec('git ls-files "*.php"')),
    function ($f) {
        return '' !== $f
            && is_readable($f)
            && 0 !== strpos($f, 'packages/web/vendor/')
            && 0 !== strpos($f, 'tests/');
    }
);
$constructors = [];
foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    if (preg_match_all(
        '#(?:getClass\(\s*[\'"]AuditChange[\'"]|new\s+AuditChange\b)#',
        $src,
        $m
    )) {
        $constructors[$file] = count($m[0]);
    }
}
$checks++;
$allowed = 'packages/web/lib/fog/audit.class.php';
$stray = array_diff_key($constructors, [$allowed => true]);
if (count($stray)) {
    $failures[] = 'AuditChange is constructed outside Audit::changes(), in: '
        . implode(', ', array_keys($stray))
        . '. Every change row must go through the one writer that consults '
        . 'Redaction, or a caller decides for itself which values are safe '
        . 'to store -- which is how 58483d6 and #1261/#1262 happened.';
}
$checks++;
if (!isset($constructors[$allowed])) {
    $failures[] = 'Audit::changes() no longer constructs an AuditChange; the '
        . 'detail half of the audit trail has gone.';
}

$changes = methodBody($audit, 'public static function changes(');
$checks++;
if (null === $changes || false === strpos($changes, 'Redaction::values')) {
    $failures[] = 'Audit::changes() does not call Redaction::values(). '
        . 'Without it every credential a changed object carries is written '
        . 'to auditChange in clear.';
}

/*
 * 3. No write routes onto either table.
 *
 * Read from Route's source rather than the class, so this needs no boot: the
 * property is a literal list and adding to it is what would create the
 * routes.
 */
if (preg_match(
    '#public static \$validClasses = \[(.*?)\];#s',
    $route,
    $m
)) {
    preg_match_all('#[\'"](\w+)[\'"]#', $m[1], $classes);
    foreach (['auditlog', 'auditchange'] as $forbidden) {
        $checks++;
        if (in_array($forbidden, $classes[1], true)) {
            $failures[] = "Route::\$validClasses contains '$forbidden'. That "
                . 'list generates ten generic operations per class, create, '
                . 'update and delete among them, so this hands out an edit '
                . 'route onto the audit trail (ADR 0021 Decision 8).';
        }
    }
    $checks++;
    if (count($classes[1]) < 30) {
        $failures[] = 'parsed only ' . count($classes[1]) . ' entries from '
            . 'Route::$validClasses; the scan is broken, not the list';
    }
} else {
    $checks++;
    $failures[] = 'could not read Route::$validClasses';
}

/*
 * 4. The log-table guard names all three tables.
 */
$isLog = methodBody($controller, 'private function _isLogTable(');
foreach (['History', 'AuditLog', 'AuditChange'] as $cls) {
    $checks++;
    if (null === $isLog || false === strpos($isLog, $cls)) {
        $failures[] = "FOGController::_isLogTable() does not cover $cls, so "
            . 'writing one of its rows also writes a history row about '
            . 'having written it.';
    }
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks audit invariant(s) hold\n";
exit(0);
