<?php
/** B36 real insertBatch table-override guards and benign OUI staging control. */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 batch overrides: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('batch override test requires explicit scratch opt-in and mysql DSN');
}
require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-batch-overrides');
$pdo = new PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('batch override test requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new RuntimeException('DATABASE_NAME differs from validated scratch DB');
}
$link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);
$t = new FogChecks();

$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};
do {
    $hostID = random_int(1500000000, 1800000000);
    $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
    $q->execute([$hostID]);
} while ((int)$q->fetchColumn() !== 0);
$hostName = 'P2OV' . strtoupper(bin2hex(random_bytes(4)));
$fingerprint = hash('sha256', random_bytes(32));
$target = [
    'guid' => $guid(),
    'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
    'account' => $hostName . '$'
];
$config = ['version' => 1, 'protocol' => 2, 'name' => $hostName, 'domain' => 'u2p2.invalid'];
$state = [
    'version' => 1, 'protocol' => 2, 'phase' => 'started',
    'attempt_id' => bin2hex(random_bytes(16)), 'host_id' => $hostID,
    'fingerprint' => $fingerprint, 'operation' => 'rebind', 'name' => $hostName,
    'target' => $target, 'last_deploy_task_id' => 0, 'trigger_task_id' => 0
];
$hostInserted = false;
$directoryInserted = false;
$tempTable = 'oui_temp_u2p2_' . bin2hex(random_bytes(6));
$tempTableCreated = false;
try {
    $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,1,?,?,?)'
    )->execute([$hostID, $hostName, 'u2p2.invalid', 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $hostInserted = true;
    $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),?,?)'
    )->execute([$hostID, 'u2p2.invalid', 'CN=' . $hostName . ',OU=Computers,DC=u2p2,DC=invalid',
        $hostName . '$', json_encode($config, JSON_THROW_ON_ERROR), json_encode($state, JSON_THROW_ON_ERROR)]);
    $directoryInserted = true;
    $rawHost = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
    $rawHost->execute([$hostID]);
    $hostNameBefore = $rawHost->fetchColumn();
    $rawDirectory = $pdo->prepare('SELECT hdID,hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID=?');
    $rawDirectory->execute([$hostID]);
    $directoryBefore = $rawDirectory->fetch(PDO::FETCH_ASSOC);

    // This is the valid HostManager map compiled to its explicit physical
    // table argument. The duplicate-PK upsert would rename a protected Host.
    $hostManagerReason = '';
    try {
        (new FOG\Managers\HostManager())->insertBatch(
            ['id', 'name'], [[$hostID, 'P2OVX' . strtoupper(bin2hex(random_bytes(3)))]], 'hosts'
        );
    } catch (Throwable $e) {
        $hostManagerReason = $e->getMessage();
    }
    $rawHost->execute([$hostID]);
    $hostNameAfter = $rawHost->fetchColumn();
    $t->check('B36 HostManager insertBatch explicit hosts override cannot rename protected Host',
        'directory_host_operation_protected' === $hostManagerReason
        && $hostNameBefore === $hostNameAfter);

    // The HostDirectoryManager map is valid for this physical table. An
    // insert/upsert carrying authority state must not edit the protected row.
    $changedState = $state;
    $changedState['phase'] = 'failed';
    $hdManagerReason = '';
    try {
        (new FOG\Managers\HostDirectoryManager())->insertBatch(
            ['id', 'rejoinState'], [[(int)$directoryBefore['hdID'], json_encode($changedState, JSON_THROW_ON_ERROR)]],
            'hostDirectory'
        );
    } catch (Throwable $e) {
        $hdManagerReason = $e->getMessage();
    }
    $rawDirectory->execute([$hostID]);
    $directoryAfter = $rawDirectory->fetch(PDO::FETCH_ASSOC);
    $t->check('B36 HostDirectoryManager insertBatch explicit hostDirectory override preserves authority history',
        'directory_authority_history_protected' === $hdManagerReason
        && $directoryBefore === $directoryAfter);

    // OUIManager's supported side-table override is the established staging
    // path used by FOGConfigurationPage. Preserve its real compiler behavior.
    $pdo->exec('CREATE TABLE `' . $tempTable . '` LIKE `oui`');
    $tempTableCreated = true;
    $prefix = strtoupper(bin2hex(random_bytes(4)));
    $vendor = 'U2P2 harmless override ' . bin2hex(random_bytes(4));
    $batchResult = (new FOG\Managers\OUIManager())->insertBatch(
        ['prefix', 'name'], [[$prefix, $vendor]], $tempTable
    );
    $ouiQ = $pdo->prepare('SELECT COUNT(*) FROM `' . $tempTable . '` WHERE ouiMACPrefix=? AND ouiMan=?');
    $ouiQ->execute([$prefix, $vendor]);
    $t->check('B36 supported OUIManager side-table override retains ordinary batch insert behavior',
        is_array($batchResult) && 1 === (int)$batchResult[1] && 1 === (int)$ouiQ->fetchColumn());

    echo "NOTE: cross-manager HostManager->hostDirectory and HostDirectoryManager->hosts column overrides are not valid baseline SQL: their actual mapped columns belong to the source manager's schema. No synthetic remapping was introduced.\n";
} finally {
    if ($tempTableCreated) {
        $pdo->exec('DROP TABLE IF EXISTS `' . $tempTable . '`');
    }
    if ($directoryInserted) {
        $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?')->execute([$hostID]);
    }
    if ($hostInserted) {
        $pdo->prepare('DELETE FROM hosts WHERE hostID=?')->execute([$hostID]);
    }
}
$t->finish();
