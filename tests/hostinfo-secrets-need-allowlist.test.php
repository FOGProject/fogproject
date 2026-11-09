<?php
/**
 * service/hostinfo.php sends the join password and the product key only to a
 * caller inside the declared imaging networks.
 *
 * GHSA-666x-5fjv-v8j9. The host token that opens hostinfo.php comes from
 * status/hostgetkey.php, which is unauthenticated and MAC-resolved. So any
 * caller on the network could get the plaintext join password and the
 * product key while the host had a task. Now both values are empty unless
 * FOG_HOSTKEY_ALLOWED_SOURCES is set and the caller is inside it.
 *
 * The script runs top to bottom with no function to call. So this test runs
 * the script's own source from the product-key read to the reply, cut out by
 * its anchors, against stubs. Both anchors exist in the code before the fix,
 * so the test fails there on the values, not on a missing anchor.
 *
 * Requires no database.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace HostinfoSecretsTest;

require_once __DIR__ . '/lib/fog-test-harness.php';

/**
 * Stand-in host: answers get() from a field map.
 */
class StubHost
{
    /**
     * The fields.
     *
     * @var array
     */
    public $fields;
    /**
     * Holds the fields.
     *
     * @param array $fields the fields
     */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }
    /**
     * Reads a field.
     *
     * @param string $key the field
     *
     * @return mixed
     */
    public function get($key)
    {
        return $this->fields[$key] ?? '';
    }
}

/**
 * Stand-in for FOGCore: the host, the setting, and the source check.
 */
class FOGCore
{
    /**
     * The host the request is for.
     *
     * @var StubHost
     */
    public static $Host;
    /**
     * The value of FOG_HOSTKEY_ALLOWED_SOURCES.
     *
     * @var string
     */
    public static $sources = '';
    /**
     * Whether the caller is inside the sources.
     *
     * @var bool
     */
    public static $inside = true;
    /**
     * Decrypts nothing: the stub stores plain text.
     *
     * @param string $value the stored value
     *
     * @return string
     */
    public static function aesdecrypt($value)
    {
        return (string)$value;
    }
    /**
     * Reads a setting.
     *
     * @param string $key the setting
     *
     * @return string
     */
    public static function getSetting($key)
    {
        return 'FOG_HOSTKEY_ALLOWED_SOURCES' === $key ? self::$sources : '';
    }
    /**
     * Tells whether the caller is inside the sources.
     *
     * @param string $ip the caller
     *
     * @return bool
     */
    public static function hostKeySourceAllowed($ip)
    {
        return self::$inside;
    }
}

/**
 * Stand-in for filter_input: the CLI has no REMOTE_ADDR.
 *
 * @param int    $type the input type
 * @param string $name the variable
 *
 * @return string
 */
function filter_input($type, $name)
{
    return '192.0.2.10';
}

$t = new \FogChecks();

$source = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/service/hostinfo.php'
);
$start = strpos($source, "    \$productKey = FOGCore::\$Host->get('productKey');");
$end = strpos($source, "    \$repFields = [");
$t->check(
    'the product-key-to-reply block is found by its anchors',
    false !== $start && false !== $end && $start < $end
);
$block = substr($source, (int)$start, (int)$end - (int)$start);
$t->check(
    'the reply sends the gated password and key',
    false !== strpos($source, "'hostadpass' => trim(\$pass),")
        && false !== strpos($source, "'hostproductkey' => trim(\$productKey),")
);

/**
 * Runs the block for one setting value and caller position.
 *
 * @param string $block   the block's source
 * @param string $sources the setting value
 * @param bool   $inside  whether the caller is inside the sources
 *
 * @return array [password, product key]
 */
function run($block, $sources, $inside)
{
    FOGCore::$Host = new StubHost(['productKey' => 'AAAAA-BBBBB-CCCCC-DDDDD-EEEEE']);
    FOGCore::$sources = $sources;
    FOGCore::$inside = $inside;
    // The join-account block before this one has already set $pass.
    $pass = 'letmein';
    // The block sets this; declared here so it is defined outside the eval.
    $productKey = null;
    // The block names FOGCore unqualified, so run it in this namespace.
    eval("namespace HostinfoSecretsTest;\n" . $block);
    return [(string)$pass, (string)$productKey];
}

$t->check(
    'with no imaging networks declared, no password and no key are sent',
    ['', ''] === run($block, '', true)
);
$t->check(
    'with only whitespace declared, no password and no key are sent',
    ['', ''] === run($block, "  \n", true)
);
$t->check(
    'a caller outside the declared networks gets no password and no key',
    ['', ''] === run($block, '10.0.0.0/8', false)
);
$t->check(
    'a caller inside the declared networks gets both',
    ['letmein', 'AAAAA-BBBBB-CCCCC-DDDDD-EEEEE'] === run($block, '10.0.0.0/8', true)
);

$t->finish();
