<?php
/**
 * service/hostinfo.php tells FOS whether it may continue into the task
 * without a reboot (samebootok), mounts the node the task was queued on,
 * and sends the imaging arguments the boot menu puts on the kernel line.
 *
 * FOS asks hostinfo.php after a host registers with "deploy now". If the
 * boot that FOS is already in equals the boot the task would have had, FOS
 * starts the deploy at once. Any override of the kernel, the init, or the
 * kernel arguments makes the task boot differently, so samebootok must then
 * be 0 and FOS reboots. A plugin on BOOT_ITEM_NEW_SETTINGS makes it 0 only
 * when a dry run of the hook changes what FOS boots with.
 *
 * The script runs top to bottom with no function to call, so this test runs
 * the script's own blocks, cut out by their anchors, against stubs.
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

namespace HostinfoContinueTest;

require_once __DIR__ . '/lib/fog-test-harness.php';

/**
 * Stand-in for any model: answers get() from a field map.
 */
class StubItem
{
    /**
     * The fields.
     *
     * @var array
     */
    public $fields;
    /**
     * Whether isValid() is true.
     *
     * @var bool
     */
    public $valid;
    /**
     * Holds the fields.
     *
     * @param array $fields the fields
     * @param bool  $valid  whether the item is valid
     */
    public function __construct(array $fields = [], $valid = true)
    {
        $this->fields = $fields;
        $this->valid = $valid;
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
    /**
     * Writes a field, as a plugin hook may.
     *
     * @param string $key   the field
     * @param mixed  $value the value
     *
     * @return $this
     */
    public function set($key, $value)
    {
        $this->fields[$key] = $value;
        return $this;
    }
    /**
     * Whether the item is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->valid;
    }
}

/**
 * Stand-in task: has a storage node.
 */
class StubTask extends StubItem
{
    /**
     * The node the task was queued against.
     *
     * @var StubItem
     */
    public $node;
    /**
     * The node.
     *
     * @return StubItem
     */
    public function getStorageNode()
    {
        return $this->node;
    }
}

/**
 * Stand-in storage group: its optimal node is a marker node.
 */
class StubGroup
{
    /**
     * The optimal node.
     *
     * @return StubItem
     */
    public function getOptimalStorageNode()
    {
        return new StubItem(['ip' => '10.0.0.99'], true);
    }
}

/**
 * Stand-in for HookManager: listeners per event, called like the real one.
 */
class StubHooks
{
    /**
     * Listeners (callables) by event.
     *
     * @var array
     */
    public $listeners = [];
    /**
     * How many listeners an event has.
     *
     * @param string $event the event
     *
     * @return int
     */
    public function listenerCount($event)
    {
        return count($this->listeners[$event] ?? []);
    }
    /**
     * Calls each listener with the arguments, as HookManager does.
     *
     * @param string $event     the event
     * @param array  $arguments the arguments, by reference inside
     *
     * @return void
     */
    public function processEvent($event, $arguments = [])
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($arguments);
        }
    }
}

/**
 * Stand-in for FOGCore: the host, and settings from a map.
 */
class FOGCore
{
    /**
     * The host.
     *
     * @var StubItem
     */
    public static $Host;
    /**
     * Setting values by name.
     *
     * @var array
     */
    public static $settings = [];
    /**
     * Returns the settings asked for, in order.
     *
     * @param array $names the names
     *
     * @return array
     */
    public static function getSetting($names)
    {
        $out = [];
        foreach ($names as $name) {
            $out[] = self::$settings[$name] ?? '';
        }
        return $out;
    }
    /**
     * Mirrors FOGBase::chkdskFlag().
     *
     * @param mixed $disabled the setting
     *
     * @return int
     */
    public static function chkdskFlag($disabled)
    {
        return $disabled == 1 ? 0 : 1;
    }
    /**
     * Mirrors FOGBase::mcastRdvForNode() for the plain addresses used here.
     *
     * @param string $rdv    the rendezvous setting
     * @param string $nodeIP the node address
     *
     * @return string
     */
    public static function mcastRdvForNode($rdv, $nodeIP)
    {
        if ('' === $rdv || !filter_var($rdv, FILTER_VALIDATE_IP)) {
            return $rdv;
        }
        $isV6 = function ($ip) {
            return false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        };
        return $isV6($rdv) === $isV6($nodeIP) ? $rdv : '';
    }
    /**
     * Mirrors the real one for the plain addresses used here.
     *
     * @param string $host the address
     *
     * @return string
     */
    public static function canonicalIP($host)
    {
        return trim((string)$host);
    }
}

// The settings the dry run reads, with the installer's default names.
const BOOT_SETTINGS = [
    'FOG_TFTP_PXE_KERNEL' => 'bzImage',
    'FOG_PXE_BOOT_IMAGE' => 'init.xz',
    'FOG_KERNEL_LOGLEVEL' => '4',
    'FOG_KERNEL_RAMDISK_SIZE' => '275000',
    'FOG_KEYMAP' => '',
    'FOG_MEMTEST_KERNEL' => 'memtest.bin',
    'FOG_WEB_HOST' => '10.0.0.1',
    'FOG_WEB_ROOT' => '/fog/',
];

$t = new \FogChecks();

$source = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/service/hostinfo.php'
);

/**
 * Cuts the source between two anchors.
 *
 * @param string $source the file
 * @param string $from   the first anchor, kept
 * @param string $to     the second anchor, dropped
 *
 * @return string
 */
function cut($source, $from, $to)
{
    $start = strpos($source, $from);
    $end = strpos($source, $to);
    if (false === $start || false === $end || $start >= $end) {
        return '';
    }
    return substr($source, $start, $end - $start);
}

$sameBlock = cut($source, '    $sameBoot = true;', '    $fdrive = FOGCore::$Host');
$nodeBlock = cut(
    $source,
    '        // The node the task was queued',
    "        \$osid = \$Image->get('osID');"
);
$pigzBlock = cut(
    $source,
    "        // The kernel arguments use the image's level",
    '        $shutdown = intval('
);
$fieldBlock = cut(
    $source,
    "        'chkdsk' =>",
    "        'shutdown' => \$shutdown,"
);
$t->check('the samebootok block is found by its anchors', '' !== $sameBlock);
$t->check('the storage node block is found by its anchors', '' !== $nodeBlock);
$t->check('the imaging settings block is found by its anchors', '' !== $pigzBlock);
$t->check('the new reply fields are found by their anchors', '' !== $fieldBlock);

/**
 * Runs the samebootok block and returns the field's value.
 *
 * @param string $block    the block's source
 * @param array  $host     host fields
 * @param array  $taskType task type fields
 * @param array  $listeners BOOT_ITEM_NEW_SETTINGS listeners
 *
 * @return string '1' or '0'
 */
function sameBoot($block, array $host, array $taskType, array $listeners)
{
    // The task hostinfo.php loaded before the samebootok block.
    $Task = new StubItem(['isDebug' => 0]);
    FOGCore::$Host = new StubItem($host + ['task' => $Task]);
    FOGCore::$settings = BOOT_SETTINGS;
    $TaskType = new StubItem($taskType);
    $HookManager = new StubHooks();
    $HookManager->listeners['BOOT_ITEM_NEW_SETTINGS'] = $listeners;
    // The block assigns $sameBoot; the eval hands it back.
    $result = eval(
        "namespace HostinfoContinueTest;\n" . $block
        . "\nreturn \$sameBoot ? '1' : '0';"
    );
    // The task the reply reads after the block.
    $GLOBALS['replyTask'] = $Task;
    return $result;
}

$t->check(
    'no override and no listener: samebootok is 1',
    '1' === sameBoot($sameBlock, [], [], [])
);
$t->check(
    'a host kernel makes samebootok 0',
    '0' === sameBoot($sameBlock, ['kernel' => 'bzImage-old'], [], [])
);
$t->check(
    'a host init makes samebootok 0',
    '0' === sameBoot($sameBlock, ['init' => 'init.xz'], [], [])
);
$t->check(
    'host kernel arguments make samebootok 0',
    '0' === sameBoot($sameBlock, ['kernelArgs' => 'nomodeset'], [], [])
);
$t->check(
    'a task type kernel makes samebootok 0',
    '0' === sameBoot($sameBlock, [], ['kernel' => 'bzImage-tt'], [])
);
$t->check(
    'a task type initrd makes samebootok 0',
    '0' === sameBoot($sameBlock, [], ['initrd' => 'init-tt.xz'], [])
);
// Each listener writes one key, the way a plugin hook does.
$setter = function ($key, $value) {
    return function ($arguments) use ($key, $value) {
        $arguments[$key] = $value;
    };
};
// The writes of LocationChangeItems::bootItemSettings() with the TFTP option
// on (fog-plugins location/src/Hooks/LocationChangeItems.php): the same file
// names, served from the location's node.
$location = function ($arguments) {
    $ip = '10.9.0.5';
    $arguments['webserver'] = $ip;
    $arguments['memdisk'] = "http://{$ip}/fog/service/ipxe/{$arguments['memdisk']}";
    $arguments['memtest'] = "http://{$ip}/fog/service/ipxe/{$arguments['memtest']}";
    $arguments['bzImage'] = "http://{$ip}/fog/service/ipxe/{$arguments['bzImage']}";
    $arguments['imagefile'] = "http://{$ip}/fog/service/ipxe/{$arguments['initrd']}";
};
$t->check(
    'a listener that changes nothing leaves samebootok 1',
    '1' === sameBoot($sameBlock, [], [], [function ($arguments) {
    }])
);
$t->check(
    'Location serving the same kernel and init from its node leaves samebootok 1',
    '1' === sameBoot($sameBlock, [], [], [$location])
);
$t->check(
    'a listener that picks another kernel makes samebootok 0',
    '0' === sameBoot($sameBlock, [], [], [$setter('bzImage', 'bzImage-site')])
);
$t->check(
    'a listener that picks another init by imagefile makes samebootok 0',
    '0' === sameBoot($sameBlock, [], [], [$setter('imagefile', 'init-site.xz')])
);
$t->check(
    'a listener that picks another init by initrd makes samebootok 0',
    '0' === sameBoot($sameBlock, [], [], [$setter('initrd', 'init-site.xz')])
);
$t->check(
    'a listener that changes loglevel makes samebootok 0',
    '0' === sameBoot($sameBlock, [], [], [$setter('loglevel', '7')])
);
$t->check(
    'a listener that changes the ramdisk size makes samebootok 0',
    '0' === sameBoot($sameBlock, [], [], [$setter('ramsize', '500000')])
);
$t->check(
    'a listener that changes the keymap makes samebootok 0',
    '0' === sameBoot($sameBlock, [], [], [$setter('keymap', 'de')])
);
$t->check(
    'Location plus a listener that changes the kernel makes samebootok 0',
    '0' === sameBoot($sameBlock, [], [], [$location, $setter('bzImage', 'bzImage-site')])
);
$t->check(
    'a host override still makes samebootok 0 with a no-change listener',
    '0' === sameBoot($sameBlock, ['kernel' => 'bzImage-old'], [], [$location])
);
$t->check(
    'a listener cannot replace the host the reply is built from',
    '1' === sameBoot(
        $sameBlock,
        ['name' => 'real'],
        [],
        [$setter('Host', new StubItem(['name' => 'fake']))]
    )
    && 'real' === FOGCore::$Host->get('name')
);
$t->check(
    'a listener cannot change a field of the host the reply is built from',
    '1' === sameBoot($sameBlock, ['name' => 'real'], [], [function ($arguments) {
        $arguments['Host']->set('name', 'fake');
    }])
    && 'real' === FOGCore::$Host->get('name')
);
$t->check(
    'a listener cannot change the task the reply reads',
    '1' === sameBoot($sameBlock, [], [], [function ($arguments) {
        $arguments['Host']->get('task')->set('isDebug', 1);
    }])
    && 0 === $GLOBALS['replyTask']->get('isDebug')
);
$t->check(
    'a listener that gives the host kernel arguments makes samebootok 0',
    '0' === sameBoot($sameBlock, [], [], [function ($arguments) {
        $arguments['Host']->set('kernelArgs', 'nomodeset');
    }])
);
$t->check(
    'white space only in an override does not count as an override',
    '1' === sameBoot($sameBlock, ['kernelArgs' => "  \n"], [], [])
);

/**
 * Runs the node block and returns the chosen node's ip.
 *
 * @param string $block the block's source
 * @param mixed  $node  the task's node (a StubItem or null)
 *
 * @return string
 */
function chosenNode($block, $node)
{
    $Task = new StubTask();
    $Task->node = $node;
    $StorageNode = null;
    $StorageGroup = new StubGroup();
    // The block assigns $StorageNode; the eval hands its ip back.
    return eval(
        "namespace HostinfoContinueTest;\n" . $block
        . "\nreturn \$StorageNode->get('ip');"
    );
}

$t->check(
    'a valid task node is the node FOS mounts',
    '10.0.0.5' === chosenNode(
        $nodeBlock,
        new StubItem(['ip' => '10.0.0.5'], true)
    )
);
$t->check(
    'an invalid task node falls back to the optimal node',
    '10.0.0.99' === chosenNode(
        $nodeBlock,
        new StubItem(['ip' => '10.0.0.5'], false)
    )
);
$t->check(
    'no task node falls back to the optimal node',
    '10.0.0.99' === chosenNode($nodeBlock, null)
);

/**
 * Runs the imaging settings block and returns what it computed.
 *
 * @param string $block    the block's source
 * @param array  $settings the settings
 * @param array  $image    image fields
 * @param string $nodeIp   the node's ip
 *
 * @return array [chkdsk, mcastrdv, PIGZ_COMP]
 */
function imagingValues($block, array $settings, array $image, $nodeIp)
{
    FOGCore::$settings = $settings;
    $Image = new StubItem($image);
    $StorageNode = new StubItem(['ip' => $nodeIp]);
    $imgFormat = $image['format'] ?? 0;
    // The block assigns these; the eval hands them back.
    return eval(
        "namespace HostinfoContinueTest;\n" . $block
        . "\nreturn [\$chkdsk, \$mcastrdv, \$PIGZ_COMP];"
    );
}

$sets = [
    'FOG_DISABLE_CHKDSK' => '1',
    'FOG_MULTICAST_RENDEZVOUS' => '',
    'FOG_PIGZ_COMP' => '6',
];
$v = imagingValues($pigzBlock, $sets, ['compress' => '-1', 'format' => 0], '10.0.0.5');
$t->check(
    'an image compress of -1 uses the global level, not --1',
    '6' === (string)$v[2]
);
$v = imagingValues($pigzBlock, $sets, ['compress' => '3', 'format' => 0], '10.0.0.5');
$t->check('an image level of 3 is used', '3' === (string)$v[2]);
$v = imagingValues($pigzBlock, $sets, ['compress' => '11', 'format' => 0], '10.0.0.5');
$t->check('a level above 9 is capped at 9 for a pigz format', '9' === (string)$v[2]);
$t->check('FOG_DISABLE_CHKDSK 1 sends chkdsk 0', 0 === $v[0]);
$v = imagingValues(
    $pigzBlock,
    ['FOG_DISABLE_CHKDSK' => '0'] + $sets,
    ['compress' => '3', 'format' => 0],
    '10.0.0.5'
);
$t->check('FOG_DISABLE_CHKDSK 0 sends chkdsk 1', 1 === $v[0]);
$v = imagingValues(
    $pigzBlock,
    ['FOG_MULTICAST_RENDEZVOUS' => '10.0.0.7'] + $sets,
    ['compress' => '3'],
    '10.0.0.5'
);
$t->check('an IPv4 rendezvous for an IPv4 node is kept', '10.0.0.7' === $v[1]);
$v = imagingValues(
    $pigzBlock,
    ['FOG_MULTICAST_RENDEZVOUS' => '10.0.0.7'] + $sets,
    ['compress' => '3'],
    'fd00::5'
);
$t->check('an IPv4 rendezvous for an IPv6 node is dropped', '' === $v[1]);

/**
 * Runs the new reply fields and returns them.
 *
 * @param string $block the field block's source
 * @param array  $task  task fields
 *
 * @return array
 */
function replyFields($block, array $task)
{
    $Task = new StubItem($task);
    $chkdsk = 1;
    $mcastrdv = '10.0.0.7';
    $sameBoot = true;
    return eval('return [' . $block . '];');
}

$f = replyFields($fieldBlock, ['bypassbitlocker' => 1, 'isDebug' => 1]);
$t->check('bitlockerbypass is 1 when the task bypasses BitLocker', '1' === $f['bitlockerbypass']);
$t->check('isdebug is yes when the task is a debug task', 'yes' === $f['isdebug']);
$t->check('chkdsk and mcastrdv are sent', 1 === $f['chkdsk'] && '10.0.0.7' === $f['mcastrdv']);
$t->check('samebootok is sent', '1' === $f['samebootok']);
$f = replyFields($fieldBlock, []);
$t->check('bitlockerbypass and isdebug are empty by default', '' === $f['bitlockerbypass'] && '' === $f['isdebug']);

$t->finish();
