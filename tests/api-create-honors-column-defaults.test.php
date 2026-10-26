<?php
/**
 * An API create fills an omitted field with the column DEFAULT (GH-1946).
 *
 * POST /image without isEnabled made a disabled image: Route::create() left
 * the field unset, and FOGController::save() wrote emptyValueFor() for it --
 * 0 for `imageEnabled tinyint(1) NOT NULL DEFAULT 1`. The next capture then
 * failed with "Image is not enabled", while the OpenAPI document promised a
 * default of 1.
 *
 * A field the body does send keeps its value, even "0". A field whose
 * DEFAULT is what save() would write anyway is left alone, so save()'s own
 * fill-ins (createdBy, createdTime) still run.
 *
 * DB-free: _applyCreateFields() only sets values on the object.
 *
 * Usage: php tests/api-create-honors-column-defaults.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('api-create-defaults');
FogTestHarness::fakeDb();

use FOG\Items\Image;
use FOG\Router\Route;

$t = new FogChecks();

$apply = new \ReflectionMethod(Route::class, '_applyCreateFields');
$apply->setAccessible(true);
$classVars = new \ReflectionMethod(Route::class, '_classVars');
$classVars->setAccessible(true);
$vars = $classVars->invoke(null, 'Image');

// The body a client sends when it relies on the documented defaults.
$image = new Image();
$apply->invoke(null, $image, 'image', $vars, (object)['name' => 'x', 'path' => 'x']);
$t->check(
    'isEnabled omitted: the column DEFAULT 1, not 0',
    '1' === (string)$image->get('isEnabled')
);
$t->check(
    'toReplicate omitted: the column DEFAULT 1, not 0',
    '1' === (string)$image->get('toReplicate')
);
$t->check(
    'createdBy omitted: left empty for save() to fill',
    '' === (string)$image->get('createdBy')
);

// An explicit value always wins over the default.
$image = new Image();
$apply->invoke(
    null,
    $image,
    'image',
    $vars,
    (object)['name' => 'x', 'path' => 'x', 'isEnabled' => 0]
);
$t->check(
    'isEnabled sent as 0: stays 0',
    '0' === (string)$image->get('isEnabled')
);

// user.api is the exception: DEFAULT 1 would grant API access to a user
// created without the field, so an omitted value stays off.
$user = new FOG\Items\User();
$apply->invoke(
    null,
    $user,
    'user',
    $classVars->invoke(null, 'User'),
    (object)['name' => 'x']
);
$t->check(
    'user.api omitted: not granted by the DEFAULT',
    '1' !== (string)$user->get('api')
);

$t->finish();
