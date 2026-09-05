<?php
/**
 * The join credential goes to as few machines as possible, for as short a
 * time as possible.
 *
 * Design 0009 section 6. Every check here is one way the credential could
 * quietly go somewhere it should not, or one way a join could quietly do
 * damage:
 *
 * - `Client\HostnameChanger::json()` puts `ADUser` and `ADPass` in the
 *   answer to EVERY check-in of EVERY host with `useAD` set, joined or not.
 *   `DirectoryJoin::desired()` must return null in every case where nothing
 *   can be done with the credential, because null is what keeps it off the
 *   machine.
 * - The cooldown is not politeness. A join that fails on a bad password is a
 *   failed authentication against somebody's domain controller, and without
 *   the stamp it is one per host per poll -- which is how a service account
 *   with a lockout policy gets locked out, taking every other host's join
 *   with it.
 * - A settled status clears the error, or the report shows a stale failure
 *   against a machine that is now joined.
 * - The stored-secret decoder's base64 test has to be STRICT. Non-strict
 *   base64_decode does not fail on an ordinary password -- it drops the
 *   characters outside the alphabet and decodes what is left -- so a lax
 *   test hands back a string the admin never typed. That is a live bug in
 *   the legacy client's copy of this dance.
 *
 * DB-free: the harness's fake connection stands in.
 *
 * Usage: php tests/agent-directory-join.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-directory-join');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Call a protected static on DirectoryJoin.
 *
 * @param string $name the method
 * @param array  $args the arguments
 *
 * @return mixed
 */
function dj($name, array $args)
{
    $m = new \ReflectionMethod(\FOG\Agent\DirectoryJoin::class, $name);
    $m->setAccessible(true);

    return $m->invokeArgs(null, $args);
}

/**
 * A host that needs no database to answer for itself.
 *
 * @param array $fields what to set on it
 *
 * @return \FOG\Items\Host
 */
function djHost(array $fields = [])
{
    $Host = new \FOG\Items\Host();
    $Host->set('id', 7)->set('name', 'WS-014');
    foreach ($fields as $k => $v) {
        $Host->set($k, $v);
    }

    return $Host;
}

/**
 * A membership row that needs no database.
 *
 * @param array $fields what to set on it
 *
 * @return \FOG\Items\HostDirectory
 */
function djObserved(array $fields = [])
{
    $Observed = new \FOG\Items\HostDirectory();
    $Observed->set('id', 3)->set('hostID', 7);
    foreach ($fields as $k => $v) {
        $Observed->set($k, $v);
    }

    return $Observed;
}

// ------------------------------------------------- the columns are mapped

$mapped = array_keys(
    (function () {
        $p = new \ReflectionProperty(
            \FOG\Items\HostDirectory::class,
            'databaseFields'
        );
        $p->setAccessible(true);
        return (array)$p->getValue(new \FOG\Items\HostDirectory());
    })()
);
foreach (['joinAt', 'joinError'] as $field) {
    $t->check(
        sprintf('HostDirectory maps %s', $field),
        in_array($field, $mapped, true)
    );
}

// --------------------------------------------------------------- the modes

$t->check(
    'every settled status is a real status',
    [] === array_diff(
        \FOG\Agent\DirectoryJoin::SETTLED_STATUSES,
        \FOG\Agent\DirectoryJoin::STATUSES
    )
);
$t->check(
    'refused is a status of its own, not a kind of failure',
    in_array('refused', \FOG\Agent\DirectoryJoin::STATUSES, true)
        && !in_array('refused', \FOG\Agent\DirectoryJoin::SETTLED_STATUSES, true)
);

// ----------------------------------------------------- the capability gate

$caps = new \ReflectionClassConstant(\FOG\Agent\State::class, 'CAPABILITIES');
$t->check(
    'the directory capability is gated on the EXISTING hostnamechanger '
        . 'module, not a new switch an admin has to find',
    'hostnamechanger' === ($caps->getValue()['directory'] ?? null)
);

$itemReports = new \ReflectionClassConstant(\FOG\Agent\State::class, 'ITEM_REPORTS');
$t->check(
    'the join result routes through the existing registry, not a new path',
    \FOG\Agent\DirectoryJoin::class === ($itemReports->getValue()['directory'] ?? null)
);

// Why it has to be an ITEM report and not a plain one: the join's own
// vocabulary does not fit in the outer `status` field, which carries the
// capability's applied/unchanged/pending_reboot/failed. `joined` is not one
// of those, and `failed` means two different things in the two places --
// which is exactly why they need two fields.
$resultStatuses = new \ReflectionClassConstant(\FOG\Agent\State::class, 'RESULT_STATUSES');
foreach (['joined', 'already_joined', 'refused', 'unsupported'] as $own) {
    $t->check(
        sprintf('%s has nowhere to live in the capability status field', $own),
        in_array($own, \FOG\Agent\DirectoryJoin::STATUSES, true)
            && !in_array($own, $resultStatuses->getValue(), true)
    );
}

// A host may only report on its own membership.
$refused = false;
try {
    \FOG\Agent\DirectoryJoin::report(djHost(), 999, ['status' => 'joined']);
} catch (\RuntimeException $e) {
    $refused = 404 === $e->getCode();
}
$t->check(
    'a host reporting on somebody else\'s membership is refused',
    $refused
);

// -------------------------------------------------- when nothing is sent

$t->check(
    'a host not set to use AD is sent nothing',
    null === \FOG\Agent\DirectoryJoin::desired(djHost(['useAD' => 0]))
);
$t->check(
    'a host with no domain is sent nothing',
    null === \FOG\Agent\DirectoryJoin::desired(
        djHost(['useAD' => 1, 'ADDomain' => '  '])
    )
);

// The rest go through blockFor(), which takes the membership row rather
// than looking it up -- the rule about when a credential leaves this server
// needs no database to state.
/**
 * blockFor() with a given membership row.
 *
 * @param \FOG\Items\Host               $Host     the host
 * @param \FOG\Items\HostDirectory|null $Observed what it last reported
 *
 * @return array|null
 */
function djDesired($Host, $Observed)
{
    return \FOG\Agent\DirectoryJoin::blockFor($Host, $Observed);
}

$joinable = ['useAD' => 1, 'ADDomain' => 'corp.example.com',
    'ADUser' => 'svc-join', 'ADPass' => 'letmein',
    'ADOU' => 'OU=Workstations,DC=corp,DC=example,DC=com', 'enforce' => 1];

$t->check(
    'a host that has never reported its membership is sent nothing -- the '
        . 'server does not know whether it is joined, and a credential is '
        . 'not something to send on a guess',
    null === djDesired(djHost($joinable), null)
);
$t->check(
    'a host already joined is sent nothing, which is most of an estate most '
        . 'of the time',
    null === djDesired(
        djHost($joinable),
        djObserved(['joined' => 1, 'domain' => 'corp.example.com'])
    )
);
$t->check(
    'a host joined to a DIFFERENT domain is sent nothing either: the agent '
        . 'would refuse, so sending the credential exposes it for nothing',
    null === djDesired(
        djHost($joinable),
        djObserved(['joined' => 1, 'domain' => 'other.example.com'])
    )
);

$block = djDesired(djHost($joinable), djObserved(['joined' => 0]));
$t->check('an unjoined host IS sent a block', is_array($block));
$t->check(
    'the block carries the domain',
    'corp.example.com' === ($block['domain'] ?? null)
);
$t->check(
    'the block carries the OU, so the object is created where it belongs '
        . 'instead of landing in CN=Computers and needing a move',
    'OU=Workstations,DC=corp,DC=example,DC=com' === ($block['ou'] ?? null)
);
$t->check(
    'the account is domain-qualified',
    'corp.example.com\\svc-join' === ($block['username'] ?? null)
);
$t->check(
    'the password is there to send',
    'letmein' === ($block['password'] ?? null)
);
$t->check(
    'the reboot permission is the host\'s existing enforce flag, not a new one',
    true === ($block['reboot'] ?? null)
);

// An account an admin already qualified is left alone.
$qualified = $joinable;
$qualified['ADUser'] = 'CORP\\svc-join';
$block2 = djDesired(djHost($qualified), djObserved(['joined' => 0]));
$t->check(
    'an already-qualified account is not qualified twice',
    'CORP\\svc-join' === ($block2['username'] ?? null)
);
$upn = $joinable;
$upn['ADUser'] = 'svc-join@corp.example.com';
$block3 = djDesired(djHost($upn), djObserved(['joined' => 0]));
$t->check(
    'a userPrincipalName is left as typed',
    'svc-join@corp.example.com' === ($block3['username'] ?? null)
);

// A host with no credential still gets a block, so the agent can report why.
$nocred = $joinable;
$nocred['ADUser'] = '';
$block4 = djDesired(djHost($nocred), djObserved(['joined' => 0]));
$t->check(
    'a host with no credential still gets a block, so the agent reports '
        . 'refused with a reason instead of looking identical to a joined host',
    is_array($block4) && '' === ($block4['username'] ?? null)
        && '' === ($block4['password'] ?? null)
);

// ------------------------------------------------------------ the cooldown

$t->check(
    'a host never attempted is not cooling',
    false === dj('cooling', [djObserved([])])
);
$t->check(
    'a zero datetime is not a recent attempt',
    false === dj('cooling', [djObserved(['joinAt' => '0000-00-00 00:00:00'])])
);
$storeTz = (new \ReflectionMethod(\FOG\Base\FOGBase::class, 'storageTimeZone'));
$storeTz->setAccessible(true);
$tz = $storeTz->invoke(null);
$recent = (new \DateTime('-1 minute'))->setTimezone($tz)->format('Y-m-d H:i:s');
$t->check(
    'an attempt a minute ago is cooling -- without this, a wrong password '
        . 'is one failed authentication per host per poll, which is how a '
        . 'service account with a lockout policy gets locked out',
    true === dj('cooling', [djObserved(['joinAt' => $recent])])
);
$old = (new \DateTime('-2 hours'))->setTimezone($tz)->format('Y-m-d H:i:s');
$t->check(
    'an attempt two hours ago is not cooling, so a corrected password is '
        . 'acted on without an admin restarting anything',
    false === dj('cooling', [djObserved(['joinAt' => $old])])
);
$t->check(
    'the cooldown outlasts a poll interval by a wide margin',
    \FOG\Agent\DirectoryJoin::RETRY_AFTER >= 1800
);

$cooling = djDesired(
    djHost($joinable),
    djObserved(['joined' => 0, 'joinAt' => $recent])
);
$t->check('a cooling host is sent nothing', null === $cooling);

// -------------------------------------------------------- the error rule

$t->check(
    'a failure keeps its message',
    'adcli: Insufficient access'
        === dj('errorFor', ['failed', 'adcli: Insufficient access'])
);
$t->check(
    'a refusal keeps its message',
    'already joined to other.example.com'
        === dj('errorFor', ['refused', 'already joined to other.example.com'])
);
$t->check(
    'unsupported keeps its message',
    'neither adcli nor realm is installed'
        === dj('errorFor', ['unsupported', 'neither adcli nor realm is installed'])
);
foreach (\FOG\Agent\DirectoryJoin::SETTLED_STATUSES as $settled) {
    $t->check(
        sprintf(
            '%s CLEARS a stale error -- an admin chasing a message against '
                . 'a machine that is now joined is worse off than one '
                . 'chasing none',
            $settled
        ),
        '' === dj('errorFor', [$settled, 'adcli: Insufficient access'])
    );
}
$t->check(
    'a provider novel is cut to what the column holds',
    \FOG\Agent\DirectoryJoin::MAX_ERROR
        === strlen(dj('errorFor', ['failed', str_repeat('x', 4000)]))
);

// ------------------------------------------- the stored-secret decoder

$t->check(
    'an empty stored secret decodes to empty',
    '' === \FOG\Agent\DirectoryPlacement::decodeStored('   ')
);
$t->check(
    'a plain password comes back as itself',
    'S3cret!pass' === \FOG\Agent\DirectoryPlacement::decodeStored('S3cret!pass')
);
$t->check(
    'a base64-stored password is decoded',
    'S3cret!pass'
        === \FOG\Agent\DirectoryPlacement::decodeStored(base64_encode('S3cret!pass'))
);
// The strictness check. Non-strict base64_decode() skips characters outside
// the alphabet and decodes what is left, so an ordinary password that
// happens to contain some base64 characters comes back as garbage -- which
// is the live bug in Client\HostnameChanger's copy of this dance.
$awkward = 'Passw0rd!!';
$t->check(
    'a password that merely looks base64-ish is NOT decoded',
    $awkward === \FOG\Agent\DirectoryPlacement::decodeStored($awkward)
);

// ------------------------------------------------ the report is honest

$rp = new \ReflectionMethod(\FOG\Reports\Directory_Membership::class, 'join');
$rp->setAccessible(true);
$t->check(
    'a join error is what the report shows',
    'adcli: Insufficient access'
        === $rp->invoke(null, ['hdJoinError' => 'adcli: Insufficient access'])
);
$t->check(
    'a host never attempted shows nothing rather than ok',
    '' === $rp->invoke(null, ['hdJoinAt' => '', 'hdJoinError' => ''])
);
$t->check(
    'an attempt with no error shows ok',
    'ok' === $rp->invoke(
        null,
        ['hdJoinAt' => '2026-09-04 21:00:00', 'hdJoinError' => '']
    )
);

$src = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Reports/Directory_Membership.php'
);
foreach (['hdJoinAt', 'hdJoinError'] as $column) {
    $t->check(
        sprintf('the report actually selects %s', $column),
        false !== strpos($src, '`' . $column . '`')
    );
}

// -------------------------------------- nothing logs the credential

$src = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Agent/DirectoryJoin.php'
);
$t->check(
    'the audit line never formats the password',
    false === strpos($src, "get('ADPass')")
        || 1 === preg_match_all("/get\('ADPass'\)/", $src)
);

$t->finish();
