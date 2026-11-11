<?php
/**
 * A failed legacy client handshake is recorded on the host, and only that.
 *
 * Forum topic 18312: a FOG Client that cannot authenticate retries on every
 * check-in, runs no modules, and the only place that said so was the
 * client's own log. FOGPage::authorize() now stamps the host (schema 459)
 * and the Client Authentication Failures report lists those hosts.
 *
 * Four properties, each a way the obvious version goes wrong:
 *
 *  1. Only 'ist' and 'ihc' are recorded. 'ih' has no host to record against,
 *     and anything else is not an authentication answer.
 *  2. The write is a targeted UPDATE of the four columns. authorize() can
 *     throw after it has set() a rotated token; a Host::save() here would
 *     commit the token the client never received.
 *  3. The columns are server-owned and stay out of grids and exports.
 *  4. A success clears them in the same save() that commits the reply, so a
 *     host leaves the report when it recovers, not when someone clicks.
 *
 * DB-free: FogFakeDb logs the SQL.
 *
 * Usage: php tests/client-auth-failure-recorded.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('client-auth-failure-recorded');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * A loaded-looking host with a rotation pending in memory.
 *
 * @return \FOG\Items\Host
 */
function authFailHost()
{
    $Host = new \FOG\Items\Host();
    $Host->set('id', 7)
        ->set('name', 'WS-014')
        ->set('sec_tok', 'rotated-but-not-delivered')
        ->set('authFailCount', 2);
    return $Host;
}

/**
 * Call the private recorder and return the SQL it issued.
 *
 * @param FogFakeDb       $db   the fake
 * @param string          $code the protocol answer
 * @param \FOG\Items\Host $Host the request's host
 *
 * @return array
 */
function authFailRecord($db, $code, $Host)
{
    FogTestHarness::setStatic('FOGBase', 'Host', $Host);
    $db->log = [];
    $page = (new \ReflectionClass(\FOG\Pages\HostManagement::class))
        ->newInstanceWithoutConstructor();
    $m = new \ReflectionMethod(\FOG\Base\FOGPage::class, '_recordAuthFailure');
    $m->setAccessible(true);
    $m->invoke($page, $code);
    return array_values(array_filter($db->log, function ($sql) {
        return false !== stripos($sql, 'UPDATE');
    }));
}

// ---- 1. which answers are recorded -----------------------------------------

$ist = authFailRecord($db, '#!ist', authFailHost());
$t->check('#!ist is recorded with one UPDATE', 1 === count($ist));
$t->check(
    'the UPDATE writes all four failure columns',
    1 === count($ist)
        && false !== strpos($ist[0], 'hostAuthFailAt')
        && false !== strpos($ist[0], 'hostAuthFailCode')
        && false !== strpos($ist[0], 'hostAuthFailCount')
        && false !== strpos($ist[0], 'hostAuthFailIP')
);
$t->check(
    'and nothing else: a pending token rotation is not committed',
    1 === count($ist)
        && false === strpos($ist[0], 'hostSecToken')
        && false === strpos($ist[0], 'hostPubKey')
        && false === strpos($ist[0], 'hostName')
);
$t->check(
    '#!ihc is recorded',
    1 === count(authFailRecord($db, '#!ihc', authFailHost()))
);
$t->check(
    '#!ih is not: there is no host to record against',
    0 === count(authFailRecord($db, '#!ih', authFailHost()))
);
$t->check(
    'an error that is not an auth answer is not recorded',
    0 === count(authFailRecord($db, 'Private key path not found', authFailHost()))
);
$t->check(
    'an invalid host is not recorded',
    0 === count(authFailRecord($db, '#!ist', new \FOG\Items\Host()))
);

// ---- 2. the fields ----------------------------------------------------------

$fields = new \ReflectionProperty(\FOG\Items\Host::class, 'databaseFields');
$fields->setAccessible(true);
$map = $fields->getValue(new \FOG\Items\Host());
$want = [
    'authFailAt' => 'hostAuthFailAt',
    'authFailCode' => 'hostAuthFailCode',
    'authFailCount' => 'hostAuthFailCount',
    'authFailIP' => 'hostAuthFailIP',
];
foreach ($want as $key => $column) {
    $t->check(
        "host.$key maps to $column",
        $column === ($map[$key] ?? null)
    );
    $t->check(
        "host.$key is server-owned",
        in_array($key, \FOG\Router\Route::serverOwnedFields('host'), true)
    );
    $t->check(
        "host.$key is kept out of grids and exports",
        in_array($key, \FOG\Router\Route::$sensitiveFields['host'], true)
    );
}

// ---- 3. success clears it, inside the committing save ----------------------

$src = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Base/FOGPage.php'
);
$start = strpos($src, 'public function authorize()');
$end = strpos($src, 'private function _recordAuthFailure(');
$body = substr($src, (int)$start, (int)$end - (int)$start);
$clear = strpos($body, "->set('authFailCount', 0)");
$save = strpos($body, 'self::$Host->save();');
$t->check(
    'a successful handshake clears the failure before the save that commits it',
    false !== $clear && false !== $save && $clear < $save
);
$t->check(
    'the catch records the failure',
    false !== strpos($body, '$this->_recordAuthFailure($e->getMessage());')
);

// ---- 4. the report ----------------------------------------------------------

$t->check(
    'the report has a real title, not one derived from its file name',
    'Client Authentication Failures'
        === \FOG\Pages\ReportManagement::titleFor('client authentication')
);
$db->log = [];
$db->responder = function ($sql) {
    if (false === strpos($sql, 'hostAuthFailCount')) {
        return null;
    }
    return [[
        'hostID' => 7,
        'hostName' => 'WS-014',
        'hostIP' => '10.0.0.14',
        'hostLastCheckin' => null,
        'hostAuthFailAt' => '2026-11-11 09:00:00',
        'hostAuthFailCode' => 'ist',
        'hostAuthFailCount' => 3,
        'hostAuthFailIP' => '10.0.0.99',
    ]];
};
$report = (new \ReflectionClass(\FOG\Reports\Client_Authentication::class))
    ->newInstanceWithoutConstructor();
$rows = new \ReflectionMethod(\FOG\Reports\Client_Authentication::class, 'reportRows');
$rows->setAccessible(true);
$payload = $rows->invoke($report);
$db->responder = null;
$t->check(
    'the report lists only hosts holding a failure',
    1 === count(array_filter($db->log, function ($sql) {
        return false !== strpos($sql, '`hostAuthFailCount` > 0');
    }))
);
$row = $payload['data'][0] ?? [];
$t->check(
    'a row names the host, the problem in words, the count and the source',
    7 === ($row['id'] ?? null)
        && 'WS-014' === ($row['hostName'] ?? null)
        && 'Security token mismatch' === ($row['problem'] ?? null)
        && 3 === ($row['failures'] ?? null)
        && '10.0.0.99' === ($row['fromIP'] ?? null)
        && '' === ($row['checkin'] ?? null)
);
$t->check('an uncapped answer is not marked truncated', false === $payload['truncated']);
$t->check(
    'the report is gated on host.view, not the shared `report` node',
    'host' === (\FOG\Auth\Authorization::REPORT_NODES['client_authentication'] ?? null)
);
$js = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/management/js/fog/report/fog.report.file.js'
);
$at = strpos($js, "case 'client authentication':");
$block = false === $at ? '' : substr($js, $at, strpos($js, 'break;', $at) - $at);
foreach (['hostName', 'problem', 'failures', 'failedAt', 'fromIP', 'checkin'] as $col) {
    $t->check(
        "the grid asks for the '$col' key the report emits",
        false !== strpos($block, "data: '$col'")
            && array_key_exists($col, $row)
    );
}
$t->check(
    'the host name is linked with .text(), not concatenated as HTML',
    false !== strpos($block, '.text(data)')
);

$t->finish();
