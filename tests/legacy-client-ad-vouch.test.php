<?php
/**
 * The legacy FOG Client is sent the domain join password only on a key that
 * was vouched for (GHSA-97vv-f7hc-rp5m).
 *
 * The legacy handshake, FOGPage::authorize(), selects the host by the MAC
 * the caller sends. A host with no token yet accepts the first key it is
 * offered, and HostnameChanger::json() then sent ADPass to that key on every
 * check-in. Anyone who knew a new host's MAC and connected before its client
 * did received the join account.
 *
 * Executed here, against the real classes:
 *   - json() withholds ADPass from an unvouched key, and keeps the rest of
 *     the AD block, so the client logs "required domain information is
 *     missing" and skips the join rather than unjoining anything;
 *   - Enrollment::recentDeploy(), the rule a first handshake is vouched by;
 *   - the product key is held back on the same rule;
 *   - sec_vouched is server-owned, for 'Host' as import spells it, and
 *     maps to a real column.
 *
 * Pinned by source, because each needs a full HTTP request or a task run:
 *   - authorize() sets sec_vouched on a first handshake only, after the
 *     token check, from recentDeploy();
 *   - task completion and Reset Encryption Data clear it with the key;
 *   - Approve FOG Client is reached through editPost() (host.edit and
 *     object scope), needs a key, and refuses one other than the key the
 *     page showed;
 *   - CSV import skips the server-owned fields before it sets any field.
 *
 * DB-free: the harness's fake connection stands in.
 *
 * Usage: php tests/legacy-client-ad-vouch.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('legacy-client-ad-vouch');
FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

/**
 * A host that needs no database to answer for itself.
 *
 * @param array $fields what to set on it
 *
 * @return \FOG\Items\Host
 */
function vouchHost(array $fields = [])
{
    $Host = new \FOG\Items\Host();
    $Host->set('id', 7)->set('name', 'WS-014');
    foreach ($fields as $k => $v) {
        $Host->set($k, $v);
    }
    return $Host;
}

/**
 * What the legacy client is sent for one host.
 *
 * @param \FOG\Items\Host $Host the host
 *
 * @return array
 */
function vouchLegacy($Host)
{
    FogTestHarness::setStatic('FOGBase', 'Host', $Host);
    $hc = (new \ReflectionClass(\FOG\Client\HostnameChanger::class))
        ->newInstanceWithoutConstructor();
    return $hc->json();
}

/**
 * Sets the deploy window, in hours, for recentDeploy().
 *
 * @param string $hours the setting value
 *
 * @return void
 */
function vouchWindow($hours)
{
    FogTestHarness::setStatic('FOGBase', '_settingsCache', [
        'FOG_AGENT_ENROLL_DEPLOY_WINDOW' => ['value' => $hours]
    ]);
    FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
}

/**
 * The PHP source of a file with comments removed.
 *
 * @param string $path the file
 *
 * @return string
 */
function vouchCode($path)
{
    $out = '';
    foreach (token_get_all((string)file_get_contents($path)) as $tok) {
        if (is_array($tok) && in_array($tok[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $out .= is_array($tok) ? $tok[1] : $tok;
    }
    return $out;
}

/**
 * The body of one method, up to the next method declaration.
 *
 * @param string $code the source
 * @param string $name the method
 *
 * @return string
 */
function vouchMethod($code, $name)
{
    $re = '/function\s+' . preg_quote($name, '/') . '\s*\((.*?)'
        . '(?=\n\s*(?:public|protected|private)\s+(?:static\s+)?function\s|\z)/s';
    return preg_match($re, $code, $m) ? $m[1] : '';
}

// ---- 1. HostnameChanger::json() ---------------------------------------------

$joinable = ['useAD' => 1, 'ADDomain' => 'corp.example.com',
    'ADUser' => 'svc-join', 'ADPass' => 'letmein',
    'ADOU' => 'OU=Workstations,DC=corp,DC=example,DC=com', 'enforce' => 1];

$unvouched = vouchLegacy(vouchHost($joinable + ['sec_vouched' => 0]));
$t->check(
    'an unvouched key is not sent the domain join password',
    '' === $unvouched['ADPass']
);
$t->check(
    'an unvouched key still gets the rest of the AD block, so the client'
        . ' skips the join instead of treating the host as not joining',
    true === $unvouched['AD']
        && 'corp.example.com' === $unvouched['ADDom']
        && 'corp.example.com\\svc-join' === $unvouched['ADUser']
);
$t->check(
    'a key with no vouch recorded at all is not sent the password',
    '' === vouchLegacy(vouchHost($joinable))['ADPass']
);
$t->check(
    'a vouched key is sent the domain join password',
    'letmein' === vouchLegacy(vouchHost($joinable + ['sec_vouched' => 1]))['ADPass']
);
$t->check(
    'an unvouched key is not sent the Windows product key',
    !array_key_exists('Key', vouchLegacy(vouchHost(
        $joinable + ['sec_vouched' => 0, 'productKey' => 'AAAAA-BBBBB-CCCCC-DDDDD-EEEEE']
    )))
);
$t->check(
    'a vouched key is sent the Windows product key',
    'AAAAA-BBBBB-CCCCC-DDDDD-EEEEE' === (vouchLegacy(vouchHost(
        $joinable + ['sec_vouched' => 1, 'productKey' => 'AAAAA-BBBBB-CCCCC-DDDDD-EEEEE']
    ))['Key'] ?? null)
);

// ---- 2. recentDeploy(), the vouch on a first handshake ----------------------

$ago = function ($seconds) {
    return date('Y-m-d H:i:s', time() - $seconds);
};
vouchWindow('24');
$t->check(
    'a host deployed an hour ago is vouched for',
    true === \FOG\Agent\Enrollment::recentDeploy(vouchHost(['deployed' => $ago(3600)]))
);
$t->check(
    'a host deployed two days ago is not',
    false === \FOG\Agent\Enrollment::recentDeploy(vouchHost(['deployed' => $ago(172800)]))
);
$t->check(
    'a host never deployed is not',
    false === \FOG\Agent\Enrollment::recentDeploy(vouchHost())
);
vouchWindow('0');
$t->check(
    'a window of 0 vouches for nothing',
    false === \FOG\Agent\Enrollment::recentDeploy(vouchHost(['deployed' => $ago(60)]))
);

// ---- 3. the field, the API, the permission ----------------------------------

$fields = (new \ReflectionProperty(\FOG\Items\Host::class, 'databaseFields'));
$fields->setAccessible(true);
$t->check(
    'host.sec_vouched maps to hostSecVouched',
    'hostSecVouched' === ($fields->getValue(new \FOG\Items\Host())['sec_vouched'] ?? null)
);
$t->check(
    'host.sec_vouched is server-owned, so a host edit cannot approve a key',
    in_array('sec_vouched', \FOG\Router\Route::serverOwnedFields('host'), true)
);
// The import skip passes childClass ('Host'), so the list must answer for
// that spelling, not only for 'host'.
$t->check(
    "serverOwnedFields('Host') holds sec_tok and sec_vouched, as importPost() asks",
    [] === array_diff(
        ['sec_tok', 'sec_vouched'],
        \FOG\Router\Route::serverOwnedFields('Host')
    )
);

// ---- 4. the paths that need a request: by source ----------------------------

$page = vouchCode($web . '/src/Base/FOGPage.php');
$authorize = vouchMethod($page, 'authorize');
$istAt = strpos($authorize, "throw new \\Exception('#!ist');");
$vouchAt = strpos($authorize, "if (\$secTok === '') {");
$saveAt = strpos($authorize, 'self::$Host->save();');
$t->check(
    'authorize() decides the vouch only on a first handshake, from recentDeploy()',
    false !== $vouchAt
        && 1 === preg_match(
            "/if \(\\\$secTok === ''\) \{\s*self::\\\$Host->set\(\s*'sec_vouched',\s*"
                . "Enrollment::recentDeploy\(self::\\\$Host\) \? 1 : 0\s*\);\s*\}/",
            $authorize
        )
);
$t->check(
    'authorize() decides it after the token check and before the save',
    false !== $istAt && false !== $vouchAt && false !== $saveAt
        && $istAt < $vouchAt && $vouchAt < $saveAt
);
$t->check(
    'Reset Encryption Data clears the vouch with the key',
    1 === preg_match("/'sec_tok' => '',.*'sec_vouched' => 0/s", vouchMethod($page, 'clearAES'))
);

$queue = vouchCode($web . '/src/TaskHandling/TaskQueue.php');
$t->check(
    'task completion clears the vouch with the key, and writes it',
    1 === preg_match("/->set\('sec_tok', ''\).*->set\('sec_vouched', 0\)/s", $queue)
        && false !== strpos($queue, "'sec_vouched' => self::\$Host->get('sec_vouched'),")
);

$hm = vouchCode($web . '/src/Pages/HostManagement.php');
// Reached only through editPost(), which FOGPageManager gates on host.edit
// and the host's object scope before it runs.
$t->check(
    'Approve FOG Client is dispatched from editPost() on the AD tab',
    1 === preg_match(
        "/'host-active-directory' === \\\$tab\s*&& '1' === \(string\)filter_input\(INPUT_POST, "
            . "'approve_client'\)\s*\) \{\s*\\\$this->hostApproveClientPost\(\);\s*return;/",
        vouchMethod($hm, 'editPost')
    )
);
$approve = vouchMethod($hm, 'hostApproveClientPost');
$noKeyAt = strpos($approve, "if (!\$this->obj->get('sec_tok')) {");
$setAt = strpos($approve, "->set('sec_vouched', 1)");
$t->check(
    'approving refuses a host with no key before it sets anything',
    false !== $noKeyAt && false !== $setAt && $noKeyAt < $setAt
);
$keyAt = strpos(
    $approve,
    "if (!hash_equals(self::_legacyClientKeyId(\$this->obj), \$shown)) {"
);
$t->check(
    'approving refuses when the key differs from the one the page showed,'
        . ' before it sets anything',
    false !== $keyAt && false !== $setAt && $keyAt < $setAt
        && false !== strpos($approve, "filter_input(INPUT_POST, 'client_key')")
);
$t->check(
    'the page names the shown key on the button',
    false !== strpos(
        vouchMethod($hm, '_legacyClientVouchPanel'),
        'data-client-key="'
    )
);

// CSV import used to write every field, sec_tok and sec_vouched included,
// so a host.create holder could vouch for a key with no approval.
$import = vouchMethod($page, 'importPost');
$loopAt = strpos($import, 'foreach ((array)$dbkeys as &$field) {');
$skipAt = false === $loopAt ? false
    : strpos($import, "if (in_array(\$field, \$importServerOwned, true)) {\n", $loopAt);
$firstSetAt = false === $loopAt ? false : strpos($import, '$Item->set($field', $loopAt);
$t->check(
    'CSV import skips the server-owned fields before it sets any field',
    false !== strpos($import, '$importServerOwned = Route::serverOwnedFields($this->childClass);')
        && false !== $skipAt && false !== $firstSetAt && $skipAt < $firstSetAt
        && false !== strpos(
            substr($import, $skipAt, $firstSetAt - $skipAt),
            'continue;'
        )
);

$t->check(
    'approving checks auth and CSRF first',
    0 === strpos(ltrim(substr($approve, strpos($approve, '{') + 1)), 'self::checkAuthAndCSRF();')
);

$t->finish();
