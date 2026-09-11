<?php
/**
 * A model key ending in "id" that is not a foreign key says so.
 *
 * FOGController infers "this is an integer id" from the key's name ending in
 * "id". That is right for every real foreign key in the tree and wrong for a
 * string identifier spelled the same way, and the two failure modes differ:
 *
 *   required -> throws "Required database field is empty: <key>" while the
 *               field holds a perfectly good value, so the object can never
 *               be saved;
 *   optional -> filter_var fails, the value is replaced with 0 and written,
 *               so the real value is lost with no error anywhere.
 *
 * The inference lives in TWO methods -- save() and isValid() -- each with its
 * own copy. Fixing one is the trap: the object saves, and then every later
 * isValid() says no. Both are pinned below.
 *
 * There is no column-type sweep here, unlike the 1.6 test this is ported
 * from. That test reads commons/schema-expected.php, a generated manifest of
 * the live schema; this branch has no such file, and deriving types by regex
 * over commons/schema.php's accumulated ALTER statements gives wrong answers
 * -- it matched a `msState` belonging to a different table when the sweep was
 * run by hand. So the sweep was done once, against a real 1.5.10 install's
 * information_schema, keyed on the (table, column) pairs the models actually
 * declare. It found exactly two, and both are pinned by name below:
 *
 *   inventory.iSystemUUID  varchar(255)
 *   taskLog.taskID         mediumtext
 *
 * All 38 plugin *id columns were checked the same way, from their own
 * Schema::createTable() type lists; every one is INTEGER.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$failures = [];
$checks = 0;

/**
 * Reads a bracketed PHP array literal assigned to a property.
 *
 * Deliberately textual: loading the models would need the autoloader, a
 * database and a booted FOG. Comments are stripped through the tokenizer
 * first so a commented-out entry cannot satisfy the test.
 *
 * @param string $src  the file's source
 * @param string $prop the property name, without the dollar
 *
 * @return array the quoted strings found, empty when not declared
 */
function notIntArrayLiteral($src, $prop)
{
    $clean = '';
    foreach (token_get_all($src) as $token) {
        if (is_array($token)
            && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)
        ) {
            continue;
        }
        $clean .= is_array($token) ? $token[1] : $token;
    }
    $at = strpos($clean, '$' . $prop);
    if ($at === false) {
        return [];
    }
    // array( ... ) on this branch, but accept [ ... ] too so a future
    // restyling does not read as a missing declaration.
    $paren = strpos($clean, '(', $at);
    $brack = strpos($clean, '[', $at);
    if ($paren === false && $brack === false) {
        return [];
    }
    if ($paren === false || ($brack !== false && $brack < $paren)) {
        $open = $brack;
        $openChar = '[';
        $closeChar = ']';
    } else {
        $open = $paren;
        $openChar = '(';
        $closeChar = ')';
    }
    $depth = 0;
    $len = strlen($clean);
    for ($i = $open; $i < $len; $i++) {
        if ($clean[$i] === $openChar) {
            $depth++;
        } elseif ($clean[$i] === $closeChar) {
            $depth--;
            if ($depth === 0) {
                $body = substr($clean, $open, $i - $open + 1);
                preg_match_all('#[\'"]([^\'"]+)[\'"]#', $body, $m);
                return array_map('strtolower', $m[1]);
            }
        }
    }
    return [];
}

// ---------------------------------------------------------------
// 1. The base class declares the opt-out and both methods honor it.
// ---------------------------------------------------------------
$controller = file_get_contents($web . '/lib/fog/fogcontroller.class.php');
$squashed = preg_replace('#\s+#', '', $controller);

$checks++;
if (strpos($squashed, 'protected$databaseFieldsNotInt=array();') === false
    && strpos($squashed, 'protected$databaseFieldsNotInt=[];') === false
) {
    $failures[] = 'FOGController does not declare $databaseFieldsNotInt';
}

$build = 'foreach($this->databaseFieldsNotIntas$strKey){'
    . '$notInt[$this->key($strKey)]=true;}';

// save(): the branch is an elseif, because the primary key is handled first.
$saveBranch = "elseif(strtolower(substr(\$key,-2))==='id'&&!isset(\$notInt[\$key]))";
$checks++;
if (strpos($squashed, $saveBranch) === false) {
    $failures[] = 'save()\'s foreign-key branch does not exclude $notInt keys';
}

// isValid(): scoped to the method, because the file holds two of everything.
$at = strpos($squashed, 'publicfunctionisValid(){');
$checks++;
if ($at === false) {
    $failures[] = 'FOGController::isValid() not found';
    $body = '';
} else {
    $end = strpos($squashed, 'publicfunction', $at + 20);
    $body = substr($squashed, $at, $end === false ? null : $end - $at);
}

$validBranch = "if(strtolower(substr(\$key,-2))==='id'&&!isset(\$notInt[\$key]))";
$checks++;
if ($body !== '' && strpos($body, $validBranch) === false) {
    $failures[] = 'isValid()\'s foreign-key branch does not exclude $notInt keys';
}

// Each method needs its own lookup, built before the branch that reads it.
$checks++;
if ($body !== '' && strpos($body, $build) === false) {
    $failures[] = 'isValid() does not build the $notInt lookup';
}
$checks++;
if ($body !== ''
    && strpos($body, $build) !== false
    && strpos($body, $validBranch) !== false
    && strpos($body, $build) > strpos($body, $validBranch)
) {
    $failures[] = 'isValid() builds $notInt after the branch that reads it';
}
$checks++;
if (substr_count($squashed, $build) < 2) {
    $failures[] = 'the $notInt lookup is built fewer than twice -- save() and '
        . 'isValid() each need their own';
}
// save()'s copy has to come before save()'s branch, same as isValid()'s.
$checks++;
if (strpos($squashed, $build) !== false
    && strpos($squashed, $saveBranch) !== false
    && strpos($squashed, $build) > strpos($squashed, $saveBranch)
) {
    $failures[] = 'save() builds $notInt after the branch that reads it';
}

// ---------------------------------------------------------------
// 2. The two casualties are declared.
// ---------------------------------------------------------------
$known = [
    'inventory.class.php' => ['sysuuid', 'iSystemUUID is varchar(255)'],
    'tasklog.class.php' => ['taskid', 'taskLog.taskID is mediumtext'],
];
foreach ($known as $file => $want) {
    list($key, $why) = $want;
    $checks++;
    $src = file_get_contents($web . '/lib/fog/' . $file);
    if (!in_array($key, notIntArrayLiteral($src, 'databaseFieldsNotInt'), true)) {
        $failures[] = sprintf(
            '%s does not declare %s in $databaseFieldsNotInt (%s)',
            $file,
            $key,
            $why
        );
    }
}

if (count($failures) > 0) {
    echo "FAIL databasefields-notint (" . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS databasefields-notint ($checks checks)\n";
exit(0);
