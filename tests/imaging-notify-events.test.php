<?php
/**
 * An imaging task must announce the outcome it actually had.
 *
 * Three defects, all fixed together in #1202 and all invisible without this:
 *
 *   1. `HOST_IMAGEUP_COMPLETE` had no caller anywhere in the tree, so a
 *      capture announced itself with the deploy name and nothing could tell
 *      "an image finished uploading" from "a machine finished being imaged".
 *      All three bundled notification plugins register a listener for it.
 *   2. `HOST_IMAGE_FAIL` had no caller either, so its listeners had never run.
 *   3. The notification fired for tasks that are not imaging at all --
 *      TaskQueue::checkout() is reached from Post_Wipe.php as well as
 *      Post_Stage2/3.php, so wiping a disk sent "finished imaging".
 *
 * No database. TaskQueue is built without its constructor and its collaborators
 * are stubs, because the only thing under test is which name gets notified with
 * what payload.
 *
 * Usage: php tests/imaging-notify-events.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

require $web . '/commons/init.php';
new Initiator();

$fails = [];

/**
 * Records what was notified instead of dispatching it.
 */
class NotifySpy
{
    public $calls = [];

    public function notify($event, $data = [])
    {
        $this->calls[] = ['event' => $event, 'data' => $data];
        return true;
    }
}

/**
 * The parts of Task that _notifyImagingOutcome() actually reads.
 */
class TaskStub
{
    private $capture;
    private $typeText;

    public function __construct($capture, $typeText)
    {
        $this->capture = $capture;
        $this->typeText = $typeText;
    }

    public function isCapture()
    {
        return $this->capture;
    }

    public function getTaskTypeText()
    {
        return $this->typeText;
    }
}

/**
 * The parts of Image and Host it reads. `valid` false is the shape a task
 * whose image row has gone away arrives in.
 */
class NamedStub
{
    private $name;
    private $valid;

    public function __construct($name, $valid = true)
    {
        $this->name = $name;
        $this->valid = $valid;
    }

    public function get($field)
    {
        return 'name' === $field ? $this->name : '';
    }

    public function isValid()
    {
        return $this->valid;
    }
}

$bare = function ($class) {
    return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
};

$hostProp = new \ReflectionProperty('FOG\Base\FOGBase', 'Host');
$hostProp->setAccessible(true);
$emProp = new \ReflectionProperty('FOG\Base\FOGBase', 'EventManager');
$emProp->setAccessible(true);

$method = new \ReflectionMethod('FOG\TaskHandling\TaskQueue', '_notifyImagingOutcome');
$method->setAccessible(true);

/**
 * Drives one outcome and returns what was notified.
 *
 * @param bool   $imaging  Whether the task is a deploy or capture at all.
 * @param bool   $capture  Capture rather than deploy.
 * @param object $image    The image stub, or null.
 * @param string $reason   Empty for success.
 *
 * @return array
 */
$run = function ($imaging, $capture, $image, $reason = '') use (
    $bare,
    $hostProp,
    $emProp,
    $method
) {
    $spy = new NotifySpy();
    $emProp->setValue(null, $spy);
    $hostProp->setValue(null, new NamedStub('labhost'));

    $tq = $bare('FOG\TaskHandling\TaskQueue');
    foreach ([
        'imagingTask' => $imaging,
        'Task' => new TaskStub($capture, $capture ? 'Upload' : 'Deploy'),
        'Image' => $image,
    ] as $name => $value) {
        $p = new \ReflectionProperty('FOG\TaskHandling\TaskingElement', $name);
        $p->setAccessible(true);
        $p->setValue($tq, $value);
    }
    $method->invoke($tq, $reason);
    return $spy->calls;
};

// ---------------------------------------------------------------- deploy

$calls = $run(true, false, new NamedStub('Win11-Lab'));
if (1 !== count($calls)) {
    $fails[] = 'a completed deploy notifies ' . count($calls) . ' times, not once';
} elseif ('HOST_IMAGE_COMPLETE' !== $calls[0]['event']) {
    $fails[] = 'a completed deploy notifies ' . $calls[0]['event']
        . ' instead of HOST_IMAGE_COMPLETE';
}

// HostName is the only key any listener read before #1202 -- core, the three
// bundled plugins, and whatever third-party listeners exist. Everything else
// is additive, so this key is the compatibility contract.
if (($calls[0]['data']['HostName'] ?? null) !== 'labhost') {
    $fails[] = 'the payload no longer carries HostName, which is the only key'
        . ' every existing listener reads';
}
foreach (['Host', 'Task', 'Image', 'ImageName', 'TaskType'] as $key) {
    if (!array_key_exists($key, $calls[0]['data'])) {
        $fails[] = "the payload does not carry $key, so a listener still cannot"
            . ' say which image finished';
    }
}
if (($calls[0]['data']['ImageName'] ?? null) !== 'Win11-Lab') {
    $fails[] = 'ImageName is not the image name';
}

// ---------------------------------------------------------------- capture

$calls = $run(true, true, new NamedStub('Win11-Lab'));
if (1 !== count($calls) || 'HOST_IMAGEUP_COMPLETE' !== $calls[0]['event']) {
    $fails[] = 'a completed capture does not notify HOST_IMAGEUP_COMPLETE,'
        . ' which is the name that had no caller at all before #1202';
}

// ---------------------------------------------------------------- failure

$calls = $run(true, false, new NamedStub('Win11-Lab'), 'Failed to update host');
if (1 !== count($calls) || 'HOST_IMAGE_FAIL' !== $calls[0]['event']) {
    $fails[] = 'a failed imaging task does not notify HOST_IMAGE_FAIL';
} elseif (($calls[0]['data']['Reason'] ?? '') !== 'Failed to update host') {
    $fails[] = 'the failure payload does not carry the reason, which is the'
        . ' only part of it an admin can act on';
}
$calls = $run(true, false, new NamedStub('Win11-Lab'));
if (isset($calls[0]['data']['Reason'])) {
    $fails[] = 'a successful task carries a failure Reason';
}

// ------------------------------------------------------------ not imaging

// Post_Wipe.php reaches the same checkout(), so this is a real request shape,
// not a hypothetical one.
$calls = $run(false, false, null);
if ([] !== $calls) {
    $fails[] = 'a non-imaging task still notifies ' . $calls[0]['event']
        . ', so wiping a disk announces that imaging finished';
}

// ------------------------------------------------------- image gone missing

$calls = $run(true, false, new NamedStub('Win11-Lab', false));
if (($calls[0]['data']['ImageName'] ?? null) !== '') {
    $fails[] = 'an invalid image is not reported as an empty name, so a'
        . ' listener formats a notification around whatever came back';
}
$calls = $run(true, false, null);
if (($calls[0]['data']['ImageName'] ?? null) !== '') {
    $fails[] = 'a null image is not reported as an empty name';
}

// --------------------------------------------------------------- placement

// The notify used to sit outside the imagingTask guard and outside any
// deploy/capture branch. Assert on the source too: driving the method proves
// the method is right, not that checkout() still calls it.
$src = file_get_contents($web . '/src/TaskHandling/TaskQueue.php');
if (false === strpos($src, '$this->_notifyImagingOutcome();')) {
    $fails[] = 'checkout() no longer announces a completed imaging task';
}
if (false === strpos($src, '$this->_notifyImagingOutcome($e->getMessage());')) {
    $fails[] = 'checkout() no longer announces a failed imaging task, which is'
        . ' the only place in core that can';
}
if (preg_match('#notify\(\s*\n\s*\'HOST_IMAGE_COMPLETE\'#', $src)) {
    $fails[] = 'checkout() notifies HOST_IMAGE_COMPLETE directly again,'
        . ' bypassing the deploy/capture and imaging-task decisions';
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: imaging tasks announce the outcome they actually had\n";
exit(0);
