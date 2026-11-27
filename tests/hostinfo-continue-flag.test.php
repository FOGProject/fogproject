<?php
/**
 * service/hostinfo.php tells FOS whether it may continue into the task
 * without a reboot (samebootok), mounts the node the task was queued on,
 * and sends the imaging arguments the boot menu puts on the kernel line.
 *
 * FOS asks hostinfo.php after a host registers with "deploy now". If the
 * boot that FOS is already in equals the boot the task would have had, FOS
 * starts the deploy at once. Any override of the kernel, the init, or the
 * kernel arguments, or any plugin listening on BOOT_ITEM_NEW_SETTINGS, makes
 * the task boot differently, so samebootok must then be 0 and FOS reboots.
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
 * Stand-in for HookManager: a listener count per event.
 */
class StubHooks
{
    /**
     * Listener counts by event.
     *
     * @var array
     */
    public $counts = [];
    /**
     * How many listeners an event has.
     *
     * @param string $event the event
     *
     * @return int
     */
    public function listenerCount($event)
    {
        return $this->counts[$event] ?? 0;
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
 * @param int    $listeners BOOT_ITEM_NEW_SETTINGS listener count
 *
 * @return string '1' or '0'
 */
function sameBoot($block, array $host, array $taskType, $listeners)
{
    FOGCore::$Host = new StubItem($host);
    $TaskType = new StubItem($taskType);
    $HookManager = new StubHooks();
    $HookManager->counts['BOOT_ITEM_NEW_SETTINGS'] = $listeners;
    // The block assigns $sameBoot; the eval hands it back.
    return eval(
        "namespace HostinfoContinueTest;\n" . $block
        . "\nreturn \$sameBoot ? '1' : '0';"
    );
}

$t->check(
    'no override and no listener: samebootok is 1',
    '1' === sameBoot($sameBlock, [], [], 0)
);
$t->check(
    'a host kernel makes samebootok 0',
    '0' === sameBoot($sameBlock, ['kernel' => 'bzImage-old'], [], 0)
);
$t->check(
    'a host init makes samebootok 0',
    '0' === sameBoot($sameBlock, ['init' => 'init.xz'], [], 0)
);
$t->check(
    'host kernel arguments make samebootok 0',
    '0' === sameBoot($sameBlock, ['kernelArgs' => 'nomodeset'], [], 0)
);
$t->check(
    'a task type kernel makes samebootok 0',
    '0' === sameBoot($sameBlock, [], ['kernel' => 'bzImage-tt'], 0)
);
$t->check(
    'a task type initrd makes samebootok 0',
    '0' === sameBoot($sameBlock, [], ['initrd' => 'init-tt.xz'], 0)
);
$t->check(
    'a BOOT_ITEM_NEW_SETTINGS listener makes samebootok 0',
    '0' === sameBoot($sameBlock, [], [], 1)
);
$t->check(
    'white space only in an override does not count as an override',
    '1' === sameBoot($sameBlock, ['kernelArgs' => "  \n"], [], 0)
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
