<?php
/**
 * FOS reports what a Secure Boot enrollment actually did.
 *
 * PHP version 7.4+
 *
 * @category Secure_Boot_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Base\FOGBase;
use FOG\Base\FOGCore;
use FOG\Boot\SecureBootState;
use FOG\Items\TaskType;
use FOG\Managers\HostManager;
use FOG\Router\Route;

/**
 * FOS reports what a Secure Boot enrollment actually did.
 *
 * WHY THIS EXISTS AND IS NOT PART OF THE COMPLETION POST
 *
 * fog.enrollsb has three exits and every one of them currently ends with the
 * same argument-free `. /bin/fog.nonimgcomplete`:
 *
 *   - the certificate was already trusted, and nothing was enrolled;
 *   - the machine was in Setup Mode, so `db` was written and it IS enrolled;
 *   - a MOK request was STAGED, and the machine is not enrolled until a human
 *     confirms it at the MokManager screen on the next boot.
 *
 * From the server those are indistinguishable: the task completes, three
 * times over, saying nothing. Recording "enrolled" on the third case would be
 * a lie an administrator acts on -- they turn Secure Boot on in firmware and
 * the machine stops booting, because nothing ever confirmed the key.
 *
 * So the outcome is reported explicitly, by the only party that knows it,
 * before the completion POST. A separate endpoint rather than extra fields on
 * Post_Wipe.php because that script is shared by every non-imaging task and
 * has no business growing an enrollment vocabulary.
 *
 * ADVISORY, like every other machine entry point. This writes the editable
 * half of the ledger (schema step 377), which an administrator can type into
 * a form anyway, so the report adds no authority a technician did not already
 * have. Nothing reads any of it as a security control -- see ADR 0029.
 *
 * It is still narrowed to what it needs to be, because "advisory" is not a
 * reason to accept a write from anywhere: the host must have a Secure Boot
 * enrollment task actually in flight. That is true by construction when FOS
 * calls this -- the task is what booted it -- and it means an arbitrary
 * caller who knows a MAC cannot stamp an enrollment onto a host that was never
 * asked to enroll.
 *
 * Answers 200 with a plain-text body in every case, including refusal. A
 * non-2xx from a FOS script is invisible to the caller and reads as a
 * transport failure, which is the one outcome that produces a bug report
 * about the wrong thing.
 *
 * @category Secure_Boot_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/*
 * A machine entry point: the caller is a booting NIC, FOS, the fog-client
 * or a storage node, none of which can present a credential. Declared per
 * file rather than inferred from the absence of one -- see
 * Authorization::_hasNoPrincipal() for what it licenses and why the
 * distinction matters.
 */
define('FOG_MACHINE_REQUEST', true);

require '../commons/base.inc.php';

header('Content-type: text/plain');

/**
 * Say what happened and stop.
 *
 * @param string $msg the single-word result
 *
 * @return void
 */
$done = function ($msg) {
    echo '##' . $msg . "\n";
    exit;
};

$mac = filter_input(INPUT_POST, 'mac') ?: '';
FOGCore::getHostItem(false, false, true, false, false, $mac);
$Host = FOGBase::$Host;
if (!$Host || !$Host->isValid()) {
    $done('unknownhost');
}

// The task must be in flight. getTaskType() is read off the host's current
// task rather than trusting anything posted, so a caller cannot nominate the
// task type it would like to be reporting for.
$Task = $Host->get('task');
if (!$Task->isValid()
    || TaskType::ENROLL_SECUREBOOT != $Task->get('typeID')
) {
    $done('notasking');
}

// The words FOS may send, mapped onto the words the column stores. Only these.
//
// 'trusted' stays 'trusted' rather than collapsing into 'db': it means the
// machine already trusted this certificate when the task ran, and nothing
// observed how it got there. It is an enrollment fact worth keeping -- it is
// the one result that would otherwise be lost entirely -- but it is not a
// record of this server enrolling anything.
//
// 'mok' becomes 'mok-pending' because that is what FOS actually did: it staged
// a request. 'mok' is reserved for a human ticking the box after confirming it
// at the MokManager screen, which is the only point at which it is true.
$result = strtolower(trim((string)filter_input(INPUT_POST, 'result')));
$map = [
    'db' => 'db',
    'trusted' => 'trusted',
    'mok' => 'mok-pending',
    'staged' => 'mok-pending',
];
if (!array_key_exists($result, $map)) {
    $done('badresult');
}

// Shape-checked for the same reason the form is, and through the same
// normalizer: this value's whole purpose is to be compared against the
// server's own fingerprint, and a comparison against something that is not a
// SHA-256 can only ever be false -- silently, and looking exactly like "this
// host trusts an older certificate".
$cert = SecureBootState::normalizeFingerprint(
    filter_input(INPUT_POST, 'cert')
);
if ('' === $cert) {
    $done('badcert');
}

(new HostManager())->update(
    ['id' => $Host->get('id')],
    '',
    [
        'sbenrolled' => FOGBase::niceDate()->format('Y-m-d H:i:s'),
        'sbenrollvia' => $map[$result],
        'sbenrollcert' => $cert,
    ]
);

// FOS also re-reads its own firmware state on the way through, and it is a
// better observation than iPXE's: FOS can tell "the variables would not mount"
// from "there is no such variable". Same column, same vocabulary, so the two
// reporters cannot disagree about what a word means. Optional -- an older FOS
// that does not send it leaves the reported state exactly as the last PXE boot
// left it, rather than blanking it.
$state = strtolower(trim((string)filter_input(INPUT_POST, 'sbstate')));
if (SecureBootState::isKnown($state)
    && SecureBootState::UNKNOWN !== $state
) {
    (new HostManager())->update(
        ['id' => $Host->get('id')],
        '',
        [
            'sbstate' => $state,
            'sbstatetime' => FOGBase::niceDate()->format('Y-m-d H:i:s'),
        ]
    );
}

$done('ok');
