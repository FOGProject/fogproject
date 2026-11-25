<?php
/**
 * service/hostinfo.php brackets an IPv6 storage node address in storage.
 *
 * fogproject#2152. The reply sent storage=fd00:f06:0:1::1:/images/dev/ for a
 * storage node with an IPv6 address. The busybox mount in FOS splits the
 * source at the first colon, so it could not mount the share. FOS reads this
 * reply on the USB-boot path. An IPv6 literal must be in brackets in
 * host:/path, the same as in every other storage= emitter.
 *
 * The script runs top to bottom with no function to call. So this test cuts
 * the $storage = sprintf( ... ); block out of the script's own source by its
 * anchors and runs it against stub storage node and task type objects. The
 * anchors exist in the code before the fix, so the test fails there on the
 * values, not on a missing anchor.
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

namespace HostinfoIpv6StorageTest;

require_once __DIR__ . '/lib/fog-test-harness.php';

/**
 * Stand-in storage node: it answers get() for ip and path.
 */
class StubStorageNode
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
 * Stand-in task type: it answers isCapture().
 */
class StubTaskType
{
    /**
     * Whether the task is a capture.
     *
     * @var bool
     */
    public $capture;
    /**
     * Holds the flag.
     *
     * @param bool $capture whether the task is a capture
     */
    public function __construct($capture)
    {
        $this->capture = $capture;
    }
    /**
     * Tells whether the task is a capture.
     *
     * @return bool
     */
    public function isCapture()
    {
        return $this->capture;
    }
}

// FOGCore::urlHost() is the real helper, so the test loads the real class.
\FogTestHarness::boot('hostinfo-ipv6-storage');

$t = new \FogChecks();

$source = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/service/hostinfo.php'
);

$start = strpos($source, "        \$storage = sprintf(");
$end = false === $start ? false : strpos($source, ");\n", $start);
$t->check(
    'the storage block is found by its anchors',
    false !== $start && false !== $end
);
$block = substr($source, (int)$start, (int)$end + 2 - (int)$start);

/**
 * Runs the storage block for one node and returns $storage.
 *
 * @param string          $block        the block's source
 * @param StubStorageNode $StorageNode  the storage node
 * @param StubTaskType    $TaskType     the task type
 *
 * @return string
 */
function storageFor($block, $StorageNode, $TaskType)
{
    // The block assigns $storage; declare it so static analysis sees it.
    $storage = '';
    // The block names FOGCore as hostinfo.php imports it.
    eval('use FOG\Base\FOGCore; ' . $block);
    return $storage;
}

$deploy = new StubTaskType(false);
$capture = new StubTaskType(true);

$v6 = new StubStorageNode(['ip' => 'fd00:f06:0:1::1', 'path' => '/images']);
$t->check(
    'an IPv6 node address is in brackets on a capture',
    '[fd00:f06:0:1::1]:/images/dev/' === storageFor($block, $v6, $capture)
);
$t->check(
    'an IPv6 node address is in brackets on a deploy',
    '[fd00:f06:0:1::1]:/images/' === storageFor($block, $v6, $deploy)
);

$v4 = new StubStorageNode(['ip' => '10.0.0.1', 'path' => '/images']);
$t->check(
    'an IPv4 node address is unchanged',
    '10.0.0.1:/images/dev/' === storageFor($block, $v4, $capture)
);

$name = new StubStorageNode(['ip' => 'storage.lan', 'path' => '/images']);
$t->check(
    'a host name is unchanged',
    'storage.lan:/images/dev/' === storageFor($block, $name, $capture)
);

$spaced = new StubStorageNode(['ip' => '  10.0.0.1 ', 'path' => '/images']);
$t->check(
    'an address with surrounding spaces is still trimmed',
    '10.0.0.1:/images/' === storageFor($block, $spaced, $deploy)
);

$t->finish();
