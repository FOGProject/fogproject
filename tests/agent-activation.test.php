<?php
/**
 * A product key goes only where it can be used.
 *
 * Design 0016 (fog-agent). The activation block carries the host's Windows
 * product key to the agent, so every case where the agent could not use it
 * must keep it off the wire:
 *
 * - no key, or a key that is not a valid product key;
 * - a request from a seat that is not Windows (a dual-boot host's Linux
 *   seat gets no key: fog-agent#53);
 * - a host with no Windows seat.
 *
 * And the one case that sends it must send the hyphenated 29-character form
 * the agent expects, decoded from whatever encoding the legacy row holds.
 *
 * DB-free: the harness's fake connection answers the seat read.
 *
 * Usage: php tests/agent-activation.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-activation');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

// A Microsoft-published KMS client setup key: public, and real in shape.
const KEY = 'W269N-WFGWX-YVC9B-4J6C9-T83GX';

/**
 * State::_activation() for a host with this key, polled by a seat of this OS.
 *
 * @param string      $key the stored hostProductKey
 * @param string|null $os  the OS of the seat that polls, or null for a
 *                         request that is no seat's (the admin path)
 * @param array       $has the OSes the host has a seat for, on the admin path
 *
 * @return array|null
 */
function activation($key, $os, array $has = [])
{
    global $db;
    $db->responder = function ($sql, $params) use ($has) {
        if (false === stripos($sql, 'hostAgents')) {
            return null;
        }
        return in_array($params['os'] ?? '', $has, true) ? [['haID' => 1]] : [];
    };
    \FOG\Agent\Seat::$current = null === $os
        ? null
        : ['hostID' => 7, 'os' => $os, 'fingerprint' => 'f'];
    $Host = new \FOG\Items\Host();
    $Host->set('id', 7)->set('productKey', $key);
    $m = new \ReflectionMethod(\FOG\Agent\State::class, '_activation');
    $m->setAccessible(true);
    $out = $m->invoke(null, $Host);
    $db->responder = null;
    \FOG\Agent\Seat::$current = null;

    return $out;
}

$caps = new \ReflectionClassConstant(\FOG\Agent\State::class, 'CAPABILITIES');
$t->check(
    'activation is gated on the EXISTING hostnamechanger module, where the '
        . 'legacy client did it',
    'hostnamechanger' === ($caps->getValue()['activation'] ?? null)
);

$t->check(
    'a Windows host with a key gets it, hyphenated',
    ['key' => KEY] === activation(KEY, 'windows')
);
$t->check(
    'a key stored without hyphens is sent hyphenated',
    ['key' => KEY] === activation(str_replace('-', '', KEY), 'windows')
);
$t->check(
    'a base64-encoded legacy row is decoded before it is sent',
    ['key' => KEY] === activation(base64_encode(KEY), 'windows')
);
$t->check('no key sends no block', null === activation('', 'windows'));
$t->check(
    'an invalid key sends no block',
    null === activation('AAAAA-AAAAA-AAAAA-AAAAA-AAAAA', 'windows')
);
$t->check('a Linux seat gets no key', null === activation(KEY, 'linux'));
$t->check(
    'a host with no seat gets no key',
    null === activation(KEY, null)
);
$t->check(
    'a host whose only seat is Linux gets no key outside an agent request',
    null === activation(KEY, null, ['linux'])
);
$t->check(
    'a host with a Windows seat has the key outside an agent request',
    ['key' => KEY] === activation(KEY, null, ['linux', 'windows'])
);

$t->finish();
