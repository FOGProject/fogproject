<?php
/**
 * Group Order is a field an admin can actually set.
 *
 * `groups`.`groupOrder` shipped with FOG\Assign\Resolver, which orders the
 * groups a host belongs to by that column before falling back to group name.
 * The column, its index and both readers went in -- and nothing wrote it.
 * It was absent from Group::$databaseFields, so it was not settable from the
 * group page, not settable over the API, and not even loadable: every group
 * on every install sat at the default of 0 and the name fallback was the
 * only behavior there was.
 *
 * That is invisible until two groups disagree. A host in "Lab" and in
 * "Lab Overrides" that both grant a default printer resolves to whichever
 * name sorts first, and the admin has no control to change it.
 *
 * WHAT THIS DRIVES:
 *
 *   1. Group maps `order` to `groupOrder`, so the value round-trips through
 *      the same read and write path as every other group field -- which is
 *      also what puts it on the API without a second surface being built.
 *   2. Both the create form and the edit form render the input, and both
 *      save paths persist it. A form that displays a field it never saves
 *      is worse than no field: it reports success and changes nothing.
 *   3. The edit form does NOT resolve the current value with `?:`. Every
 *      other field on that form does, because for them empty and unset mean
 *      the same thing. For an order they do not: 0 is both a real value and
 *      the default one, so `?:` would discard it and redisplay the field
 *      empty on every load -- and then save it back as 0 either way, which
 *      is why the bug would look cosmetic right up until someone set an
 *      order of 0 deliberately to move a group to the front.
 *   4. The value is clamped, not trusted. The input is type=number with
 *      min=0; a negative order from a hand-built POST has no meaning the
 *      resolver could act on.
 *
 * Usage: php tests/group-order-is-settable.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Items\Group;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('group-order-is-settable');

$t = new FogChecks();
$db = FogTestHarness::fakeDb();
$root = dirname(__DIR__) . '/packages/web';

/**
 * One method's source, comments stripped.
 *
 * The assertions below are about what the code does; a comment explaining
 * the trap would otherwise satisfy a check looking for the trap.
 *
 * @param string $file  path to the class
 * @param string $method the method name
 *
 * @return string
 */
function orderMethodBody($file, $method)
{
    $src = (string)file_get_contents($file);
    $at = strpos($src, 'function ' . $method . '(');
    if (false === $at) {
        return '';
    }
    $body = substr($src, $at);
    $next = strpos($body, "\n    /**", 1);
    if (false !== $next) {
        $body = substr($body, 0, $next);
    }
    $body = preg_replace('#/\*.*?\*/#s', '', $body);

    return (string)preg_replace('#//[^\n]*#', '', (string)$body);
}

// ---------------------------------------------------------------- 1. mapping

$fields = (new \ReflectionClass('FOG\Items\Group'))
    ->getDefaultProperties()['databaseFields'];

$t->check(
    'Group maps order to the groupOrder column',
    isset($fields['order']) && $fields['order'] === 'groupOrder'
);

// The resolver's ORDER BY is the reason the column exists. If it stops
// naming the column, the field above is decoration.
$resolver = (string)file_get_contents($root . '/src/Assign/Resolver.php');
$t->check(
    'the resolver still orders groups by groupOrder first',
    false !== strpos($resolver, 'ORDER BY `groupOrder`, `groupName`, `groupID`')
);

// Round-trips for real: the value has to reach a statement naming the
// column, which is what proves the mapping rather than restating it.
/**
 * Saves a group and returns the statements the save issued.
 *
 * @param FogFakeDb $db the fake
 *
 * @return array
 */
function saveGroupWithOrder($db)
{
    $db->log = [];
    (new Group())
        ->set('name', 'Lab')
        ->set('order', 7)
        ->save();

    return $db->log;
}

$wrote = false;
foreach (saveGroupWithOrder($db) as $sql) {
    if (false !== strpos((string)$sql, 'groupOrder')) {
        $wrote = true;
    }
}
$t->check('saving a group writes the groupOrder column', $wrote);

$loaded = (new Group())->set('order', 0);
$t->check(
    'an order of 0 reads back as 0 rather than as unset',
    $loaded->get('order') === 0 || $loaded->get('order') === '0'
);

// ------------------------------------------------------- 2. forms and saving

$page = $root . '/src/Pages/GroupManagement.php';
$src = (string)file_get_contents($page);

$addFields = orderMethodBody($page, '_addFields');
$t->check(
    'the create form renders an order input',
    false !== strpos($addFields, "'order'")
    && false !== strpos($addFields, "'number'")
);

$addPost = orderMethodBody($page, 'addPost');
$t->check(
    'creating a group persists the order',
    false !== strpos($addPost, "set('order'")
);
$t->check(
    'the created order is clamped at 0',
    (bool)preg_match('#max\(\s*0,\s*\(int\)filter_input\(INPUT_POST, \'order\'\)#s', $addPost)
);

// The edit form and its save live in the general-form pair; find whichever
// methods carry them rather than pinning their names, so a rename is not a
// false failure.
$editRenders = false;
$editSaves = false;
$editClamps = false;
$editNoElvis = true;
foreach (['groupGeneral', 'editPost', 'groupGeneralPost'] as $method) {
    $body = orderMethodBody($page, $method);
    if ('' === $body) {
        continue;
    }
    if (false !== strpos($body, "'order'") && false !== strpos($body, "'number'")) {
        $editRenders = true;
        // The trap. `?:` on an order silently eats the value 0.
        if (preg_match('#filter_input\(INPUT_POST, \'order\'\)\s*\?:#', $body)) {
            $editNoElvis = false;
        }
    }
    if (false !== strpos($body, "set('order'")) {
        $editSaves = true;
        if (preg_match('#max\(\s*0,\s*\(int\)filter_input\(INPUT_POST, \'order\'\)#s', $body)) {
            $editClamps = true;
        }
    }
}

$t->check('the edit form renders an order input', $editRenders);
$t->check('editing a group persists the order', $editSaves);
$t->check('the edited order is clamped at 0', $editClamps);
$t->check(
    'the edit form does not resolve the order with ?:, which would eat 0',
    $editNoElvis
);

// Whole-file backstop: nowhere on the page may the elvis idiom be applied
// to the order field, whichever method it ends up living in.
$t->check(
    'no method on the page resolves the order with ?:',
    !preg_match('#filter_input\(INPUT_POST, \'order\'\)\s*\?:#', $src)
);

$t->finish();
