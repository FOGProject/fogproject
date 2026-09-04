<?php
/**
 * ADR 0020 phase 5 and decision 5's writer half.
 *
 * Two changes that only make sense together, so they are pinned together.
 *
 * WRITERS STOP TRANSLATING. `history` used to store a sentence assembled
 * from _() fragments at write time, so the row read as whatever language
 * the operator who triggered it was using. Phase 4 made every reader build
 * the sentence from the frame instead; this is the other half -- the stored
 * text becomes a stable, machine-comparable record rather than one
 * person's locale. The four FOGController sites now call one helper and
 * the helper wraps nothing in _().
 *
 * The text is NOT emptied, and that is the part most likely to be
 * "tidied up" later by someone who reads the heading and not the reasoning.
 * Three readers still fall back to it -- History::summary() for any row it
 * cannot frame, the REST `info` field, and the search filter -- so an
 * empty column would break all three to save nothing.
 *
 * THE UNIQUE INDEX GOES. Schema 355 drops UNIQUE (hText, hTime), which was
 * a lossy deduplicator: two different events in the same second with the
 * same prose became one row, silently. That index was also the only thing
 * bounding the debug firehose, so decision 6 requires the bound to move to
 * the writer -- FOGBase::LOG_HISTORY_MAX. Dropping the index without the
 * cap is the half that would hurt, so the cap is checked here rather than
 * left to the schema test.
 *
 * A cap rather than the log-level check the ADR offers as its alternative,
 * and the reason is checked below: `$curlog >= $level` compares two of
 * log()'s six positional arguments, and real call sites got them wrong.
 *
 * What this canNOT check is that any of it behaves that way against a
 * server -- that the DDL applies in an order MySQL accepts, that the
 * backfill fills the right rows, that two identical rows now both survive.
 * /home/telliott/labs/adr0020/prove_phase5.php does that against a lab
 * copy and is deliberately not committed: it runs DDL and creates hosts,
 * which is not something to leave one mistyped argument away from a
 * production database.
 *
 * DB-free by construction: class properties, constants and method text.
 *
 * Usage: php tests/history-untranslated-and-bounded.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Items\History;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('history-untranslated');
FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

/**
 * The source text of one method, brace-matched from its signature.
 *
 * @param string $file  the file to read
 * @param string $sig   the signature line to start at
 *
 * @return string
 */
function methodBody($file, $sig)
{
    $src = file_get_contents($file);
    $at = strpos($src, $sig);
    if (false === $at) {
        return '';
    }
    $open = strpos($src, '{', $at);
    if (false === $open) {
        return '';
    }
    $depth = 0;
    $len = strlen($src);
    for ($i = $open; $i < $len; $i++) {
        if ('{' === $src[$i]) {
            $depth++;
        } elseif ('}' === $src[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($src, $at, $i - $at + 1);
            }
        }
    }
    return '';
}

/*
 * ----------------------------------------------------------------------
 * 1. The writers pass untranslated outcome clauses.
 */
$controller = $web . '/src/Base/FOGController.php';
$helper = methodBody($controller, 'private function _historyText(');
$t->check(
    'FOGController::_historyText() exists',
    '' !== $helper
);
$t->check(
    'the helper translates nothing',
    '' !== $helper && false === strpos($helper, '_(')
);

// The four call sites, and the clause each one passes. Spelled out rather
// than derived: the point is that these exact untranslated strings are what
// lands in the column, and a derived list would happily agree with itself
// after somebody re-wrapped them all in _().
$clauses = [
    'has been successfully updated',
    'has failed to save',
    'has been successfully destroyed',
    'has failed to destroy',
];
$src = file_get_contents($controller);
foreach ($clauses as $clause) {
    $t->check(
        "'$clause' is passed as a bare string",
        false !== strpos($src, "_historyText('" . $clause . "'")
        || false !== strpos($src, "'" . $clause . "',")
    );
    $t->check(
        "'$clause' is not wrapped in _()",
        false === strpos($src, "_('" . $clause . "')")
    );
}
$t->check(
    'all four sites go through the helper',
    4 === substr_count($src, '$this->_historyText(')
);
// The old builders are gone rather than merely unused. A second copy that
// still translates is how this comes back.
$t->check(
    "no site still translates the 'ID' label into the stored text",
    false === strpos($src, "_('ID'),\n                        \$this->get('id')")
);

/*
 * ----------------------------------------------------------------------
 * 2. The text is not emptied, and it is required.
 */
$t->check(
    'the helper still produces the class, the id and the outcome',
    '' !== $helper
    && false !== strpos($helper, "'%s ID: %s'")
    && false !== strpos($helper, "' Name: %s'")
);
$history = new History();
$req = new \ReflectionProperty(get_class($history), 'databaseFieldsRequired');
$req->setAccessible(true);
$t->check(
    'History requires `info`, because TEXT NOT NULL can carry no DEFAULT',
    in_array('info', (array)$req->getValue($history), true)
);

/*
 * ----------------------------------------------------------------------
 * 3. The renderer moved to the model, and both readers use it.
 */
$t->check(
    'History::summary() is public static',
    is_callable(['FOG\Items\History', 'summary'])
);
$route = $web . '/src/Router/Route.php';
$routeSummary = methodBody($route, 'private static function _historySummary(');
$t->check(
    'Route delegates rather than keeping a second copy',
    '' !== $routeSummary
    && false !== strpos($routeSummary, 'History::summary($row)')
    && false === strpos($routeSummary, 'switch ($type)')
);
// The dashboard used to carry a Recent Activity card, which was the second
// reader History::summary() was extracted for. It was removed as not earning
// its place on the dashboard; the activity grid is the reader that remains.
// Pinned so the card cannot come back reading `hText` raw -- the untranslated
// column this whole test exists about.
$dash = file_get_contents($web . '/src/Pages/DashboardPage.php');
$t->check(
    'the dashboard does not read the history table at all',
    false === strpos($dash, '`history`')
    && false === strpos($dash, "hText")
);

// And the renderer still works, which is the thing all three depend on.
$rendered = \FOG\Items\History::summary(
    [
        'hType' => \FOG\Items\History::TYPE_UPDATE,
        'hSubjectType' => 'host',
        'hSubjectID' => 7,
        'hSubjectLabel' => 'bench-7',
        'hText' => 'Host ID: 7 Name: bench-7 has been successfully updated.',
    ]
);
$t->check(
    'a framed row renders a sentence, not the stored text',
    false !== strpos($rendered, 'bench-7')
    && false === strpos($rendered, 'has been successfully updated')
);
$t->check(
    'an unframed row still falls back to the stored text',
    'legacy prose' === \FOG\Items\History::summary(['hText' => 'legacy prose'])
);

/*
 * ----------------------------------------------------------------------
 * 4. The writer bound that replaces the unique index.
 */
$t->check(
    'FOGBase declares a per-request cap',
    defined('FOG\Base\FOGBase::LOG_HISTORY_MAX')
);
$max = defined('FOG\Base\FOGBase::LOG_HISTORY_MAX')
    ? constant('FOG\Base\FOGBase::LOG_HISTORY_MAX')
    : 0;
$t->check(
    'the cap is a positive int, and generous enough not to ration real logging',
    is_int($max) && $max >= 20
);
$logBody = methodBody(
    $web . '/src/Base/FOGBase.php',
    'public static function log('
);
$t->check(
    'log() returns before writing once the cap is reached',
    false !== strpos($logBody, 'self::$logHistoryRows >= self::LOG_HISTORY_MAX')
);
$t->check(
    'the counter is incremented on the way past',
    false !== strpos($logBody, 'self::$logHistoryRows++')
);
// The cap must gate the history write ONLY. Gating the echo too would make
// a debug session go quiet at row 100, which is not what is being bounded.
$capAt = strpos($logBody, 'self::$logHistoryRows >=');
$echoAt = strpos($logBody, 'echo $txt;');
$t->check(
    'the cap sits after the echo, so it bounds storage and not output',
    false !== $capAt && false !== $echoAt && $capAt > $echoAt
);
// save()/destroy()'s own rows are the audit trail and are NOT capped.
$t->check(
    'the cap is not applied to logHistory() itself',
    false === strpos(
        methodBody(
            $web . '/src/Base/FOGBase.php',
            'protected static function logHistory('
        ),
        'LOG_HISTORY_MAX'
    )
);

/*
 * ----------------------------------------------------------------------
 * 5. Why it is a cap and not a level check: the arguments it would have
 *    depended on. Both user.class.php calls used to pass the object in the
 *    $logbrow slot, leaving $level at its default of 1 against a $curlog of
 *    0 -- so a level gate would have dropped the login rows.
 */
$user = file_get_contents($web . '/src/Items/User.php');
$t->check(
    "user.class.php no longer passes \$this in log()'s \$logbrow slot",
    false === strpos($user, "            0,\n            0,\n            \$this,\n            0\n        );")
);
$t->check(
    'both login rows pass the object in the $obj slot',
    2 === substr_count(
        $user,
        "            0,\n            0,\n            0,\n            \$this\n        );"
    )
);

/*
 * ----------------------------------------------------------------------
 * 6. The schema step exists and does all three things, in the one order a
 *    server accepts. A varchar inside a unique index cannot become TEXT,
 *    so the DROP has to precede the MODIFY.
 */
$schema = file_get_contents($web . '/commons/schema.php');
$at355 = strpos($schema, "\n// 355\n");
$t->check('schema step 355 exists', false !== $at355);
if (false !== $at355) {
    $step = substr($schema, $at355);
    $drop = strpos($step, 'DROP INDEX `updateTime`');
    $modify = strpos($step, 'MODIFY `hText` TEXT NOT NULL');
    $add = strpos($step, 'ADD INDEX `hTime`');
    $fill = strpos($step, 'UPDATE `userTracking` ');
    $t->check('355 drops the unique index', false !== $drop);
    $t->check('355 widens hText to TEXT', false !== $modify);
    $t->check('355 adds the hTime index', false !== $add);
    $t->check('355 backfills utHostName', false !== $fill);
    $t->check(
        'the index drop comes before the type change',
        false !== $drop && false !== $modify && $drop < $modify
    );
    $t->check(
        'the backfill only touches rows with no copy yet',
        false !== strpos($step, "`userTracking`.`utHostName` = ''")
    );
    $t->check(
        'the backfill only touches rows whose host still exists',
        false !== strpos($step, 'JOIN `hosts` ON `hosts`.`hostID` = `userTracking`.`utHostID`')
    );
}

/*
 * And the manifest describes the end state, not the one before the step.
 */
$manifest = require $web . '/commons/schema-expected.php';
$hist = $manifest['tables']['history'] ?? [];
$t->check(
    'the manifest has hText as TEXT',
    isset($hist['columns']['hText'])
    && false !== stripos($hist['columns']['hText'], 'text')
    && false === stripos($hist['columns']['hText'], 'varchar')
);
$t->check(
    'the manifest has dropped the unique index',
    isset($hist['create'])
    && false === strpos($hist['create'], 'UNIQUE KEY `updateTime`')
);
$t->check(
    'the manifest carries KEY `hTime`',
    isset($hist['create']) && false !== strpos($hist['create'], 'KEY `hTime`')
);

$t->finish();
