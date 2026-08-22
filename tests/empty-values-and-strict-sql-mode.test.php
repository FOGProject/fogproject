<?php
/**
 * FOG must run under the server's own sql_mode, and save() must write values
 * every column type can actually hold.
 *
 * GH-1245. `PDODB::_connect()` issued `SET SESSION sql_mode=''` on every
 * connection from 13661edb (2016-05-02, "Try to set sql_mode to non-strict
 * which should allow 5.7 mysql to operate") until this change. That commit
 * shipped the blanket clear with a TARGETED mode commented out one line above
 * it, and the targeted one KEPT `STRICT_TRANS_TABLES` -- so the intent was
 * never to disable validation.
 *
 * For nine years it meant every statement FOG issued ran with the server's
 * checks off: truncations, out-of-range numerics and invalid enum members
 * were coerced silently and surfaced only as warnings nothing reads.
 *
 * What actually needed fixing was `FOGController::save()`, which wrote `''`
 * for every unset optional field whose key does not end in "id". `''` is a
 * value only a string column can hold. `emptyValueFor()` writes down the
 * coercion the server was already performing:
 *
 *   date/time  ->  NULL, or omitted when NOT NULL so the DEFAULT applies
 *   integer    ->  0
 *   enum/set   ->  the first member
 *   otherwise  ->  '', which is what a string column wanted all along
 *
 * This branch has no commons/schema-expected.php and no SchemaReconciler, so
 * the types come from `information_schema` at runtime -- which means the
 * per-column assertion 1.6's equivalent test makes cannot be made here
 * without a database. What is checked instead is that every piece of the
 * mechanism is still in place, because each one failing is silent:
 *
 *   - the clear does not come back;
 *   - the '' arm calls emptyValueFor() rather than assigning '';
 *   - emptyValueFor() still has a branch per type family;
 *   - a null is bound rather than dropped from the statement;
 *   - a null FILTER reads as IS NULL in all four query builders;
 *   - niceDate() still reads empty as "no value", and nothing in the tree
 *     passes '' to it meaning "now";
 *   - schema step 284 still makes the eleven date columns nullable.
 *
 * The empirical half is not here because it needs two database servers:
 * scripts/background_scripts/probe_sql_mode_removal_15_1245.sh boots the real
 * stack against a replayed schema on MySQL 8 and MariaDB 11.8 under their own
 * default sql_modes and sweeps the read and write surfaces.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$failures = array();
$checks = 0;

/**
 * Source with comments removed, so a commented-out line can neither satisfy
 * a check nor fail one.
 *
 * @param string $file the file to read
 *
 * @return string
 */
function evStrip($file)
{
    $clean = '';
    foreach (token_get_all(file_get_contents($file)) as $token) {
        if (is_array($token)
            && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)
        ) {
            continue;
        }
        $clean .= is_array($token) ? $token[1] : $token;
    }
    return $clean;
}

/**
 * Records a check.
 *
 * @param bool   $ok      whether it passed
 * @param string $message what failed, stated as the defect
 *
 * @return void
 */
function evCheck($ok, $message)
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $message;
    }
}

// ---------------------------------------------------------------
// 1. The clear does not come back.
// ---------------------------------------------------------------
$pdodb = evStrip($web . '/lib/db/pdodb.class.php');
evCheck(
    !preg_match('#SET\s+SESSION\s+sql_mode#i', $pdodb),
    'PDODB sets a session sql_mode again. FOG ran for nine years with the '
    . "server's checks off; every value it stored was unvalidated. See "
    . 'GH-1245 and the comment in _connect().'
);

// ---------------------------------------------------------------
// 2. save() asks emptyValueFor() what an empty field becomes.
// ---------------------------------------------------------------
//
// save() still asks the question; the seam that answers it moved to FOGBase
// when insertBatch() needed it too. FOGController and FOGManagerController
// are siblings, so a helper only one of them can reach is a helper the other
// write path silently does without -- which is why a strict server rejected
// saving FOG settings while saving a host was fine. Both files are read
// here, so a check does not care which one a given piece lives in.
$controller = evStrip($web . '/lib/fog/fogcontroller.class.php')
    . evStrip($web . '/lib/fog/fogbase.class.php');
$squashed = preg_replace('#\s+#', '', $controller);

evCheck(
    strpos($squashed, '$val=self::emptyValueFor($this->databaseTable,$column)') !== false,
    "FOGController::save() no longer asks emptyValueFor() what an empty "
    . 'optional field should be written as. Assigning \'\' directly is the '
    . 'GH-1245 bug: \'\' is a value only a string column can hold.'
);

// ---------------------------------------------------------------
// 3. Every type family still has a branch.
//
// A family losing its branch falls through to '', which the server refuses
// under a strict mode and silently coerces without one -- the whole bug.
// ---------------------------------------------------------------
$at = strpos($squashed, 'staticfunctionemptyValueFor($table,$column)');
evCheck($at !== false, 'emptyValueFor() is gone');
$body = false === $at ? '' : substr($squashed, $at, 900);

foreach (array(
    'date/time' => 'datetime|timestamp|date',
    'integer' => 'tiny|small|medium|big',
    'enum/set' => 'enum|set',
) as $family => $needle) {
    evCheck(
        '' !== $body && strpos($body, $needle) !== false,
        sprintf(
            'emptyValueFor() lost its %s branch, so those columns would be '
            . "written '' again",
            $family
        )
    );
}
evCheck(
    '' !== $body && substr_count($body, 'return\'\';') >= 2,
    "emptyValueFor() no longer falls back to '' -- a string column, and an "
    . 'unknown one, must keep the behaviour that shipped before GH-1245'
);

// ---------------------------------------------------------------
// 4. A null is BOUND, not dropped.
//
// Omitting the column leaves ON DUPLICATE KEY UPDATE with nothing to say
// about it, so an existing date could never be cleared: the write reports
// success and changes nothing.
// ---------------------------------------------------------------
evCheck(
    strpos($squashed, 'if($val===null&&!$writeNull){continue;}') !== false,
    'FOGController::save() drops every null again, so an emptied date column '
    . 'can never be cleared -- the write reports success and changes nothing'
);
evCheck(
    strpos($squashed, '$writeNull=(null===$val)&&self::columnIsNullable(') !== false,
    'save() no longer checks that the column can hold NULL before binding '
    . 'one. Binding a NULL into a NOT NULL column is error 1048; omitting it '
    . "lets the server's DEFAULT apply, which is what those columns want."
);

// ---------------------------------------------------------------
// 5. A null FILTER reads as IS NULL, in every builder.
//
// Bound as a placeholder it becomes `col = NULL`, which is never true, so the
// query silently returns nothing. It has real callers now: the date columns
// that carried '0000-00-00 00:00:00' as their "not yet" sentinel hold NULL
// from step 284 on, and TaskingElement::imageLog() looks for exactly those.
// ---------------------------------------------------------------
$manager = evStrip($web . '/lib/fog/fogmanagercontroller.class.php');
$mSquashed = preg_replace('#\s+#', '', $manager);
$nullArms = substr_count($mSquashed, 'elseif(null===$value){');
evCheck(
    $nullArms >= 4,
    sprintf(
        'FOGManagerController has %d null-filter arm(s), expected 4 -- '
        . 'find(), count(), perform_update() and distinct() each build their '
        . 'own WHERE, and a builder without one silently matches no rows',
        $nullArms
    )
);
evCheck(
    strpos($mSquashed, '$value=(null===$value)?null:trim($value);') !== false,
    'FOGManagerController::perform_update() trims the value again. '
    . "trim(null) is '' -- and a PHP 8.1 deprecation -- which puts the zero "
    . 'date straight back into a column being cleared.'
);

// ---------------------------------------------------------------
// 6. Empty means "no value", and nothing asks for now by saying ''.
// ---------------------------------------------------------------
$fogbase = evStrip($web . '/lib/fog/fogbase.class.php');
evCheck(
    strpos(preg_replace('#\s+#', '', $fogbase), "\$date='0000-00-00 00:00:00';") !== false
    || strpos(preg_replace('#\s+#', '', $fogbase), '$date=\'0000-00-0000:00:00\';') !== false,
    'FOGBase::niceDate() lost its empty guard. new DateTime(\'\') and '
    . 'new DateTime(null) both return the CURRENT time, so a date column '
    . 'holding no value renders as a real timestamp.'
);

$literals = array();
$iter = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($web, \FilesystemIterator::SKIP_DOTS)
);
foreach ($iter as $file) {
    if (substr($file->getFilename(), -4) !== '.php') {
        continue;
    }
    $clean = evStrip($file->getPathname());
    if (preg_match('#(formatTime|niceDate)\(\s*(\'\'|"")\s*[,)]#', $clean)) {
        $literals[] = str_replace($root . '/', '', $file->getPathname());
    }
}
evCheck(
    count($literals) === 0,
    sprintf(
        "passing '' to formatTime()/niceDate() means \"no value\" since "
        . 'GH-1245, not "now". Say \'now\'. Found in: %s',
        implode(', ', $literals)
    )
);

// ---------------------------------------------------------------
// 7. Step 284 makes the eleven date columns nullable.
//
// Without it save() writing a real NULL is worse than a no-op: an explicit
// NULL into a NOT NULL column errors under a strict mode and is coerced
// straight back to the zero date without one.
//
// snapinTasks.stCheckinDate and userTracking.utDateTime are deliberately
// absent -- both declare DEFAULT current_timestamp(), so save() omits them
// and the server supplies a real value.
// ---------------------------------------------------------------
$schemaSrc = file_get_contents($web . '/commons/schema.php');
foreach (array(
    'hosts' => 'hostLastDeploy',
    'images' => 'imageLastDeploy',
    'imagingLog' => 'ilFinishTime',
    'inventory' => 'iDeleteDate',
    'multicastSessions' => 'msStartDateTime',
    'snapinTasks' => 'stCompleteDate',
    'tasks' => 'taskScheduledStartTime',
    'userTracking' => 'utDate',
) as $table => $column) {
    evCheck(
        (bool) preg_match(
            '#MODIFY COLUMN `' . $column . '` \w+ NULL DEFAULT NULL#',
            $schemaSrc
        ),
        sprintf(
            'no schema step makes %s.%s nullable, so save() writing NULL for '
            . 'an empty date is an error there',
            $table,
            $column
        )
    );
}
foreach (array('stCheckinDate', 'utDateTime') as $exempt) {
    evCheck(
        !preg_match('#MODIFY COLUMN `' . $exempt . '` \w+ NULL#', $schemaSrc),
        sprintf(
            '%s was made nullable. It declares DEFAULT current_timestamp(), '
            . 'so save() omits it and the server fills it in; making it '
            . 'nullable turns a real value into no value.',
            $exempt
        )
    );
}

// The gate is only meaningful if the scan reached the tree at all.
evCheck(
    strlen($schemaSrc) > 100000 && strlen($controller) > 10000,
    'the scan did not reach the sources and would pass vacuously'
);

if (count($failures) > 0) {
    echo 'FAIL empty-values-and-strict-sql-mode (' . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS empty-values-and-strict-sql-mode ($checks checks)\n";
exit(0);
