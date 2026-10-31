<?php
/**
 * Focused regression checks for the MJ-1 fields in the host AD tab.
 *
 * Usage: php tests/host-ad-mj1.test.php
 */

use FOG\Items\Host;
use FOG\Pages\HostManagement;

require_once __DIR__ . '/lib/fog-test-harness.php';

class HostAdMj1Statement extends FogFakeStatement
{
    private $row;

    public function __construct(array $row)
    {
        parent::__construct([]);
        $this->row = $row;
    }

    public function fetch($mode = null)
    {
        return $this->row;
    }

    public function closeCursor()
    {
        return true;
    }
}

class HostAdMj1Pdo extends FogFakePdo
{
    public static $config = [
        'version' => 1,
        'allow_create' => true,
        'target' => [
            'guid' => '11111111-2222-3333-4444-555555555555',
            'sid' => 'S-1-5-21-10-20-30'
        ],
        'source' => [
            'account' => '<img src=x onerror="alert(1)">',
            'guid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'sid' => 'S-1-5-21-40-50-60'
        ]
    ];
    public static $state = [
        'version' => 1,
        'phase' => 'ready',
        'reason' => '<script>alert(2)</script>',
        'target' => [
            'guid' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
            'sid' => '<svg onload="alert(3)">'
        ]
    ];

    public function prepare($sql)
    {
        if (false !== strpos($sql, 'FROM hostDirectory')) {
            return new HostAdMj1Statement([
                'hdRejoinConfig' => json_encode(self::$config),
                'hdRejoinState' => json_encode(self::$state)
            ]);
        }
        return parent::prepare($sql);
    }
}

FogTestHarness::boot('host-ad-mj1');
$db = FogTestHarness::fakeDb();
$db->pdo = new HostAdMj1Pdo();
$knownEvents = new \ReflectionProperty(
    FOG\Base\HookManager::class,
    'knownEvents'
);
$knownEvents->setAccessible(true);
$knownEvents->setValue(null, ['HOST_EDIT_AD_FIELDS' => true]);
$t = new FogChecks();

$host = (new Host())
    ->set('id', 17)
    ->set('name', 'MJ1 test host')
    ->set('useAD', 1)
    ->set('ADDomain', 'corp.example')
    ->set('ADOU', 'ou=computers,dc=corp,dc=example')
    ->set('ADUser', 'join-user')
    ->set('ADPass', 'must-not-render');
$page = (new \ReflectionClass(HostManagement::class))
    ->newInstanceWithoutConstructor();
$obj = new \ReflectionProperty(FOG\Base\FOGPage::class, 'obj');
$obj->setAccessible(true);
$obj->setValue($page, $host);
$childClass = new \ReflectionProperty(FOG\Base\FOGPage::class, 'childClass');
$childClass->setAccessible(true);
$childClass->setValue($page, 'Host');
$GLOBALS['node'] = 'host';

ob_start();
$render = new \ReflectionMethod(HostManagement::class, 'hostAD');
$render->setAccessible(true);
$render->invoke($page);
$html = ob_get_clean();

$t->check('MJ-1 fields render inside the existing AD form',
    false !== strpos($html, 'id="active-directory-form"')
    && false !== strpos($html, 'name="rejoin_settings_present"')
    && false !== strpos($html, 'name="rejoin_allow_create"')
    && false === strpos($html, 'name="rejoin_enabled"')
    && false === strpos($html, 'name="rejoin_source_account"')
    && false === strpos($html, 'name="rejoin_source_guid"')
    && false === strpos($html, 'name="rejoin_target_guid"')
    && false === strpos($html, 'name="rejoin_retry"')
    && false !== strpos($html, 'rejoin_inspect_target=1')
);
$t->check('account creation defaults on and explicit false remains off',
    (bool)preg_match('/name="rejoin_allow_create"[^>]*checked/', $html)
    && false === strpos($html, 'value="11111111-2222-3333-4444-555555555555"')
    && false === strpos($html, '&lt;img src=x onerror=&quot;alert(1)&quot;&gt;')
);
HostAdMj1Pdo::$config = ['version' => 1];
ob_start();
$render->invoke($page);
$defaultHtml = ob_get_clean();
$t->check('missing creation permission defaults on',
    (bool)preg_match('/name="rejoin_allow_create"[^>]*checked/', $defaultHtml)
);
HostAdMj1Pdo::$config = ['version' => 1, 'allow_create' => false];
ob_start();
$render->invoke($page);
$legacyFalseHtml = ob_get_clean();
$t->check('explicit legacy false remains unchecked',
    0 === preg_match('/name="rejoin_allow_create"[^>]*checked/', $legacyFalseHtml)
);
HostAdMj1Pdo::$config = [
    'version' => 1,
    'allow_create' => true,
    'target' => [
        'guid' => '11111111-2222-3333-4444-555555555555',
        'sid' => 'S-1-5-21-10-20-30'
    ],
    'source' => [
        'account' => '<img src=x onerror="alert(1)">',
        'guid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'sid' => 'S-1-5-21-40-50-60'
    ]
];
HostAdMj1Pdo::$state = [
    'version' => 1,
    'phase' => 'ready',
    'reason' => '<script>alert(2)</script>',
    'target' => [
        'guid' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
        'sid' => '<svg onload="alert(3)">'
    ]
];
ob_start();
$render->invoke($page);
$html = ob_get_clean();
$t->check('status is collapsed and untrusted values are escaped',
    false === strpos($html, 'name="rejoin_current_target_guid"')
    && false === strpos($html, 'name="rejoin_current_target_sid"')
    && false !== strpos($html, '<details class="mb-3"><summary>Directory operation history')
    && false !== strpos($html, '&lt;script&gt;alert(2)&lt;/script&gt;')
    && false === strpos($html, '<script>alert(2)</script>')
    && false === strpos($html, 'must-not-render')
    && false !== strpos($html, 'Domain Join Status')
    && false !== strpos($html, 'Domain Join Status:</strong> Ready')
    && false !== strpos($html, 'class="form-check-input"')
    && false === strpos($html, 'class="form-control" name="rejoin_allow_create"')
);
foreach (['failed', 'unknown'] as $protectedPhase) {
    HostAdMj1Pdo::$state['phase'] = $protectedPhase;
    ob_start();
    $render->invoke($page);
    $protectedHtml = ob_get_clean();
    $t->check('protected ' . $protectedPhase . ' state does not offer Retry',
        false === strpos($protectedHtml, 'name="rejoin_retry"')
        && false !== strpos($protectedHtml, 'This is a point-in-time observation, not proof of online authentication')
    );
}
HostAdMj1Pdo::$state['phase'] = 'verified';
ob_start();
$render->invoke($page);
$verifiedHtml = ob_get_clean();
$t->check('retry control is absent for verified state',
    false === strpos($verifiedHtml, 'name="rejoin_retry"')
    && false !== strpos($verifiedHtml, 'Join operation verified; current status not confirmed')
    && false === strpos($verifiedHtml, 'Joined and secure channel healthy')
);

HostAdMj1Pdo::$state = [
    'version' => 1,
    'phase' => 'verified',
    'reason' => 'directory_recovery_confirmation_required',
    'secure_channel' => 'healthy'
];
ob_start();
$render->invoke($page);
$recoveryHtml = ob_get_clean();
$t->check('historical recovery reason alone does not expose retry',
    false === strpos($recoveryHtml, 'name="rejoin_retry"')
);

$dc = 'dc1.corp.example';
$hostObservation = [
    'current_name' => 'MJ1 test host',
    'pending_name' => 'MJ1 test host',
    'domain' => 'corp.example',
    'netbios' => 'CORP',
    'secure_channel' => 'false',
    'dc' => $dc,
    'dc_reachable' => true,
    'revision' => FOG\Agent\DirectoryOperation::revision(
        $host,
        HostAdMj1Pdo::$config,
        $dc
    )
];
HostAdMj1Pdo::$state = [
    'version' => 1,
    'phase' => 'verified',
    'reason' => 'verified',
    'secure_channel' => 'healthy',
    'check_dc' => $dc,
    'observed_at' => time(),
    'observation' => $hostObservation
];
ob_start();
$render->invoke($page);
$brokenVerifiedHtml = ob_get_clean();
$t->check('verified fresh False explains the observation and offers explicit Retry',
    false !== strpos($brokenVerifiedHtml, 'Latest secure channel check returned False')
    && false !== strpos($brokenVerifiedHtml, 'name="rejoin_retry"')
    && false === strpos($brokenVerifiedHtml, 'Joined and secure channel healthy')
    && false !== strpos($brokenVerifiedHtml, 'not proof of online authentication')
    && false !== strpos($brokenVerifiedHtml, 'explicit administrator judgment')
);

HostAdMj1Pdo::$state['observation']['secure_channel'] = 'unknown';
HostAdMj1Pdo::$state['observation']['revision'] = FOG\Agent\DirectoryOperation::revision(
    $host,
    HostAdMj1Pdo::$config,
    $dc
);
ob_start();
$render->invoke($page);
$unknownTrustHtml = ob_get_clean();
$t->check('fresh unknown trust overrides historical verified',
    false !== strpos($unknownTrustHtml, 'Secure channel status unknown')
    && false === strpos($unknownTrustHtml, 'Joined and secure channel healthy')
);

HostAdMj1Pdo::$state['observation']['secure_channel'] = 'true';
HostAdMj1Pdo::$state['observation']['current_name'] = 'OTHER-HOST';
HostAdMj1Pdo::$state['observation']['pending_name'] = 'OTHER-HOST';
HostAdMj1Pdo::$state['observation']['revision'] = FOG\Agent\DirectoryOperation::revision(
    $host,
    HostAdMj1Pdo::$config,
    $dc
);
ob_start();
$render->invoke($page);
$wrongContextHtml = ob_get_clean();
$t->check('verified observation for a different host is not presented as healthy',
    false !== strpos($wrongContextHtml, 'Current directory observation does not match this host')
    && false === strpos($wrongContextHtml, 'Joined and secure channel healthy')
);

$renderState = static function (array $state) use ($page, $render) {
    HostAdMj1Pdo::$state = $state;
    ob_start();
    $render->invoke($page);
    return ob_get_clean();
};
$automaticObservation = $hostObservation;
$automaticObservation['secure_channel'] = 'true';
$automaticObservation['revision'] = FOG\Agent\DirectoryOperation::revision(
    $host,
    HostAdMj1Pdo::$config,
    ''
);
$automaticState = [
    'version' => 1,
    'phase' => 'verified',
    'secure_channel' => 'healthy',
    'observed_at' => time(),
    'observation' => $automaticObservation
];
$historyRecoveryState = $automaticState;
$historyRecoveryState['reason'] = 'directory_recovery_confirmation_required';
$historyRecoveryState['secure_channel'] = 'broken';
$historyRecoveryHtml = $renderState($historyRecoveryState);
$t->check('T08 fresh True stays current while stale reason and broken result remain history',
    false !== strpos($historyRecoveryHtml, 'Joined and secure channel healthy')
    && false === strpos($historyRecoveryHtml, 'name="rejoin_retry"')
    && false !== strpos($historyRecoveryHtml, 'directory_recovery_confirmation_required')
    && false !== strpos($historyRecoveryHtml, 'broken')
    && false !== strpos($historyRecoveryHtml, 'Directory operation history')
);

foreach (['failed', 'unknown'] as $unresolvedPhase) {
    $unresolvedState = $historyRecoveryState;
    $unresolvedState['phase'] = $unresolvedPhase;
    $unresolvedHtml = $renderState($unresolvedState);
    $t->check('T09 ' . $unresolvedPhase . ' protected operation has no Retry with fresh True',
        false === strpos($unresolvedHtml, 'name="rejoin_retry"')
        && false !== strpos($unresolvedHtml, 'Joined and secure channel healthy')
    );
}

$pendingTrueState = $automaticState;
$pendingTrueState['phase'] = 'started';
$pendingTrueState['placement_hold'] = true;
$pendingTrueHtml = $renderState($pendingTrueState);
$t->check('T18 fresh True with hold is not shown as healthy and has no retry',
    false !== strpos($pendingTrueHtml, 'Latest secure channel check passed; directory actions are on hold')
    && false === strpos($pendingTrueHtml, 'Joined and secure channel healthy')
    && false === strpos($pendingTrueHtml, 'name="rejoin_retry"')
);
foreach (['started', 'awaiting_reboot'] as $activePhase) {
    $activeState = $automaticState;
    $activeState['phase'] = $activePhase;
    $activeHtml = $renderState($activeState);
    $t->check('T18 ' . $activePhase . ' phase does not offer retry',
        false === strpos($activeHtml, 'name="rejoin_retry"')
    );
}

$automaticUnsetHtml = $renderState($automaticState);
$t->check('unset check DC accepts a fresh discovered DC observation',
    false !== strpos($automaticUnsetHtml, 'Joined and secure channel healthy')
    && false === strpos($automaticUnsetHtml, 'Current directory observation does not match this host')
);
$automaticState['check_dc'] = '';
$automaticEmptyHtml = $renderState($automaticState);
$t->check('empty check DC accepts a fresh discovered DC observation',
    false !== strpos($automaticEmptyHtml, 'Joined and secure channel healthy')
    && false === strpos($automaticEmptyHtml, 'Current directory observation does not match this host')
);

$wrongDcState = $automaticState;
$wrongDcState['check_dc'] = 'other-dc.corp.example';
$wrongDcState['observation']['revision'] = FOG\Agent\DirectoryOperation::revision(
    $host,
    HostAdMj1Pdo::$config,
    $wrongDcState['check_dc']
);
$wrongDcHtml = $renderState($wrongDcState);
$t->check('explicit check DC still requires an exact observation DC match',
    false !== strpos($wrongDcHtml, 'Current directory observation does not match this host')
    && false === strpos($wrongDcHtml, 'Joined and secure channel healthy')
);

$emptyObservedDcState = $automaticState;
$emptyObservedDcState['observation']['dc'] = '';
$emptyObservedDcState['observation']['revision'] = FOG\Agent\DirectoryOperation::revision(
    $host,
    HostAdMj1Pdo::$config,
    ''
);
$emptyObservedDcHtml = $renderState($emptyObservedDcState);
$t->check('empty observed DC is never presented as current and healthy',
    false !== strpos($emptyObservedDcHtml, 'Current directory observation does not match this host')
    && false === strpos($emptyObservedDcHtml, 'Joined and secure channel healthy')
);

$staleRevisionState = $automaticState;
$staleRevisionState['observation']['revision'] = str_repeat('0', 32);
$staleRevisionHtml = $renderState($staleRevisionState);
$t->check('stale observation revision cannot produce a healthy status',
    false !== strpos($staleRevisionHtml, 'Join operation verified; current status not confirmed')
    && false === strpos($staleRevisionHtml, 'Joined and secure channel healthy')
);

$expiredState = $automaticState;
$expiredState['observed_at'] = time() - 601;
$expiredHtml = $renderState($expiredState);
$t->check('T18 expired observation is unconfirmed and historical reason alone gives no retry',
    false !== strpos($expiredHtml, 'Join operation verified; current status not confirmed')
    && false === strpos($expiredHtml, 'name="rejoin_retry"')
);

$freshFalseState = $automaticState;
$freshFalseState['observation']['secure_channel'] = 'false';
$freshFalseHtml = $renderState($freshFalseState);
$t->check('T18 verified fresh False explains observation and offers Retry',
    false !== strpos($freshFalseHtml, 'Latest secure channel check returned False')
    && false !== strpos($freshFalseHtml, 'not proof of online authentication')
    && false !== strpos($freshFalseHtml, 'explicit administrator judgment')
    && false !== strpos($freshFalseHtml, 'name="rejoin_retry"')
);

// hostADPost deliberately runs auth/CSRF first. Keep this focused test
// independent of session setup by pinning the handler's compatibility guard
// and the exact field-to-config mapping it uses after that guard.
$source = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Pages/HostManagement.php'
);
$start = strpos($source, 'public function hostADPost()');
$end = strpos($source, 'private function hostAD()', $start);
$postBody = false === $start || false === $end
    ? '' : substr($source, $start, $end - $start);
$markerAt = strpos($postBody, 'isset($_POST[\'rejoin_settings_present\'])');
$setAdAt = strpos($postBody, '$this->obj->setAD(');
$t->check('marker-less legacy POST remains on the existing AD path',
    '' !== $postBody
    && false !== $setAdAt
    && false !== $markerAt
    && $setAdAt < $markerAt
    && false !== strpos($postBody, '\'1\' === (string)$_POST[\'rejoin_settings_present\']')
);
$t->check('marked POST maps only the declared MJ-1 settings and actor',
    false === strpos($postBody, '\'rejoin_enabled\' =>')
    && false === strpos($postBody, '\'rejoin_source_account\' =>')
    && false === strpos($postBody, '\'rejoin_source_guid\' =>')
    && false !== strpos($postBody, '\'rejoin_allow_create\' => $checkbox(')
    && false !== strpos($postBody, '\'rejoin_confirm_target\' => $checkbox(')
    && false !== strpos($postBody, '\'rejoin_target_guid\' => $text(\'rejoin_target_guid\')')
    && false !== strpos($postBody, '\'rejoin_retry\' => $checkbox(\'rejoin_retry\')')
    && false !== strpos($postBody, '(string)self::$FOGUser->get(\'name\')')
);

$pot = file_get_contents(
    dirname(__DIR__) . '/packages/web/management/languages/messages.pot'
);
$ja = file_get_contents(
    dirname(__DIR__)
        . '/packages/web/management/languages/ja_JP.UTF-8/LC_MESSAGES/messages.po'
);
$translated = [
    'Allow Computer Account Creation',
    'Computer GUID',
    'Computer SID',
    'Domain Join Status',
    'Confirm that this account is assigned to this host or its handoff is approved. Do not select an account belonging to another computer by mistake.',
    'Ready',
    'In progress',
    'Awaiting reboot',
    'Verified',
    'Blocked',
    'Needs operator review',
    'No status reported',
    'Latest secure channel check returned False',
    'Secure channel status unknown',
    'Joined and secure channel healthy',
    'Join operation verified; current status not confirmed',
    'Current directory observation does not match this host',
    'No matching computer account was found.',
    'Target lookup is unavailable.',
    'Target computer account',
    'Inspect the current directory candidate before confirming it.',
    'Inspect target candidate',
    'Confirm Retry',
    'Directory operation history',
    'Directory operation phase',
    'Saved diagnostic reason (may be historical)',
    'Recorded operation result (history)',
    'Native status (history)',
    'Last operation result time (UTC)',
    'Latest secure channel check passed; directory actions are on hold',
    'This is a point-in-time observation, not proof of online authentication or a confirmed credential mismatch. Confirm the target and use explicit administrator judgment before retrying.'
];
$catalogsPresent = true;
foreach ($translated as $msgid) {
    $quoted = preg_quote($msgid, '/');
    $catalogsPresent = $catalogsPresent
        && 1 === preg_match('/msgid "' . $quoted . '"\s+msgstr ""/', $pot)
        && 1 === preg_match('/msgid "' . $quoted . '"\s+msgstr "[^"\\\\]+"/', $ja);
}
$t->check('new UI strings have gettext entries and Japanese translations',
    $catalogsPresent
);

$t->finish();
