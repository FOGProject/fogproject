<?php
/**
 * An agent can be asked to broadcast for a neighbor, and for nothing else.
 *
 * Design 0011. A magic packet is unauthenticated by construction -- that is
 * the whole protocol -- so every control has to be on WHO MAY ASK and WHAT
 * MAY BE ASKED FOR, and both of those live on this side. What is checked
 * here is exactly that:
 *
 * - The block carries host ids and MACs and NO DESTINATION. An agent that
 *   could be given an address to send to would be a UDP reflector for
 *   whoever could feed it one, and "only the server can feed it one today"
 *   is not a property worth relying on.
 * - A target is a row in `hosts` and its MACs are that host's own rows.
 *   There is no path from an arbitrary MAC to the wire.
 * - A host may only report on a wake it was actually ASKED to send. This is
 *   the one item report whose id is another host's, so without the pending
 *   row any enrolled agent could write a result against any host.
 * - PENDING MACs are excluded, which `Group::wakeOnLAN()` already does and
 *   `Host::wakeOnLAN()` does not: a MAC nobody has accepted is not one to
 *   ask the fleet to shout at.
 * - The relay is OFF until an install turns it on. It asks one customer
 *   machine to put traffic on the network for another, which is opted into
 *   rather than discovered after an upgrade.
 *
 * DB-free: the harness's fake connection stands in.
 *
 * Usage: php tests/agent-wake-relay.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-wake-relay');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Call a protected static on WakeRelay.
 *
 * @param string $name the method
 * @param array  $args the arguments
 *
 * @return mixed
 */
function wr($name, array $args = [])
{
    $m = new \ReflectionMethod(\FOG\Agent\WakeRelay::class, $name);
    $m->setAccessible(true);

    return $m->invokeArgs(null, $args);
}

/**
 * Call a protected static on NetworkFacts.
 *
 * @param string $name the method
 * @param array  $args the arguments
 *
 * @return mixed
 */
function nf($name, array $args = [])
{
    $m = new \ReflectionMethod(\FOG\Agent\NetworkFacts::class, $name);
    $m->setAccessible(true);

    return $m->invokeArgs(null, $args);
}

// ------------------------------------------------- the columns are mapped

foreach ([
    [\FOG\Items\HostNetwork::class, ['hostID', 'name', 'mac', 'ipv4', 'prefix',
        'network', 'broadcast', 'up', 'wireless', 'observedAt']],
    [\FOG\Items\AgentWake::class, ['targetID', 'senderID', 'requestedAt',
        'expiresAt', 'status', 'packets', 'detail', 'reportedAt',
        'requestedBy']]
] as [$class, $fields]) {
    $p = new \ReflectionProperty($class, 'databaseFields');
    $p->setAccessible(true);
    $mapped = array_keys((array)$p->getValue(new $class()));
    foreach ($fields as $field) {
        $t->check(
            sprintf('%s maps %s', basename(str_replace('\\', '/', $class)), $field),
            in_array($field, $mapped, true)
        );
    }
}

// ------------------------------------------------ the link math is the DB's

// The agent computes these too, and this side recomputes them anyway. A
// host that could claim a network address it is not on would be a host that
// could join any link's relay group it liked.
foreach ([
    ['10.255.20.7', 24, '10.255.20.0', '10.255.20.255'],
    ['192.168.1.66', 26, '192.168.1.64', '192.168.1.127'],
    ['172.16.4.9', 16, '172.16.0.0', '172.16.255.255'],
    ['10.0.0.5', 8, '10.0.0.0', '10.255.255.255'],
] as [$ip, $prefix, $network, $broadcast]) {
    $long = nf('ipToLong', [$ip]);
    $t->check(
        sprintf('%s/%d is on %s', $ip, $prefix, $network),
        $network === nf('networkFor', [$long, $prefix]),
        (string)nf('networkFor', [$long, $prefix])
    );
    $t->check(
        sprintf('%s/%d broadcasts to %s', $ip, $prefix, $broadcast),
        $broadcast === nf('broadcastFor', [$long, $prefix]),
        (string)nf('broadcastFor', [$long, $prefix])
    );
}

// A /31 is a point-to-point pair (RFC 3021) and a /32 is a host route.
// Sending the all-ones address on a /31 names the PEER, not the link.
foreach ([31, 32] as $prefix) {
    $t->check(
        sprintf('a /%d has no broadcast address', $prefix),
        '' === nf('broadcastFor', [nf('ipToLong', ['10.0.0.1']), $prefix])
    );
}

// A /0 is spelled out rather than shifted: `-1 << 32` is undefined across
// platforms and PHP hands back -1, which would put every host in the estate
// on one link.
$t->check(
    'a /0 masks nothing rather than everything',
    '0.0.0.0' === nf('networkFor', [nf('ipToLong', ['10.255.20.7']), 0]),
    (string)nf('networkFor', [nf('ipToLong', ['10.255.20.7']), 0])
);

// ip2long alone accepts shortened forms no interface reports.
foreach (['10.1', '10.255.20', 'not-an-address', '', '999.1.1.1'] as $bad) {
    $t->check(
        sprintf('%s is not an address', '' === $bad ? '(empty)' : $bad),
        null === nf('ipToLong', [$bad])
    );
}

// -------------------------------------------- a reported block is cleaned

$clean = (new \ReflectionMethod(
    \FOG\Agent\NetworkFacts::class,
    'clean'
));
$clean->setAccessible(true);

$rows = $clean->invoke(null, [
    // The address is believed; the network and broadcast are NOT. This host
    // claims to be on a link it is not on.
    ['name' => 'eno1', 'mac' => 'AA:BB:CC:DD:EE:FF', 'ipv4' => '10.255.20.7',
        'prefix' => 24, 'network' => '10.9.9.0', 'broadcast' => '10.9.9.255',
        'up' => true, 'wireless' => false],
    // Same interface and address twice, as a host with a duplicated row
    // would send it: the unique index would otherwise roll back the whole
    // poll mid-insert.
    ['name' => 'eno1', 'mac' => 'AA:BB:CC:DD:EE:FF', 'ipv4' => '10.255.20.7',
        'prefix' => 24, 'network' => '10.9.9.0', 'broadcast' => '10.9.9.255',
        'up' => true, 'wireless' => false],
    // No name, no address, an impossible prefix: each dropped.
    ['name' => '', 'ipv4' => '10.0.0.1', 'prefix' => 24],
    ['name' => 'eno2', 'ipv4' => 'nonsense', 'prefix' => 24],
    ['name' => 'eno3', 'ipv4' => '10.0.0.1', 'prefix' => 99],
    ['name' => 'eno4', 'ipv4' => '10.0.0.1', 'prefix' => -1],
    'not even an array'
]);

$t->check('one row survives the cleaning', 1 === count($rows), (string)count($rows));
$row = array_shift($rows);
$t->check(
    'the network is RECOMPUTED, not taken from the host',
    '10.255.20.0' === $row['network'],
    (string)$row['network']
);
$t->check(
    'and so is the broadcast, so a host cannot name a link it is not on',
    '10.255.20.255' === $row['broadcast'],
    (string)$row['broadcast']
);
$t->check(
    'the MAC is folded to lower case, the way hostMAC stores it',
    'aa:bb:cc:dd:ee:ff' === $row['mac'],
    (string)$row['mac']
);
$t->check('a reported flag survives', 1 === $row['up']);

// ------------------------------------------------------ the statuses

$t->check(
    'sent is a status an agent may report',
    in_array(\FOG\Agent\WakeRelay::STATUS_SENT, \FOG\Agent\WakeRelay::STATUSES, true)
);
// The server's own bookkeeping words are NOT things an agent may claim.
// Without this an agent could report itself `expired` and take a request
// off the board that nobody sent.
foreach ([
    \FOG\Agent\WakeRelay::STATUS_PENDING,
    \FOG\Agent\WakeRelay::STATUS_EXPIRED
] as $internal) {
    $t->check(
        sprintf('an agent may not report %s', $internal),
        !in_array($internal, \FOG\Agent\WakeRelay::STATUSES, true)
    );
}

// ------------------------------------------------------- the block shape

// The one that matters. Whatever else changes, an agent must never be told
// an address.
$forbidden = ['ip', 'address', 'addr', 'broadcast', 'destination', 'dst',
    'host', 'target_ip', 'port'];
$block = ['targets' => [['id' => 41, 'macs' => ['00:11:22:33:44:55']]]];
$t->check(
    'the wake block names no destination at all',
    [] === array_intersect($forbidden, array_keys($block['targets'][0]))
);
$t->check(
    'a target is a host id and its MACs, and nothing else',
    ['id', 'macs'] === array_keys($block['targets'][0])
);

// ------------------------------------------------------ the registration

$t->check(
    'wake is gated on the EXISTING powermanagement module, not a new switch',
    'powermanagement' === (\FOG\Agent\State::CAPABILITIES['wake'] ?? null),
    (string)(\FOG\Agent\State::CAPABILITIES['wake'] ?? '')
);
$t->check(
    'a wake result rides the item half of the result route',
    \FOG\Agent\WakeRelay::class === (\FOG\Agent\State::ITEM_REPORTS['wake'] ?? null)
);
$t->check(
    'the interfaces are a fact kind, not a route of their own',
    \FOG\Agent\NetworkFacts::class === (\FOG\Agent\State::FACT_REPORTS['network'] ?? null)
);

// ------------------------------------------------------- the relay is off

$t->check(
    'the relay setting is off in a fresh schema step',
    false !== strpos(
        file_get_contents(__DIR__ . '/../packages/web/commons/schema.php'),
        "'FOG_AGENT_WAKE_RELAY_ENABLED','This setting defines if FOG may ask "
    )
);

// -------------------------------------------------- the ceilings are ours

$t->check(
    'the target ceiling is a constant here, not a number an agent is told',
    is_int(\FOG\Agent\WakeRelay::MAX_TARGETS)
        && \FOG\Agent\WakeRelay::MAX_TARGETS > 0
);
$t->check(
    'more than one neighbor is asked, so one going to sleep is not fatal',
    \FOG\Agent\WakeRelay::MAX_SENDERS > 1,
    (string)\FOG\Agent\WakeRelay::MAX_SENDERS
);
$t->check(
    'a request expires, so a wake is never a standing instruction',
    \FOG\Agent\WakeRelay::TTL > 0 && \FOG\Agent\WakeRelay::TTL <= 3600,
    (string)\FOG\Agent\WakeRelay::TTL
);
$t->check(
    'a sender has to have checked in recently enough to be awake',
    \FOG\Agent\WakeRelay::AWAKE_WITHIN > 0
        && \FOG\Agent\WakeRelay::AWAKE_WITHIN <= 3600,
    (string)\FOG\Agent\WakeRelay::AWAKE_WITHIN
);

// ------------------------------------------- the sender query is the design

$sql = (function () {
    $file = file_get_contents(
        __DIR__ . '/../packages/web/src/Agent/WakeRelay.php'
    );
    $start = strpos($file, 'protected static function senders');
    return substr($file, $start, strpos($file, 'protected static function macsFor') - $start);
})();

foreach ([
    ['the same network', '`mine`.`hnNetwork` = `theirs`.`hnNetwork`'],
    ['AND the same prefix -- a /16 and a /24 are not one link',
        '`mine`.`hnPrefix` = `theirs`.`hnPrefix`'],
    ['the interface is up', '`mine`.`hnUp` = 1'],
    ['the link has a broadcast address at all', "`mine`.`hnBroadcast` <> ''"],
    ['not wireless -- an AP will not bridge a broadcast to a sleeping station',
        '`mine`.`hnWireless` = 0'],
    ['the sender has checked in recently enough to be awake',
        '`hostAgentCheckin` >= :fresh'],
    ['and it is not the sleeping machine itself',
        '`mine`.`hnHostID` <> :target2']
] as [$what, $needle]) {
    $t->check(
        'a candidate sender is chosen by: ' . $what,
        false !== strpos($sql, $needle)
    );
}

// ------------------------------------------ pending MACs stay off the wire

$macs = (function () {
    $file = file_get_contents(
        __DIR__ . '/../packages/web/src/Agent/WakeRelay.php'
    );
    $start = strpos($file, 'protected static function macsFor');
    return substr($file, $start, 1400);
})();
$t->check(
    'a pending MAC is never broadcast at -- Group::wakeOnLAN already '
        . 'filters these and Host::wakeOnLAN does not',
    false !== strpos($macs, "'pending' => [0, '']")
);

// --------------------------------- the existing path is not replaced by it

$host = file_get_contents(
    __DIR__ . '/../packages/web/src/Items/Host.php'
);
$t->check(
    'Host::wakeOnLAN still fans out to the storage nodes first',
    1 === preg_match(
        '/function wakeOnLAN\(\)\s*\{\s*self::wakeUp\(\$this->getMyMacs\(\)\);/',
        $host
    )
);
$t->check(
    'and the relay is additional to it',
    false !== strpos($host, 'WakeRelay::request($this,')
);

exit($t->finish());
