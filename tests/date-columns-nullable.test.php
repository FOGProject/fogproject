<?php
/**
 * Every date column a model can leave empty must be able to hold NULL.
 *
 * FOGController::save() writes '' for any unset optional field whose key does
 * not end in "id". A date column cannot hold '': the server refuses it under a
 * strict sql_mode and coerces it to '0000-00-00 00:00:00' without one. FOG only
 * ever sees the second, because PDODB::_connect() issues
 * `SET SESSION sql_mode=''` on every connection -- which is why 83 of 86 hosts
 * on the maintainer's own server carry a zero hostLastDeploy on a MariaDB
 * whose configuration forbids that value (GH-1245).
 *
 * save() now writes a real NULL for an empty date field, decided from the
 * column's TYPE rather than its name. That only helps if the column can take
 * one: an explicit NULL into a NOT NULL column errors under a strict mode and
 * is coerced back to the zero date without one, so the fix would be a silent
 * no-op on exactly the servers it was written for.
 *
 * So the invariant is a joint one, across a model and the schema, and neither
 * file can be checked alone:
 *
 *   a date column, whose model field is optional and not auto-filled
 *   ->  the column is nullable, or the server supplies a default
 *
 * A new date field added to a model without a nullable column fails here
 * rather than quietly accumulating zero dates for a release. Column types come
 * from commons/schema-expected.php, which is generated from a real server, so
 * a column that changes type under a model fails here too.
 *
 * Two exemptions, both deliberate and both listed below rather than inferred:
 * snapinTasks.stCheckinDate and userAuths.uaExpireDate declare
 * DEFAULT current_timestamp(), so the server writes a real value instead of a
 * zero date. uaExpireDate must stay NOT NULL -- UserAuth::reapExpired() deletes
 * on `uaExpireDate` < now, and NULL satisfies no comparison, so a nullable
 * expiry would turn a token that fails safe into one that is never reaped.
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
 * Source with comments removed, so a commented-out entry cannot satisfy a
 * check and a commented-out literal cannot fail one.
 *
 * @param string $src the file's source
 *
 * @return string
 */
function dcStripComments($src)
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
    return $clean;
}

/**
 * The bracketed body of an array literal assigned to a property.
 *
 * @param string $clean comment-stripped source
 * @param string $prop  the property name, without the dollar
 *
 * @return string the body including its brackets, or '' when not declared
 */
function dcArrayBody($clean, $prop)
{
    $at = strpos($clean, '$' . $prop);
    if (false === $at) {
        return '';
    }
    $open = strpos($clean, '[', $at);
    if (false === $open) {
        return '';
    }
    $depth = 0;
    $len = strlen($clean);
    for ($i = $open; $i < $len; $i++) {
        if ('[' === $clean[$i]) {
            $depth++;
        } elseif (']' === $clean[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($clean, $open, $i - $open + 1);
            }
        }
    }
    return '';
}

$manifest = require $web . '/commons/schema-expected.php';
$tables = $manifest['tables'] ?? [];
if (count($tables) < 1) {
    fwrite(STDERR, "FAIL: schema-expected.php produced no tables\n");
    exit(1);
}

// Keys save()'s own switch fills in, so they can never reach the empty arm.
$autoFilled = ['createdtime'];

// Columns allowed to stay NOT NULL, with the reason. See the docblock.
$exempt = [
    'snapintasks.stcheckindate' => 'DEFAULT current_timestamp()',
    'userauths.uaexpiredate' => 'DEFAULT current_timestamp(); reapExpired() '
        . 'compares on it, and NULL satisfies no comparison',
];

$models = glob($web . '/src/*/*.php');
$dateFields = 0;
foreach ($models as $file) {
    $clean = dcStripComments(file_get_contents($file));

    if (!preg_match('#\$databaseTable\s*=\s*[\'"]([A-Za-z0-9_]+)[\'"]#', $clean, $m)) {
        continue;
    }
    $table = $m[1];
    if (!isset($tables[$table]['columns'])) {
        continue;
    }
    $columns = $tables[$table]['columns'];

    preg_match_all(
        '#[\'"]([A-Za-z0-9_]+)[\'"]\s*=>\s*[\'"]([A-Za-z0-9_]+)[\'"]#',
        dcArrayBody($clean, 'databaseFields'),
        $fm
    );
    if (!count($fm[1])) {
        continue;
    }
    preg_match_all(
        '#[\'"]([A-Za-z0-9_]+)[\'"]#',
        dcArrayBody($clean, 'databaseFieldsRequired'),
        $rm
    );
    $required = array_map('strtolower', $rm[1]);

    foreach (array_combine($fm[1], $fm[2]) as $key => $column) {
        if (!isset($columns[$column])) {
            continue;
        }
        $type = $columns[$column];
        if (!preg_match('#^\s*(datetime|timestamp|date)\b#i', $type)) {
            continue;
        }
        $dateFields++;
        $checks++;

        if (in_array(strtolower($key), $required, true)
            || in_array(strtolower($key), $autoFilled, true)
        ) {
            continue;
        }
        $ref = strtolower($table . '.' . $column);
        if (isset($exempt[$ref])) {
            // An exemption that stopped being true is its own failure.
            if (false === stripos($type, 'default current_timestamp')) {
                $failures[] = sprintf(
                    '%s is exempt for "%s", but its type is now "%s"',
                    $ref,
                    $exempt[$ref],
                    trim($type)
                );
            }
            continue;
        }
        if (false === stripos($type, 'default null')) {
            $failures[] = sprintf(
                '%s -> %s.%s is %s and the model leaves it optional, so '
                . 'save() writes NULL into a column that cannot hold one',
                basename($file),
                $table,
                $column,
                trim($type)
            );
        }
    }
}

// A scan that matched nothing would pass silently.
$checks++;
if ($dateFields < 25) {
    $failures[] = sprintf(
        'only %d date fields found across the models; the scan is not '
        . 'reaching them and would pass vacuously',
        $dateFields
    );
}

// ---------------------------------------------------------------
// The zero date is not a value the code writes or looks for.
// ---------------------------------------------------------------
$allowed = [
    // The one place that defines what an empty date means.
    'packages/web/src/Base/FOGBase.php',
    // Reads BOTH spellings, because an upgraded server carries both until
    // schema step 344 has run.
    'packages/web/src/TaskHandling/TaskQueue.php',
];
$dirs = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($web . '/lib', \FilesystemIterator::SKIP_DOTS)
);
foreach ($dirs as $file) {
    if ('php' !== strtolower($file->getExtension())) {
        continue;
    }
    $path = $file->getPathname();
    $rel = substr($path, strlen($root) + 1);
    if (in_array($rel, $allowed, true) || false !== strpos($rel, '/plugins/')) {
        continue;
    }
    $checks++;
    $clean = dcStripComments(file_get_contents($path));
    if (false !== strpos($clean, '0000-00-00')) {
        $failures[] = sprintf(
            '%s still carries a 0000-00-00 literal; NULL is what "never '
            . 'happened" means now',
            $rel
        );
    }
}

if (count($failures) > 0) {
    echo "FAIL date-columns-nullable (" . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS date-columns-nullable ($checks checks, $dateFields date fields)\n";
exit(0);
