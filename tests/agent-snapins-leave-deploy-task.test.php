<?php
/**
 * The agent's snapin path leaves a deploy task alone (#1875).
 *
 * A deploy queued with snapins creates its snapin job when the task is
 * created, because those snapins run on the system the image puts down. The
 * legacy client refuses them until then ('it', SnapinClient::json()). The
 * agent path did not: State handed the queue out next to the deploy, the
 * agent ran a snapin on the OLD system, and close() then completed the
 * host's active task -- the deploy. The task left Active Tasks before the
 * machine PXE booted, and the machine fell through to the iPXE menu.
 *
 * Pinned by driving the real methods with in-memory objects, not by reading
 * the source:
 *
 *   - close() on the last snapin leaves a deploy task's state unchanged,
 *     and still completes a snapin-only task (the control).
 *   - queue() hands out nothing while a deploy task is active.
 *
 * Usage: php tests/agent-snapins-leave-deploy-task.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-snapins-leave-deploy-task');
$db = FogTestHarness::fakeDb();
// No snapin task left in the job: close() takes its "last one" branch.
$db->pdo->countValue = 0;

use FOG\Agent\Snapins;
use FOG\Items\Host;
use FOG\Items\Snapin;
use FOG\Items\SnapinJob;
use FOG\Items\SnapinTask;
use FOG\Items\Task;
use FOG\Items\TaskType;

/**
 * Records what would be written instead of writing it.
 */
class SpyTask extends Task
{
    public $saved = [];
    public function save()
    {
        $this->saved[] = (int)$this->get('stateID');
        return $this;
    }
}
class SpySnapinJob extends SnapinJob
{
    public function save()
    {
        return $this;
    }
}
class SpySnapinTask extends SnapinTask
{
    public $snapin;
    public function getSnapin()
    {
        return $this->snapin;
    }
    public function save()
    {
        return $this;
    }
}
class StubHost extends Host
{
    public $parts = [];
    public function get($key = '')
    {
        if (array_key_exists($key, $this->parts)) {
            return $this->parts[$key];
        }
        return parent::get($key);
    }
}

/**
 * A host whose active task has the given type, with one live snapin job.
 *
 * @param int $typeID the active task's type
 *
 * @return array [StubHost, SpyTask, SpySnapinTask]
 */
function snapinFixture($typeID)
{
    $queued = Snapins::getQueuedState();
    $Task = (new SpyTask())
        ->set('id', 9)
        ->set('typeID', $typeID)
        ->set('hostID', 1)
        ->set('stateID', $queued);
    $Job = (new SpySnapinJob())
        ->set('id', 4)
        ->set('hostID', 1)
        ->set('stateID', $queued);
    $Snapin = (new Snapin())
        ->set('id', 2)
        ->set('name', 'probe')
        ->set('file', 'probe.exe');
    $SnapinTask = (new SpySnapinTask())
        ->set('id', 7)
        ->set('jobID', 4)
        ->set('snapinID', 2)
        ->set('stateID', $queued);
    $SnapinTask->snapin = $Snapin;
    $Host = (new StubHost())->set('id', 1)->set('name', 'host1');
    $Host->parts = ['task' => $Task, 'snapinjob' => $Job];
    return [$Host, $Task, $SnapinTask];
}

$t = new FogChecks();
$queued = (int)Snapins::getQueuedState();
$complete = (int)Snapins::getCompleteState();

/*
 * 1. The control: a snapin-only task completes with its last snapin. If
 *    this fails, the fixture does not reach the branch, and check 2 proves
 *    nothing.
 */
list($Host, $Task, $SnapinTask) = snapinFixture(TaskType::ALL_SNAPINS);
$t->check('the fixture task is valid', $Task->isValid());
Snapins::close($Host, $SnapinTask, 0, 'ok');
$t->check(
    'the last snapin completes a snapin-only task',
    in_array($complete, $Task->saved, true)
);

/*
 * 2. The bug: the same close with a deploy active leaves the deploy alone.
 */
list($Host, $Task, $SnapinTask) = snapinFixture(TaskType::DEPLOY);
Snapins::close($Host, $SnapinTask, 0, 'ok');
$t->check('close() writes nothing to a deploy task', [] === $Task->saved);
$t->check(
    'the deploy task is still queued',
    $queued === (int)$Task->get('stateID')
);

/*
 * 3. And the agent is not handed the deploy's snapins in the first place:
 *    queue() answers empty without reading the job's snapin tasks. Reading
 *    them is the failure, so it is caught and counted, not left to fatal.
 */
list($Host) = snapinFixture(TaskType::DEPLOY);
$db->pdo->log = $db->log = [];
try {
    $queue = Snapins::queue($Host);
} catch (\Throwable $e) {
    $queue = null;
}
$read = preg_grep('/`snapinTasks`/', array_merge($db->pdo->log, $db->log));
$t->check('queue() is empty while a deploy is active', [] === $queue);
$t->check('queue() never reads the snapin tasks of a deploy', [] === $read);

$t->finish();
