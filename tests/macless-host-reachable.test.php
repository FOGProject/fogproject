<?php
/**
 * A host with no primary MAC must still be reachable.
 *
 * Host declares a relationship to MACAddressAssociation carrying the filter
 * array('primary' => 1), so that $host->get('primac') is the primary MAC.
 * buildQuery() emitted that filter into the WHERE clause -- and a WHERE
 * predicate on the optional side of a LEFT OUTER JOIN silently degrades it to
 * an INNER JOIN. A host with no hmPrimary='1' row therefore matched nothing:
 *
 *   new Host($id)->isValid()                -> false
 *   HostManager->find(array('id' => $id))   -> 0 objects
 *
 * on a row that is sitting in the table. The host is un-loadable,
 * un-editable and un-deletable through the ORM, and the API answers 404.
 * Fixed on working-1.6 in 1cd7446f6 (June 2026); this is the same fix here,
 * plus the tests that commit did not ship.
 *
 * IT IS NOT ONLY HOSTS. buildQuery() recurses, so the filter reaches every
 * class whose relationship chain passes through Host -- on this branch that
 * is Task, SnapinJob, SnapinTask, ImagingLog, NodeFailure, UserTracking,
 * LocationAssociation, SiteHostAssociation and the example plugin. A task
 * belonging to a host with no primary MAC was invisible to TaskManager. Those
 * are asserted too, because fixing only the Host case would leave the
 * interesting half of the bug in place.
 *
 * How a host loses its primary MAC in the first place: an update that
 * replaces the MAC set, a primary MAC deleted, a MAC left pending, or a host
 * created through the API or a CSV import with no MAC at all. Host::save()
 * now promotes the first remaining approved MAC rather than leaving the host
 * stranded, which is the second half of the same fix.
 *
 * Needs a real 1.5 schema -- the failure IS the SQL, so a fake database
 * cannot show it. SKIPs when there is none.
 *
 * Usage: php tests/macless-host-reachable.test.php
 * Exit status 0 = pass (or skip), 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';

require __DIR__ . '/lib/scope-harness.php';

$reason = scopeHarnessDbReason();
if (null !== $reason) {
    echo "SKIP: $reason\n";
    exit(0);
}

$tmp = sys_get_temp_dir() . '/fog-macless-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
@mkdir($tmp . '/plugins', 0700, true);
register_shutdown_function(
    function () use ($tmp) {
        if (!is_dir($tmp)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($tmp);
    }
);
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $tmp . '/plugins');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once $web . '/commons/init.php';
new Initiator();
$dbProp = new \ReflectionProperty('FOGBase', 'DB');
$dbProp->setAccessible(true);
$db = new PDODB();
$dbProp->setValue(null, $db);
// FOGController::save() stamps createdBy from the acting user, and reads it
// unconditionally. Nothing is logged in as a test process, so seat an empty
// (invalid) User -- exactly what a logged-out request already has.
$userProp = new \ReflectionProperty('FOGBase', 'FOGUser');
$userProp->setAccessible(true);
$userProp->setValue(null, new User(0));

$failures = [];
$checks = 0;

function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

/*
 * Fixture: three hosts, differing only in their MAC rows.
 *
 *   primary   one MAC, hmPrimary='1'   -- the control
 *   nonprim   one MAC, hmPrimary='0'   -- had a MAC, lost its primary flag
 *   nomac     no MAC rows at all       -- created by API or import
 *   pending   one MAC, hmPending='1'   -- MAC seen but not yet approved
 *   multi     primary + an additional  -- the ordinary multi-NIC machine
 *   multi2    the same, rows reversed  -- primary is not the lowest hmID
 *
 * The control matters: without it "returns nothing" would pass for a fixture
 * that never inserted anything.
 */
// `hostName` is varchar(16) -- the NetBIOS limit -- and carries a unique
// index, so a long fixture prefix truncates and the second host silently
// fails to insert. Keep the whole name inside sixteen characters.
$mark = 'zzml' . (getmypid() % 100000);
register_shutdown_function(
    function () use ($db, $mark) {
        $db->query(
            "DELETE `hostMAC` FROM `hostMAC` JOIN `hosts`"
            . " ON `hosts`.`hostID` = `hostMAC`.`hmHostID`"
            . " WHERE `hosts`.`hostName` LIKE '" . $mark . "%'"
        );
        $db->query(
            "DELETE `tasks` FROM `tasks` JOIN `hosts`"
            . " ON `hosts`.`hostID` = `tasks`.`taskHostID`"
            . " WHERE `hosts`.`hostName` LIKE '" . $mark . "%'"
        );
        $db->query("DELETE FROM `hosts` WHERE `hostName` LIKE '" . $mark . "%'");
    }
);

$mkHost = function ($suffix) use ($db, $mark) {
    $db->query(
        "INSERT INTO `hosts` (`hostName`,`hostIP`,`hostUseAD`)"
        . " VALUES ('" . $mark . $suffix . "','','0')"
    );
    return (int)$db->insertId();
};
$mkMac = function ($hostID, $primary, $pending = '0', $nth = 0) use ($db) {
    // A locally-administered address, so it can never collide with real
    // hardware if this fixture ever outlives a crashed run. $nth keeps a
    // host's second MAC distinct from its first; hmMAC is unique.
    $mac = sprintf('02:%02x:%02x:%02x:%02x:%02x', $nth & 255, ($hostID >> 24) & 255, ($hostID >> 16) & 255, ($hostID >> 8) & 255, $hostID & 255);
    $db->query(
        "INSERT INTO `hostMAC` (`hmHostID`,`hmMAC`,`hmDesc`,`hmPrimary`,`hmPending`)"
        . " VALUES (" . (int)$hostID . ",'" . $mac . "','fixture','" . $primary
        . "','" . $pending . "')"
    );
};

$hosts = [
    'primary' => $mkHost('p'),
    'nonprim' => $mkHost('n'),
    'nomac' => $mkHost('x'),
    'pending' => $mkHost('q'),
    'multi' => $mkHost('m'),
    'multi2' => $mkHost('m2'),
];
$mkMac($hosts['primary'], '1');
$mkMac($hosts['nonprim'], '0');
$mkMac($hosts['pending'], '0', '1');
$mkMac($hosts['multi'], '1');
$mkMac($hosts['multi'], '0', '0', 1);
// Same two MACs, inserted the other way round: the additional NIC was
// recorded first and the primary set afterwards. Row order is what
// array_shift() picks from, so this is the arrangement in which a
// self-heal that forgot to check for an existing primary would promote
// the WRONG MAC and leave two rows flagged primary.
$mkMac($hosts['multi2'], '0', '0', 1);
$mkMac($hosts['multi2'], '1');

/*
 * 1. load() -- the single-object path, which is what the API 404 came from.
 */
foreach ($hosts as $label => $id) {
    $h = new Host($id);
    check(
        "new Host(<$label>) loads",
        $h->isValid(),
        $failures,
        $checks
    );
    check(
        "new Host(<$label>) has its name",
        0 === strpos((string)$h->get('name'), $mark),
        $failures,
        $checks
    );
}

/*
 * 2. The manager path. Same filter, reached through find(), which is what
 *    every list, every report and every association lookup runs.
 */
foreach ($hosts as $label => $id) {
    $found = FOGBase::getClass('HostManager')->find(['id' => [$id]]);
    check(
        "HostManager->find(<$label>) returns the host",
        1 === count((array)$found),
        $failures,
        $checks
    );
}

/*
 * 3. And the classes that only reach the filter transitively. Task is the one
 *    that matters -- a task nobody can see is a task nobody can cancel -- so
 *    it is driven for real rather than reasoned about.
 */
foreach ($hosts as $label => $id) {
    $db->query(
        "INSERT INTO `tasks` (`taskName`,`taskCreateTime`,`taskCheckIn`,"
        . "`taskHostID`,`taskStateID`,`taskTypeID`,`taskCreateBy`,`taskNFSGroupID`,"
        . "`taskNFSMemberID`,`taskImageID`,`taskPCT`,`taskBPM`,`taskTimeElapsed`,"
        . "`taskTimeRemaining`,`taskDataCopied`,`taskDataTotal`,`taskPercentText`)"
        . " VALUES ('fixture',NOW(),NOW()," . (int)$id . ",1,1,'fixture',0,0,0,"
        . "'','','','','','','')"
    );
}
foreach ($hosts as $label => $id) {
    $tasks = FOGBase::getClass('TaskManager')->find(['hostID' => [$id]]);
    check(
        "TaskManager->find(hostID=<$label>) sees the task",
        1 === count((array)$tasks),
        $failures,
        $checks
    );
}

/*
 * 4. The self-heal. A host holding approved MACs but none flagged primary is
 *    one save away from being stranded again, so save() promotes the first
 *    remaining one. The no-MAC host must NOT be given a MAC it does not have.
 */
$h = new Host($hosts['nonprim']);
$h->set('description', 'touched by the test')->save();
$promoted = FOGBase::getSubObjectIDs(
    'MACAddressAssociation',
    ['hostID' => $hosts['nonprim'], 'primary' => '1'],
    'mac'
);
check(
    'save() promotes a remaining approved MAC when none is primary',
    1 === count((array)$promoted),
    $failures,
    $checks
);
/*
 * A MAC awaiting approval is not a MAC the host may boot from, so the
 * self-heal must leave it pending rather than reach for the nearest row.
 * Promoting it would approve an unapproved MAC as a side effect of an
 * unrelated save, which is a worse bug than the one being fixed.
 */
$h3 = new Host($hosts['pending']);
$h3->set('description', 'touched by the test')->save();
$stillPending = FOGBase::getSubObjectIDs(
    'MACAddressAssociation',
    ['hostID' => $hosts['pending'], 'primary' => '1'],
    'mac'
);
check(
    'save() does not promote a MAC that is still pending approval',
    0 === count((array)$stillPending),
    $failures,
    $checks
);
/*
 * And the self-heal must not fire when there is nothing to heal. A machine
 * with a primary MAC and a second NIC is the common case, and promoting its
 * additional MAC as well would leave two rows flagged primary -- which the
 * primac join then answers arbitrarily.
 */
foreach (['multi', 'multi2'] as $label) {
    $h4 = new Host($hosts[$label]);
    $h4->set('description', 'touched by the test')->save();
    $multiPrimary = FOGBase::getSubObjectIDs(
        'MACAddressAssociation',
        ['hostID' => $hosts[$label], 'primary' => '1'],
        'mac'
    );
    check(
        "save() leaves <$label>, which already has a primary MAC, with exactly one",
        1 === count((array)$multiPrimary),
        $failures,
        $checks
    );
}
$h2 = new Host($hosts['nomac']);
$h2->set('description', 'touched by the test')->save();
$invented = FOGBase::getSubObjectIDs(
    'MACAddressAssociation',
    ['hostID' => $hosts['nomac']],
    'mac'
);
check(
    'save() does not invent a MAC for a host that has none',
    0 === count((array)$invented),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}
echo "ok  $checks checks passed\n";
