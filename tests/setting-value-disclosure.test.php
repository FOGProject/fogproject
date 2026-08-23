<?php
/**
 * A credential setting's value must not leave over the API.
 *
 * globalSettings is a key/value table, so the secret is the value of a
 * particular ROW rather than a column present on every row -- which is why
 * nothing on this branch was masking it. GET /service returned every setting
 * with its value: FOG_API_TOKEN, the AD default password, the proxy password,
 * the TFTP FTP password, the storage node MySQL password, and the node
 * signing key added in GH-1312. Any authenticated API caller could read all
 * of them, uType 1 mobile users included.
 *
 * THREE DOORS, NOT ONE. Masking the response is the obvious half and on its
 * own it is cosmetic:
 *
 *   1. GET /service and GET /service/{id} returned the value outright.
 *   2. FOGManagerController::search() fills its WHERE from EVERY declared
 *      field, so /service/search/<guess> brought the row back on a substring
 *      of a masked value. The row carries no value; its arrival is the
 *      answer.
 *   3. `value` is a declared field, so /service/ids/value=<guess> and a
 *      {"value":"<guess>"} list body were exact-match oracles for the same
 *      reason.
 *
 * AND THE THING THAT MAKES IT AWKWARD. FOG Configuration builds its own form
 * from Route::listem('service', ...) and reads $Service->value back out to
 * render each field and to save it. Masking unconditionally would blank every
 * credential in the UI and then write the blank back. Only api/index.php does
 * `new Route`, so construction is the seam: masking applies to HTTP API
 * answers and not to the 116 in-tree call sites that use Route as a library.
 * That gate is pinned here, in both directions.
 *
 * Usage: php tests/setting-value-disclosure.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];
$file = 'packages/web/lib/router/route.class.php';
$src = (string)file_get_contents($file);

/*
 * 1. Build a probe from the shipped predicate and its data.
 *
 * The pattern and both lists are lifted rather than restated -- a test that
 * carries its own copy of the rule cannot tell you the rule changed.
 */
if (!preg_match(
    '#\n    const SENSITIVE_SETTING_PATTERN = (.+?);\n#',
    $src,
    $m
)) {
    $fails[] = 'Route::SENSITIVE_SETTING_PATTERN is gone';
}
$pattern = isset($m[1]) ? trim($m[1]) : "''";

$lists = [];
foreach (['sensitiveSettings', 'sensitiveSettingsExempt'] as $name) {
    if (!preg_match(
        '#\n    public static \$' . $name . ' = array\((.*?)\);\n#s',
        $src,
        $lm
    )) {
        $fails[] = "Route::\$$name is gone";
        $lists[$name] = '';
        continue;
    }
    $lists[$name] = $lm[1];
}

$methods = [];
foreach (
    [
        'isSensitiveSetting',
        'maskSensitiveSetting',
        '_settingHitIsVisible',
        '_refuseSettingValueFilter'
    ] as $name
) {
    if (!preg_match(
        '#\n    (?:public|private) static function '
        . preg_quote($name, '#')
        . '\(.*?\n    \}#s',
        $src,
        $mm
    )) {
        $fails[] = "Route::$name() is gone";
        continue;
    }
    $methods[$name] = $mm[0];
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

/**
 * Stands in for HTTPResponseCodes so a refusal is observable.
 *
 * The real sendResponse() exits, which is the correct behaviour and an
 * untestable one. Throwing preserves the only property under test: that the
 * request does not continue.
 */
class SettingProbeRefused extends Exception
{
}

eval(
    'class SettingProbe {'
    . ' const SENSITIVE_SETTING_PATTERN = ' . $pattern . ';'
    . ' public static $sensitiveSettings = array('
    . $lists['sensitiveSettings'] . ');'
    . ' public static $sensitiveSettingsExempt = array('
    . $lists['sensitiveSettingsExempt'] . ');'
    . ' public static $_isApiRequest = true;'
    . ' public static function sendResponse($code, $msg = false) {'
    . ' throw new SettingProbeRefused((string)$msg); }'
    // The probe calls two of these from outside the class; visibility is
    // not what is under test.
    . str_replace(
        'private static function',
        'public static function',
        implode("\n", $methods)
    )
    . ' }'
);
// The real class reads these off HTTPResponseCodes; the probe only needs the
// one it can be handed.
if (!class_exists('HTTPResponseCodes')) {
    eval('class HTTPResponseCodes { const HTTP_BAD_REQUEST = 400; }');
}

/*
 * 2. The predicate, against the keys a real 1.5.10 install actually has.
 *
 * Both directions matter and the false-positive side is the one that breaks
 * the product: FOG_USER_MINPASSLENGTH is password POLICY and the UI has to
 * describe its own rules, FOG_KEYMAP and FOG_KEY_SEQUENCE are boot config,
 * and FOG_HOSTKEY_ALLOWED_SOURCES is an address list. All four are why the
 * pattern requires PASSWORD/PASSWD/PWD rather than PASS, and why KEY is not
 * in it at all.
 */
$secret = [
    'FOG_AD_DEFAULT_PASSWORD',
    'FOG_AD_DEFAULT_PASSWORD_LEGACY',
    'FOG_API_TOKEN',
    'FOG_PROXY_PASSWORD',
    'FOG_TFTP_FTP_PASSWORD',
    'FOG_STORAGENODE_MYSQLPASS',
    'FOG_NODE_API_KEY',
];
$notSecret = [
    'FOG_USER_MINPASSLENGTH',
    'FOG_USER_VALIDPASSCHARS',
    'FOG_USER_VALIDPASSHELPMSG',
    'FOG_KEYMAP',
    'FOG_KEY_SEQUENCE',
    'FOG_HOSTKEY_ALLOWED_SOURCES',
    'FOG_QUICKREG_PROD_KEY_BIOS',
    'FOG_TFTP_FTP_USERNAME',
    'FOG_REAUTH_ON_DELETE',
    'FOG_WEB_HOST',
];
foreach ($secret as $key) {
    if (!SettingProbe::isSensitiveSetting($key)) {
        $fails[] = "$key is not treated as a credential; its value would be"
            . ' returned by the API';
    }
}
foreach ($notSecret as $key) {
    if (SettingProbe::isSensitiveSetting($key)) {
        $fails[] = "$key is treated as a credential; the UI cannot read a"
            . ' setting it needs and the page renders wrong';
    }
}

/*
 * 3. The mask drops the value and keeps everything else. A consumer must
 *    still be able to see that the setting exists and what it is for --
 *    that is the difference between masking and hiding.
 */
$row = [
    'id' => '42',
    'name' => 'FOG_API_TOKEN',
    'description' => 'The API token',
    'value' => 'super-secret',
    'category' => 'API System'
];
$masked = SettingProbe::maskSensitiveSetting($row);
if (isset($masked['value'])) {
    $fails[] = 'maskSensitiveSetting() still returns the value of a'
        . ' credential setting';
}
foreach (['id', 'name', 'description', 'category'] as $keep) {
    if (!isset($masked[$keep])) {
        $fails[] = "maskSensitiveSetting() dropped '$keep'; only the value"
            . ' should go';
    }
}
$plain = [
    'id' => '7',
    'name' => 'FOG_KEYMAP',
    'description' => 'Keymap',
    'value' => 'us',
    'category' => 'FOG Boot Settings'
];
if (!isset(SettingProbe::maskSensitiveSetting($plain)['value'])) {
    $fails[] = 'maskSensitiveSetting() blanked an ordinary setting';
}
// A row that is not a setting at all must pass through untouched -- getter()
// hands this whatever the class produced.
if (SettingProbe::maskSensitiveSetting(['ip' => '10.0.0.1']) !== ['ip' => '10.0.0.1']) {
    $fails[] = 'maskSensitiveSetting() altered a row with no name';
}

/*
 * 4. The search oracle. A masked row may come back only when the term is
 *    visible in something the caller is allowed to read.
 */
$hit = [
    'id' => '42',
    'name' => 'FOG_API_TOKEN',
    'description' => 'The API token',
    'category' => 'API System'
];
$visibleCases = [
    // term                    keep?  why
    ['API_TOKEN',              true,  'matched the name'],
    ['API System',             true,  'matched the category'],
    ['The API',                true,  'matched the description'],
    ['super-secret',           false, 'matched only the masked value'],
    ['aBcD1234',               false, 'matched nothing visible'],
];
foreach ($visibleCases as list($term, $keep, $why)) {
    $got = SettingProbe::_settingHitIsVisible('service', $hit, $term);
    if ($got !== $keep) {
        $fails[] = sprintf(
            'search hit for "%s" (%s) should%s be returned',
            $term,
            $why,
            $keep ? '' : ' not'
        );
    }
}
/*
 * The same, on a row that still carries its value.
 *
 * In the shipped flow getter() has already masked by the time this runs, so
 * the field is gone -- which makes the 'value' skip inside the loop look
 * dead. It is the backstop for the mask being bypassed or reordered, and
 * without exercising it here the skip could be deleted with every test still
 * green.
 */
$unmasked = $hit + ['value' => 'super-secret'];
if (SettingProbe::_settingHitIsVisible('service', $unmasked, 'super-secret')) {
    $fails[] = 'a search hit is kept because the term matched the value,'
        . ' when the value happens still to be on the row';
}
if (!SettingProbe::_settingHitIsVisible('service', $unmasked, 'API System')) {
    $fails[] = 'a search hit on a visible field was dropped';
}

// An ordinary setting is never dropped, whatever it matched on.
if (!SettingProbe::_settingHitIsVisible('service', $plain, 'us')) {
    $fails[] = 'a non-credential setting was dropped from search results';
}
// And no other class is touched.
if (!SettingProbe::_settingHitIsVisible('host', $hit, 'super-secret')) {
    $fails[] = '_settingHitIsVisible() filters classes other than service';
}

/*
 * 5. The filter oracle. Refused, not silently dropped -- a dropped term
 *    returns the whole table, which a caller reads as "no such value".
 */
$refused = function ($classname, array $keys) {
    try {
        SettingProbe::_refuseSettingValueFilter($classname, $keys);
    } catch (SettingProbeRefused $e) {
        return true;
    }
    return false;
};
if (!$refused('service', ['value'])) {
    $fails[] = 'filtering settings by value is not refused; it is an'
        . ' exact-match oracle for every masked credential';
}
if (!$refused('service', ['name', 'value'])) {
    $fails[] = 'a value filter alongside another field is not refused';
}
if ($refused('service', ['name', 'category'])) {
    $fails[] = 'an ordinary settings filter is refused';
}
if ($refused('host', ['value'])) {
    $fails[] = 'a value filter on another class is refused';
}
if ($refused('service', [])) {
    $fails[] = 'an unfiltered settings list is refused';
}

/*
 * 6. None of it applies when Route is used as a library.
 *
 * This is the half that breaks FOG Configuration if it goes wrong, and it
 * fails open by design -- the page must keep reading real values.
 */
SettingProbe::$_isApiRequest = false;
if ($refused('service', ['value'])) {
    $fails[] = 'a value filter is refused for an in-process caller; FOG'
        . ' Configuration filters settings as a library call';
}
if (!SettingProbe::_settingHitIsVisible('service', $hit, 'super-secret')) {
    $fails[] = 'an in-process search drops credential rows; the settings'
        . ' page would lose them from its own results';
}
SettingProbe::$_isApiRequest = true;

/*
 * 7. The wiring, pinned by shape -- each of these needs a live router.
 */
if (!preg_match(
    '#if \(self::\$_isApiRequest && \'service\' === \$classname\) \{\s*\n\s*'
    . '\$data = self::maskSensitiveSetting\(\$data\);#',
    $src
)) {
    $fails[] = 'getter() no longer masks credential settings on the way out,'
        . ' or no longer gates that on the request being an API request';
}
if (!preg_match('#public function __construct\(\)\s*\n\s*\{.*?'
    . 'self::\$_isApiRequest = true;#s', $src)) {
    $fails[] = 'the API-request flag is no longer set when api/index.php'
        . ' constructs Route; nothing would ever be masked';
}
if (!preg_match(
    '#if \(!self::_settingHitIsVisible\(\$classname, \$row, \$item\)\) \{#',
    $src
)) {
    $fails[] = 'search() no longer drops a hit that matched only a masked'
        . ' value';
}
/*
 * Bounded to each function's own body. A `.*?` reaching forward from the
 * function header finds the helper's own DEFINITION further down the file
 * and matches whether or not the call is still there -- which is a gate that
 * cannot fail, and was one until a mutation run said so.
 */
foreach (['handleWhereItems', 'getsearchbody'] as $fn) {
    if (!preg_match(
        '#\n    public static function ' . $fn . '\(.*?\n    \}#s',
        $src,
        $fm
    )) {
        $fails[] = "Route::$fn() is gone";
        continue;
    }
    if (false === strpos($fm[0], '_refuseSettingValueFilter(')) {
        $fails[] = "$fn() no longer refuses a filter on a setting's value";
    }
}
/*
 * getsearchbody() reassigns $class to an instance partway through, so the
 * classname has to be captured before that. Passing the reassigned variable
 * is not an error PHP reports -- the refusal simply never fires.
 */
if (preg_match('#function getsearchbody\(.*?\n    \}#s', $src, $gm)) {
    if (!preg_match('#\$classname = strtolower\(\(string\)\$class\);#', $gm[0])) {
        $fails[] = 'getsearchbody() no longer captures the class name before'
            . ' reassigning $class; the refusal would silently never fire';
    }
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: credential setting values do not leave over the API\n";
exit(0);
