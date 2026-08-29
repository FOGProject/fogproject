<?php
/**
 * The ICMP echo probe: packet construction and reply matching.
 *
 * These are the two halves of an echo ping that fail SILENTLY, which is why
 * they are worth pinning without a network:
 *
 *   1. The checksum. A packet with a wrong checksum is discarded by the
 *      target's kernel with no ICMP error and nothing on the wire coming
 *      back. That is byte-for-byte indistinguishable from a host that is
 *      switched off, so a mutation here does not break the ping -- it turns
 *      the whole fleet permanently offline, which is exactly the failure the
 *      ICMP probe was added to end.
 *
 *   2. Reply matching. A RAW socket receives EVERY ICMP packet the machine
 *      sees, including replies to other processes' pings. Two traps:
 *        - the IP header comes with it and its length is IHL x 4, not a
 *          fixed 20. Assume 20 and a reply carrying IP options is read eight
 *          bytes early, so type/id/seq are all garbage.
 *        - somebody else's reply must be rejected on identifier, or its
 *          sequence number gets credited to whichever of OUR hosts happens
 *          to hold that sequence -- an offline host reported up because a
 *          cron job elsewhere on the box pinged something.
 *      Neither shows up in testing on a quiet machine with no IP options.
 *
 * Also pinned: echoBatch()'s "absent, not false" contract, and that the
 * service composes the two probes the way that contract requires. A host the
 * echo could not reach must fall through to TCP; if it were recorded false
 * instead, an ICMP-filtered fleet would report every host offline while the
 * TCP probe that would have answered correctly never ran.
 *
 * No sockets are opened. Every check here is arithmetic on byte strings or
 * on input validation that happens before any socket call.
 *
 * Usage: php tests/ping-icmp-echo.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Net\Ping;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('ping-icmp-echo');

$failures = [];
$checks = 0;

function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

/**
 * The internet checksum, written independently of the implementation.
 *
 * Deliberately NOT a copy of Ping::_checksum() -- a test that reimplements
 * the code it tests passes any mutation applied to both. This is the RFC
 * 1071 description worked straight through: sum the 16-bit words, fold the
 * carries back in, complement.
 */
function refChecksum($buf)
{
    if (strlen($buf) % 2) {
        $buf .= "\x00";
    }
    $sum = 0;
    for ($i = 0; $i < strlen($buf); $i += 2) {
        $sum += (ord($buf[$i]) << 8) | ord($buf[$i + 1]);
    }
    while ($sum > 0xffff) {
        $sum = ($sum & 0xffff) + ($sum >> 16);
    }

    return (~$sum) & 0xffff;
}

// ---------------------------------------------------------------------
// 1. echoPacket() builds a well-formed echo request.
// ---------------------------------------------------------------------

$pkt = Ping::echoPacket(0x1234, 7);

check(
    'an echo request is 8 header bytes plus the payload',
    strlen($pkt) === 8 + strlen('FOGPING'),
    $failures,
    $checks
);
check(
    'type is 8 (echo request), code 0',
    8 === ord($pkt[0]) && 0 === ord($pkt[1]),
    $failures,
    $checks
);

$hdr = unpack('Ctype/Ccode/nchecksum/nident/nsequence', substr($pkt, 0, 8));
check(
    'the identifier survives into the packet',
    0x1234 === $hdr['ident'],
    $failures,
    $checks
);
check(
    'the sequence number survives into the packet',
    7 === $hdr['sequence'],
    $failures,
    $checks
);

// The self-verifying property of the internet checksum: sum a buffer that
// already CARRIES its checksum and you get zero. This is what the receiving
// kernel actually does, so it is the check that matters most -- it holds
// whatever the payload is and needs no golden value.
check(
    'the packet checksums to zero over itself, as a receiver computes it',
    0 === refChecksum($pkt),
    $failures,
    $checks
);

// And the value itself, against the independent reference, so a mutation
// that zeroes the field is caught too -- a zero checksum field also makes
// the sum-over-self test above pass for some payloads.
check(
    'the checksum field matches an independent RFC 1071 computation',
    $hdr['checksum'] === refChecksum(pack('C2n3', 8, 0, 0, 0x1234, 7) . 'FOGPING'),
    $failures,
    $checks
);
check(
    'the checksum field is not left zero',
    0 !== $hdr['checksum'],
    $failures,
    $checks
);

// Odd-length payload: 'FOGPING' is seven bytes, so the whole packet is 15
// and the odd-length padding branch is the one that runs above. Pin that
// changing the sequence changes the checksum, i.e. it is computed rather
// than constant.
$pkt2 = Ping::echoPacket(0x1234, 8);
$hdr2 = unpack('Ctype/Ccode/nchecksum/nident/nsequence', substr($pkt2, 0, 8));
check(
    'the checksum tracks the sequence number',
    $hdr2['checksum'] !== $hdr['checksum'],
    $failures,
    $checks
);
check(
    'the second packet also checksums to zero over itself',
    0 === refChecksum($pkt2),
    $failures,
    $checks
);

// Identifier and sequence are 16-bit fields. A caller handing over a larger
// int must not corrupt the neighboring field. Note that pack('n') truncates
// to 16 bits by itself, so REMOVING the explicit mask in echoPacket() is an
// equivalent mutation and these checks will not catch it -- what they pin is
// that the value round-trips, which is what reply matching depends on. A
// mutation that alters the sequence (rather than merely not masking it) is
// caught.
$wide = Ping::echoPacket(0x11234, 0x10007);
$wideHdr = unpack('Ctype/Ccode/nchecksum/nident/nsequence', substr($wide, 0, 8));
check(
    'an oversized identifier is masked to 16 bits',
    0x1234 === $wideHdr['ident'],
    $failures,
    $checks
);
check(
    'an oversized sequence is masked to 16 bits',
    7 === $wideHdr['sequence'],
    $failures,
    $checks
);
check(
    'a masked packet still carries a valid checksum',
    0 === refChecksum($wide),
    $failures,
    $checks
);

// ---------------------------------------------------------------------
// 2. echoReplySeq() -- DGRAM.
// ---------------------------------------------------------------------

/**
 * An ICMP echo REPLY (type 0) with a correct checksum.
 */
function reply($id, $seq)
{
    $sum = refChecksum(pack('C2n3', 0, 0, 0, $id, $seq) . 'FOGPING');

    return pack('C2n3', 0, 0, $sum, $id, $seq) . 'FOGPING';
}

check(
    'a DGRAM reply yields its sequence number',
    42 === Ping::echoReplySeq(reply(0x1234, 42), false, 0x1234),
    $failures,
    $checks
);
// The kernel OWNS the identifier on a ping socket and rewrites it on the way
// out, so ours is not what comes back. Checking it here would reject every
// reply and the DGRAM path would silently never work -- falling through to
// RAW, which needs root, on machines that did not need it.
check(
    'a DGRAM reply is accepted whatever identifier it carries',
    42 === Ping::echoReplySeq(reply(0x9999, 42), false, 0x1234),
    $failures,
    $checks
);
check(
    'sequence zero is a real answer, not a falsy no-answer',
    0 === Ping::echoReplySeq(reply(0x1234, 0), false, 0x1234),
    $failures,
    $checks
);
check(
    'an echo REQUEST seen on the socket is not a reply',
    null === Ping::echoReplySeq(Ping::echoPacket(0x1234, 3), false, 0x1234),
    $failures,
    $checks
);
check(
    'a destination-unreachable message (type 3) is not a reply',
    null === Ping::echoReplySeq(
        pack('C2n3', 3, 1, 0, 0, 0) . 'x',
        false,
        0x1234
    ),
    $failures,
    $checks
);
check(
    'a truncated datagram yields null rather than a garbage sequence',
    null === Ping::echoReplySeq(substr(reply(0x1234, 42), 0, 6), false, 0x1234),
    $failures,
    $checks
);
check(
    'an empty datagram yields null',
    null === Ping::echoReplySeq('', false, 0x1234),
    $failures,
    $checks
);

// ---------------------------------------------------------------------
// 3. echoReplySeq() -- RAW, where the IP header and the identifier matter.
// ---------------------------------------------------------------------

/**
 * A reply as a RAW socket delivers it: IP header first.
 *
 * @param int $ihl header length in 32-bit words -- 5 is the usual 20 bytes,
 *                 anything larger means IP options are present
 */
function rawReply($id, $seq, $ihl = 5)
{
    $ip = chr(0x40 | ($ihl & 0x0f)) . str_repeat("\x00", ($ihl * 4) - 1);

    return $ip . reply($id, $seq);
}

check(
    'a RAW reply with a 20-byte IP header yields its sequence',
    42 === Ping::echoReplySeq(rawReply(0x1234, 42, 5), true, 0x1234),
    $failures,
    $checks
);
// The trap. A fixed 20-byte skip reads four bytes early here, landing on the
// tail of the options rather than the ICMP type, so the type test rejects a
// perfectly good reply and the host reads as offline.
check(
    'a RAW reply carrying IP options is read at IHL x 4, not a fixed 20',
    42 === Ping::echoReplySeq(rawReply(0x1234, 42, 6), true, 0x1234),
    $failures,
    $checks
);
check(
    'a RAW reply with a maximum-length IP header is still read correctly',
    42 === Ping::echoReplySeq(rawReply(0x1234, 42, 15), true, 0x1234),
    $failures,
    $checks
);
// The other trap. A RAW socket sees another process' ping replies; crediting
// one to a host of ours reports an offline machine as up.
check(
    "another process' reply is rejected on the identifier",
    null === Ping::echoReplySeq(rawReply(0x9999, 42, 5), true, 0x1234),
    $failures,
    $checks
);
check(
    'our own outbound request, seen on the RAW socket, is not a reply',
    null === Ping::echoReplySeq(
        rawReply(0x1234, 42, 5)[0]
        . substr(rawReply(0x1234, 42, 5), 1, 19)
        . Ping::echoPacket(0x1234, 42),
        true,
        0x1234
    ),
    $failures,
    $checks
);
check(
    'a RAW buffer too short to hold its own IP header yields null',
    null === Ping::echoReplySeq(substr(rawReply(0x1234, 42, 5), 0, 12), true, 0x1234),
    $failures,
    $checks
);
check(
    'an empty RAW buffer yields null',
    null === Ping::echoReplySeq('', true, 0x1234),
    $failures,
    $checks
);

// ---------------------------------------------------------------------
// 4. echoBatch() input handling -- reached before any socket is opened.
// ---------------------------------------------------------------------

check(
    'an empty target list returns an empty result without opening a socket',
    [] === Ping::echoBatch([], 2),
    $failures,
    $checks
);
// These pin echoBatch()'s CONTRACT -- an unprobeable target comes back
// absent, never false -- not the input filter itself. Dropping the
// FILTER_FLAG_IPV4 flag is an equivalent mutation: a v6 address that got
// through fails socket_sendto() and is dropped from the pending map, so the
// return value is identical. Returning false for those IS caught.
//
// IPv6 is a different protocol entirely (ICMPv6, protocol 58, different
// message types). Attempting it on an AF_INET socket cannot work, so a v6
// target must come back UNANSWERED -- which sends it to the TCP path, and
// executeBatch() does support AF_INET6.
check(
    'an IPv6 target is left unanswered so the TCP probe gets it',
    [] === Ping::echoBatch(['a' => '2001:db8::1'], 2),
    $failures,
    $checks
);
check(
    'a hostname is left unanswered rather than probed as an address',
    [] === Ping::echoBatch(['a' => 'not.an.ip'], 2),
    $failures,
    $checks
);
check(
    'an empty address is left unanswered',
    [] === Ping::echoBatch(['a' => '', 'b' => '   '], 2),
    $failures,
    $checks
);

// ---------------------------------------------------------------------
// 5. The service composes the two probes correctly.
//
// Source lints, and honest about being so: the compose lives inside
// FOGPingHosts' cycle, which needs a database, a settings table and a live
// network to reach. What these pin is the SHAPE the "absent, not false"
// contract above depends on -- a change that breaks either one turns a
// working ICMP probe into a fleet-wide false negative, with no error.
// ---------------------------------------------------------------------

$svc = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Service/PingHosts.php'
);

check(
    'the TCP batch gets everything the echo did not positively answer',
    false !== strpos($svc, 'array_diff_key($targets, $results)'),
    $failures,
    $checks
);
check(
    'an echo answer is recorded as code 0 with method icmp',
    false !== strpos($svc, 'Ping::METHOD_ICMP;'),
    $failures,
    $checks
);
// Code and method travel together as one group key. Grouping on the code
// alone would write one group's method onto the other's rows -- saying the
// TCP port answered for a host the echo reached, and vice versa.
check(
    'update groups are keyed on the code AND the method',
    false !== strpos($svc, "\$byResult[\$code . '|' . \$how][]"),
    $failures,
    $checks
);
check(
    'ICMP is skipped when no echo socket can be opened',
    false !== strpos($svc, 'Ping::echoAvailable()'),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
