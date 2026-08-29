<?php
/**
 * Counts, for every relationship in commons/schema-constraints.php, the rows
 * that would make `ALTER TABLE ... ADD FOREIGN KEY` fail with error 1452.
 *
 * WHY: MySQL validates existing data when a constraint is added. After a
 * decade in which nothing enforced anything, the count is the deliverable --
 * it decides whether a relationship is a one-line ALTER or a cleanup step
 * with a report attached.
 *
 * Three numbers per relationship, because they need three different fixes:
 *
 *   orphans   child rows whose value names a parent row that is not there.
 *             Data to delete or repoint before the constraint can exist.
 *   sentinel  rows holding the column's "no reference" value, almost always
 *             0. A foreign key accepts NULL for that and nothing else, so
 *             these need the column made nullable and the zeros converted --
 *             a column change and a PHP change, not a cleanup.
 *   types     whether the child and parent column types differ. InnoDB
 *             requires an exact match, collation included; a mismatch is a
 *             MODIFY on the child before the constraint.
 *
 * The `action` each relationship carries in the map is printed too, so the
 * scan and the decision can be read side by side rather than in two files.
 *
 * Reads the database out of a FOG web tree's config.class.php, or takes an
 * explicit DSN for a lab copy. Read-only: it issues nothing but SELECT.
 *
 * Usage:
 *   php bin/fk-orphan-scan.php <web-root>
 *   php bin/fk-orphan-scan.php --host=H --port=P --db=D --user=U --pass=P
 *   ... [--format=table|csv] [--all]
 *
 * --all also prints relationships with nothing wrong, which is most of them.
 *
 * PHP version 7.4+
 *
 * @category Schema
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$opts = [];
$web = '';
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--') === 0) {
        [$k, $v] = array_pad(explode('=', substr($arg, 2), 2), 2, '1');
        $opts[$k] = $v;
    } else {
        $web = $arg;
    }
}

if ($web !== '') {
    foreach (['/commons/config.class.php', '/lib/fog/config.class.php'] as $rel) {
        if (!file_exists($web . $rel)) {
            continue;
        }
        $src = file_get_contents($web . $rel);
        foreach (['HOST' => 'host', 'NAME' => 'db', 'USERNAME' => 'user', 'PASSWORD' => 'pass'] as $k => $o) {
            if (preg_match("/define\(\s*'DATABASE_$k'\s*,\s*'(.*?)'\s*\)/s", $src, $m)) {
                $opts[$o] = $m[1];
            }
        }
        break;
    }
}

$host = $opts['host'] ?? '127.0.0.1';
$port = (int)($opts['port'] ?? 3306);
$db = $opts['db'] ?? '';
if ($db === '') {
    fwrite(STDERR, "no database: give a web root or --db\n");
    exit(2);
}

try {
    $pdo = new \PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s', $host, $port, $db),
        $opts['user'] ?? '',
        $opts['pass'] ?? '',
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
} catch (\PDOException $e) {
    fwrite(STDERR, 'connect failed: ' . $e->getMessage() . "\n");
    exit(1);
}

/**
 * Column type as InnoDB compares it for constraint compatibility.
 *
 * @param \PDO   $pdo   open connection
 * @param string $db    schema name
 * @param string $table table name
 * @param string $col   column name
 *
 * @return array|null
 */
function colType(\PDO $pdo, string $db, string $table, string $col): ?array
{
    static $cache = [];
    $key = "$table.$col";
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $st = $pdo->prepare(
        'SELECT column_type, is_nullable, collation_name'
        . ' FROM information_schema.columns'
        . ' WHERE table_schema = ? AND table_name = ? AND column_name = ?'
    );
    $st->execute([$db, $table, $col]);
    $row = $st->fetch(\PDO::FETCH_ASSOC);
    return $cache[$key] = ($row === false ? null : $row);
}

$rows = [];
$candidates = require __DIR__ . '/../packages/web/commons/schema-constraints.php';

foreach ($candidates as $c) {
    $child = $c['child'];
    $col = $c['column'];
    $parent = $c['parent'];
    $pcol = $c['pcolumn'];
    $class = $c['class'];
    $sentinel = $c['sentinel'] ?? null;

    if ($class === 'poly') {
        $rows[] = [$child, $col, $parent, $c['action'], '-', '-', '-', 'polymorphic'];
        continue;
    }

    $ct = colType($pdo, $db, $child, $col);
    $pt = colType($pdo, $db, $parent, $pcol);
    if ($ct === null || $pt === null) {
        $rows[] = [$child, $col, "$parent.$pcol", $c['action'], '-', '-', '-', 'absent'];
        continue;
    }

    $note = [];
    if ($ct['column_type'] !== $pt['column_type']) {
        $note[] = 'type ' . $ct['column_type'] . ' vs ' . $pt['column_type'];
    }
    if (($ct['collation_name'] ?? null) !== ($pt['collation_name'] ?? null)) {
        $note[] = 'collation ' . ($ct['collation_name'] ?? 'n/a')
            . ' vs ' . ($pt['collation_name'] ?? 'n/a');
    }
    if ($sentinel !== null && $ct['is_nullable'] === 'NO') {
        $note[] = 'NOT NULL, sentinel ' . var_export($sentinel, true);
    }

    // Orphans: a value that names no parent row. The sentinel is excluded
    // and counted separately -- it is a different defect with a different fix.
    $where = sprintf('c.`%s` IS NOT NULL', $col);
    if ($sentinel !== null) {
        $where .= sprintf(' AND c.`%s` <> %s', $col, $pdo->quote((string)$sentinel));
    }
    $sql = sprintf(
        'SELECT COUNT(*) AS n, COUNT(DISTINCT c.`%s`) AS d FROM `%s` c'
        . ' LEFT JOIN `%s` p ON p.`%s` = c.`%s`'
        . ' WHERE %s AND p.`%s` IS NULL',
        $col,
        $child,
        $parent,
        $pcol,
        $col,
        $where,
        $pcol
    );
    $o = $pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);

    $sent = 0;
    if ($sentinel !== null) {
        $st = $pdo->prepare(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = ?', $child, $col)
        );
        $st->execute([$sentinel]);
        $sent = (int)$st->fetchColumn();
    }

    $total = (int)$pdo->query(
        sprintf('SELECT COUNT(*) FROM `%s`', $child)
    )->fetchColumn();

    $rows[] = [
        $child,
        $col,
        "$parent.$pcol",
        $c['action'],
        (string)$total,
        $o['n'] . ($o['n'] > 0 ? ' (' . $o['d'] . ' ids)' : ''),
        $sentinel === null ? '-' : (string)$sent,
        implode('; ', $note),
    ];
}

$head = ['child', 'column', 'parent', 'action', 'rows', 'orphans', 'sentinel', 'notes'];
$show = array_key_exists('all', $opts);
$out = array_filter(
    $rows,
    static function ($r) use ($show) {
        return $show || $r[5] !== '0' || ($r[6] !== '-' && $r[6] !== '0') || $r[7] !== '';
    }
);

if (($opts['format'] ?? 'table') === 'csv') {
    $fh = fopen('php://output', 'w');
    fputcsv($fh, $head);
    foreach ($out as $r) {
        fputcsv($fh, $r);
    }
    exit(0);
}

$w = [];
foreach (array_merge([$head], $out) as $r) {
    foreach ($r as $i => $v) {
        $w[$i] = max($w[$i] ?? 0, strlen((string)$v));
    }
}
$line = static function ($r) use ($w) {
    $p = [];
    foreach ($r as $i => $v) {
        $p[] = str_pad((string)$v, $w[$i]);
    }
    return rtrim(implode('  ', $p));
};
echo "database: $db  candidates: " . count($rows)
    . "  shown: " . count($out) . "\n\n";
echo $line($head) . "\n";
echo str_repeat('-', array_sum($w) + 2 * count($w)) . "\n";
foreach ($out as $r) {
    echo $line($r) . "\n";
}
