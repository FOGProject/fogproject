<?php
/**
 * A snapin can run before the host's software (forum 18322).
 *
 * The snapin carries a "Run before software" flag (sBeforeSoftware, step
 * 464). Snapins::desired() sends it to the agent as `before_software`. The
 * create path (UI and API share Snapin::uploadAndCreate()) and the edit
 * path both save it.
 *
 * DB-free: reads the field map and the source.
 *
 * Usage: php tests/snapin-before-software.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('snapin-before-software');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';

$ref = new \ReflectionClass(\FOG\Items\Snapin::class);
$fields = $ref->getDefaultProperties()['databaseFields'];
$t->check(
    'Snapin maps beforeSoftware to sBeforeSoftware',
    'sBeforeSoftware' === ($fields['beforeSoftware'] ?? null)
);

// Source pins: desired() reads the host's job from the database, so it
// cannot run here.
$agentSrc = (string)file_get_contents($web . '/src/Agent/Snapins.php');
$t->check(
    'Snapins::desired() emits before_software from the beforeSoftware field',
    false !== strpos(
        $agentSrc,
        "'before_software' => (bool)\$Snapin->get('beforeSoftware')"
    )
);

$itemSrc = (string)file_get_contents($web . '/src/Items/Snapin.php');
$t->check(
    'uploadAndCreate() saves beforeSoftware',
    false !== strpos(
        $itemSrc,
        "\$beforeSoftware = (int)isset(\$post['beforeSoftware']);"
    )
    && false !== strpos($itemSrc, "->set('beforeSoftware', \$beforeSoftware)")
);

$pageSrc = (string)file_get_contents($web . '/src/Pages/SnapinManagement.php');
$t->check(
    'snapin edit save writes beforeSoftware',
    false !== strpos(
        $pageSrc,
        "\$beforeSoftware = (int)isset(\$_POST['beforeSoftware']);"
    )
    && false !== strpos($pageSrc, "->set('beforeSoftware', \$beforeSoftware)")
);
$t->check(
    'create and edit forms both carry the beforeSoftware checkbox',
    2 === substr_count($pageSrc, "'beforeSoftware',\n                _('Run before software')")
);

$schemaSrc = (string)file_get_contents($web . '/commons/schema.php');
$t->check(
    'schema step 464 adds sBeforeSoftware to snapins',
    (bool)preg_match(
        '/\/\/ 464\n\$this->schema\[\] = \[.*?ALTER TABLE `snapins` "\s*'
        . '\. "ADD COLUMN `sBeforeSoftware` tinyint\(1\) NOT NULL DEFAULT 0/s',
        $schemaSrc
    )
);

$expected = (string)file_get_contents($web . '/commons/schema-expected.php');
$t->check(
    'schema-expected lists sBeforeSoftware in create and columns',
    false !== strpos($expected, '`sBeforeSoftware` tinyint(1) NOT NULL DEFAULT 0, `sOS` varchar(20)')
    && false !== strpos($expected, "'sBeforeSoftware' => 'tinyint(1) NOT NULL DEFAULT 0'")
);

$t->finish();
