<?php
/**
 * A posted boot token finds the hostless task, and only that task.
 *
 * Forum 18086: FOS reports check-in, progress, completion and errors for an
 * unregistered machine with `fogtoken`. BootToken::taskFor() and
 * BootToken::resolve() are what every FOS endpoint now asks first. The cases
 * that must NOT resolve matter as much as the one that does: a wrong token, a
 * wrong MAC, a task that is no longer active, and a request with no token at
 * all (a registered host, which has to take the host path untouched).
 *
 * Part 1 drives the real BootToken methods against a fake `tasks` table that
 * reads the SQL it is sent (tests/lib/hostless-fixture.php), so a missing
 * state, MAC or NULL-host clause in the real query changes the answer.
 * Part 2 needs a real request, because the token is read with filter_input(),
 * which a CLI process cannot be given. It re-runs this file in a php-cgi child
 * and sends the POST body for real, then reads what the child reports. It
 * includes the actual body of service/progress.php. Part 2 SKIPs without
 * php-cgi, as tests/api-bad-request-statuses.test.php does.
 *
 * Usage: php tests/boot-token-lookup.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGBase;
use FOG\Items\TaskState;
use FOG\TaskHandling\BootToken;

require __DIR__ . '/lib/fog-test-harness.php';
require __DIR__ . '/lib/hostless-fixture.php';

const RAW = 'ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12';
const MAC = 'aa:bb:cc:dd:ee:ff';

/**
 * Runs one case in a php-cgi child and returns its marker lines.
 *
 * @param string $case the case name
 * @param string $body the urlencoded POST body
 *
 * @return array the lines the child printed that start with a marker
 */
function lookupChild($case, $body)
{
    $bin = trim((string)@shell_exec('command -v php-cgi 2>/dev/null'));
    $env = [
        'REDIRECT_STATUS' => '200',
        'SCRIPT_FILENAME' => __FILE__,
        'REQUEST_METHOD' => 'POST',
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'CONTENT_LENGTH' => (string)strlen($body),
        'QUERY_STRING' => 'case=' . rawurlencode($case),
        'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
    ];
    $pipes = [];
    $proc = proc_open(
        [$bin, '-q'],
        // stderr goes to the null device: a child that logs a lot of PHP
        // warnings would fill an unread pipe and block forever.
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
        null,
        $env
    );
    if (!is_resource($proc)) {
        return ['SPAWN FAILED'];
    }
    fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = '';
    fclose($pipes[1]);
    proc_close($proc);
    $lines = [];
    foreach (preg_split('/\r?\n/', $out) as $line) {
        if (preg_match('/^(##|RESOLVED|NONE|HASHQ|UPDATE|CRASH|ECHO)/', trim($line))) {
            $lines[] = trim($line);
        }
    }
    return $lines ?: ['NO MARKER: ' . trim(str_replace("\n", ' | ', $out . ' ' . $err))];
}

/**
 * The active hostless task every case starts from.
 *
 * @return array tasks keyed by id
 */
function freshTasks()
{
    return [7 => hostlessRow(['taskTokenHash' => hash('sha256', RAW)])];
}

// ---------------------------------------------------------------- child mode

if ('cli' !== PHP_SAPI) {
    FogTestHarness::boot('boot-token-lookup-child');
    $db = FogTestHarness::fakeDb();
    $tasks = freshTasks();
    $seen = [];
    $db->responder = hostlessResponder($tasks, $seen);
    $case = (string)($_GET['case'] ?? '');
    $hashQueries = function () use (&$seen) {
        $n = 0;
        foreach ($seen as $one) {
            if (false !== strpos($one[0], '`taskTokenHash` = :hash')) {
                $n++;
            }
        }
        return $n;
    };
    if ('resolve' === $case) {
        $Task = BootToken::resolve();
        if (null === $Task) {
            echo "NONE\n";
        } else {
            printf(
                "RESOLVED id=%d hostValid=%d\n",
                $Task->get('id'),
                FOGBase::$Host->isValid() ? 1 : 0
            );
        }
        echo 'HASHQ ' . $hashQueries() . "\n";
    } elseif ('ack' === $case) {
        // The hostless deploy already completed, and FOS retries its
        // checkout because the first reply was lost.
        $tasks[7]['taskStateID'] = TaskState::getCompleteState();
        $tasks[7]['taskCheckIn'] = FOGBase::niceDate()->format('Y-m-d H:i:s');
        // Prints '##' and exits when the token finds that task.
        FOG\TaskHandling\TaskQueue::ackIfAlreadyComplete();
        echo "NONE\n";
    } elseif ('progress' === $case) {
        // The real body of service/progress.php, from its require to its
        // closing exit. Cut by those anchors, so a change to the script is
        // what this runs.
        $src = file_get_contents(
            dirname(__DIR__) . '/packages/web/service/progress.php'
        );
        $start = strpos($src, "require '../commons/base.inc.php';");
        $end = strrpos($src, 'exit;');
        if (false === $start || false === $end) {
            echo "CRASH progress.php anchors not found\n";
            exit;
        }
        $body = substr(
            $src,
            $start + strlen("require '../commons/base.inc.php';"),
            $end - $start - strlen("require '../commons/base.inc.php';")
        );
        eval(
            'use FOG\Base\FOGCore; use FOG\Items\TaskType; '
            . 'use FOG\TaskHandling\BootToken; ' . $body
        );
        foreach ($seen as $one) {
            // save() is an upsert: INSERT ... ON DUPLICATE KEY UPDATE.
            if (0 === strpos($one[0], 'INSERT INTO `tasks`')
                && preg_match('#\(([^)]*)\)\s*VALUES#is', $one[0], $m)
            ) {
                preg_match_all('#`([^`]+)`#', $m[1], $names);
                echo 'UPDATE ' . json_encode(
                    array_combine($names[1], array_values($one[1]))
                ) . "\n";
            }
        }
        echo 'HASHQ ' . $hashQueries() . "\n";
    }
    exit;
}

// ---------------------------------------------------------------- part 1

FogTestHarness::boot('boot-token-lookup');
$db = FogTestHarness::fakeDb();
$t = new FogChecks();

$tasks = freshTasks();
$seen = [];
$db->responder = hostlessResponder($tasks, $seen);

$Task = BootToken::taskFor([MAC], RAW);
$t->check('the right token and MAC resolve the task', $Task->isValid());
$t->check('it is the hostless task', 7 === (int)$Task->get('id') && $Task->isHostless());
$t->check(
    'the MAC may be one of several the machine sent, in any case',
    BootToken::taskFor(['11:22:33:44:55:66', strtoupper(MAC)], RAW)->isValid()
);
$t->check(
    'a CheckedIn task still resolves',
    (function () use (&$tasks) {
        $tasks[7]['taskStateID'] = TaskState::getCheckedInState();
        return BootToken::taskFor([MAC], RAW)->isValid();
    })()
);
$t->check(
    'an In-Progress task still resolves',
    (function () use (&$tasks) {
        $tasks[7]['taskStateID'] = TaskState::getProgressState();
        return BootToken::taskFor([MAC], RAW)->isValid();
    })()
);

$tasks = freshTasks();
$other = str_repeat('cd', 32);
$t->check(
    'a wrong token does not resolve',
    !BootToken::taskFor([MAC], $other)->isValid()
);
$t->check(
    'the right token from another MAC does not resolve',
    !BootToken::taskFor(['11:22:33:44:55:66'], RAW)->isValid()
);
foreach ([
    'Complete' => TaskState::getCompleteState(),
    'Canceled' => TaskState::getCancelledState(),
] as $name => $state) {
    $tasks[7]['taskStateID'] = $state;
    $t->check(
        "a $name task does not resolve",
        !BootToken::taskFor([MAC], RAW)->isValid()
    );
}
$tasks = freshTasks();
$tasks[7]['taskHostID'] = 12;
$t->check(
    'a task that has a host is not found by a token',
    !BootToken::taskFor([MAC], RAW)->isValid()
);

// A malformed token or no MAC never reaches the database.
$tasks = freshTasks();
/**
 * How many statements the fake database has been sent that name the hash.
 *
 * @return int
 */
function hashQueriesSent()
{
    global $db;
    $n = 0;
    foreach ($db->log as $sql) {
        if (false !== strpos($sql, 'taskTokenHash')) {
            $n++;
        }
    }
    return $n;
}
$before = hashQueriesSent();
BootToken::taskFor([MAC], 'not-hex');
BootToken::taskFor([MAC], '');
BootToken::taskFor([], RAW);
$asked = hashQueriesSent() - $before;
$t->check('a malformed token, or no MAC, asks the database nothing', 0 === $asked);

// The token variant of the idempotent completion ack.
$tasks = freshTasks();
$tasks[7]['taskStateID'] = TaskState::getCompleteState();
$tasks[7]['taskCheckIn'] = FOGBase::niceDate()->format('Y-m-d H:i:s');
$t->check(
    'a Complete deploy checked in just now is acked',
    BootToken::completedRecently([MAC], RAW)
);
$t->check(
    'a Complete deploy is not acked for the wrong MAC',
    !BootToken::completedRecently(['11:22:33:44:55:66'], RAW)
);
$t->check(
    'a Complete deploy is not acked for the wrong token',
    !BootToken::completedRecently([MAC], $other)
);
$tasks[7]['taskCheckIn'] = FOGBase::niceDate('-2 days')->format('Y-m-d H:i:s');
$t->check(
    'a Complete deploy checked in two days ago is not acked',
    !BootToken::completedRecently([MAC], RAW)
);
$tasks[7]['taskCheckIn'] = FOGBase::niceDate()->format('Y-m-d H:i:s');
$tasks[7]['taskStateID'] = TaskState::getProgressState();
$t->check(
    'an In-Progress task is not acked as complete',
    !BootToken::completedRecently([MAC], RAW)
);

// ---------------------------------------------------------------- part 2

if ('' === trim((string)@shell_exec('command -v php-cgi 2>/dev/null'))) {
    echo "SKIP: no php-cgi binary, so the request-level cases were not run\n";
    $t->finish();
}

$post = function (array $fields) {
    return http_build_query($fields);
};

$lines = lookupChild('resolve', $post(['fogtoken' => RAW, 'mac' => MAC]));
$t->check(
    'a posted token with its MAC resolves, and the host is left empty'
    . ' (got: ' . implode(' | ', $lines) . ')',
    in_array('RESOLVED id=7 hostValid=0', $lines, true)
);
$lines = lookupChild('resolve', $post(['fogtoken' => $other, 'mac' => MAC]));
$t->check(
    'a posted wrong token resolves nothing (got: ' . implode(' | ', $lines) . ')',
    in_array('NONE', $lines, true)
);
$lines = lookupChild('resolve', $post(['fogtoken' => RAW, 'mac' => '11:22:33:44:55:66']));
$t->check(
    'a posted token from the wrong MAC resolves nothing'
    . ' (got: ' . implode(' | ', $lines) . ')',
    in_array('NONE', $lines, true)
);
// A registered host sends no token. It must take the host path, and the
// token lookup must not even be attempted.
$lines = lookupChild('resolve', $post(['mac' => MAC]));
$t->check(
    'a request with no token resolves nothing and never queries by token'
    . ' (got: ' . implode(' | ', $lines) . ')',
    in_array('NONE', $lines, true) && in_array('HASHQ 0', $lines, true)
);

// TaskQueue::ackIfAlreadyComplete() with a token: '##' for the Complete
// hostless deploy, and nothing (the normal flow carries on) for a wrong one.
$lines = lookupChild('ack', $post(['fogtoken' => RAW, 'mac' => MAC, 'type' => 'down']));
$t->check(
    "a retried checkout of a Complete hostless deploy is acked '##'"
    . ' (got: ' . implode(' | ', $lines) . ')',
    ['##'] === $lines
);
$lines = lookupChild('ack', $post(['fogtoken' => $other, 'mac' => MAC, 'type' => 'down']));
$t->check(
    'a wrong token is not acked (got: ' . implode(' | ', $lines) . ')',
    ['NONE'] === $lines
);
$lines = lookupChild('ack', $post(['fogtoken' => RAW, 'mac' => MAC, 'type' => 'up']));
$t->check(
    'an upload type is not acked from a token (got: ' . implode(' | ', $lines) . ')',
    ['NONE'] === $lines
);

// service/progress.php with a token saves the percentage on the hostless task.
$status = base64_encode('1.5@00:10@00:20@1G@2G@50');
$lines = lookupChild(
    'progress',
    $post(['fogtoken' => RAW, 'mac' => MAC, 'status' => $status])
);
$saved = '';
foreach ($lines as $line) {
    if (0 === strpos($line, 'UPDATE ')) {
        $saved = $line;
    }
}
$row = json_decode(substr($saved, strlen('UPDATE ')), true);
$t->check(
    'progress.php with a token saves the percent on the hostless task'
    . ' (got: ' . implode(' | ', $lines) . ')',
    is_array($row)
    && 7 === (int)$row['taskID']
    && 50 === (int)$row['taskPCT']
    && '2G' === $row['taskDataTotal']
    && (!array_key_exists('taskHostID', $row) || null === $row['taskHostID'])
);
$lines = lookupChild(
    'progress',
    $post(['fogtoken' => $other, 'mac' => MAC, 'status' => $status])
);
$wrote = false;
foreach ($lines as $line) {
    if (0 === strpos($line, 'UPDATE ')) {
        $wrote = true;
    }
}
$t->check('progress.php with a wrong token saves nothing', !$wrote);

$t->finish();
