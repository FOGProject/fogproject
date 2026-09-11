<?php
/**
 * A relationship's filter belongs in the JOIN ON clause, never in WHERE.
 *
 * `$databaseFieldClassRelationships` entries may carry an optional 4th
 * element -- a filter on the joined table. Exactly one exists in the whole
 * codebase, and it is load-bearing:
 *
 *     'MACAddressAssociation' => ['hostID', 'id', 'primac', ['primary' => 1]]
 *
 * so that `$host->get('primac')` is the host's PRIMARY MAC rather than
 * whichever row came back first. `buildQuery()` used to emit that filter into
 * `$whereArrayAnd`, and a WHERE predicate on the right-hand table of a LEFT
 * OUTER JOIN is not a filter -- it is an INNER JOIN written the long way.
 * Rows with nothing to join to are dropped by the WHERE, so a host with no
 * `hmPrimary='1'` row stopped existing:
 *
 *     new Host($id)->isValid()   -> false
 *     HostManager->find(...)     -> 0 objects
 *     GET /fog/host/$id          -> 404
 *
 * on a row sitting in the table. Un-loadable, un-editable, un-deletable.
 * And `buildQuery()` recurses, so the same predicate reached every class
 * whose relationship chain passes through Host -- a task belonging to such a
 * host was invisible to TaskManager, which is the half that turns a display
 * bug into an operational one.
 *
 * This is a STRUCTURAL test and needs no database: the defect is in the SQL
 * text, so the SQL text is what it reads. The behavioural proof, which drives
 * real rows through a real schema, is a separate file and skips where there
 * is no database to drive -- meaning this is the arm that actually gates CI.
 *
 * Usage: php tests/relationship-filter-in-join.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/scope-harness.php';

// This branch has no shared FogTestHarness; scopeHarnessBoot() is the
// equivalent, and it already installs a fake database, so nothing here
// touches a real one.
scopeHarnessBoot(dirname(__DIR__) . '/packages/web');

$t = new RelFilterChecks();

/**
 * The assertion helper every test on this branch hand-rolls.
 */
class RelFilterChecks
{
    /** @var array */
    public $failures = array();

    /** @var int */
    public $count = 0;

    /**
     * @param string $label what is being asserted
     * @param bool   $cond  the assertion
     *
     * @return bool the assertion, so a caller can branch on it
     */
    public function check($label, $cond)
    {
        $this->count++;
        if (!$cond) {
            $this->failures[] = $label;
        }
        return (bool)$cond;
    }

    /**
     * Print the verdict and exit with the suite's convention.
     *
     * @return void
     */
    public function finish()
    {
        if (count($this->failures)) {
            fwrite(
                STDERR,
                'FAIL (' . count($this->failures) . ' of ' . $this->count . "):\n"
            );
            foreach ($this->failures as $f) {
                fwrite(STDERR, "  - $f\n");
            }
            exit(1);
        }
        echo 'ok  ' . $this->count . " checks passed\n";
        exit(0);
    }
}

/**
 * Builds one class's join text and its WHERE additions.
 *
 * @param string $class the class to build for
 *
 * @return array [joins, whereArrayAnd]
 */
function relFilterBuild($class)
{
    $obj = FOGCore::getClass($class);
    $join = array();
    $where = array();
    $c = null;
    return $obj->buildQuery($join, $where, $c);
}

/*
 * 1. The filter is really there. Without this every assertion below would
 *    pass just as well against a relationship map that had quietly lost it,
 *    and the test would be measuring nothing.
 */
$relProp = new \ReflectionProperty(
    get_class(FOGCore::getClass('Host')),
    'databaseFieldClassRelationships'
);
$relProp->setAccessible(true);
$rels = $relProp->getValue(FOGCore::getClass('Host'));
$macRel = isset($rels['MACAddressAssociation'])
    ? $rels['MACAddressAssociation']
    : null;
$t->check(
    'Host still declares a filtered relationship to MACAddressAssociation',
    is_array($macRel) && isset($macRel[3]) && is_array($macRel[3])
    && array_key_exists('primary', $macRel[3])
);

/*
 * 2. It is emitted inside the ON clause of the hostMAC join, and the join is
 *    still an outer one. Both halves matter: moving the predicate to ON while
 *    turning the join inner would drop exactly the same rows.
 */
list($joins, $where) = relFilterBuild('Host');
$t->check(
    'the hostMAC join is still a LEFT OUTER JOIN',
    false !== strpos($joins, 'LEFT OUTER JOIN `hostMAC` ON ')
);
$t->check(
    "hmPrimary is part of the hostMAC ON clause",
    false !== strpos(
        $joins,
        "ON `hostMAC`.`hmHostID`=`hosts`.`hostID` AND `hostMAC`.`hmPrimary` = '1'"
    )
);

/*
 * 3. And nothing about the optional table reached WHERE -- for Host, and for
 *    every class that inherits the join transitively. The list is spelled out
 *    rather than derived so that a class LOSING its path to Host shows up as
 *    a skipped name here, not as silence.
 */
$classes = array(
    'Host',
    'Task',
    'SnapinJob',
    'SnapinTask',
    'ImagingLog',
    'NodeFailure',
    'UserTracking',
    'LocationAssociation',
    'SiteHostAssociation',
);
foreach ($classes as $class) {
    if (!class_exists($class)) {
        $t->check("$class exists, so its join is actually being checked", false);
        continue;
    }
    list($j, $w) = relFilterBuild($class);
    $t->check(
        "$class puts no hostMAC predicate in WHERE",
        !preg_grep('/hostMAC|hmPrimary/', (array)$w)
    );
    // A class that reaches Host must actually carry the join, or "no
    // predicate in WHERE" is true for the boring reason.
    if ('Host' !== $class) {
        $t->check(
            "$class reaches the hostMAC join at all",
            false !== strpos($j, 'JOIN `hostMAC` ON ')
        );
    }
}

$t->finish();
