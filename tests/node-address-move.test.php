<?php
/**
 * Forum 18327: create_update_node.php's nodeIp branch moves only this
 * server's own storage node record to its new address.
 *
 * The installer posts the old and the new address when checkServerAddress
 * finds the server moved. moveNodeAddress() must:
 *
 *   - change only the row at the old address, and only its address and NIC,
 *   - refuse a new address another node already holds (409), because every
 *     lookup in that file finds a node by its address,
 *   - refuse anything that is not an IP address (400),
 *   - allow old == new, which is a NIC-only move, without the clash check
 *     finding the node's own row.
 *
 * This runs the REAL function body, lifted from the shipped file, against
 * fakes for the manager, the router and the model, and asserts on the saves
 * it makes.
 *
 * Usage: php tests/node-address-move.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$src = __DIR__ . '/../packages/web/maintenance/create_update_node.php';
$code = file_get_contents($src);
if (false === $code) {
    fwrite(STDERR, "cannot read $src\n");
    exit(1);
}
$start = strpos($code, 'function moveNodeAddress(');
if (false === $start) {
    fwrite(STDERR, "moveNodeAddress() not found in $src\n");
    exit(1);
}
$open = strpos($code, '{', $start);
$depth = 0;
$end = null;
for ($i = $open, $n = strlen($code); $i < $n; $i++) {
    if ('{' === $code[$i]) {
        $depth++;
    } elseif ('}' === $code[$i]) {
        $depth--;
        if (0 === $depth) {
            $end = $i;
            break;
        }
    }
}
if (null === $end) {
    fwrite(STDERR, "unbalanced braces in moveNodeAddress()\n");
    exit(1);
}

/**
 * The storage node table: id => [ip, interface].
 */
$GLOBALS['rows'] = [];
/**
 * Every save: [id, fields set].
 */
$GLOBALS['saves'] = [];

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
class StorageNodeManager
{
    public function exists($val, $id, $field)
    {
        foreach ($GLOBALS['rows'] as $rid => $row) {
            if ($row[$field] === $val && $rid !== $id) {
                return true;
            }
        }
        return false;
    }
}
class Route
{
    public static function getList($class, $where)
    {
        $out = [];
        foreach ($GLOBALS['rows'] as $rid => $row) {
            if ($row['ip'] === $where['ip']) {
                $out[] = (object) ['id' => $rid];
            }
        }
        return $out;
    }
}
class StorageNode
{
    private $id;
    private $set = [];
    public function __construct($id)
    {
        $this->id = $id;
    }
    public function set($key, $val)
    {
        $this->set[$key] = $val;
        return $this;
    }
    public function save()
    {
        $GLOBALS['saves'][] = [$this->id, $this->set];
        return $this;
    }
}
// phpcs:enable

// The branch's wiring, which the lifted body cannot show: the old address,
// then the decoded new one, then the NIC, and the status sent back.
$branch = substr($code, (int) strpos($code, "isset(\$_POST['nodeIp'])"));
$wired = (bool) preg_match(
    '/moveNodeAddress\(\s*\$stripped\[\'oldip\'\][^,]*,\s*\$ip,\s*\$stripped\[\'interface\'\]/',
    $branch
) && false !== strpos($branch, 'http_response_code($status)');

// Lifted as a closure, so the shipped name is never declared here.
$move = eval(
    'return '
    . str_replace(
        'function moveNodeAddress(',
        'function (',
        substr($code, $start, $end - $start + 1)
    )
    . ';'
);
if (!$move instanceof \Closure) {
    fwrite(STDERR, "moveNodeAddress() did not lift as a closure\n");
    exit(1);
}

/**
 * Prints one result and returns it.
 *
 * @param string $label What the assertion proves.
 * @param bool   $cond  Whether it held.
 *
 * @return bool
 */
function check($label, $cond)
{
    echo ($cond ? '  ok    ' : '  FAIL  '), $label, "\n";
    return $cond;
}
$results = [];
$results[] = check(
    'the nodeIp branch passes oldip, ip and interface, and sends the status',
    $wired
);
function reset_table()
{
    $GLOBALS['rows'] = [
        1 => ['ip' => '10.0.0.5', 'interface' => 'eth0'],
        2 => ['ip' => '10.0.0.6', 'interface' => 'eth0'],
    ];
    $GLOBALS['saves'] = [];
}

reset_table();
[$status, $reason] = $move('10.0.0.5', '10.0.0.9', 'ens18');
$results[] = check('a free new address returns 200 with no reason', 200 === $status && '' === $reason);
$results[] = check(
    'only the row at the old address is saved, with the new address and NIC',
    [[1, ['ip' => '10.0.0.9', 'interface' => 'ens18']]] === $GLOBALS['saves']
);

reset_table();
$move('10.0.0.5', '10.0.0.9', '');
$results[] = check(
    'an empty NIC leaves the NIC as it is',
    [[1, ['ip' => '10.0.0.9']]] === $GLOBALS['saves']
);

reset_table();
[$status, $reason] = $move('10.0.0.5', '10.0.0.6', 'ens18');
$results[] = check('an address another node holds returns 409', 409 === $status);
$results[] = check('the 409 reason names the address', false !== strpos($reason, '10.0.0.6'));
$results[] = check('the 409 reason carries no markup', false === strpos($reason, '<'));
$results[] = check('a refused move saves nothing', [] === $GLOBALS['saves']);

reset_table();
[$status] = $move('10.0.0.5', '10.0.0.5', 'ens18');
$results[] = check('a NIC-only move is not refused by the node\'s own row', 200 === $status);
$results[] = check(
    'a NIC-only move saves the new NIC',
    [[1, ['ip' => '10.0.0.5', 'interface' => 'ens18']]] === $GLOBALS['saves']
);

reset_table();
[$status] = $move('10.0.0.5', 'not-an-ip', 'ens18');
$results[] = check('a new value that is not an address returns 400', 400 === $status);
[$status] = $move('', '10.0.0.9', 'ens18');
$results[] = check('an empty old address returns 400', 400 === $status);
[$status] = $move('10.0.0.5', '10.0.0.9', 'eth0;reboot');
$results[] = check('an interface that is not an interface name returns 400', 400 === $status);
$results[] = check('a 400 saves nothing', [] === $GLOBALS['saves']);

reset_table();
[$status] = $move('10.0.0.50', '10.0.0.9', 'ens18');
$results[] = check('an old address no node holds is a no-op 200', 200 === $status && [] === $GLOBALS['saves']);

$failed = count(array_filter($results, function ($r) {
    return !$r;
}));
printf("\n%d passed, %d failed\n", count($results) - $failed, $failed);
exit($failed > 0 ? 1 : 0);
