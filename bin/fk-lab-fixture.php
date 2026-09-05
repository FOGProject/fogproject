<?php
/**
 * Builds a fleet-shaped, aged FOG database in a LAB container, so the
 * foreign-key work can be exercised against something the maintainer's own
 * test installs cannot supply: volume, and a decade of deletions.
 *
 * DESTRUCTIVE. It scales an existing clone up and then deletes through it.
 * Point it at a throwaway copy and nothing else.
 *
 * WHAT IT IS FOR, AND WHAT IT IS NOT
 *
 * It is a fixture for testing the migration -- does the sweep remove exactly
 * what it should, does ADD CONSTRAINT behave at scale, is the step
 * re-runnable. It is NOT evidence about which orphan classes exist in the
 * wild: orphans invented here are only the ones
 * commons/schema-constraints.php already predicts, so finding them would
 * prove nothing. The evidence for the classes
 * is Route::deletemass()'s switch, which is read, not sampled.
 *
 * So the aging below never writes an orphan directly. It deletes parent rows
 * through the three mechanisms that really occur, and lets the orphans fall
 * out on their own:
 *
 *   bare       a plain DELETE with no cleanup at all. This is the pre-cascade
 *              era, and any path today that does not go through deletemass().
 *   storage    exactly what FOG does now for a storage group or node: delete
 *              the row, clean up nothing. There is no case for either in the
 *              switch.
 *   cascade    today's deletemass() list, applied faithfully. Whatever is
 *              still orphaned after this is a CURRENT defect, and that is the
 *              one result here worth reading as a finding.
 *
 * Usage:
 *   php bin/fk-lab-fixture.php --host=H --port=P --db=D --user=U --pass=P \
 *       [--hosts=5000] --yes-destroy
 *
 * PHP version 7.4+
 *
 * @category Schema
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--') === 0) {
        [$k, $v] = array_pad(explode('=', substr($arg, 2), 2), 2, '1');
        $opts[$k] = $v;
    }
}

$host = $opts['host'] ?? '127.0.0.1';
$port = (int)($opts['port'] ?? 0);
$db = $opts['db'] ?? '';
$want = (int)($opts['hosts'] ?? 5000);

// Live-database guard. FOG's own MariaDB owns 3306, so a fixture run that
// defaulted to it would scale up and then delete through the install this
// box actually uses. There is no flag to override this.
if ($port === 3306 || $port === 0) {
    fwrite(STDERR, "refusing port $port: 3306 is the live server. Use a lab port.\n");
    exit(2);
}
if ($db === '' || !isset($opts['yes-destroy'])) {
    fwrite(STDERR, "usage: --host --port --db --user --pass [--hosts=N] --yes-destroy\n");
    exit(2);
}

$pdo = new \PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s', $host, $port, $db),
    $opts['user'] ?? '',
    $opts['pass'] ?? '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);

echo "target: $host:$port/$db  scaling to $want hosts\n\n";

/**
 * Runs a statement and returns the affected row count.
 *
 * @param \PDO   $pdo open connection
 * @param string $sql statement to run
 *
 * @return int
 */
function ex(\PDO $pdo, string $sql): int
{
    return (int)$pdo->exec($sql);
}

// ---------------------------------------------------------------------------
// 1. Reference data the fleet hangs off. Small, and mostly already present in
//    a clone -- topped up so the fixture does not depend on what the source
//    install happened to hold.
// ---------------------------------------------------------------------------
$pdo->exec("INSERT INTO `nfsGroups` (`ngName`) SELECT CONCAT('lab-group-', n) FROM (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) q");
$groupIDs = $pdo->query("SELECT ngID FROM `nfsGroups`")->fetchAll(\PDO::FETCH_COLUMN);

foreach ($groupIDs as $g) {
    $pdo->exec(
        "INSERT INTO `nfsGroupMembers`"
        . " (`ngmGroupID`,`ngmMemberName`,`ngmRootPath`,`ngmFTPPath`,`ngmHostname`,`ngmUser`,`ngmPass`)"
        . " VALUES ($g, CONCAT('node-$g'), '/images', '/images', '10.0.0.$g', 'fogproject', 'x')"
    );
}
$nodeIDs = $pdo->query("SELECT ngmID FROM `nfsGroupMembers`")->fetchAll(\PDO::FETCH_COLUMN);

for ($i = 1; $i <= 20; $i++) {
    $pdo->exec(
        "INSERT INTO `images` (`imageName`,`imagePath`,`imageTypeID`,`imagePartitionTypeID`,`imageOSID`)"
        . " VALUES ('lab-image-$i', '/images/lab-$i', 1, 1, 1)"
    );
    $pdo->exec("INSERT INTO `snapins` (`sName`,`sFilePath`) VALUES ('lab-snapin-$i', '/opt/fog/snapins/lab-$i')");
}
for ($i = 1; $i <= 40; $i++) {
    $pdo->exec("INSERT INTO `groups` (`groupName`) VALUES ('lab-group-$i')");
}
$imageIDs = $pdo->query("SELECT imageID FROM `images`")->fetchAll(\PDO::FETCH_COLUMN);
$snapinIDs = $pdo->query("SELECT sID FROM `snapins`")->fetchAll(\PDO::FETCH_COLUMN);
$gIDs = $pdo->query("SELECT groupID FROM `groups`")->fetchAll(\PDO::FETCH_COLUMN);
$moduleIDs = $pdo->query("SELECT id FROM `modules`")->fetchAll(\PDO::FETCH_COLUMN);
$stateIDs = $pdo->query("SELECT tsID FROM `taskStates`")->fetchAll(\PDO::FETCH_COLUMN);
$typeIDs = $pdo->query("SELECT ttID FROM `taskTypes`")->fetchAll(\PDO::FETCH_COLUMN);

printf(
    "reference: %d storage groups, %d nodes, %d images, %d snapins, %d groups\n",
    count($groupIDs), count($nodeIDs), count($imageIDs), count($snapinIDs), count($gIDs)
);

// ---------------------------------------------------------------------------
// 2. The fleet. Hosts, and for each the satellites and associations a real
//    host accumulates.
// ---------------------------------------------------------------------------
$have = (int)$pdo->query("SELECT COUNT(*) FROM `hosts`")->fetchColumn();
$make = max(0, $want - $have);
$pdo->beginTransaction();
for ($i = 0; $i < $make; $i += 500) {
    $rows = [];
    for ($j = $i; $j < min($i + 500, $make); $j++) {
        $img = $imageIDs[$j % count($imageIDs)];
        $rows[] = "('labhost" . $j . "', $img)";
    }
    $pdo->exec("INSERT INTO `hosts` (`hostName`,`hostImage`) VALUES " . implode(',', $rows));
}
$pdo->commit();

// Only the hosts this fixture created. The clone's own hosts already carry
// satellites, and moduleStatusByHost has a UNIQUE (msHostID, msModuleID) that
// a blind top-up collides with.
$hostIDs = $pdo->query(
    "SELECT hostID FROM `hosts` WHERE hostName LIKE 'labhost%'"
)->fetchAll(\PDO::FETCH_COLUMN);
echo "hosts: " . count($hostIDs) . " lab hosts of "
    . (int)$pdo->query("SELECT COUNT(*) FROM `hosts`")->fetchColumn() . " total\n";

$pdo->beginTransaction();
$mac = $mod = $grp = $sna = $inv = 0;
foreach (array_chunk($hostIDs, 500) as $chunk) {
    $m = $ms = $gm = $sa = $iv = [];
    foreach ($chunk as $k => $h) {
        $m[] = "($h, '" . sprintf('02:00:%02x:%02x:%02x:%02x', ($h >> 24) & 255, ($h >> 16) & 255, ($h >> 8) & 255, $h & 255) . "')";
        foreach ($moduleIDs as $mid) {
            $ms[] = "($h, $mid, '1')";
        }
        $gm[] = "($h, " . $gIDs[$h % count($gIDs)] . ")";
        $sa[] = "($h, " . $snapinIDs[$h % count($snapinIDs)] . ")";
        $iv[] = "($h, 'lab')";
    }
    $pdo->exec("INSERT INTO `hostMAC` (`hmHostID`,`hmMAC`) VALUES " . implode(',', $m));
    $mac += count($m);
    $pdo->exec("INSERT INTO `moduleStatusByHost` (`msHostID`,`msModuleID`,`msState`) VALUES " . implode(',', $ms));
    $mod += count($ms);
    $pdo->exec("INSERT INTO `groupMembers` (`gmHostID`,`gmGroupID`) VALUES " . implode(',', $gm));
    $grp += count($gm);
    $pdo->exec("INSERT INTO `snapinAssoc` (`saHostID`,`saSnapinID`) VALUES " . implode(',', $sa));
    $sna += count($sa);
    $pdo->exec("INSERT INTO `inventory` (`iHostID`,`iPrimaryUser`) VALUES " . implode(',', $iv));
    $inv += count($iv);
}
$pdo->commit();
echo "  hostMAC $mac, moduleStatusByHost $mod, groupMembers $grp, snapinAssoc $sna, inventory $inv\n";

// ---------------------------------------------------------------------------
// 3. Work. Several tasks per host across a spread of states, each with a
//    handful of taskLog rows, plus user-tracking history.
// ---------------------------------------------------------------------------
$pdo->beginTransaction();
$tk = 0;
foreach (array_chunk($hostIDs, 250) as $chunk) {
    $t = $ut = [];
    foreach ($chunk as $h) {
        for ($n = 0; $n < 8; $n++) {
            $t[] = '(' . $h . ', ' . $imageIDs[$h % count($imageIDs)]
                . ', ' . $stateIDs[($h + $n) % count($stateIDs)]
                . ', ' . $typeIDs[($h + $n) % count($typeIDs)]
                . ', ' . $groupIDs[$h % count($groupIDs)]
                . ', ' . $nodeIDs[$h % count($nodeIDs)]
                . ', ' . $nodeIDs[$h % count($nodeIDs)] . ')';
        }
        $ut[] = "($h, 'labuser')";
    }
    $pdo->exec(
        "INSERT INTO `tasks` (`taskHostID`,`taskImageID`,`taskStateID`,`taskTypeID`,"
        . "`taskNFSGroupID`,`taskNFSMemberID`,`taskLastMemberID`) VALUES " . implode(',', $t)
    );
    $tk += count($t);
    $pdo->exec("INSERT INTO `userTracking` (`utHostID`,`utUserName`) VALUES " . implode(',', $ut));
}
$pdo->commit();

$pdo->exec(
    "INSERT INTO `taskLog` (`taskID`,`taskStateID`,`logHostID`,`logHostName`)"
    . " SELECT t.taskID, t.taskStateID, t.taskHostID, h.hostName"
    . " FROM `tasks` t JOIN `hosts` h ON h.hostID = t.taskHostID"
);
printf(
    "work: tasks %d, taskLog %d, userTracking %d\n\n",
    $tk,
    (int)$pdo->query("SELECT COUNT(*) FROM `taskLog`")->fetchColumn(),
    (int)$pdo->query("SELECT COUNT(*) FROM `userTracking`")->fetchColumn()
);

// ---------------------------------------------------------------------------
// 4. Aging. Three mechanisms, none of which writes an orphan directly.
// ---------------------------------------------------------------------------
echo "aging:\n";

// (a) bare -- no cleanup at all. The pre-cascade era, and any path today that
//     does not reach deletemass().
$bare = $pdo->query(
    "SELECT hostID FROM `hosts` ORDER BY hostID LIMIT 300"
)->fetchAll(\PDO::FETCH_COLUMN);
$in = implode(',', $bare);
ex($pdo, "DELETE FROM `hosts` WHERE hostID IN ($in)");
echo "  bare      deleted " . count($bare) . " hosts with no cleanup\n";

// (b) storage -- exactly what FOG does today. There is no case for either
//     'storagegroup' or 'storagenode' in Route::deletemass()'s switch, and
//     neither model overrides destroy(), so the row goes and nothing else does.
$dropGroup = (int)$pdo->query("SELECT ngID FROM `nfsGroups` ORDER BY ngID DESC LIMIT 1")->fetchColumn();
$dropNode = (int)$pdo->query("SELECT ngmID FROM `nfsGroupMembers` WHERE ngmGroupID <> $dropGroup ORDER BY ngmID DESC LIMIT 1")->fetchColumn();
ex($pdo, "DELETE FROM `nfsGroups` WHERE ngID = $dropGroup");
ex($pdo, "DELETE FROM `nfsGroupMembers` WHERE ngmID = $dropNode");
echo "  storage   deleted storage group $dropGroup and node $dropNode as FOG does today\n";

// (c) cascade -- today's deletemass('host') list, applied faithfully. Anything
//     still orphaned after this is a CURRENT defect rather than history.
$casc = $pdo->query(
    "SELECT hostID FROM `hosts` ORDER BY hostID DESC LIMIT 300"
)->fetchAll(\PDO::FETCH_COLUMN);
$in = implode(',', $casc);
$jobs = $pdo->query("SELECT sjID FROM `snapinJobs` WHERE sjHostID IN ($in)")->fetchAll(\PDO::FETCH_COLUMN);
if ($jobs) {
    ex($pdo, "DELETE FROM `snapinTasks` WHERE stJobID IN (" . implode(',', $jobs) . ")");
}
foreach ([
    'nfsFailures' => 'nfHostID',
    'snapinJobs' => 'sjHostID',
    'tasks' => 'taskHostID',
    'hostAutoLogOut' => 'haloHostID',
    'groupMembers' => 'gmHostID',
    'snapinAssoc' => 'saHostID',
    'printerAssoc' => 'paHostID',
    'moduleStatusByHost' => 'msHostID',
    'inventory' => 'iHostID',
    'hostMAC' => 'hmHostID',
    'powerManagement' => 'pmHostID',
    'siteHostMembers' => 'shmHostID',
] as $tbl => $col) {
    ex($pdo, "DELETE FROM `$tbl` WHERE `$col` IN ($in)");
}
ex($pdo, "DELETE FROM `scheduledTasks` WHERE stIsGroup = '0' AND stGroupHostID IN ($in)");
ex($pdo, "DELETE FROM `hosts` WHERE hostID IN ($in)");
echo "  cascade   deleted " . count($casc) . " hosts through deletemass()'s own list\n";

echo "\nfixture built. Run bin/fk-orphan-scan.php against it.\n";
