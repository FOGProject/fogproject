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
 *   5. A CHANGE ROW NAMES ITS SUBJECT. acSubjectLabel is denormalized at
 *      write time for the reason history's hSubjectLabel is: resolved at
 *      read time it goes blank the day the subject is deleted. The chain is
 *      four links -- the manifest column, the model's field map, the writer
 *      setting it, the page selecting it -- and breaking any one of them
 *      degrades silently to the `setting#496` this replaced, because every
 *      reader falls back to type#id by design.
 *
 * Textual, and DB-free, because all five are properties of the source.
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
    $web . '/src/Base/FOGController.php'
);
$audit = (string) @file_get_contents($web . '/src/Audit/Audit.php');
$route = (string) @file_get_contents($web . '/src/Router/Route.php');

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
$allowed = 'packages/web/src/Audit/Audit.php';
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
 * 2b. The subject label survives end to end.
 *
 * Every link is checked rather than just the writer, because the failure is
 * silent at each of them: a row with no label renders as type#id, which is
 * precisely the unreadable state the column was added to end. A settings
 * edit is the worst case and the reason it exists -- globalSettings has one
 * editable column, so `field` reads `value` for every setting in the install
 * and the key is the only identifying part there is.
 */
$checks++;
if (null === $changes || false === strpos($changes, "->set('subjectLabel'")) {
    $failures[] = 'Audit::changes() does not set subjectLabel on the change '
        . 'row, so every row falls back to type#id and a settings edit reads '
        . '`value | old | new` naming no setting.';
}
$checks++;
$acModel = (string) file_get_contents($web . '/src/Items/AuditChange.php');
if (false === strpos($acModel, "'subjectLabel' => 'acSubjectLabel'")) {
    $failures[] = 'AuditChange does not map subjectLabel to acSubjectLabel. '
        . 'set() on an unmapped key is silently dropped by the ORM, so the '
        . 'writer above would store nothing and report success.';
}
$checks++;
$manifest = (string) file_get_contents($web . '/commons/schema-expected.php');
if (false === strpos($manifest, "'acSubjectLabel' =>")) {
    $failures[] = 'schema-expected.php does not carry auditChange.'
        . 'acSubjectLabel, so the column the writer depends on is not part '
        . 'of the schema an install is checked against.';
}
$checks++;
$auditPage = (string) file_get_contents(
    $web . '/src/Pages/AuditManagement.php'
);
if (false === strpos($auditPage, 'acSubjectLabel')
    || false === strpos($auditPage, "'subjectLabel' =>")
) {
    $failures[] = 'AuditManagement::getChanges() does not select and emit '
        . 'the subject label, so it is stored and never shown -- which looks '
        . 'identical to it never having been stored.';
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

/*
 * 4b. The permission node stays its own, with both actions.
 *
 * ADR 0021 Decision 9. `settings.edit` was the obvious place to gate
 * retention and it is not a gate: six page nodes map onto that one
 * permission, so it would have made "may shorten the audit window" and "may
 * edit the OUI table" the same grant.
 */
$authz = (string) @file_get_contents(
    $root . '/packages/web/src/Auth/Authorization.php'
);
$checks++;
if (false === strpos($authz, "'audit' => ['view', 'manage']")) {
    $failures[] = "coreRegistry() no longer declares 'audit' => "
        . "['view', 'manage']. Retention and reading the trail are different "
        . 'powers, and neither belongs on settings.edit (ADR 0021 '
        . 'Decision 9).';
}

/*
 * 4c. A read-only node does not advertise a create link.
 *
 * _buildSubMenuItems() defaults EVERY node to a list/add pair, and the
 * permission filter that would otherwise drop `add` cannot: a '*' holder
 * passes every permission string handed to can(), including one naming an
 * action the node never declared. So `audit.create` sailed through and put
 * "Create New Audit" in the sidebar of a table that has no write route at
 * all -- and `sub=add` there resolves to index(), so the link did not go
 * where it said either.
 *
 * The fix must stay derived from the registry. The alternative is the
 * hand-kept case list in that switch, which already carries six nodes for
 * this reason and is where the omission happened.
 */
$fogpage = (string) @file_get_contents(
    $root . '/packages/web/src/Base/FOGPage.php'
);
$checks++;
if (false === strpos($fogpage, "unset(\$menu['add'], \$menu['import']);")
    || false === strpos($fogpage, 'Authorization::registry();')
) {
    $failures[] = '_buildSubMenuItems() no longer drops the create link for '
        . 'a registry node that declares no `create` action, so every '
        . 'read-only page advertises "Create New <node>" again.';
}
$checks++;
if (preg_match(
    "#case '(?:audit|activity)':#",
    $fogpage
)) {
    $failures[] = 'fogpage.class.php special-cases audit or activity by name '
        . 'in the sub-menu switch. That is the hand-kept list the registry '
        . 'guard exists to replace -- the next read-only node would be the '
        . 'seventh thing somebody had to remember to add.';
}

/*
 * 5. The machine paths carry headers, and the polling paths do not.
 *
 * ADR 0021 Decision 4: service/ and reg-task/ contain zero Authorization::
 * calls, so there is no gate to hang a header on -- and a host registering
 * itself or a task reporting failure is exactly what an audit trail is for.
 * They write their own, with the empty `permission` every record() default
 * produces, which is the signal that says "this write bypassed
 * authorization".
 */
$machinePaths = [
    'packages/web/src/Boot/Registration.php' => 'host.register',
    'packages/web/src/TaskHandling/TaskQueue.php' => 'task.start',
    'packages/web/src/TaskHandling/TaskError.php' => 'task.failed',
    'packages/web/src/Audit/Blame.php' => 'task.blamed',
    'packages/web/service/inventory.php' => 'host.inventory',
];
foreach ($machinePaths as $path => $type) {
    $src = (string) @file_get_contents($root . '/' . $path);
    $checks++;
    if (false === strpos($src, "'type' => '$type'")) {
        $failures[] = "$path no longer records a '$type' header. That path "
            . 'writes state no gate saw, so nothing else records it at all.';
    }
    $checks++;
    if (false === strpos($src, 'Audit::SOURCE_ANONYMOUS')) {
        $failures[] = "$path records a header without SOURCE_ANONYMOUS. "
            . 'These endpoints identify a host by the MAC in the request and '
            . 'check no credential, so anything else overstates what FOG '
            . 'knows about who made the write.';
    }
}

/*
 * The volume decision, stated as a test because it is otherwise invisible:
 * progress.php is called every few seconds by every imaging host for the
 * whole length of a task. A header there is not a stricter audit trail, it
 * is a table that grows faster than the images do.
 */
$checks++;
$progress = (string) @file_get_contents(
    $root . '/packages/web/service/progress.php'
);
if (false !== strpos($progress, 'Audit::record')) {
    $failures[] = 'service/progress.php writes an audit header. FOS calls it '
        . 'every few seconds per imaging host for the length of the task; the '
        . 'auditable events are the start and the finish, which '
        . 'TaskQueue::checkIn() and checkout() already record.';
}

/*
 * 6. The exemption seam is wired end to end.
 *
 * A plugin can only classify its own pattern-matching columns if the
 * 'exempt' bucket actually reaches Redaction, and every link in that chain
 * fails silently: a missing seed leaves core's own exemptions out of the
 * map, a missing event argument means no plugin can ever append, and
 * isPatternExempt() reading the raw property instead of the built map
 * ignores every plugin declaration while still answering plausibly for core.
 * That is the same mistake as 58483d6 -- reading the property rather than
 * the accessor -- one class along.
 */
$route = (string) @file_get_contents(
    $root . '/packages/web/src/Router/Route.php'
);
$redaction = (string) @file_get_contents(
    $root . '/packages/web/src/Auth/Redaction.php'
);
$map = '';
if (preg_match(
    '#public static function sensitiveFieldMap\(\).*?
    \}#s',
    $route,
    $m
)) {
    $map = $m[0];
}
$checks++;
if ('' === $map) {
    $failures[] = 'could not locate Route::sensitiveFieldMap(); this test '
        . 'cannot check what it cannot find';
} else {
    $checks++;
    if (false === strpos($map, 'Redaction::$patternExempt')) {
        $failures[] = "Route::sensitiveFieldMap() no longer seeds the "
            . "'exempt' bucket from Redaction::\$patternExempt, so core's own "
            . 'exemptions are absent from the map every caller reads and '
            . 'hotkey, keysequence and passreset are redacted again.';
    }
    $checks++;
    if (false === strpos($map, "'exempt' => &\$exempt")) {
        $failures[] = "Route::sensitiveFieldMap() does not pass 'exempt' by "
            . 'reference to API_SENSITIVE_FIELDS, so a plugin has no way to '
            . 'classify a column of its own that matches the pattern and is '
            . 'not a credential. Core cannot hold that answer for it: the '
            . 'bundled plugins are a fetched artifact.';
    }
}
$exemptFn = '';
if (preg_match(
    '#public static function isPatternExempt\(.*?
    \}#s',
    $redaction,
    $m
)) {
    $exemptFn = $m[0];
}
$checks++;
if ('' === $exemptFn) {
    $failures[] = 'could not locate Redaction::isPatternExempt(); this test '
        . 'cannot check what it cannot find';
} else {
    $checks++;
    if (false !== strpos($exemptFn, 'self::$patternExempt')) {
        $failures[] = 'Redaction::isPatternExempt() reads $patternExempt '
            . 'directly instead of the map built from Route, so every '
            . 'plugin-declared exemption is silently ignored -- the same '
            . 'read-the-property mistake as 58483d6.';
    }
    $checks++;
    if (false === strpos($exemptFn, '_load()')) {
        $failures[] = 'Redaction::isPatternExempt() no longer builds the '
            . 'exempt map before reading it, so the answer depends on '
            . 'whether some other call happened to build it first.';
    }
}

/*
 * 7. A delete header names what it destroyed.
 *
 * ADR 0021 Decision 7 is two halves, and section 1 above only pins the half
 * that withholds: no auditChange rows on a delete. The other half is that
 * the header carries subjectType/subjectID/subjectLabel -- and it has to,
 * because with no change rows the header is the ONLY record a delete leaves
 * and the row it describes is gone. Shipped without this, every UI delete
 * recorded "somebody exercised host.delete" against subjectID 0.
 */
$checks++;
if (null === $destroy) {
    $failures[] = 'could not locate FOGController::destroy(); this test '
        . 'cannot check what it cannot find';
} else {
    $checks++;
    if (false === strpos($destroy, 'Audit::identify(')) {
        $failures[] = 'FOGController::destroy() does not identify what it '
            . 'destroyed, so the delete header records subjectID 0 and the '
            . 'object it names is already gone (ADR 0021 Decision 7).';
    }
}
$page = '';
if (preg_match(
    '#public static function requirePagePermission\(.*?
    \}#s',
    (string) @file_get_contents(
        $root . '/packages/web/src/Auth/Authorization.php'
    ),
    $m
)) {
    $page = $m[0];
}
$checks++;
if ('' === $page) {
    $failures[] = 'could not locate Authorization::requirePagePermission(); '
        . 'this test cannot check what it cannot find';
} else {
    $checks++;
    if (!preg_match(
        '#_auditGate\(\$perm, Audit::ALLOWED, .page., \$node, #',
        $page
    )) {
        $failures[] = 'Authorization::requirePagePermission() records a page '
            . 'header without the object id. The API arm has always passed '
            . 'one; without it every page-surface mutation is audited '
            . 'against subjectID 0.';
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
