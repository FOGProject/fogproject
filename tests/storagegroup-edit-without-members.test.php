<?php
/**
 * A storage group with no members can still be edited.
 *
 * Creating a group makes an empty one, and the edit page is the only place
 * to give it its first member. That page opened by asking the group for its
 * master node, and getMasterStorageNode() answers "no members" by throwing
 * -- right for tasking, which cannot run without one, and a blank 500 here,
 * on the one page that could have fixed it. On the lab, group 6 ("something")
 * could be created and never opened again.
 *
 * DB-free: the fake DB has no nodes, so every group is an empty one, which is
 * exactly the state under test.
 *
 * Usage: php tests/storagegroup-edit-without-members.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Items\StorageGroup;
use FOG\Pages\StorageGroupManagement;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('storagegroup-edit-without-members');
FogTestHarness::fakeDb();

$t = new FogChecks();

$page = new StorageGroupManagement();
$obj = new \ReflectionProperty($page, 'obj');
$obj->setAccessible(true);
$obj->setValue($page, new StorageGroup());

$threw = '';
try {
    ob_start();
    $page->edit();
    ob_end_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    $threw = get_class($e) . ': ' . $e->getMessage();
}
$t->check(
    'editing a group with no members does not throw' . ($threw ? " ($threw)" : ''),
    '' === $threw
);

$t->finish();
