<?php
/**
 * A new row's never-set fields take the column DEFAULT (GH-1946).
 *
 * POST /image without isEnabled made a disabled image: save() filled every
 * unset field with emptyValueFor() -- 0 for `imageEnabled tinyint(1) NOT
 * NULL DEFAULT 1` -- so the column DEFAULT never applied, and the next
 * capture failed with "Image is not enabled".
 *
 * save() now leaves a never-set field out of a new row's INSERT, and the
 * server applies the DEFAULT. These checks read the INSERT save() builds:
 *   - a never-set field is left out;
 *   - a field set to anything, even 0, is written;
 *   - a column the server cannot default (NOT NULL, no DEFAULT) is still
 *     written, or the INSERT is error 1364;
 *   - createdBy is still filled in;
 *   - an API-created user still gets api 0, not uAllowAPI's DEFAULT 1.
 *
 * Usage: php tests/api-create-honors-column-defaults.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('api-create-defaults');
$db = FogTestHarness::fakeDb();

use FOG\Items\Image;
use FOG\Items\User;
use FOG\Router\Route;

// The fake catalog says imageSize is NOT NULL with no DEFAULT.
$db->responder = function ($sql) {
    if (false !== strpos($sql, 'information_schema')
        && false !== strpos($sql, 'COLUMN_DEFAULT')
    ) {
        return [['c' => 'imageSize', 'ty' => 'varchar(255)']];
    }
    return null;
};

/**
 * The column list of the INSERT that save() sent.
 *
 * @param FogFakeDb $db the fake database
 *
 * @return string
 */
function insertColumns($db)
{
    foreach (array_reverse($db->log) as $sql) {
        if (preg_match('/^\s*INSERT INTO\s+`?\w+`?\s*\(([^)]*)\)/i', $sql, $m)) {
            return $m[1];
        }
    }
    return '';
}

$t = new FogChecks();

$image = new Image();
$image->set('name', 'x')->set('path', 'x')->set('imageTypeID', 1)->set('imagePartitionTypeID', 1)->set('osID', 1);
$image->save();
$cols = insertColumns($db);
$t->check('imageEnabled never set: left out, so DEFAULT 1 applies', false === strpos($cols, '`imageEnabled`'));
$t->check('imageReplicate never set: left out, so DEFAULT 1 applies', false === strpos($cols, '`imageReplicate`'));
$t->check('imageSize has no DEFAULT: still written', false !== strpos($cols, '`imageSize`'));
$t->check('imageCreateBy is still filled in', false !== strpos($cols, '`imageCreateBy`'));

$image = new Image();
$image->set('name', 'x')->set('path', 'x')->set('imageTypeID', 1)->set('imagePartitionTypeID', 1)->set('osID', 1)
    ->set('isEnabled', 0);
$image->save();
$t->check('imageEnabled set to 0: written', false !== strpos(insertColumns($db), '`imageEnabled`'));

// The API path: a user created without "api" keeps the old 0.
$apply = new \ReflectionMethod(Route::class, '_applyCreateFields');
$apply->setAccessible(true);
$classVars = new \ReflectionMethod(Route::class, '_classVars');
$classVars->setAccessible(true);
$user = new User();
$apply->invoke(null, $user, 'user', $classVars->invoke(null, 'User'), (object)['name' => 'x']);
$t->check('API user without api: set to 0, not DEFAULT 1', '0' === (string)$user->get('api'));

$t->finish();
