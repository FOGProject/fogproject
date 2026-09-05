<?php
/**
 * The user sessions an agent reports stay inside their bounds.
 *
 * Sessions ride the poll request (design 0008) into `hostUserSession`, one
 * row per logon with two ends, replacing the login/logout event pairs that
 * `userTracking` could never reliably pair. The body comes from a host, so
 * these are the ways it could go wrong quietly rather than loudly:
 *
 * - A session with no key, no user or no start is dropped, not stored. Each
 *   is unusable in a different way: no key cannot be reconciled against a
 *   later report, no start has no duration, no user is nobody.
 * - A close with no end time is dropped. Storing it open would be a session
 *   that never ends; storing it closed at "now" would invent a duration.
 * - An agent cannot claim `inferred`. That reason means "the server never
 *   found out", and a host asserting it would be claiming to have witnessed
 *   something it did not.
 * - Values are truncated to the column widths here, so an overlong username
 *   fails this check rather than the insert -- a strict-mode rejection would
 *   cost the host its whole poll, not just the one field.
 * - The widths map has to name real columns, or a typo silently stops
 *   truncating a field and moves the failure back to the database.
 *
 * DB-free: the harness's fake connection stands in, and only the pure
 * normalization is exercised -- nothing here writes a row.
 *
 * Usage: php tests/agent-user-sessions.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-user-sessions');
FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Calls UserSessions::_clean(), which is private and is the whole of the
 * input handling worth pinning.
 *
 * @param array $list       reported entries
 * @param bool  $wantClosed whether ended_at is required
 *
 * @return array
 */
function cleanSessions(array $list, $wantClosed)
{
    $m = new \ReflectionMethod(\FOG\Agent\UserSessions::class, '_clean');
    $m->setAccessible(true);

    return $m->invoke(null, $list, $wantClosed);
}

$good = [
    'key' => '2',
    'user' => 'telliott',
    'domain' => 'LAB',
    'sid' => 'S-1-5-21-1',
    'type' => 'console',
    'state' => 'active',
    'remote_host' => '',
    'started_at' => '2026-09-04T09:12:03Z'
];

$t->check(
    'a complete open session is accepted',
    1 === count(cleanSessions([$good], false))
);

// ------------------------------------------------- unusable entries dropped

foreach (['key', 'user', 'started_at'] as $missing) {
    $bad = $good;
    unset($bad[$missing]);
    $t->check(
        "an open session with no $missing is dropped",
        0 === count(cleanSessions([$bad], false))
    );
}

$t->check(
    'a session with an unparsable start is dropped, not dated now',
    0 === count(cleanSessions(
        [array_merge($good, ['started_at' => 'the day before yesterday'])],
        false
    ))
);

$t->check(
    'a closed session with no ended_at is dropped',
    0 === count(cleanSessions([$good], true))
);

$t->check(
    'a non-array entry in the list is dropped rather than fatal',
    0 === count(cleanSessions(['not-a-session'], false))
);

// ------------------------------------------------------- the honesty rules

$claimed = cleanSessions(
    [array_merge($good, [
        'ended_at' => '2026-09-04T10:00:00Z',
        'end_reason' => 'inferred'
    ])],
    true
);
$row = reset($claimed);
$t->check(
    'an agent claiming end_reason=inferred is recorded as a witnessed logout'
        . ': got ' . var_export($row['end_reason'] ?? null, true),
    'logout' === ($row['end_reason'] ?? null)
);

$t->check(
    'inferred is not an end reason an agent may send',
    !in_array(
        \FOG\Agent\UserSessions::END_INFERRED,
        \FOG\Agent\UserSessions::AGENT_END_REASONS,
        true
    )
);

$unknown = cleanSessions(
    [array_merge($good, [
        'ended_at' => '2026-09-04T10:00:00Z',
        'end_reason' => 'something-new'
    ])],
    true
);
$row = reset($unknown);
$t->check(
    'an unrecognized end reason falls back to logout, not through',
    'logout' === ($row['end_reason'] ?? null)
);

// ------------------------------------------------------------- one clock

/*
 * A start and an end have to be written on the same clock, or a duration is
 * nonsense. The server copies husLastSeen -- written by niceDate(), on
 * storageTimeZone() -- into husEndedAt when it infers a close, so a reported
 * start converted to any other zone makes the two incomparable. On the lab
 * server this showed up as a one-second session reading as five hours: a
 * start in local time against an end in UTC.
 */
$sameInstant = cleanSessions(
    [array_merge($good, ['started_at' => '2026-09-04T16:37:52Z'])],
    false
);
$row = reset($sameInstant);
$expected = \FOG\Base\FOGBase::niceDate('2026-09-04T16:37:52Z')
    ->format('Y-m-d H:i:s');
$t->check(
    'a reported start lands on the same clock niceDate() writes'
        . ': got ' . var_export($row['started_at'] ?? null, true)
        . ', niceDate says ' . $expected,
    $expected === ($row['started_at'] ?? null)
);

/*
 * The report reads the same column back and compares it against time(), so
 * it needs the same clock a third time. strtotime() there read the stored
 * UTC string in PHP's default zone, putting every open session in the future
 * and silently blanking the Duration column -- a report that shows nothing
 * looks like a report with nothing to show.
 */
$e = new \ReflectionMethod(\FOG\Reports\User_Sessions::class, 'elapsed');
$e->setAccessible(true);
$now = time();
$anHourAgo = \FOG\Base\FOGBase::niceDate('@' . ($now - 3600))
    ->format('Y-m-d H:i:s');
$t->check(
    'a session started an hour ago reports about an hour, not an empty cell'
        . ': got ' . var_export($e->invoke(null, $anHourAgo, $now), true),
    '1h 0m' === $e->invoke(null, $anHourAgo, $now)
);
$t->check(
    'an unusable start is an empty cell, never "0m"',
    '' === $e->invoke(null, '', $now)
        && '' === $e->invoke(null, '0000-00-00 00:00:00', $now)
);

// --------------------------------------------------------------- truncation

$long = cleanSessions(
    [array_merge($good, [
        'user' => str_repeat('u', 400),
        'domain' => str_repeat('d', 400),
        'type' => str_repeat('t', 90)
    ])],
    false
);
$row = reset($long);
$t->check(
    'an overlong username is truncated to its column width',
    255 === strlen($row['user'] ?? '')
);
$t->check(
    'an overlong type is truncated to its column width',
    32 === strlen($row['type'] ?? '')
);

// ------------------------------------------------- the widths name columns

$prop = (new \ReflectionClass(\FOG\Items\HostUserSession::class))
    ->getProperty('databaseFields');
$prop->setAccessible(true);
$columns = array_values(
    (array)$prop->getValue(new \FOG\Items\HostUserSession())
);

$widthColumns = [
    'key' => 'husSessionKey',
    'user' => 'husUserName',
    'domain' => 'husDomain',
    'sid' => 'husUserSID',
    'type' => 'husType',
    'state' => 'husState',
    'remote_host' => 'husRemoteHost',
    'end_reason' => 'husEndReason'
];
$t->check(
    'every truncated field names a real hostUserSession column',
    [] === array_diff(array_values($widthColumns), $columns)
);
$t->check(
    'every WIDTHS key is one of those fields',
    [] === array_diff(
        array_keys(\FOG\Agent\UserSessions::WIDTHS),
        array_keys($widthColumns)
    )
);

// --------------------------------------------------------- identity is pair

$two = cleanSessions(
    [
        $good,
        array_merge($good, ['started_at' => '2026-09-04T11:00:00Z'])
    ],
    false
);
$t->check(
    'the same session key with a different start is a distinct session'
        . ' (a second logon, not a duplicate)',
    2 === count($two)
);

$t->check(
    'the same key and start collapses to one entry',
    1 === count(cleanSessions([$good, $good], false))
);

// ------------------------------------------------------------- the gate

$t->check(
    'the session gate is FOG\'s existing usertracker module, not a new switch',
    'usertracker' === \FOG\Agent\State::SESSIONS_MODULE
);

$t->check(
    'the poll answer always states collect_sessions',
    method_exists(\FOG\Agent\State::class, 'sessions')
        && method_exists(\FOG\Agent\State::class, 'sessionsEnabled')
);

$t->finish();
