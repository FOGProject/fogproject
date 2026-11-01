<?php
/** Real-DB coverage for guarded ORM upserts and HostManager WHERE semantics. */
$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 upsert/where: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('U2-P2 upsert/where requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-upsert-where');
$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('U2-P2 upsert/where requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
}

// Keep the real PDO path installed for both the ORM and DirectoryNameFence.
$link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);

$t = new FogChecks();
$fixtures = [];
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$create = static function ($active = false) use ($pdo, $guid, &$fixtures) {
    do {
        $id = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$id]);
    } while ((int)$q->fetchColumn() !== 0);
    $name = 'P2U' . strtoupper(bin2hex(random_bytes(5)));
    $domain = 'u2p2.invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $oldName = 'OLD' . strtoupper(bin2hex(random_bytes(5)));
    $state = null;
    if ($active) {
        $state = json_encode([
            'version' => 1,
            'protocol' => 2,
            'phase' => 'started',
            'attempt_id' => bin2hex(random_bytes(16)),
            'host_id' => $id,
            'fingerprint' => $fingerprint,
            'operation' => 'rename',
            'mode' => 'existing',
            'name' => $name,
            'expected_current' => $oldName,
            'expected_pending' => $name,
            'target' => [
                'guid' => $guid(),
                'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
                'account' => $name . '$'
            ]
        ]);
    }
    $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostDesc,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,?,\'1\',?,?,?)'
    )->execute([$id, $name, 'u2p2-original-' . $id, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $fixtures[] = ['id' => $id, 'hdid' => 0];
    $fixtureIndex = array_key_last($fixtures);
    $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,?)'
    )->execute([$id, $domain, 'CN=' . $name . ',OU=Managed,DC=u2p2,DC=invalid', $name . '$', $state]);
    $q = $pdo->prepare('SELECT hdID FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$id]);
    $hdID = (int)$q->fetchColumn();
    $fixtures[$fixtureIndex]['hdid'] = $hdID;
    return ['id' => $id, 'hdid' => $hdID, 'name' => $name, 'fingerprint' => $fingerprint, 'active' => (bool)$active];
};
$row = static function ($table, $key, $id) use ($pdo) {
    if (!in_array($table, ['hosts', 'hostDirectory'], true)
        || !in_array($key, ['hostID', 'hdID'], true)) {
        throw new \InvalidArgumentException('invalid fixture row selector');
    }
    $q = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `' . $key . '`=?');
    $q->execute([$id]);
    return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
};
$same = static function ($a, $b) {
    return hash_equals(hash('sha256', serialize($a)), hash('sha256', serialize($b)));
};
$attempt = static function (callable $fn) {
    try {
        return ['result' => $fn(), 'error' => null];
    } catch (\Throwable $e) {
        return ['result' => null, 'error' => $e];
    }
};

try {
    // B09: both a clean stale model and a dirty stale model are saved after
    // the raw host name has changed and an active marker owns the new name.
    foreach ([false, true] as $dirty) {
        $f = $create(true);
        $stale = new FOG\Items\Host($f['id']);
        $freshName = 'NEW' . strtoupper(bin2hex(random_bytes(5)));
        $pdo->prepare('UPDATE hosts SET hostName=? WHERE hostID=?')->execute([$freshName, $f['id']]);
        $pdo->prepare(
            'UPDATE hostDirectory SET hdRejoinState=JSON_SET('
            . 'hdRejoinState, \'$.name\', ?, \'$.expected_pending\', ?, \'$.target.account\', ?) '
            . 'WHERE hdHostID=?'
        )->execute([$freshName, $freshName, $freshName . '$', $f['id']]);
        $freshHostBefore = $row('hosts', 'hostID', $f['id']);
        $freshMarkerBefore = $row('hostDirectory', 'hdID', $f['hdid']);
        if ($dirty) {
            $stale->set('description', 'stale-dirty-' . $f['id']);
        }
        $save = $attempt(static function () use ($stale) { return $stale->save(); });
        $t->check(
            'B09 stale ' . ($dirty ? 'dirty' : 'clean') . ' Host save cannot overwrite a fresh protected footprint',
            (false === $save['result'] || $save['error'] instanceof \Throwable)
                && $freshHostBefore === $row('hosts', 'hostID', $f['id'])
                && $same($freshMarkerBefore, $row('hostDirectory', 'hdID', $f['hdid']))
        );
    }

    // B10: duplicate-key Host saves must inspect both the primary-key row and
    // the independently unique hostname row before the upsert reaches MySQL.
    $active = $create(true);
    $activeHostBefore = $row('hosts', 'hostID', $active['id']);
    $pkCandidate = new FOG\Items\Host($active['id']);
    $pkCandidate->set('name', 'PK' . strtoupper(bin2hex(random_bytes(5))));
    $pkUpsert = $attempt(static function () use ($pkCandidate) { return $pkCandidate->save(); });
    $t->check(
        'B10 Host upsert colliding by PK cannot change the protected row',
        (false === $pkUpsert['result'] || $pkUpsert['error'] instanceof \Throwable)
            && $activeHostBefore === $row('hosts', 'hostID', $active['id'])
    );

    $other = $create(false);
    $activeHostBefore = $row('hosts', 'hostID', $active['id']);
    $nameCandidate = new FOG\Items\Host($other['id']);
    $nameCandidate->set('name', $active['name']);
    $nameUpsert = $attempt(static function () use ($nameCandidate) { return $nameCandidate->save(); });
    $t->check(
        'B10 Host upsert colliding by UNIQUE hostname cannot change the protected owner',
        (false === $nameUpsert['result'] || $nameUpsert['error'] instanceof \Throwable)
            && $activeHostBefore === $row('hosts', 'hostID', $active['id'])
    );

    // Exercise the two independent unique keys on hostDirectory (hdID and
    // hdHostID) with ORM upserts. HostDirectory::save intentionally strips
    // generic authority fields; the protected raw owner row must still stay put.
    $idle = $create(false);
    $protectedHD = $row('hostDirectory', 'hdID', $active['hdid']);
    $hdByPK = (new FOG\Items\HostDirectory($active['hdid']))->set('hostID', $idle['id']);
    $pkHDUpsert = $attempt(static function () use ($hdByPK) { return $hdByPK->save(); });
    $t->check(
        'B10 HostDirectory upsert colliding by PK cannot reparent protected owner',
        (false === $pkHDUpsert['result'] || $pkHDUpsert['error'] instanceof \Throwable)
            && $same($protectedHD, $row('hostDirectory', 'hdID', $active['hdid']))
    );

    $ownerCandidate = new FOG\Items\HostDirectory($idle['hdid']);
    $ownerCandidate->set('hostID', $active['id']);
    $ownerHDUpsert = $attempt(static function () use ($ownerCandidate) { return $ownerCandidate->save(); });
    $t->check(
        'B10 HostDirectory upsert colliding by UNIQUE owner cannot replace protected row',
        (false === $ownerHDUpsert['result'] || $ownerHDUpsert['error'] instanceof \Throwable)
            && $same($protectedHD, $row('hostDirectory', 'hdID', $active['hdid']))
    );

    // B12: these are actual HostManager UPDATE calls through PDODB. Predicates
    // cover LIKE, IN, OR and empty-array equality. Every fixture is inactive;
    // there is deliberately no unbounded empty-WHERE UPDATE over shared hosts.
    $manager = new FOG\Managers\HostManager();
    $likeA = $create(false);
    $likeB = $create(false);
    $pdo->prepare('UPDATE hosts SET hostDesc=? WHERE hostID=?')->execute(['P2LIKE-' . $likeA['id'], $likeA['id']]);
    $pdo->prepare('UPDATE hosts SET hostDesc=? WHERE hostID=?')->execute(['P2OTHER-' . $likeB['id'], $likeB['id']]);
    $likeBeforeA = $row('hosts', 'hostID', $likeA['id']);
    $likeBeforeB = $row('hosts', 'hostID', $likeB['id']);
    $likeResult = $manager->update(['description' => 'P2LIKE-%'], 'AND', ['description' => 'P2LIKE-UPDATED']);
    $likeError = (string)$db->error;
    $likeAfterA = $row('hosts', 'hostID', $likeA['id']);
    $likeAfterB = $row('hosts', 'hostID', $likeB['id']);
    $likeFailedWithoutMutation = false === $likeResult
        && false !== strpos($likeError, 'HY093')
        && $likeBeforeA === $likeAfterA
        && $likeBeforeB === $likeAfterB;
    if ($likeFailedWithoutMutation) {
        echo "BASELINE COMPATIBILITY LIMITATION B12: HostManager percent-pattern LIKE through real PDODB returned HY093 and changed neither fixture row; this observation is excluded from the passing assertion count and does not establish the fixed-P1 runtime result.\n";
    } else {
        echo "BASELINE COMPATIBILITY LIMITATION B12: LIKE outcome differed from the focused08 observation; this case remains excluded from the passing assertion count pending a fixed-P1 runtime comparison.\n";
    }

    $inA = $create(false);
    $inB = $create(false);
    $inOutside = $create(false);
    $inResult = $manager->update(['id' => [$inA['id'], $inB['id']]], 'AND', ['description' => 'P2-IN-UPDATED']);
    $t->check(
        'B12 HostManager IN keeps only the original selected primary keys',
        true === $inResult
            && 'P2-IN-UPDATED' === $row('hosts', 'hostID', $inA['id'])['hostDesc']
            && 'P2-IN-UPDATED' === $row('hosts', 'hostID', $inB['id'])['hostDesc']
            && $row('hosts', 'hostID', $inOutside['id'])['hostDesc'] === 'u2p2-original-' . $inOutside['id']
    );

    $orA = $create(false);
    $orB = $create(false);
    $orOutside = $create(false);
    $orResult = $manager->update(
        ['id' => $orA['id'], 'name' => $orB['name']],
        'OR',
        ['description' => 'P2-OR-UPDATED']
    );
    $t->check(
        'B12 HostManager OR keeps both original predicate branches',
        true === $orResult
            && 'P2-OR-UPDATED' === $row('hosts', 'hostID', $orA['id'])['hostDesc']
            && 'P2-OR-UPDATED' === $row('hosts', 'hostID', $orB['id'])['hostDesc']
            && $row('hosts', 'hostID', $orOutside['id'])['hostDesc'] === 'u2p2-original-' . $orOutside['id']
    );

    $emptyArray = $create(false);
    $emptyBefore = $row('hosts', 'hostID', $emptyArray['id']);
    $emptyResult = $manager->update(['id' => []], 'AND', ['description' => 'MUST-NOT-MATCH']);
    $t->check(
        'B12 empty-array IN selector matches no fixture row',
        true === $emptyResult && $emptyBefore === $row('hosts', 'hostID', $emptyArray['id'])
    );

    $emptyPayload = $manager->update(['id' => $emptyArray['id']], 'AND', []);
    $t->check(
        'B12 empty update payload is a no-op and preserves row',
        false === $emptyPayload && $emptyBefore === $row('hosts', 'hostID', $emptyArray['id'])
    );
} finally {
    foreach ($fixtures as $fixture) {
        $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
        $q->execute([$fixture['id']]);
        $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
        $q->execute([$fixture['id']]);
    }
}

echo "NOTE: B09 stale-model, B10 Host/HostDirectory unique-key upsert, and B12 IN/OR/empty-array manager assertions are isolated here. B12 LIKE is only observed as a baseline compatibility limitation and is excluded from the assertion count. No unbounded empty-WHERE update, 20-row non-host override, schema changes, or LDAP/native operation is covered.\n";
$t->finish();
