<?php
/**
 * Every model key ending in "id" is either a real integer column or is
 * declared a string.
 *
 * FOGController::save() infers "this is a foreign key" from the key's name
 * ending in "id". That is right for the 95 genuine foreign keys in the tree
 * and wrong for a string identifier spelled the same way, and the two failure
 * modes differ:
 *
 *   required -> throws "Required database field is empty: <key>" while the
 *               field holds a perfectly good value, so the object can never
 *               be saved at all (OIDC's clientId, which is why this exists);
 *   optional -> filter_var fails, the value is replaced with 0 and written,
 *               so the real value is lost with no error anywhere
 *               (Inventory's sysuuid -> iSystemUUID, a varchar(255)).
 *
 * The name is a proxy for the column's type, so this test checks the type
 * itself. Column types come from commons/schema-expected.php, which is
 * generated from the live schema -- so a model that gains such a field, or a
 * column that changes type under a model, fails here rather than in the
 * field.
 *
 * The check runs both ways on purpose. An entry in $databaseFieldsNotInt
 * naming an integer column is also a failure: a stale exclusion turns the
 * foreign-key validation off for a field that wants it, which is the same
 * class of silent hole in the other direction.
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
 * @return array|null the quoted strings found, or null if not declared
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
        return null;
    }
    $open = strpos($clean, '[', $at);
    if ($open === false) {
        return null;
    }
    $depth = 0;
    $len = strlen($clean);
    for ($i = $open; $i < $len; $i++) {
        if ($clean[$i] === '[') {
            $depth++;
        } elseif ($clean[$i] === ']') {
            $depth--;
            if ($depth === 0) {
                $body = substr($clean, $open, $i - $open + 1);
                preg_match_all('#[\'"]([^\'"]+)[\'"]#', $body, $m);
                return $m[1];
            }
        }
    }
    return null;
}

/**
 * Reads the key => column pairs of a $databaseFields literal.
 *
 * @param string $src the file's source
 *
 * @return array key => column, empty when the model declares none
 */
function fieldMap($src)
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
    $at = strpos($clean, '$databaseFields');
    if ($at === false) {
        return [];
    }
    $open = strpos($clean, '[', $at);
    if ($open === false) {
        return [];
    }
    $depth = 0;
    $len = strlen($clean);
    for ($i = $open; $i < $len; $i++) {
        if ($clean[$i] === '[') {
            $depth++;
        } elseif ($clean[$i] === ']') {
            $depth--;
            if ($depth === 0) {
                $body = substr($clean, $open, $i - $open + 1);
                preg_match_all(
                    '#[\'"]([A-Za-z0-9_]+)[\'"]\s*=>\s*[\'"]([A-Za-z0-9_]+)[\'"]#',
                    $body,
                    $m
                );
                return array_combine($m[1], $m[2]);
            }
        }
    }
    return [];
}

/**
 * Whether a column definition is an integer type.
 *
 * @param string $type the column definition from the manifest
 *
 * @return bool
 */
function isIntColumn($type)
{
    return (bool)preg_match('#^\s*(tiny|small|medium|big)?int\b#i', $type);
}

$manifest = require $web . '/commons/schema-expected.php';
$tables = $manifest['tables'] ?? [];
if (count($tables) < 1) {
    $failures[] = 'schema-expected.php produced no tables';
}

// ---------------------------------------------------------------
// 1. The base class still consults the model's list.
// ---------------------------------------------------------------
$controller = file_get_contents($web . '/lib/fog/fogcontroller.class.php');
$squashed = preg_replace('#\s+#', '', $controller);

$gate = 'protected$databaseFieldsNotInt=[];';
$checks++;
if (strpos($squashed, $gate) === false) {
    $failures[] = 'FOGController no longer declares $databaseFieldsNotInt';
}

$build = 'foreach($this->databaseFieldsNotIntas$strKey){'
    . '$notInt[$this->key($strKey)]=true;}';
$checks++;
if (strpos($squashed, $build) === false) {
    $failures[] = 'FOGController::save() no longer builds the $notInt lookup';
}

$branch = "elseif(strtolower(substr(\$key,-2))==='id'&&!isset(\$notInt[\$key]))";
$checks++;
if (strpos($squashed, $branch) === false) {
    $failures[] = 'the foreign-key branch no longer excludes $notInt keys';
}

// The lookup has to be built before the loop that reads it, or every model
// silently loses the exclusion.
$checks++;
if (strpos($squashed, $build) !== false
    && strpos($squashed, $branch) !== false
    && strpos($squashed, $build) > strpos($squashed, $branch)
) {
    $failures[] = '$notInt is built after the branch that reads it';
}

// ---------------------------------------------------------------
// 1b. isValid() honors the same opt-out -- the half GH-1153 missed.
//
// save() and isValid() each carry their own copy of the "ends in id, so
// it is a foreign key" inference, and only save()'s was fixed. The half
// that was missed is the quieter one: the object saves, and then every
// later isValid() says no. An enabled OIDC provider whose clientId is
// "fog-web" simply had no login button, with nothing logged anywhere.
// ---------------------------------------------------------------
$at = strpos($squashed, 'publicfunctionisValid(){');
$checks++;
if ($at === false) {
    $failures[] = 'FOGController::isValid() not found';
    $isValidBody = '';
} else {
    $end = strpos($squashed, 'publicfunction', $at + 20);
    $isValidBody = substr(
        $squashed,
        $at,
        $end === false ? null : $end - $at
    );
}

$checks++;
if ($isValidBody !== '' && strpos($isValidBody, $build) === false) {
    $failures[] = 'FOGController::isValid() does not build the $notInt lookup';
}

$vBranch = "if(strtolower(substr(\$key,-2))==='id'&&!isset(\$notInt[\$key]))";
$checks++;
if ($isValidBody !== '' && strpos($isValidBody, $vBranch) === false) {
    $failures[] = 'isValid()\'s foreign-key branch does not exclude $notInt keys';
}

$checks++;
if ($isValidBody !== ''
    && strpos($isValidBody, $build) !== false
    && strpos($isValidBody, $vBranch) !== false
    && strpos($isValidBody, $build) > strpos($isValidBody, $vBranch)
) {
    $failures[] = 'isValid() builds $notInt after the branch that reads it';
}

// ---------------------------------------------------------------
// 2. Every model's *id keys match their column's actual type.
// ---------------------------------------------------------------
$iter = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($web . '/lib')
);
foreach ($iter as $file) {
    if (substr($file->getFilename(), -10) !== '.class.php') {
        continue;
    }
    $src = file_get_contents($file->getPathname());
    if (!preg_match('#\$databaseTable\s*=\s*[\'"]([A-Za-z0-9_]+)[\'"]#', $src, $t)) {
        continue;
    }
    $table = $t[1];
    if (!isset($tables[$table]['columns'])) {
        // Plugin tables are not in the core manifest. The plugin repo's own
        // suite covers those; skipping here beats guessing at their types.
        continue;
    }
    $columns = $tables[$table]['columns'];
    $fields = fieldMap($src);
    $notInt = array_map('strtolower', (array)notIntArrayLiteral($src, 'databaseFieldsNotInt'));
    $name = $file->getFilename();

    foreach ($fields as $key => $column) {
        if (strtolower($key) === 'id') {
            continue;
        }
        if (strtolower(substr($key, -2)) !== 'id') {
            continue;
        }
        if (!isset($columns[$column])) {
            continue;
        }
        $declared = in_array(strtolower($key), $notInt, true);
        $isInt = isIntColumn($columns[$column]);
        $checks++;
        if (!$isInt && !$declared) {
            $failures[] = sprintf(
                '%s: %s -> %s is %s, not an integer, and is not in '
                . '$databaseFieldsNotInt -- save() will reject it or '
                . 'overwrite it with 0',
                $name,
                $key,
                $column,
                trim($columns[$column])
            );
        }
        if ($isInt && $declared) {
            $failures[] = sprintf(
                '%s: %s -> %s IS an integer column but is listed in '
                . '$databaseFieldsNotInt, so its foreign-key validation '
                . 'is switched off for nothing',
                $name,
                $key,
                $column
            );
        }
    }
}

// ---------------------------------------------------------------
// 3. The known casualty is actually declared.
// ---------------------------------------------------------------
$inventory = file_get_contents($web . '/lib/fog/inventory.class.php');
$checks++;
if (!in_array(
    'sysuuid',
    array_map('strtolower', (array)notIntArrayLiteral($inventory, 'databaseFieldsNotInt')),
    true
)) {
    $failures[] = 'Inventory does not declare sysuuid in $databaseFieldsNotInt';
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
