<?php
/**
 * assocSetter() tells listeners which links it added and removed.
 *
 * ASSOCIATION_UPDATED fires once, after the rows are written, with the ids
 * of the other end that were linked and unlinked. A plugin uses it to keep
 * its own rule about a membership whichever page or route saved it: the
 * directorygroups plugin keeps a host in one OU group and writes that OU
 * onto the host (GH-1879).
 *
 * It must not fire when nothing changed. Every Host::save() reaches
 * assocSetter() for each association it touched, and a listener that ran
 * on a no-op save would do its work on every host edit.
 *
 * DB-free: the harness's fake connection answers the current-links read.
 *
 * Usage: php tests/association-updated-event.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('association-updated-event');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Saves a group's hosts over the links the database already holds.
 *
 * @param array $have  host ids the fake groupMembers table holds
 * @param array $want  host ids the group is saved with
 *
 * @return array every ASSOCIATION_UPDATED argument array, in order
 */
function saveHosts(array $have, array $want)
{
    global $db;
    $db->responder = function ($sql) use ($have) {
        if (false === stripos($sql, 'groupMembers')
            || 0 !== stripos(ltrim($sql), 'SELECT')
        ) {
            return null;
        }
        $rows = [];
        foreach ($have as $id) {
            $rows[] = ['gmID' => $id, 'gmHostID' => $id, 'hostID' => $id];
        }
        return $rows;
    };
    $seen = [];
    $hooks = new \FOG\Base\HookManager();
    $hooks->register(
        'ASSOCIATION_UPDATED',
        function ($args) use (&$seen) {
            $seen[] = $args;
        }
    );
    FogTestHarness::setStatic('FOGBase', 'HookManager', $hooks);

    $Group = new \FOG\Items\Group();
    $Group->set('id', 5)->set('hosts', $want);
    $Group->assocSetter('Group', 'host');
    $db->responder = null;

    return $seen;
}

$seen = saveHosts([2, 3], [1, 2]);
$t->check('a changed membership fires the event once', 1 === count($seen));
$args = $seen[0] ?? [];
$t->check(
    'the event names the association and the other end',
    'GroupAssociation' === ($args['association'] ?? null)
        && 'host' === ($args['item'] ?? null)
);
$t->check(
    'the owner is the saved group',
    isset($args['owner']) && 5 === (int)$args['owner']->get('id')
);
$t->check('added holds the newly linked host', [1] === ($args['added'] ?? null));
$t->check('removed holds the unlinked host', [3] === ($args['removed'] ?? null));

$seen = saveHosts([2, 3], [2]);
$t->check(
    'a removal alone still fires, with nothing added',
    1 === count($seen) && [] === $seen[0]['added'] && [3] === $seen[0]['removed']
);

$seen = saveHosts([2, 3], [2, 3]);
$t->check('an unchanged membership fires nothing', 0 === count($seen));

$t->finish();
