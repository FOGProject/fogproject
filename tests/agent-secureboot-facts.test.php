<?php
/**
 * What an agent may say about its own Secure Boot posture.
 *
 * The block rides the poll (design 0012) and is written by a host, so these
 * checks are about what a host can and cannot cause:
 *
 * - The state stored is the one SecureBootState::fromBootRequest() derives,
 *   never a name the agent chose. The six words were copied verbatim from
 *   FOS's sbState() so the reporters could not drift into two vocabularies
 *   for one fact; a third implementation in Go would reintroduce exactly
 *   that.
 * - SETUP beats ENFORCING. A machine in Setup Mode accepts a db write
 *   whatever its SecureBoot byte says, and fog.enrollsb branches on the
 *   difference -- it is unattended enrollment versus a human at MokManager.
 * - A malformed block writes NOTHING. Overwriting a real observation with
 *   "never reported" is worse than the stale value it replaced.
 * - A missing key is not an empty one. '' means the machine looked and
 *   found nothing readable; absent means it did not answer. Collapsing them
 *   makes a malformed block assert "UEFI, state unreadable" about a machine
 *   that asserted nothing.
 * - The stored time is the SERVER's. The column answers "how stale is
 *   this", which is only meaningful in the server's own time base.
 *
 * DB-free: the harness's fake connection stands in and the statements are
 * inspected rather than executed.
 *
 * Usage: php tests/agent-secureboot-facts.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-secureboot-facts');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * A host that needs no database to answer for itself.
 *
 * @param int    $id    the host
 * @param string $state the posture already stored
 *
 * @return \FOG\Items\Host
 */
function sbHost($id, $state = '')
{
    $Host = new \FOG\Items\Host();
    $Host->set('id', $id)->set('name', 'lab-01')->set('sbstate', $state);

    return $Host;
}

/**
 * Run report() against the fake connection and return the UPDATE binds.
 *
 * @param FogFakeDb $db    the fake connection
 * @param array     $block the reported block
 * @param string    $prior the posture already stored
 *
 * @return array [statements, binds]
 */
function sbReport($db, array $block, $prior = '')
{
    $db->log = [];
    $binds = [];
    $db->responder = function ($sql, $params) use (&$binds) {
        $binds[] = [$sql, $params];
        return null;
    };
    \FOG\Agent\SecureBootFacts::report(sbHost(7, $prior), $block);
    $db->responder = null;

    return [$db->log, $binds];
}

/**
 * The value an UPDATE actually bound to the hostSbState COLUMN.
 *
 * Deliberately reads the column and not the placeholder. perform_update()
 * names the bind :update_<field> from the PROPERTY name and puts the column
 * from Host::$databaseFields into the SQL, so a report passing 'hostSbState'
 * still produces the string "hostSbState" in the statement -- as the
 * placeholder -- while the column collapses to `hosts`.``. Matching the
 * placeholder made this whole file pass against exactly that bug.
 *
 * @param array $binds the recorded statements
 *
 * @return string the bound value, or '' when no such UPDATE was issued
 */
function sbWritten(array $binds)
{
    foreach ($binds as list($sql, $params)) {
        if (false === stripos($sql, 'UPDATE')
            || !preg_match('/`hosts`\.`hostSbState`\s*=\s*:(\w+)/i', $sql, $m)
        ) {
            continue;
        }
        foreach ((array)$params as $k => $v) {
            if (ltrim((string)$k, ':') === $m[1]) {
                return (string)$v;
            }
        }
    }

    return '';
}

/**
 * How many UPDATEs against hosts the report issued.
 *
 * "Writes nothing" has to mean no statement at all. Asking only whether a
 * RECOGNIZED state was written lets an UNKNOWN through, which is the one
 * write the guard exists to prevent.
 *
 * @param array $binds the recorded statements
 *
 * @return int
 */
function sbUpdates(array $binds)
{
    $n = 0;
    foreach ($binds as list($sql,)) {
        if (false !== stripos($sql, 'UPDATE') && false !== stripos($sql, '`hosts`')) {
            $n++;
        }
    }

    return $n;
}

// ------------------------------------------------- the mapping is the server's

$cases = [
    'enforcing' => ['platform' => 'efi', 'secure_boot' => '01', 'setup_mode' => '00'],
    'disabled' => ['platform' => 'efi', 'secure_boot' => '00', 'setup_mode' => '00'],
    // Setup Mode wins over the SecureBoot byte: db is writable there
    // whatever SecureBoot says, and that is the difference between an
    // enrollment nobody has to attend and one that needs a human.
    'setup' => ['platform' => 'efi', 'secure_boot' => '01', 'setup_mode' => '01'],
    'nonefi' => ['platform' => 'bios', 'secure_boot' => '', 'setup_mode' => ''],
    'noefivars' => ['platform' => 'efi', 'secure_boot' => '', 'setup_mode' => ''],
];
foreach ($cases as $want => $block) {
    list(, $binds) = sbReport($db, $block);
    $t->check(
        'a ' . $want . ' machine stores ' . $want,
        sbWritten($binds) === $want
    );
}

// The measured lab case, both platforms, design 0012's status line.
list(, $binds) = sbReport(
    $db,
    ['platform' => 'efi', 'secure_boot' => '01', 'setup_mode' => '00'],
    'disabled'
);
$t->check(
    'telliottwin11 reporting {efi 01 00} replaces the stale disabled',
    'enforcing' === sbWritten($binds)
);

// ------------------------------------------------------ a malformed block

// Nothing at all. fromBootRequest answers UNKNOWN, and storing that would
// erase a real observation in favor of "nobody has ever said".
list(, $binds) = sbReport($db, [], 'enforcing');
$t->check('an empty block writes no UPDATE at all', 0 === sbUpdates($binds));

// The keys are absent rather than empty, which is a different input: were
// they cast to '' this would store noefivars and assert that an enforcing
// machine's firmware had become unreadable.
list(, $binds) = sbReport($db, ['platform' => 'efi'], 'enforcing');
$t->check(
    'a block with no observations in it writes nothing, not noefivars',
    0 === sbUpdates($binds) && '' === sbWritten($binds)
);

// The same shape WITH the keys present is a real answer and must still
// store -- otherwise the guard above would swallow honest reports too.
list(, $binds) = sbReport(
    $db,
    ['platform' => 'efi', 'secure_boot' => '', 'setup_mode' => ''],
    'enforcing'
);
$t->check(
    'a machine that looked and could not read the variables still reports',
    'noefivars' === sbWritten($binds)
);

// A host cannot invent a state: the block carries bytes, and any byte that
// is not 00 or 01 lands in noefivars rather than passing through.
list(, $binds) = sbReport(
    $db,
    ['platform' => 'efi', 'secure_boot' => 'yes', 'setup_mode' => 'no']
);
$t->check(
    'an unrecognized byte does not become a state of its own',
    'noefivars' === sbWritten($binds)
);

// A host cannot claim a platform either. Anything but efi is nonefi, so
// there is no way to reach a state by naming one.
list(, $binds) = sbReport(
    $db,
    ['platform' => 'enforcing', 'secure_boot' => '01', 'setup_mode' => '00']
);
$t->check(
    'a made-up platform is nonefi, not the string the host sent',
    'nonefi' === sbWritten($binds)
);

// The time is stamped here, in the server's own base. The column answers
// "how stale is this", so a client-supplied timestamp would make the one
// question it exists for unanswerable.
list(, $binds) = sbReport(
    $db,
    ['platform' => 'efi', 'secure_boot' => '01', 'setup_mode' => '00']
);
$stamped = '';
foreach ($binds as list($sql, $params)) {
    if (preg_match('/`hosts`\.`hostSbStateTime`\s*=\s*:(\w+)/i', $sql, $m)) {
        foreach ((array)$params as $k => $v) {
            if (ltrim((string)$k, ':') === $m[1]) {
                $stamped = (string)$v;
            }
        }
    }
}
$t->check(
    'the state is stamped with a server-side datetime',
    1 === preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $stamped)
);
// Parsed in the STORAGE timezone, which is the one it was formatted in.
// strtotime() would read it in PHP's default zone instead and the check
// would drift by the offset between the two.
$parsed = '' === $stamped ? false : \DateTime::createFromFormat(
    'Y-m-d H:i:s',
    $stamped,
    \FOG\Base\FOGBase::storageTimeZone()
);
$t->check(
    'the stamp is now, not a value the host could have chosen',
    false !== $parsed && abs(time() - $parsed->getTimestamp()) < 120
);

/**
 * The text of the audit line the report recorded, or '' for none.
 *
 * @param array $binds the recorded statements
 *
 * @return string
 */
function sbAuditText(array $binds)
{
    foreach ($binds as list($sql, $params)) {
        if (false === stripos($sql, 'auditLog')) {
            continue;
        }
        foreach ((array)$params as $v) {
            if (is_string($v) && false !== strpos($v, 'agent reported')) {
                return $v;
            }
        }
    }

    return '';
}

// The audit line is what an admin actually reads, and it shipped saying
// "agent reported Secure Boot Secure Boot ON" -- label() already spells the
// words. Every other check in this file passed straight through that.
list(, $binds) = sbReport(
    $db,
    ['platform' => 'efi', 'secure_boot' => '01', 'setup_mode' => '00'],
    'disabled'
);
$text = sbAuditText($binds);
$t->check('the report records an audit line', '' !== $text);
$t->check(
    'the audit line does not repeat "Secure Boot"',
    '' !== $text && false === strpos($text, 'Secure Boot Secure Boot')
);
$t->check(
    'the audit line names both ends of the move',
    false !== strpos($text, 'Secure Boot ON')
    && false !== strpos($text, 'was Secure Boot OFF')
);

// A first report has no previous value to name, and "(was Never reported)"
// is noise on every newly enrolled host.
list(, $binds) = sbReport(
    $db,
    ['platform' => 'efi', 'secure_boot' => '01', 'setup_mode' => '00']
);
$t->check(
    'a first report does not claim a previous state',
    false === strpos(sbAuditText($binds), '(was')
);

$t->finish();
