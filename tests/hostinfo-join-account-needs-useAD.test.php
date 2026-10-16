<?php
/**
 * service/hostinfo.php sends the join account to FOS only when useAD is on.
 *
 * FOS reads hostinfo.php into its shell environment at the start of a task.
 * The reply used to carry hostaduser and the decrypted hostadpass for every
 * host, joined or not. A host that does not join a domain has no use for
 * the account, so the reply now carries empty values for it.
 *
 * Kernel arguments never carried the account: IpxeBootMenu excludes ADUser
 * and ADPass by name. hostinfo.php is the one path that sends it.
 *
 * The script runs top to bottom with no function to call, so this test
 * runs the script's own join-account block, cut out by its anchors, against
 * a stub host. A rewrite of the block changes what is run, not what is
 * asserted.
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

namespace HostinfoJoinTest;

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
 * Stand-in for FOGCore: the current host and a pass-through decrypt.
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
}

/**
 * Stand-in for core's resolver: a plugin that answers for the domain.
 */
class DirectoryJoin
{
    /**
     * The domain's account when the host has a domain, else the host's own.
     *
     * @param StubHost $Host     the host
     * @param string   $user     the host's own user
     * @param string   $password the host's own password
     *
     * @return array [user, password]
     */
    public static function account($Host, $user, $password)
    {
        if ('' === (string)$Host->get('ADDomain')) {
            return [$user, $password];
        }
        return ['domain-user', 'domain-secret'];
    }
}

$t = new \FogChecks();

$source = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/service/hostinfo.php'
);
$start = strpos($source, "    // The join account goes to FOS only");
$end = strpos($source, "    \$productKey = FOGCore::\$Host->get('productKey');");
$t->check(
    'the join-account block is found by its anchors',
    false !== $start && false !== $end && $start < $end
);
$block = substr($source, (int)$start, (int)$end - (int)$start);
$t->check(
    'the reply sends the gated user and password, not the host fields',
    false !== strpos($source, "'hostaduser' => \$adUser,")
        && false !== strpos($source, "'hostadpass' => trim(\$pass),")
);

/**
 * Runs the block for one host.
 *
 * @param string $block  the block's source
 * @param array  $fields the host's fields
 *
 * @return array [user, password]
 */
function run($block, array $fields)
{
    FOGCore::$Host = new StubHost($fields);
    $adUser = $pass = null;
    // The block names FOGCore unqualified, so run it in this namespace.
    eval("namespace HostinfoJoinTest;\n" . $block);
    return [$adUser, trim((string)$pass)];
}

$host = ['ADUser' => 'svc-join', 'ADPass' => 'letmein', 'ADDomain' => 'corp.example.com'];

$t->check(
    'a host that joins a domain gets the account its domain answers with',
    ['domain-user', 'domain-secret'] === run($block, ['useAD' => '1'] + $host)
);
$t->check(
    'a host that joins with no domain account gets its own',
    ['svc-join', 'letmein'] === run($block, ['useAD' => '1', 'ADDomain' => ''] + $host)
);
// The gate covers the domain's account too: a host that does not join
// gets neither its own account nor its domain's.
$t->check(
    'a host with useAD off gets no join account',
    ['', ''] === run($block, ['useAD' => '0'] + $host)
);
$t->check(
    'a host with useAD unset gets no join account',
    ['', ''] === run($block, $host)
);

$t->finish();
