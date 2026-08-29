<?php
/**
 * Route::userpref() must not read "no value" as "clear this".
 *
 * An empty value is the documented way to clear a preference, which makes an
 * absent one dangerous: PHP populates $_POST only for a form-encoded POST, so
 * a form-encoded PUT reaches the handler with nothing in either spelling. Read
 * as an empty value, that DELETES the preference it was sent to update and
 * answers 200 having done it -- the "route arm returns 200 having done
 * nothing" shape, except it destroys something on the way.
 *
 * Found by exercising the route against a lab database: the store path was
 * driven through the form-field spelling, nothing was stored, and the caller
 * was told it succeeded.
 *
 * Also pinned here: the acting user comes from the session, never from the
 * request. The whole feature is per-user storage, so a request-supplied user
 * id would let anyone read or overwrite anyone else's preferences.
 *
 * Usage: php tests/userpref-route-absent-is-not-empty.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$route = file_get_contents($root . '/packages/web/src/Router/Route.php');

$fails = [];

// Isolate the handler: a match elsewhere in this class must not stand in for
// the thing being tested.
if (!preg_match(
    '#public static function userpref\(\$key\).*?\n    \}\n#s',
    $route,
    $m
)) {
    echo "FAIL: Route::userpref() not found -- the gate cannot see its subject\n";
    exit(1);
}
$handler = $m[0];

// ------------------------------------------------------- absent vs empty

// A flag that records whether a value was actually supplied, set in each
// reading branch. Without it the two cases are indistinguishable.
if (!preg_match('#\$supplied\s*=\s*false;#', $handler)) {
    $fails[] = 'userpref() does not track whether a value was supplied; an '
        . 'absent value is then indistinguishable from an empty one';
}

// The form-field spelling must be tested for presence, not cast. A bare
// (string)filter_input() turns a missing field into '', which is the clear
// instruction.
if (!preg_match(
    '#null\s*!==\s*filter_input\(\s*INPUT_POST,\s*\'value\'\s*\)#',
    $handler
)) {
    $fails[] = 'userpref() does not test filter_input(INPUT_POST, \'value\') '
        . 'against null; a missing form field casts to \'\' and clears';
}

// The JSON branch must use array_key_exists, so an explicit {"value":""} is
// still recognized as supplied -- clearing by request stays possible.
if (!preg_match('#array_key_exists\(\s*\'value\',\s*\$decoded\s*\)#', $handler)) {
    $fails[] = 'userpref() does not use array_key_exists on the JSON body; an '
        . 'explicit empty value would stop counting as supplied';
}

// Nothing supplied must refuse, and refuse before reaching store().
if (!preg_match(
    '#if\s*\(!\$supplied\)\s*\{\s*self::sendResponse\(\s*HTTPResponseCodes::HTTP_BAD_REQUEST#s',
    $handler
)) {
    $fails[] = 'userpref() does not answer 400 when no value was supplied';
}

// The refusal must come BEFORE the store call, or it refuses after the
// damage. Compared by position within the isolated handler.
$refusal = strpos($handler, 'if (!$supplied)');
$store = strpos($handler, "->store(");
if (false !== $refusal && false !== $store && $refusal > $store) {
    $fails[] = 'userpref() refuses a bodyless store only after calling store()';
}

// DELETE is the spelling that clears without a body, and must stay exempt
// from the guard -- otherwise clearing becomes impossible.
if (!preg_match('#if\s*\(\$method\s*!==\s*\'DELETE\'\)#', $handler)) {
    $fails[] = 'userpref() no longer exempts DELETE from needing a body';
}

// ------------------------------------------------------------- self-scope

// The acting user is read from the session. Any read of a request-supplied
// user id here would be a cross-user read or write.
if (!preg_match('#\$userID\s*=\s*\(int\)self::\$FOGUser->get\(\'id\'\);#', $handler)) {
    $fails[] = 'userpref() does not take the acting user from the session';
}
foreach (['userID', 'user_id', 'uid'] as $param) {
    if (preg_match(
        '#filter_input\(\s*INPUT_(GET|POST),\s*\'' . $param . '\'#i',
        $handler
    )) {
        $fails[] = "userpref() reads '$param' from the request; the acting "
            . 'user must come from the session only';
    }
}

// The same two properties on the list route.
if (!preg_match(
    '#public static function userprefs\(\).*?\n    \}\n#s',
    $route,
    $m
)) {
    $fails[] = 'Route::userprefs() not found';
} elseif (!preg_match('#\(int\)self::\$FOGUser->get\(\'id\'\)#', $m[0])) {
    $fails[] = 'userprefs() does not take the acting user from the session';
}

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: userpref() distinguishes an absent value from an empty one, and "
    . "scopes to the session user\n";
exit(0);
