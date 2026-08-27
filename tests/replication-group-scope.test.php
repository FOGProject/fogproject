<?php
/**
 * Group -> Group replication searches the OTHER groups, not its own.
 *
 * FOGService::replicateItems() picks the nodes to send to by building one
 * $find array and handing it to Route::getList(). Which nodes belong in it
 * depends entirely on $master:
 *
 *   $master = false   Group -> Nodes.  The peers in MY group.
 *                     storagegroupID = my group id, no isMaster term.
 *   $master = true    Group -> Group.  The MASTER of every group the item
 *                     being replicated belongs to.
 *                     storagegroupID = $Obj->get('storagegroups'),
 *                     plus isMaster.
 *
 * From 2018 (commit 49c1c87a9) until this test was written, the master
 * branch's assignment sat AFTER $find had already copied $groupID by value:
 *
 *     $groupID = $myStorageGroupID;
 *     $find = ['isEnabled' => [1], 'storagegroupID' => $groupID];
 *     if ($master) {
 *         $groupID = $Obj->get('storagegroups');   // dead write
 *         $find['isMaster'] = [1];
 *     }
 *
 * so Group -> Group searched the replicator's own group for masters. A group
 * has exactly one master, the transfer loop then skips it as self, and the
 * count check reported "There are no members to sync to" -- every cycle, on
 * every install, for both images and snapins, with no error anywhere. It
 * looks exactly like "nothing is due for replication", which is why it
 * survived eight years. dev-branch never had it.
 *
 * This runs the REAL method body, lifted out of the shipped file, and stops
 * it at the first Route call so no database is needed. What is asserted is
 * the $find it built -- the actual decision -- not the shape of the source.
 *
 * Usage: php tests/replication-group-scope.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$src = __DIR__ . '/../packages/web/src/Service/FOGService.php';
$code = file_get_contents($src);
if (false === $code) {
    fwrite(STDERR, "cannot read $src\n");
    exit(1);
}

// Lift replicateItems() by brace matching from its signature. Anything less
// exact (a regex to the next 'function') breaks on the closures inside it.
$start = strpos($code, 'protected function replicateItems(');
if (false === $start) {
    fwrite(STDERR, "replicateItems() not found in $src\n");
    exit(1);
}
$open = strpos($code, '{', $start);
$depth = 0;
$end = null;
for ($i = $open, $n = strlen($code); $i < $n; $i++) {
    if ('{' === $code[$i]) {
        $depth++;
    } elseif ('}' === $code[$i]) {
        $depth--;
        if (0 === $depth) {
            $end = $i;
            break;
        }
    }
}
if (null === $end) {
    fwrite(STDERR, "could not brace-match replicateItems()\n");
    exit(1);
}
$method = substr($code, $start, $end - $start + 1);

$harness = "namespace FOGTest;\n"
    . "class FindCaptured extends \\Exception {\n"
    . "    public \$find;\n"
    . "    public function __construct(\$find) { \$this->find = \$find; }\n"
    . "}\n"
    // Stops the real method at its first Route call, carrying out the one
    // thing under test. Everything past this point needs a database.
    . "class Route {\n"
    . "    public static function getItem(\$c, \$i) {\n"
    . "        throw new FindCaptured(\$GLOBALS['capturedFind']);\n"
    . "    }\n"
    . "}\n"
    . "class Item {\n"
    . "    private \$groups;\n"
    . "    public function __construct(\$groups) { \$this->groups = \$groups; }\n"
    . "    public function get(\$f) { return 'storagegroups' === \$f ? \$this->groups : ''; }\n"
    . "}\n"
    . "class Svc {\n"
    . str_replace(
        // The capture point: publish $find to the fake Route, which is
        // called on the very next line of the real body.
        "        \$myStorageNode = Route::getItem('storagenode', \$myStorageNodeID);",
        "        \$GLOBALS['capturedFind'] = \$find;\n"
        . "        \$myStorageNode = Route::getItem('storagenode', \$myStorageNodeID);",
        str_replace('protected function', 'public function', $method)
    )
    . "\n}\n";

if (false === strpos($harness, "\$GLOBALS['capturedFind'] = \$find;")) {
    fwrite(
        STDERR,
        "FAIL: the Route::getItem() capture point was not found in the "
        . "lifted method -- the harness would assert nothing.\n"
    );
    exit(1);
}

eval($harness);

$failures = [];

/**
 * Runs the real method and returns the $find it built.
 *
 * @param bool  $master Master-to-master, or master-to-nodes.
 * @param array $groups The item's storage groups.
 *
 * @return array
 */
function findFor($master, array $groups)
{
    $svc = new \FOGTest\Svc();
    try {
        $svc->replicateItems(1, 1, new \FOGTest\Item($groups), $master);
    } catch (\FOGTest\FindCaptured $e) {
        return $e->find;
    }
    return ['__never_reached_the_capture_point__' => true];
}

/**
 * Records a failed expectation.
 *
 * @param string $label What was being checked.
 * @param mixed  $want  Expected.
 * @param mixed  $got   Actual.
 *
 * @return void
 */
function check($label, $want, $got)
{
    global $failures;
    if ($want !== $got) {
        $failures[] = sprintf(
            "  %s\n    want: %s\n    got : %s",
            $label,
            json_encode($want),
            json_encode($got)
        );
    }
}

// 1. Group -> Group: the item spans groups 1 and 2 and we are group 1, so
//    the search must cover BOTH -- group 2's master is the whole point.
$g2g = findFor(true, [1, 2]);
check(
    'Group->Group searches the item\'s groups',
    [1, 2],
    $g2g['storagegroupID'] ?? null
);
check('Group->Group filters on isMaster', [1], $g2g['isMaster'] ?? null);
check('Group->Group filters on isEnabled', [1], $g2g['isEnabled'] ?? null);

// 2. The regression itself, stated as its own assertion: pinning only the
//    positive above would still pass if someone reintroduced the scalar,
//    since 1 is a plausible-looking value for that key.
if (1 === ($g2g['storagegroupID'] ?? null)) {
    $failures[] = '  Group->Group is scoped to the replicator\'s OWN group '
        . "(the 2018 dead-write regression)\n    "
        . 'storagegroupID is the scalar 1, not the item\'s group list';
}

// 3. An item in one group only. There is then genuinely nowhere to send it,
//    but the search must still be driven by the item, not by us -- if the
//    item lives in group 5 and we are group 1, asking about group 1 would
//    find our own master and try to replicate to ourselves.
$other = findFor(true, [5]);
check('Group->Group follows the item off our own group', [5], $other['storagegroupID'] ?? null);

// 4. Group -> Nodes is unchanged: our group, every enabled node, no
//    isMaster term. This is the half that always worked and the fix must
//    not disturb it.
$g2n = findFor(false, [1, 2]);
check('Group->Nodes stays on our own group', 1, $g2n['storagegroupID'] ?? null);
check('Group->Nodes filters on isEnabled', [1], $g2n['isEnabled'] ?? null);
check('Group->Nodes does not filter on isMaster', null, $g2n['isMaster'] ?? null);

if ($failures) {
    fwrite(STDERR, "FAIL: replication group scope\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo "PASS: replication group scope (7 checks)\n";
exit(0);
