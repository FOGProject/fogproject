<?php
/**
 * FOG_DEBUG_SSH_KEYS: the SSH public keys a debug task installs for root (#2193).
 *
 * The setting gives root SSH on every machine in a debug task, so this pins
 * who decides the keys and what reaches FOS:
 *
 * - DebugSshKeys::normalize() keeps only OpenSSH public keys. It refuses an
 *   authorized_keys option line, an unknown type, bad base64, and key data
 *   whose own type field does not match the line's type.
 * - DebugSshKeys::forTask() sends keys only for a debug task, by the REAL
 *   Task and TaskType rules: Debug (3), Debug Deploy (15), Debug Capture
 *   (16), and the Debug checkbox. A stored value that fails normalize() sends
 *   nothing.
 * - service/hostinfo.php always sends debugsshkeys, last. FOS lets a reply
 *   value override the kernel line, so a debugsshkeys= in the host's
 *   kernelArgs must never reach FOS, on a debug task or any other. This runs
 *   hostinfo.php's own merge block (tests/lib/hostinfo-parity.php).
 * - Setting::save(), which the REST create and edit routes use, refuses a
 *   bad value and writes an audit row naming the added key's fingerprint.
 *
 * Requires no database: the runtime boots over the fake one.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require_once __DIR__ . '/lib/fog-test-harness.php';
require_once __DIR__ . '/lib/hostinfo-parity.php';

FogTestHarness::boot('debug-ssh-keys');
$db = FogTestHarness::fakeDb();

use FOG\Items\Setting;
use FOG\Items\Task;
use FOG\Items\TaskType;
use FOG\Util\DebugSshKeys;

$t = new \FogChecks();

/**
 * An OpenSSH public key line with well-formed key data: the type, as a
 * length-prefixed string inside the base64, then 32 bytes made from $seed.
 *
 * @param string $type    the key type
 * @param string $seed    makes each key distinct
 * @param string $comment the optional comment
 *
 * @return string
 */
function debugKeyLine($type, $seed, $comment = '')
{
    $blob = pack('N', strlen($type)) . $type
        . pack('N', 32) . substr(str_repeat($seed, 32), 0, 32);
    return trim("$type " . base64_encode($blob) . " $comment");
}

/**
 * The exception normalize() throws for a value, or null.
 *
 * @param string $value the value
 *
 * @return \Exception|null
 */
function debugKeyRefusal($value)
{
    try {
        DebugSshKeys::normalize($value);
    } catch (\Exception $e) {
        return $e;
    }
    return null;
}

$ed = debugKeyLine('ssh-ed25519', 'a', 'admin@example');
$ec = debugKeyLine('ecdsa-sha2-nistp256', 'b');
$evil = debugKeyLine('ssh-ed25519', 'z', 'attacker');

// ---- normalize() ------------------------------------------------------------
$t->check(
    'blank and # lines are dropped and a repeated key is kept once',
    DebugSshKeys::normalize("\n# ops keys\r\n  $ed  \n\n$ec\n$ed\n") === "$ed\n$ec"
);
$t->check(
    'control characters are removed from a comment',
    DebugSshKeys::normalize(debugKeyLine('ssh-ed25519', 'a', "ad\x07min")) === debugKeyLine('ssh-ed25519', 'a', 'admin')
);
$t->check('an empty value is no keys', DebugSshKeys::normalize("  \n ") === '');
$refused = debugKeyRefusal("$ed\ncommand=\"/bin/sh\" $evil");
$t->check(
    'an authorized_keys option line is refused, naming its line, with code 400',
    $refused instanceof \InvalidArgumentException
        && 400 === $refused->getCode()
        && false !== strpos($refused->getMessage(), 'line 2')
);
$t->check('an unknown key type is refused', null !== debugKeyRefusal(debugKeyLine('ssh-dss', 'c')));
$t->check('key data that is not base64 is refused', null !== debugKeyRefusal('ssh-ed25519 not*base64'));
$t->check('a line with no key data is refused', null !== debugKeyRefusal('ssh-ed25519'));
$t->check(
    'key data whose own type does not match the line is refused',
    null !== debugKeyRefusal('ssh-rsa ' . explode(' ', $ed)[1])
);

// ---- forTask(): the real Task and TaskType rules ---------------------------
/**
 * forTask() for a real task and type built from field arrays.
 *
 * @param array  $task  the task fields
 * @param array  $type  the task type fields
 * @param string $value the stored setting
 *
 * @return string
 */
function debugKeysForTask(array $task, array $type, $value)
{
    // The required fields every real row carries, so isValid() is decided
    // by what a case sets. A token hash, not a host: a host id would make
    // the model load a host row the fake database does not have.
    $task += ['stateID' => 1, 'tokenHash' => str_repeat('a', 64)];
    $type += ['name' => 'type', 'icon' => 'bug'];
    return DebugSshKeys::forTask(new Task($task), new TaskType($type), $value);
}
$expect = base64_encode("$ed\n");
$t->check(
    'a Debug task (type 3, mode=onlydebug on the type) gets the keys',
    debugKeysForTask(['id' => 1, 'typeID' => 3], ['id' => 3, 'kernelArgs' => 'mode=onlydebug'], $ed) === $expect
);
$t->check(
    'a Debug Deploy task (type 15) gets the keys',
    debugKeysForTask(['id' => 1, 'typeID' => 15], ['id' => 15, 'kernelArgs' => 'type=down'], $ed) === $expect
);
$t->check(
    'a Debug Capture task (type 16) gets the keys',
    debugKeysForTask(['id' => 1, 'typeID' => 16], ['id' => 16, 'kernelArgs' => 'type=up'], $ed) === $expect
);
$t->check(
    'a deploy with the Debug checkbox gets the keys',
    debugKeysForTask(['id' => 1, 'typeID' => 1, 'isDebug' => 1], ['id' => 1, 'kernelArgs' => 'type=down'], $ed) === $expect
);
$t->check(
    'a plain deploy gets no keys',
    debugKeysForTask(['id' => 1, 'typeID' => 1], ['id' => 1, 'kernelArgs' => 'type=down'], $ed) === ''
);
$t->check(
    'a task that is not valid gets no keys',
    debugKeysForTask(['typeID' => 15], ['id' => 15], $ed) === ''
);
$t->check(
    'a stored value that fails normalize() sends nothing',
    debugKeysForTask(['id' => 1, 'typeID' => 15], ['id' => 15], "$ed\ncommand=\"x\" $evil") === ''
);
$t->check(
    'an empty setting sends nothing',
    debugKeysForTask(['id' => 1, 'typeID' => 15], ['id' => 15], '') === ''
);

// ---- service/hostinfo.php: the server's value is the one FOS gets ----------
$debugType = ['id' => 15, 'imaging' => true, 'initneeded' => true, 'kernelArgs' => 'type=down', 'debug' => true];
$settings = ['settings' => ['FOG_DEBUG_SSH_KEYS' => $ed]];
$evilArg = 'debugsshkeys=' . base64_encode("$evil\n");
$fields = hostinfoFields(parityScenario($debugType, ['kernelArgs' => $evilArg], [], $settings));
$t->check(
    'a debug task sends the setting, not the host kernelArgs value',
    ($fields['debugsshkeys'] ?? null) === $expect
);
$fields = hostinfoFields(parityScenario(PARITY_DEPLOY, ['kernelArgs' => $evilArg], [], $settings));
$t->check(
    'a plain deploy sends the field empty, so a host kernelArgs value is overridden',
    array_key_exists('debugsshkeys', $fields) && '' === $fields['debugsshkeys']
);
$fields = hostinfoFields(parityScenario($debugType, [], [], ['settings' => ['FOG_DEBUG_SSH_KEYS' => '']]));
$t->check(
    'a debug task with no keys configured sends the field empty',
    array_key_exists('debugsshkeys', $fields) && '' === $fields['debugsshkeys']
);
$fields = hostinfoFields(
    parityScenario(
        $debugType,
        [],
        [],
        $settings + ['hookMutations' => ['HOST_INFO_EXPOSE' => ['repFields' => ['debugsshkeys' => 'bGlzdGVuZXI=']]]]
    )
);
$t->check(
    'a HOST_INFO_EXPOSE listener cannot replace the keys',
    ($fields['debugsshkeys'] ?? null) === $expect
);

// ---- Setting::save(): the REST create and edit path ------------------------
/**
 * The queries the fake database saw since the last reset.
 *
 * A function, not a captured array: PHPStan cannot see a closure fill an
 * array by reference, and reads every later check on it as constant.
 *
 * @param array|null $query a [sql, params] pair to record, or null to read
 * @param bool       $reset start a new list
 *
 * @return array<int, array{0: string, 1: mixed}>
 */
function debugKeyQueries($query = null, $reset = false)
{
    static $queries = [];
    if ($reset) {
        $queries = [];
    }
    if (null !== $query) {
        $queries[] = [(string)$query[0], $query[1]];
    }
    return $queries;
}
debugKeyQueries(null, true);
$db->responder = function ($sql, $params) {
    debugKeyQueries([$sql, $params]);
    if (false !== stripos($sql, 'FROM `globalSettings`')) {
        return [[
            'settingID' => 7,
            'settingKey' => 'FOG_DEBUG_SSH_KEYS',
            'settingDesc' => '',
            'settingValue' => '',
            'settingCategory' => 'Security',
        ]];
    }
    return null;
};
$refused = null;
try {
    (new Setting())
        ->set('id', 7)
        ->set('name', 'FOG_DEBUG_SSH_KEYS')
        ->set('value', "command=\"x\" $evil")
        ->save();
} catch (\InvalidArgumentException $e) {
    $refused = $e;
}
$t->check('save() refuses a bad value with code 400', $refused && 400 === $refused->getCode());
$t->check(
    'a refused save writes nothing',
    0 === count(array_filter(debugKeyQueries(), function ($q) {
        return 0 === stripos(ltrim($q[0]), 'INSERT') || 0 === stripos(ltrim($q[0]), 'UPDATE');
    }))
);
debugKeyQueries(null, true);
$saved = (new Setting())
    ->set('id', 7)
    ->set('name', 'FOG_DEBUG_SSH_KEYS')
    ->set('value', "  $ed  \n\n")
    ->save();
$print = DebugSshKeys::fingerprints($ed)[0];
$audit = array_values(array_filter(debugKeyQueries(), function ($q) {
    return false !== stripos($q[0], 'auditLog') && 0 === stripos(ltrim($q[0]), 'INSERT');
}));
$auditText = $audit ? implode(' ', array_map('strval', (array)$audit[0][1])) : '';
$t->check('save() of good keys returns the saved object', $saved instanceof Setting);
$t->check('save() stores the normalized value', $saved && $saved->get('value') === $ed);
$t->check('save() writes one audit row', 1 === count($audit));
$t->check(
    'the audit row names the setting and the added fingerprint',
    false !== strpos($auditText, 'setting.debugsshkeys')
        && false !== strpos($auditText, "added $print")
);
debugKeyQueries(null, true);
(new Setting())->set('id', 8)->set('name', 'FOG_OTHER')->set('value', 'command="x" anything')->save();
$t->check(
    'another setting is neither validated nor audited',
    0 === count(array_filter(debugKeyQueries(), function ($q) {
        return false !== stripos($q[0], 'auditLog');
    }))
);
$db->responder = null;

// ---- The FOG Settings page: the other writer -------------------------------
// settingsPost() needs a signed-in session, CSRF and a JSON exit, so, as
// tests/settings-hidden-hook.test.php does, this reads its source. The
// behavior it calls, normalize() and recordChange(), runs for real above;
// this pins only that the page calls them, and the audit after the write.
$src = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Pages/FOGConfigurationPage.php'
);
$post = substr($src, (int)strpos($src, 'public function settingsPost()'));
$post = substr($post, 0, (int)strpos($post, "\n    public function ", 10));
$case = (int)strpos($post, 'case DebugSshKeys::SETTING:');
$write = (int)strpos($post, '$SettingMan->insertBatch(');
$audit = (int)strpos($post, 'DebugSshKeys::recordChange(');
$t->check(
    'the settings page normalizes FOG_DEBUG_SSH_KEYS in its per-key switch',
    $case > 0
        && false !== strpos(substr($post, $case, 400), '$set = DebugSshKeys::normalize(')
);
$t->check(
    'the settings page audits the change only after the write',
    $write > 0 && $audit > $write
);
// The real renderer, as tests/settings-hidden-hook.test.php drives it. A text
// input would strip the line breaks and join the keys into one line.
$db->responder = function ($sql) use ($ed, $ec) {
    if (false === strpos($sql, 'settingCategory')) {
        return null;
    }
    return [[
        'settingID' => 7,
        'settingKey' => 'FOG_DEBUG_SSH_KEYS',
        'settingDesc' => 'test',
        'settingValue' => "$ed\n$ec",
        'settingCategory' => 'Security',
    ]];
};
$page = (new \ReflectionClass(\FOG\Pages\FOGConfigurationPage::class))
    ->newInstanceWithoutConstructor();
$render = new \ReflectionMethod($page, '_renderSettings');
$render->setAccessible(true);
$html = (string)$render->invoke($page);
$db->responder = null;
$t->check(
    'the settings page shows the keys in a textarea, one per line',
    1 === preg_match('#<textarea[^>]*id="FOG_DEBUG_SSH_KEYS"[^>]*>([^<]*)</textarea>#', $html, $m)
        && 2 === count(explode("\n", trim(html_entity_decode($m[1]))))
);

$t->finish();
