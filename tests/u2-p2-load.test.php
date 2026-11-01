<?php
/**
 * Narrow scratch-DB latency samples for four U2-P2 writer boundaries.
 * Not an HTTP-poll benchmark or a load-capacity proof; no agent/native/LDAP is started.
 */
$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 load: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('U2-P2 load requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-load');
$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('U2-P2 load requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
}
$intentTable = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
);
$intentTable->execute(['directoryPlacementIntent']);
if (1 !== (int)$intentTable->fetchColumn()) {
    throw new \RuntimeException('scratch intent schema must be installed before U2-P2 load sampling');
}

// Install the real PDODB over this exact PDO so query() reaches NameFence.
$link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);

const U2P2_LOAD_SAMPLES = 10;
const U2P2_LOAD_LOCK_WAIT_MS = 3000;
const U2P2_LOAD_TIMEOUT_INFERENCE_MS = 2500;

$fixtureIds = [];
$fixtureNames = [];
$barrierDirs = [];
$categories = [
    'poll_authority_boundary' => ['uncontended' => [], 'contended' => []],
    'host_manager_update_single' => ['uncontended' => [], 'contended' => []],
    'host_manager_insertBatch_20' => ['uncontended' => [], 'contended' => []],
    'host_destroy_inactive' => ['uncontended' => [], 'contended' => []]
];
$fatalErrors = [];

$newName = static function () {
    // Fifteen characters, within hosts.hostName's 16-character limit.
    return 'P2L' . strtoupper(bin2hex(random_bytes(6)));
};
$newHost = static function () use ($pdo, $newName, &$fixtureIds, &$fixtureNames) {
    $name = $newName();
    $insert = $pdo->prepare(
        'INSERT INTO hosts (hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,\'0\',\'\',\'\',\'\')'
    );
    $insert->execute([$name]);
    $id = (int)$pdo->lastInsertId();
    $fixtureIds[] = $id;
    $fixtureNames[] = $name;
    return ['id' => $id, 'name' => $name];
};
$rememberBatchIds = static function (array $names) use ($pdo, &$fixtureIds, &$fixtureNames) {
    foreach ($names as $name) {
        $fixtureNames[] = $name;
        $q = $pdo->prepare('SELECT hostID FROM hosts WHERE hostName=?');
        $q->execute([$name]);
        $id = $q->fetchColumn();
        if (false !== $id) {
            $fixtureIds[] = (int)$id;
        }
    }
};
$measure = static function (callable $call, callable $verify, $contended = false) {
    $started = hrtime(true);
    $value = null;
    $exception = null;
    try {
        $value = $call();
    } catch (\Throwable $e) {
        $exception = $e;
    }
    $elapsed = (hrtime(true) - $started) / 1000000;
    $success = false;
    if (null === $exception) {
        try {
            $success = (bool)$verify($value);
        } catch (\Throwable $e) {
            $exception = $e;
        }
    }
    $denied = !$success;
    $reason = null === $exception ? null : get_class($exception);
    $detail = null === $exception ? null : $exception->getMessage();
    $timeout = (bool)$contended && $denied && $elapsed >= U2P2_LOAD_TIMEOUT_INFERENCE_MS;
    return [
        'elapsed_ms' => round($elapsed, 3),
        'denied' => $denied,
        'timeout_inferred' => $timeout,
        'exception_class' => $reason,
        'exception_reason' => $detail,
        'result_type' => gettype($value)
    ];
};
$newBarrier = static function () use (&$barrierDirs) {
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'u2-p2-load-' . bin2hex(random_bytes(8));
    if (!mkdir($dir, 0700)) {
        throw new \RuntimeException('cannot create isolated load barrier directory');
    }
    $barrierDirs[] = $dir;
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/fixtures/u2-p2-load-worker.php', $dir],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new \RuntimeException('cannot start scratch GET_LOCK worker');
    }
    fclose($pipes[0]);
    $readyPath = $dir . DIRECTORY_SEPARATOR . 'ready.json';
    $deadline = microtime(true) + 8.0;
    $ready = null;
    while (microtime(true) < $deadline) {
        if (is_file($readyPath)) {
            $decoded = json_decode((string)file_get_contents($readyPath), true);
            if (is_array($decoded) && true === ($decoded['ready'] ?? false)) {
                $ready = $decoded;
                break;
            }
        }
        $status = proc_get_status($process);
        if (empty($status['running'])) {
            break;
        }
        usleep(10000);
    }
    if (null === $ready) {
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'release', 'stop', LOCK_EX);
        foreach ([1, 2] as $pipeIndex) {
            stream_get_contents($pipes[$pipeIndex]);
            fclose($pipes[$pipeIndex]);
        }
        proc_close($process);
        throw new \RuntimeException('scratch GET_LOCK worker did not signal an acquired lock');
    }
    return [$dir, $process, $pipes, $ready];
};
$releaseBarrier = static function (array $barrier) {
    list($dir, $process, $pipes) = $barrier;
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'release', 'go', LOCK_EX);
    $deadline = microtime(true) + 5.0;
    do {
        $status = proc_get_status($process);
        if (empty($status['running'])) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    if (!empty($status['running'])) {
        proc_terminate($process);
    }
    foreach ([1, 2] as $pipeIndex) {
        stream_get_contents($pipes[$pipeIndex]);
        fclose($pipes[$pipeIndex]);
    }
    proc_close($process);
};
$record = static function ($name, $kind, array $sample) use (&$categories) {
    $categories[$name][$kind][] = $sample;
};
$contendedSample = static function ($name, callable $call, callable $verify) use ($newBarrier, $releaseBarrier, $measure, $record, &$fatalErrors) {
    try {
        $barrier = $newBarrier();
    } catch (\Throwable $e) {
        $fatalErrors[] = 'barrier setup: ' . $e->getMessage();
        $record($name, 'contended', [
            'elapsed_ms' => 0.0,
            'denied' => true,
            'timeout_inferred' => false,
            'setup_error' => $e->getMessage()
        ]);
        return;
    }
    try {
        $sample = $measure($call, $verify, true);
    } finally {
        $releaseBarrier($barrier);
    }
    if (empty($sample['denied'])) {
        $fatalErrors[] = $name . ': operation unexpectedly passed while the global lock was held';
    }
    $record($name, 'contended', $sample);
};

$manager = new FOG\Managers\HostManager();
$authorityFixture = null;
try {
    $authorityFixture = $newHost();
    $authorityHost = new FOG\Items\Host($authorityFixture['id']);
    for ($i = 0; $i < U2P2_LOAD_SAMPLES; $i++) {
        $called = false;
        $record('poll_authority_boundary', 'uncontended', $measure(
            static function () use ($authorityHost, &$called) {
                return FOG\Agent\DirectoryRejoin::change(
                    $authorityHost,
                    static function (&$config, &$state) use (&$called) {
                        $called = true;
                        return 'authority_boundary_complete';
                    }
                );
            },
            static function ($value) use (&$called) {
                return $called && 'authority_boundary_complete' === $value;
            }
        ));
    }

    for ($i = 0; $i < U2P2_LOAD_SAMPLES; $i++) {
        $fixture = $newHost();
        $host = new FOG\Items\Host($fixture['id']);
        $nextName = $newName();
        $sample = $measure(
            static function () use ($manager, $fixture, $nextName) {
                return $manager->update(['id' => $fixture['id']], 'AND', ['name' => $nextName]);
            },
            static function ($value) use ($pdo, $fixture, $nextName) {
                $q = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
                $q->execute([$fixture['id']]);
                return true === $value && $nextName === $q->fetchColumn();
            }
        );
        $record('host_manager_update_single', 'uncontended', $sample);
        unset($host);
    }

    for ($i = 0; $i < U2P2_LOAD_SAMPLES; $i++) {
        $names = [];
        $values = [];
        for ($row = 0; $row < 20; $row++) {
            $names[] = $newName();
            $values[] = [$names[$row]];
        }
        $sample = $measure(
            static function () use ($manager, $values) {
                return $manager->insertBatch(['name'], $values);
            },
            static function ($value) {
                return is_array($value) && 2 === count($value) && 20 === (int)$value[1];
            }
        );
        $record('host_manager_insertBatch_20', 'uncontended', $sample);
        $rememberBatchIds($names);
    }

    for ($i = 0; $i < U2P2_LOAD_SAMPLES; $i++) {
        $fixture = $newHost();
        $host = new FOG\Items\Host($fixture['id']);
        $sample = $measure(
            static function () use ($host) {
                return FOG\Router\Route::asValue(static function () use ($host) {
                    $host->destroy();
                });
            },
            static function ($value) use ($pdo, $fixture) {
                $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
                $q->execute([$fixture['id']]);
                return 0 === (int)$q->fetchColumn();
            }
        );
        $record('host_destroy_inactive', 'uncontended', $sample);
        unset($host);
    }

    // One real lock-contention sample for each boundary. The worker's GET_LOCK
    // uses the production namespace and holds it on a separate PDO connection.
    $authorityHost = new FOG\Items\Host($authorityFixture['id']);
    $called = false;
    $contendedSample(
        'poll_authority_boundary',
        static function () use ($authorityHost, &$called) {
            return FOG\Agent\DirectoryRejoin::change(
                $authorityHost,
                static function (&$config, &$state) use (&$called) {
                    $called = true;
                    return 'authority_boundary_complete';
                }
            );
        },
        static function ($value) use (&$called) {
            return $called && 'authority_boundary_complete' === $value;
        }
    );

    $updateFixture = $newHost();
    $updateHost = new FOG\Items\Host($updateFixture['id']);
    $updateName = $newName();
    $contendedSample(
        'host_manager_update_single',
        static function () use ($manager, $updateFixture, $updateName) {
            return $manager->update(['id' => $updateFixture['id']], 'AND', ['name' => $updateName]);
        },
        static function ($value) use ($pdo, $updateFixture, $updateName) {
            $q = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
            $q->execute([$updateFixture['id']]);
            return true === $value && $updateName === $q->fetchColumn();
        }
    );
    unset($updateHost);

    $batchNames = [];
    $batchValues = [];
    for ($row = 0; $row < 20; $row++) {
        $batchNames[] = $newName();
        $batchValues[] = [$batchNames[$row]];
    }
    $contendedSample(
        'host_manager_insertBatch_20',
        static function () use ($manager, $batchValues) {
            return $manager->insertBatch(['name'], $batchValues);
        },
        static function ($value) {
            return is_array($value) && 2 === count($value) && 20 === (int)$value[1];
        }
    );
    $rememberBatchIds($batchNames);

    $deleteFixture = $newHost();
    $deleteHost = new FOG\Items\Host($deleteFixture['id']);
    $contendedSample(
        'host_destroy_inactive',
        static function () use ($deleteHost) {
            return FOG\Router\Route::asValue(static function () use ($deleteHost) {
                $deleteHost->destroy();
            });
        },
        static function ($value) use ($pdo, $deleteFixture) {
            $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
            $q->execute([$deleteFixture['id']]);
            return 0 === (int)$q->fetchColumn();
        }
    );
    unset($deleteHost);
} catch (\Throwable $e) {
    $fatalErrors[] = get_class($e) . ': ' . $e->getMessage();
} finally {
    foreach ($barrierDirs as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($dir);
        }
    }
    foreach (array_values(array_unique(array_filter($fixtureIds))) as $id) {
        foreach ([
            ['history', 'hSubjectType', 'hSubjectID', 'host'],
            ['auditLog', 'alSubjectType', 'alSubjectID', 'host']
        ] as $logTable) {
            list($table, $typeColumn, $idColumn, $type) = $logTable;
            $q = $pdo->prepare("DELETE FROM `$table` WHERE `$typeColumn`=? AND `$idColumn`=?");
            $q->execute([$type, $id]);
        }
        $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
        $q->execute([$id]);
        $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
        $q->execute([$id]);
    }
}

$summary = [];
foreach ($categories as $name => $data) {
    $all = array_merge($data['uncontended'], $data['contended']);
    $latencies = array_map(static function ($sample) {
        return (float)$sample['elapsed_ms'];
    }, $all);
    sort($latencies, SORT_NUMERIC);
    $count = count($latencies);
    $summary[$name] = [
        'uncontended_samples' => $data['uncontended'],
        'contended_samples' => $data['contended'],
        'denied_count' => count(array_filter($all, static function ($sample) {
            return !empty($sample['denied']);
        })),
        'timeout_count_inferred' => count(array_filter($all, static function ($sample) {
            return !empty($sample['timeout_inferred']);
        })),
        'latency_ms' => [
            'count' => $count,
            'min' => $count ? $latencies[0] : null,
            'median' => $count ? $latencies[(int)floor(($count - 1) / 2)] : null,
            'mean' => $count ? round(array_sum($latencies) / $count, 3) : null,
            'p95' => $count ? $latencies[(int)ceil(0.95 * $count) - 1] : null,
            'max' => $count ? $latencies[$count - 1] : null
        ]
    ];
}
$uncontendedFailures = 0;
$unexpectedContendedSuccesses = 0;
foreach ($categories as $data) {
    foreach ($data['uncontended'] as $sample) {
        $uncontendedFailures += !empty($sample['denied']) ? 1 : 0;
    }
    foreach ($data['contended'] as $sample) {
        $unexpectedContendedSuccesses += empty($sample['denied']) ? 1 : 0;
    }
}
$result = [
    'kind' => 'u2-p2-boundary-latency-samples',
    'database_prefix' => 'fog_mj1',
    'php_version' => PHP_VERSION,
    'scope' => 'real PDODB + scratch PDO; direct DirectoryRejoin::change authority boundary, HostManager update/batch, inactive Host destroy',
    'poll_boundary_label' => 'authority-boundary only; not end-to-end Agent HTTP poll',
    'uncontended_samples_per_category' => U2P2_LOAD_SAMPLES,
    'contended_samples_per_category' => 1,
    'global_lock_wait_seconds' => 3,
    'timeout_inference' => [
        'threshold_ms' => U2P2_LOAD_TIMEOUT_INFERENCE_MS,
        'rule' => 'count a denied contended sample as timeout-inferred when elapsed latency reaches the threshold'
    ],
    'categories' => $summary,
    'agent_local_unknown' => [
        'status' => 'NOT_MEASURED',
        'reason' => 'no Agent process was started by this test'
    ],
    'capacity_proof' => false,
    'notes' => [
        'scratch schema is read-only from this test; no DDL or reset is performed',
        'fixture cleanup is restricted to host IDs/names created by this test',
        'each contended worker holds the actual global GET_LOCK on a separate PDO connection'
    ],
    'fatal_errors' => $fatalErrors,
    'uncontended_failure_count' => $uncontendedFailures,
    'unexpected_contended_success_count' => $unexpectedContendedSuccesses
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
$passed = !$fatalErrors && !$uncontendedFailures && !$unexpectedContendedSuccesses;
if ($passed) {
    echo "PASS U2-P2 load boundary samples\n";
}
exit($passed ? 0 : 1);
