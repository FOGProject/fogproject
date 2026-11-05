<?php
/**
 * FOGCron: a bad row must throw, a ranged step must step, Sunday is Sunday.
 *
 * One stored cron row used to be able to stop all scheduling on a server:
 *
 * - A schedule with no matching date (`0 0 30 2 *`, or a minute of `99`,
 *   which the power-management pages store after only _sanitizeCronField())
 *   made parse() loop forever.
 * - A `/0` step threw DivisionByZeroError, an \Error no catch in FOG sees.
 * - TaskScheduler caught once around its whole pass, so either one ended the
 *   pass and skipped every later task, on every cycle.
 *
 * Separately, `0-30/10` matched every value from 0 to 30, and Sunday matched
 * only when the weekday field was exactly `0`: `0,6` was Saturday only, `0-6`
 * never Sunday, and `-1` (Sunday to the FOG client) was Monday here.
 *
 * Every parse() call that could hang runs in a child php process with a
 * timeout, so a hang is a failure and not a stuck suite. _fit() and
 * _dowFits() are called through reflection, so their results do not depend
 * on the clock. The TaskScheduler and Route checks read the source, in the
 * manner of tests/group-power-management-is-a-grant.test.php.
 *
 * Usage: php tests/fogcron-schedules.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('fogcron-schedules');

// Child mode: parse one expression and report what happened on one line.
// The parent reads that line; it never calls a parse() that could hang.
if (isset($argv[1]) && '--parse' === $argv[1]) {
    try {
        $ts = \FOG\Util\FOGCron::parse((string)$argv[2]);
        echo 'RETURNED ' . $ts . "\n";
    } catch (\Throwable $e) {
        echo 'THREW ' . get_class($e) . "\n";
    }
    exit(0);
}

$t = new FogChecks();
$root = dirname(__DIR__);
$web = $root . '/packages/web';

/**
 * Run parse($cron) in a child php, killed after $timeout seconds.
 *
 * @param string $cron    the expression
 * @param int    $timeout seconds before the child counts as hung
 *
 * @return array [string result line or 'TIMEOUT', float seconds taken]
 */
$parseInChild = static function ($cron, $timeout) {
    $cmd = [PHP_BINARY, __FILE__, '--parse', $cron];
    // PHP 7.4 proc_open() takes a string command; escape each argument.
    $cmd = implode(' ', array_map('escapeshellarg', $cmd));
    $proc = proc_open(
        'exec ' . $cmd,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($proc)) {
        return ['NOSTART', 0.0];
    }
    stream_set_blocking($pipes[1], false);
    $start = microtime(true);
    $out = '';
    do {
        $out .= (string)stream_get_contents($pipes[1]);
        $status = proc_get_status($proc);
        if (!$status['running']) {
            break;
        }
        if (microtime(true) - $start > $timeout) {
            proc_terminate($proc, 9);
            proc_close($proc);
            return ['TIMEOUT', microtime(true) - $start];
        }
        usleep(20000);
    } while (true);
    $out .= (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return [trim($out), microtime(true) - $start];
};

// ---------------------------------------------------------------------
// A. A schedule that never matches throws, within a bound.

foreach (['0 0 30 2 *', '0 0 31 4 *'] as $cron) {
    list($res, $secs) = $parseInChild($cron, 60);
    $t->check(
        "parse('$cron') throws InvalidArgumentException within 60s"
        . " (got $res after " . round($secs, 1) . 's)',
        'THREW InvalidArgumentException' === $res
    );
}

foreach (['99 * * * *', '* 24 * * *', '* * 32 * *'] as $cron) {
    list($res, $secs) = $parseInChild($cron, 60);
    $t->check(
        "parse('$cron') throws InvalidArgumentException within 1s"
        . " (got $res after " . round($secs, 1) . 's)',
        'THREW InvalidArgumentException' === $res && $secs < 1.0
    );
}

foreach (['*', '0', '59', '*/5', '1-5', '1,15', '0-30/10'] as $field) {
    $t->check(
        "checkMinutesField('$field') accepts a value the picker writes",
        true === \FOG\Util\FOGCron::checkMinutesField($field)
    );
}

list($res) = $parseInChild('0 0 29 2 *', 60);
$leap = false;
if (preg_match('/^RETURNED (\d+)$/', $res, $m)) {
    // niceDate(), not date_default_timezone_get(): parse() steps in FOG's
    // own zone, so the day must be read back in that zone too.
    $leap = '02-29' === \FOG\Base\FOGBase::niceDate()
        ->setTimestamp((int)$m[1])
        ->format('m-d');
}
$t->check(
    "parse('0 0 29 2 *') returns a timestamp on a Feb 29 (got $res)",
    $leap
);

// ---------------------------------------------------------------------
// B. A step of 0 is refused, not divided by.

$t->check(
    "checkMinutesField('*/0') is false",
    false === \FOG\Util\FOGCron::checkMinutesField('*/0')
);
$t->check(
    "checkHoursField('*/0') is false",
    false === \FOG\Util\FOGCron::checkHoursField('*/0')
);
$t->check(
    "checkDOWField('*/0') is false",
    false === \FOG\Util\FOGCron::checkDOWField('*/0')
);
list($res) = $parseInChild('*/0 * * * *', 60);
$t->check(
    "parse('*/0 * * * *') throws no \\Error (got $res)",
    0 === strpos($res, 'RETURNED ')
    || 'THREW InvalidArgumentException' === $res
);

// ---------------------------------------------------------------------
// C. _fit(): a range with a step steps from the range's low end.

$fit = static function ($str, $num) {
    return FogTestHarness::callStatic('FOG\Util\FOGCron', '_fit', [$str, $num]);
};
$fitCases = [
    ['0-30/10', 7, false],
    ['0-30/10', 20, true],
    ['5-30/10', 15, true],
    ['5-30/10', 10, false],
    ['*/15', 30, true],
    ['5/15', 20, true],
    ['1-5', 3, true],
    ['1,3', 2, false],
];
foreach ($fitCases as $c) {
    list($str, $num, $want) = $c;
    $t->check(
        "_fit('$str', $num) is " . var_export($want, true),
        $want === (bool)$fit($str, $num)
    );
}

// ---------------------------------------------------------------------
// D. _dowFits(): Sunday matches as Sunday in every weekday form.

// Null when the helper is missing, so each case fails rather than the run.
$dowFits = static function ($field, $iso) {
    try {
        return (bool)FogTestHarness::callStatic(
            'FOG\Util\FOGCron',
            '_dowFits',
            [$field, $iso]
        );
    } catch (\ReflectionException $e) {
        return null;
    }
};
$dowCases = [
    // $isoDow => [fields that match, fields that do not]
    7 => [['0', '7', '-1', '0,6', '0-6', '6-7', '*/2'], ['1-5', '6', '1']],
    1 => [['1', '1-5', '0-6'], ['-1', '0', '7', '0,6']],
    6 => [['0,6', '6', '6-7', '0-6'], ['0', '7']],
];
$days = [1 => 'Monday', 6 => 'Saturday', 7 => 'Sunday'];
foreach ($dowCases as $iso => $sets) {
    foreach ([true, false] as $want) {
        foreach ($sets[$want ? 0 : 1] as $field) {
            $got = $dowFits($field, $iso);
            $t->check(
                "_dowFits('$field', $iso) on {$days[$iso]} is "
                . var_export($want, true),
                $want === $got
            );
        }
    }
}

// ---------------------------------------------------------------------
// The picker comments, and the static checks of the callers.

// Comments stripped before any behavioral grep, as in
// group-power-management-is-a-grant.test.php.
$strip = static function ($php) {
    $out = '';
    foreach (token_get_all($php) as $token) {
        if (is_array($token)
            && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
        ) {
            continue;
        }
        $out .= is_array($token) ? $token[1] : $token;
    }
    return $out;
};

$wrongClaim = '/picker\s+writes\s+-1|reads\s+-1\s+the\s+way\s+FOG\'s\s+cron\s+picker'
    . '\s+writes/i';
foreach ([
    'Assign/Resolver.php',
    'Items/GroupPowerManagement.php',
] as $rel) {
    $src = (string)file_get_contents($web . '/src/' . $rel);
    $t->check(
        "$rel no longer says the cron picker writes -1",
        !preg_match($wrongClaim, preg_replace('/\s*\*\s+/', ' ', $src))
    );
}

// TaskScheduler: a try and a catch (\Exception inside each of the three
// per-task loops, so a getTimer() throw skips one task, not the pass.
$sched = $strip(
    (string)file_get_contents($web . '/src/Service/TaskScheduler.php')
);
foreach ([
    '$ScheduledTasks->data as $Task',
    '$PMTasks->data as $Task',
    '$GroupPMGrants as $Task',
] as $loop) {
    $at = strpos($sched, 'foreach (' . $loop . ')');
    $body = '';
    if (false !== $at) {
        // The loop body: from its opening brace to the matching close.
        $open = strpos($sched, '{', $at);
        $depth = 0;
        for ($i = $open, $n = strlen($sched); $i < $n; $i++) {
            if ('{' === $sched[$i]) {
                $depth++;
            } elseif ('}' === $sched[$i]) {
                $depth--;
                if (0 === $depth) {
                    $body = substr($sched, $open, $i - $open + 1);
                    break;
                }
            }
        }
    }
    $t->check(
        "TaskScheduler: foreach ($loop) holds its own try and catch (\\Exception",
        '' !== $body
            && preg_match('/\btry\s*\{/', $body)
            && preg_match('/catch\s*\(\s*\\\\Exception\b/', $body)
            && false !== strpos($body, 'getTimer()')
    );
}

// Route: the grid formatter and the scheduledtask serializer.
$route = $strip((string)file_get_contents($web . '/src/Router/Route.php'));
$t->check(
    'Route grid formatter catches InvalidArgumentException from FOGCron::parse()',
    (bool)preg_match(
        '/try\s*\{\s*\$date\s*=\s*FOGCron::parse\(\$cronstr\);\s*\}\s*'
        . 'catch\s*\(\s*\\\\InvalidArgumentException\s+\$\w+\s*\)\s*\{\s*'
        . 'return\s+_\(\'Invalid schedule\'\);/',
        $route
    )
);
$t->check(
    'Route scheduledtask serializer catches it from getTime() and sets runtime null',
    (bool)preg_match(
        '/try\s*\{\s*(\$\w+)\s*=\s*\$class->getTime\(\);\s*\}\s*'
        . 'catch\s*\(\s*\\\\InvalidArgumentException\s+\$\w+\s*\)\s*\{\s*'
        . '\1\s*=\s*null;\s*\}/',
        $route,
        $m
    )
    && (bool)preg_match(
        '/\'runtime\'\s*(?:=>|\]\s*=)\s*' . preg_quote($m[1], '/') . '\b/',
        $route
    )
);

$t->finish();
