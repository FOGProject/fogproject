<?php
/**
 * A group grants; it does not copy.
 *
 * ADR 0038's whole point. Before this, Group::addSnapin() wrote one
 * `snapinAssoc` row per member host -- a bulk write onto the hosts that
 * existed at the moment the button was pressed. Nothing recorded that the
 * write had happened, so nothing could replay it: a host added to the group
 * afterward got nothing, and a host removed kept everything.
 *
 * Now the row lands on the GROUP -- `groupSnapinAssoc`, `groupPrinterAssoc`,
 * `groupModuleAssoc` -- and Assign\Resolver unions it with the host's own
 * rows at read time.
 *
 * WHAT THIS DRIVES:
 *
 *   1. The six add/remove methods write the GRANT table and never the host
 *      association table. Writing both would be the copy arriving by another
 *      door, and it would look identical from the group page.
 *   2. None of them reads the member host list. `$this->get('hosts')` inside
 *      one of these methods IS the copy, whatever it goes on to do with it.
 *   3. Every write is scoped by groupID. An unscoped DELETE on a grant table
 *      revokes it for every group on the server.
 *   4. addSnapin() does not name `sequence`, and addPrinter() does not name
 *      `isDefault`, in the insert field list. insertBatch upserts on the
 *      unique key and sets every column it is given, so naming them would
 *      silently reset an order or a default the admin had chosen -- the same
 *      trap Host::appendSnapinSequence() documents on the host side.
 *   5. An unsaved group and an empty id set write nothing at all, and only
 *      positive integer ids reach the database.
 *   6. The group page reads the grant tables, and the tri-state member
 *      coverage machinery it replaces is gone. That vocabulary
 *      (all/some/none, the n-of-total badge, the Has/Missing drill-down)
 *      existed only to reconstruct what the group would have looked like if
 *      it had ever owned anything; leaving it behind would report member
 *      coverage on a tab that is no longer describing member coverage.
 *
 * Usage: php tests/group-grants-are-owned.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Items\Group;
use FOG\Items\User;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('group-grants-are-owned');

$t = new FogChecks();
$db = FogTestHarness::fakeDb();
$root = dirname(__DIR__) . '/packages/web';

$admin = (new User())->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

/**
 * Runs one Group method and returns the statements it issued.
 *
 * @param FogFakeDb $db      the fake
 * @param string    $method  the method to call
 * @param mixed     $arg     its single argument
 * @param int       $groupID the group
 * @param callable  $respond optional SELECT responder
 *
 * @return array the statements issued
 */
function runGroup($db, $method, $arg, $groupID = 3, $respond = null)
{
    $db->log = [];
    $db->responder = $respond;
    (new Group())
        ->set('id', $groupID)
        ->{$method}($arg);
    $db->responder = null;

    return $db->log;
}

/**
 * The statements that touched one table.
 *
 * Scoped on purpose: a run also reads settings and may write a history row,
 * and counting those would make this assert something other than it says.
 *
 * @param array  $log   statements
 * @param string $table the table name
 *
 * @return array
 */
function onTable(array $log, $table)
{
    $out = [];
    foreach ($log as $sql) {
        if (false !== strpos((string)$sql, $table)) {
            $out[] = (string)$sql;
        }
    }

    return $out;
}

/**
 * Source with its comments stripped.
 *
 * These assertions are about what the code DOES; a comment naming the old
 * behavior would otherwise fail a check for the old behavior.
 *
 * @param string $body source
 *
 * @return string
 */
function codeOnly($body)
{
    $body = preg_replace('#/\*.*?\*/#s', '', (string)$body);

    return (string)preg_replace('#//[^\n]*#', '', (string)$body);
}

/**
 * One method's source, comments stripped.
 *
 * @param string $src    the file
 * @param string $method the method name
 *
 * @return string
 */
function methodBody($src, $method)
{
    $start = strpos($src, "\n    public function $method(");
    if (false === $start) {
        return '';
    }
    $end = strpos($src, "\n    /**", $start + 1);

    return codeOnly(
        false === $end
            ? substr($src, $start)
            : substr($src, $start, $end - $start)
    );
}

$groupSrc = (string)file_get_contents($root . '/src/Items/Group.php');
$pageSrc = (string)file_get_contents($root . '/src/Pages/GroupManagement.php');
$jsSrc = (string)file_get_contents(
    $root . '/management/js/fog/group/fog.group.edit.js'
);

$grants = [
    'snapin' => ['groupSnapinAssoc', 'snapinAssoc', 'GroupSnapinAssociationManager'],
    'printer' => ['groupPrinterAssoc', 'printerAssoc', 'GroupPrinterAssociationManager'],
    'module' => ['groupModuleAssoc', 'moduleStatusByHost', 'GroupModuleAssociationManager'],
];

// ---------------------------------------------------------------
// Invariant 1 and 2, read off the source: the grant table, and only it.
foreach ($grants as $kind => $info) {
    list($grantTable, $hostTable, $manager) = $info;
    $add = methodBody($groupSrc, 'add' . ucfirst($kind));
    $rem = methodBody($groupSrc, 'remove' . ucfirst($kind));

    $t->check(
        "add$kind() writes through $manager",
        '' !== $add && false !== strpos($add, $manager)
    );
    $t->check(
        "add$kind() does not read the member host list",
        '' !== $add && false === strpos($add, "get('hosts')")
    );
    $t->check(
        "remove$kind() does not read the member host list",
        '' !== $rem && false === strpos($rem, "get('hosts')")
    );
    $t->check(
        "remove$kind() deletes from the grant table, scoped by groupID",
        false !== strpos($rem, "'group" . $kind . "association'")
        && false !== strpos($rem, "'groupID'")
    );
}

// The host-side managers must not appear in the six methods at all. Named
// individually rather than swept, so a failure says which one came back.
foreach (['Snapin', 'Printer', 'Module'] as $kind) {
    $both = methodBody($groupSrc, 'add' . $kind)
        . methodBody($groupSrc, 'remove' . $kind);
    $t->check(
        "neither add$kind() nor remove$kind() touches {$kind}AssociationManager",
        false === strpos($both, "getClass('{$kind}AssociationManager')")
    );
    $t->check(
        "nor {$kind}Association through deletemass",
        false === strpos($both, "'" . strtolower($kind) . "association'")
    );
}

// ---------------------------------------------------------------
// Invariant 4: the columns that carry an admin's decision are left out of
// the upsert.
$add = methodBody($groupSrc, 'addSnapin');
$t->check(
    'addSnapin() inserts groupID and snapinID only',
    false !== strpos($add, "['groupID', 'snapinID']")
    && false === strpos($add, "'sequence'")
);
$t->check(
    'and numbers the new rows afterward instead',
    false !== strpos($add, 'appendSnapinSequence()')
);
$t->check(
    'addPrinter() inserts groupID and printerID only',
    false !== strpos(methodBody($groupSrc, 'addPrinter'), "['groupID', 'printerID']")
    && false === strpos(methodBody($groupSrc, 'addPrinter'), "'isDefault'")
);

// ---------------------------------------------------------------
// Behavior. One insert per kind, on the grant table, carrying the group id
// and no host id.
$log = runGroup($db, 'addModule', [4, 6]);
$stmts = onTable($log, 'groupModuleAssoc');
$t->check(
    'addModule() writes groupModuleAssoc',
    1 === count($stmts)
);
$sql = implode(' ', $stmts);
$t->check(
    'and names no host column',
    false === stripos($sql, 'hostID') && false === stripos($sql, 'msHostID')
);
$t->check(
    'and it is an upsert, so re-granting is not a unique-key error',
    false !== stripos($sql, 'ON DUPLICATE KEY UPDATE')
);
$t->check(
    'addModule() writes nothing to moduleStatusByHost',
    0 === count(onTable($log, 'moduleStatusByHost'))
);

$log = runGroup($db, 'addPrinter', [8]);
$t->check(
    'addPrinter() writes groupPrinterAssoc and not printerAssoc',
    1 === count(onTable($log, 'groupPrinterAssoc'))
    && 0 === count(onTable($log, '`printerAssoc`'))
);

// addSnapin also runs the sequence sweep, which reads its own table first.
$log = runGroup(
    $db,
    'addSnapin',
    [2],
    3,
    function ($sql) {
        if (false !== strpos($sql, 'groupSnapinAssoc')
            && 0 === stripos(ltrim($sql), 'SELECT')
        ) {
            return [['gsaSnapinID' => 2, 'gsaSequence' => 0, 'gsaID' => 1]];
        }
        return null;
    }
);
$t->check(
    'addSnapin() writes groupSnapinAssoc and not snapinAssoc',
    count(onTable($log, 'groupSnapinAssoc')) > 0
    && 0 === count(onTable($log, '`snapinAssoc`'))
);
$t->check(
    'addSnapin() numbers a row that landed at sequence 0',
    false !== stripos(implode(' ', onTable($log, 'groupSnapinAssoc')), 'UPDATE')
);

// ---------------------------------------------------------------
// Invariant 3: the DELETE is bound to this group.
$log = runGroup($db, 'removeSnapin', [2]);
$del = implode(' ', onTable($log, 'groupSnapinAssoc'));
$t->check(
    'removeSnapin() binds the group id into the DELETE',
    false !== stripos($del, 'DELETE') && false !== strpos($del, 'gsaGroupID')
);

// ---------------------------------------------------------------
// Invariant 5.
$t->check(
    'an unsaved group writes nothing',
    0 === count(onTable(runGroup($db, 'addModule', [4], 0), 'groupModuleAssoc'))
);
$t->check(
    'an empty id set writes nothing',
    0 === count(onTable(runGroup($db, 'addModule', []), 'groupModuleAssoc'))
);
$t->check(
    'no usable id writes nothing',
    0 === count(
        onTable(runGroup($db, 'addModule', ['nope', 0, -2]), 'groupModuleAssoc')
    )
);
$log = runGroup($db, 'addModule', ['4; DROP TABLE `hosts`']);
$t->check(
    'a non-numeric id cannot carry SQL through',
    false === stripos(implode(' ', $log), 'DROP TABLE')
);

// ---------------------------------------------------------------
// updateDefault and setSnapinOrder are group-level now.
$body = methodBody($groupSrc, 'updateDefault');
$t->check(
    'updateDefault() writes the grant row, not every member host',
    false !== strpos($body, 'GroupPrinterAssociationManager')
    && false === strpos($body, "get('hosts')")
);
$log = runGroup($db, 'updateDefault', 8);
$sql = implode(' ', onTable($log, 'groupPrinterAssoc'));
$t->check(
    'updateDefault() clears the old default before setting the new one',
    2 === count(onTable($log, 'groupPrinterAssoc'))
    && false !== strpos($sql, 'gpaIsDefault')
);
$t->check(
    'updateDefault(0) clears without setting anything',
    1 === count(onTable(runGroup($db, 'updateDefault', 0), 'groupPrinterAssoc'))
);
$body = methodBody($groupSrc, 'setSnapinOrder');
$t->check(
    'setSnapinOrder() writes gsaSequence on the group, not on each host',
    false !== strpos($body, 'GroupSnapinAssociationManager')
    && false === strpos($body, "get('hosts')")
    && false === strpos($body, 'new Host(')
);
$log = runGroup($db, 'setSnapinOrder', [5, 6]);
$t->check(
    'and one statement per snapin, in the submitted order',
    2 === count(onTable($log, 'groupSnapinAssoc'))
);

// ---------------------------------------------------------------
// Invariant 6: the page reads the grant tables.
// Both halves, because naming the grant table somewhere in the method is not
// the same as reading it: the modules tab keeps `groupModuleAssoc` in its ON
// clause even if the JOIN itself is pointed back at the host state table.
foreach (
    [
        'getSnapinsList' => ['groupSnapinAssoc', 'snapinAssoc'],
        'getPrintersList' => ['groupPrinterAssoc', 'printerAssoc'],
        'getModulesList' => ['groupModuleAssoc', 'moduleStatusByHost'],
    ] as $method => $tables
) {
    list($grantTable, $hostTable) = $tables;
    $body = methodBody($pageSrc, $method);
    $t->check(
        "$method() reads $grantTable",
        false !== strpos($body, $grantTable)
    );
    $t->check(
        "$method() does not read $hostTable",
        '' !== $body
        && false === strpos(str_replace($grantTable, '', $body), $hostTable)
    );
}
$t->check(
    'getSnapinOrderList() reads the group grants, not a member intersection',
    false !== strpos(
        methodBody($pageSrc, 'getSnapinOrderList'),
        "'groupsnapinassociation'"
    )
);

// The coverage vocabulary is gone from both halves.
foreach (['_groupAssocList', 'getAssocHostsList', '_uniformDefaultPrinter'] as $gone) {
    $t->check(
        "$gone() is gone from the group page",
        false === strpos($pageSrc, $gone)
    );
}
foreach (['wireGroupAssocTab', 'groupAssocRender', 'assocCount', 'assoc-drill'] as $gone) {
    $t->check(
        "$gone is gone from the group edit script",
        false === strpos($jsSrc, $gone)
    );
}

// ---------------------------------------------------------------
// The three grant classes exist and are keyed the way the resolver reads
// them. A field map that disagrees with the schema fails silently: the
// query compiles against a column that is not there.
$expect = [
    'GroupSnapinAssociation' => [
        'groupSnapinAssoc',
        ['groupID' => 'gsaGroupID', 'snapinID' => 'gsaSnapinID', 'sequence' => 'gsaSequence'],
    ],
    'GroupPrinterAssociation' => [
        'groupPrinterAssoc',
        ['groupID' => 'gpaGroupID', 'printerID' => 'gpaPrinterID', 'isDefault' => 'gpaIsDefault'],
    ],
    'GroupModuleAssociation' => [
        'groupModuleAssoc',
        ['groupID' => 'gmaGroupID', 'moduleID' => 'gmaModuleID'],
    ],
];
$schema = (string)file_get_contents($root . '/commons/schema.php');
foreach ($expect as $class => $info) {
    list($table, $fields) = $info;
    $obj = FOGCore::getClass($class);
    $ref = new \ReflectionObject($obj);
    $tProp = $ref->getProperty('databaseTable');
    $tProp->setAccessible(true);
    $fProp = $ref->getProperty('databaseFields');
    $fProp->setAccessible(true);
    $t->check(
        "$class is bound to $table",
        $table === $tProp->getValue($obj)
    );
    $map = (array)$fProp->getValue($obj);
    foreach ($fields as $common => $column) {
        $t->check(
            "$class maps $common to $column",
            isset($map[$common]) && $column === $map[$common]
        );
        $t->check(
            "and schema.php declares `$column`",
            false !== strpos($schema, '`' . $column . '`')
        );
    }
}

// ---------------------------------------------------------------
// Decision 10's LAST step: the controls that copied onto member hosts are
// gone, not merely marked. They were deprecated in #1647 and removed once
// fog-plugins #36 gave `location` and `ou` the same seam the core fields
// use, so nothing was left depending on a group-page push.
//
// These assertions replace the ones that pinned the deprecation notices.
// Inverting them rather than deleting them is deliberate: "the notice is
// present" and "the control is absent" are both statements about the same
// migration, and the second is the one worth keeping, because it is the
// state that must not silently regress.
$t->check(
    'the push-deprecation notice is gone with the controls it described',
    false === strpos($pageSrc, '_pushDeprecationNotice')
);

// The whole tabs, and their POST handlers.
foreach (
    [
        'groupImage',
        'groupImagePost',
        'groupADPost',
        '_uniformHostValues',
        '_sharedHint',
        '_sharedAloHint',
        '_groupADStateHint',
    ] as $method
) {
    $t->check(
        "$method() is gone from the group page",
        false === strpos($pageSrc, " function $method(")
    );
}

// A dispatch arm surviving its handler is the shape that fails at runtime
// rather than at parse time, so it is worth its own check.
foreach (['group-image', 'group-active-directory'] as $tab) {
    $t->check(
        "the '$tab' tab is no longer dispatched",
        false === strpos($pageSrc, "case '$tab':")
    );
}

// The pushes that lived inside surviving methods. Each of these is a write
// to the MEMBER hosts from a group control -- the exact thing ADR 0038
// removes -- and each would look, from the page, like the control that used
// to be there.
$pushes = [
    'groupGeneralPost' => ['HostManager', 'productKey', 'bootTypeExit'],
    'groupPrinterPost' => ['printerLevel', 'confirmlevelup'],
    'groupModulePost' => ['setAlo', 'confirmenforcesend'],
];
foreach ($pushes as $method => $needles) {
    $body = codeOnly(methodBody($pageSrc, $method));
    foreach ($needles as $needle) {
        $t->check(
            "$method() no longer pushes $needle onto the members",
            false === strpos($body, $needle)
        );
    }
}

// The control. groupPowermanagement CREATES TASKS rather than copying a
// value, so it is not part of this removal and must still be there -- a
// sweep that took it too would be over-deletion passing as success.
$t->check(
    'groupPowermanagement() survives, because it copies nothing',
    false !== strpos($pageSrc, ' function groupPowermanagement(')
);

// And the grants themselves are untouched: this removed the pushes, not the
// tabs the ADR is delivering.
foreach (['groupPrinters', 'groupSnapins', 'groupModules'] as $method) {
    $t->check(
        "$method() still renders its grant tab",
        false !== strpos(methodBody($pageSrc, $method), 'renderAssocTab')
        || false !== strpos(methodBody($pageSrc, $method), 'assocItemsList')
    );
}

// The JS that drove the removed cards goes with them; a handler left bound
// to an id nothing renders is dead code that still looks wired.
$js = (string)file_get_contents(
    $root . '/management/js/fog/group/fog.group.edit.js'
);
foreach (
    [
        'group-image-send',
        'printer-config-send',
        'group-displayman-send',
        'group-alo-send',
        'group-enforce-send',
        'alo-shared-hint',
    ] as $id
) {
    $t->check(
        "the group JS no longer wires #$id",
        false === strpos($js, $id)
    );
}

$t->finish();
