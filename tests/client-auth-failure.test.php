<?php
/**
 * A legacy FOG Client's failed handshakes are kept on its host (schema 459).
 *
 * Forum topic 18312: a client that fails FOGPage::authorize() retries on
 * every check-in, forever, and FOG answered with HTTP 200 and recorded
 * nothing. The Client Authentication Failures report lists these hosts.
 *
 * Executed here, against the real Host class and the harness's fake
 * connection:
 *   - recordAuthFailure() writes only the four columns, with a targeted
 *     UPDATE: the count increments in SQL, the run's first time survives,
 *     and the code and the caller's address are bound, never interpolated;
 *   - it writes nothing for a host with no id;
 *   - clearAuthFailure() writes nothing when there is no run to clear, so
 *     a healthy check-in costs no extra query, and clears all four when
 *     there is one;
 *   - the four fields map to the schema-459 columns and are server-owned.
 *
 * Pinned by source, because authorize() ends the request with exit:
 *   - the catch records '#!ist' as 'ist' and certDecrypt()'s failure as
 *     'dec', before any response is written, and never '#!ihc';
 *   - the clear runs only when the save succeeded.
 *
 * Usage: php tests/client-auth-failure.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('client-auth-failure');
FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$DB = FogTestHarness::getStatic('FOGBase', 'DB');
// An object, not an array captured by reference, so the static analysis
// sees the writes the responder makes.
$log = new \ArrayObject();
$DB->responder = function ($sql, $params) use ($log) {
    if (0 === strpos($sql, 'UPDATE `hosts`')) {
        $log->append(['sql' => $sql, 'params' => $params]);
    }
    return null;
};

/**
 * Runs one call and returns the hosts UPDATEs it made.
 *
 * @param callable $call the call under test
 *
 * @return array
 */
function hostWrites(callable $call)
{
    global $log;
    $log->exchangeArray([]);
    $call();
    return $log->getArrayCopy();
}
FogTestHarness::setStatic('FOGBase', 'remoteaddr', '10.1.2.3');

/**
 * A host that needs no database to answer for itself.
 *
 * @param array $fields what to set on it
 *
 * @return \FOG\Items\Host
 */
function authHost(array $fields = [])
{
    $Host = new \FOG\Items\Host();
    $Host->set('id', 41)->set('name', 'LAB-41');
    foreach ($fields as $k => $v) {
        $Host->set($k, $v);
    }
    return $Host;
}

// ---- 1. recording -----------------------------------------------------------

$seen = hostWrites(function () {
    authHost()->recordAuthFailure('ist');
});
$w = $seen[0] ?? ['sql' => '', 'params' => []];
$t->check('a failure is one UPDATE of the hosts row', 1 === count($seen));
$t->check(
    'the count increments in SQL, not from the loaded value',
    false !== strpos($w['sql'], '`hostAuthFailCount` = `hostAuthFailCount` + 1')
);
$t->check(
    'the first failure of the run is kept',
    false !== strpos($w['sql'], '`hostAuthFailSince` = IFNULL(`hostAuthFailSince`, :since)')
);
$t->check(
    'code, address and host are bound',
    'ist' === ($w['params'][':code'] ?? null)
        && '10.1.2.3' === ($w['params'][':ip'] ?? null)
        && 41 === ($w['params'][':id'] ?? null)
        && false !== strpos($w['sql'], 'WHERE `hostID` = :id')
);
// Every column assigned between SET and WHERE, in order.
preg_match_all(
    '/`(\w+)` = /',
    (string)strstr(substr((string)strstr($w['sql'], ' SET '), 5), ' WHERE ', true),
    $set
);
$t->check(
    'nothing but the four columns is written',
    ['hostAuthFailSince', 'hostAuthFailCode', 'hostAuthFailCount', 'hostAuthFailIP']
        === $set[1]
);

$seen = hostWrites(function () {
    (new \FOG\Items\Host())->recordAuthFailure('ist');
});
$t->check('a host with no id records nothing', [] === $seen);

// ---- 2. clearing ------------------------------------------------------------

$seen = hostWrites(function () {
    authHost(['authFailCount' => 0])->clearAuthFailure();
});
$t->check('a healthy handshake costs no extra write', [] === $seen);

$seen = hostWrites(function () {
    authHost(['authFailCount' => 3])->clearAuthFailure();
});
$c = $seen[0]['sql'] ?? '';
$t->check(
    'a recovered host has all four columns cleared',
    1 === count($seen)
        && false !== strpos($c, '`hostAuthFailSince` = NULL')
        && false !== strpos($c, "`hostAuthFailCode` = ''")
        && false !== strpos($c, '`hostAuthFailCount` = 0')
        && false !== strpos($c, "`hostAuthFailIP` = ''")
);

// ---- 3. the model -----------------------------------------------------------

$fields = (new \ReflectionClass(\FOG\Items\Host::class))
    ->getDefaultProperties()['databaseFields'];
$t->check(
    'the four fields map to the schema-459 columns',
    'hostAuthFailSince' === ($fields['authFailSince'] ?? null)
        && 'hostAuthFailCode' === ($fields['authFailCode'] ?? null)
        && 'hostAuthFailCount' === ($fields['authFailCount'] ?? null)
        && 'hostAuthFailIP' === ($fields['authFailIP'] ?? null)
);
$owned = \FOG\Router\Route::serverOwnedFields('host');
$t->check(
    'the API refuses writes to all four',
    [] === array_diff(
        ['authFailSince', 'authFailCode', 'authFailCount', 'authFailIP'],
        $owned
    )
);

// ---- 4. authorize(): by source ---------------------------------------------

$src = (string)file_get_contents($web . '/src/Base/FOGPage.php');
$start = strpos($src, 'public function authorize()');
$end = strpos($src, 'public function requestClientInfo()');
$authorize = (false !== $start && false !== $end)
    ? substr($src, $start, $end - $start)
    : '';
$catchAt = strpos($authorize, '} catch (\\Exception $e) {');
$recordAt = strpos($authorize, 'self::$Host->recordAuthFailure(');
$firstOut = min(
    array_filter(
        [
            strpos($authorize, 'die(', (int)$catchAt),
            strpos($authorize, '$this->jsonSend(', (int)$catchAt),
            strpos($authorize, 'echo  $e->getMessage()', (int)$catchAt),
        ],
        'is_int'
    ) ?: [PHP_INT_MAX]
);
$t->check(
    'authorize() records in its catch, before any response goes out',
    false !== $catchAt && false !== $recordAt
        && $catchAt < $recordAt && $recordAt < $firstOut
);
$t->check(
    'a token mismatch is recorded as ist',
    1 === preg_match(
        "/if \('#!ist' === \\\$e->getMessage\(\)\) \{\s*\\\$code = 'ist';/",
        $authorize
    )
);
$t->check(
    'a key the server cannot decrypt is recorded as dec, by the message certDecrypt() throws',
    1 === preg_match(
        "/_\('Failed to decrypt data on server'\) === \\\$e->getMessage\(\)\) \{\s*\\\$code = 'dec';/",
        $authorize
    )
    && false !== strpos(
        (string)file_get_contents($web . '/src/Base/FOGBase.php'),
        "throw new \\Exception(_('Failed to decrypt data on server'));"
    )
);
$t->check(
    '#!ihc is not recorded: a bare mac= POST reaches it',
    false === strpos($authorize, "\$code = 'ihc'")
);
$t->check(
    'the clear runs only when the save succeeded',
    1 === preg_match(
        '/if \(self::\$Host->save\(\)\) \{\s*self::\$Host->clearAuthFailure\(\);\s*\}/',
        $authorize
    )
    && strpos($authorize, 'self::$Host->clearAuthFailure();') < $catchAt
);

$t->finish();
