<?php
/**
 * fog-agent enrollment: decides whether a key may act as a host.
 *
 * PHP version 7.4+
 *
 * @category Enrollment
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Base\FOGCore;
use FOG\Base\SmbiosIdentity;
use FOG\Items\AgentEnrollment;
use FOG\Items\AgentEnrollToken;
use FOG\Items\Host;
use FOG\Items\Inventory;
use FOG\Managers\HostManager;
use FOG\Router\Route;

/**
 * fog-agent enrollment: decides whether a key may act as a host.
 *
 * The wire contract is the agent's docs/design/protocol-v1.md. In one
 * sentence: a stranger presents a firmware identity, a MAC list and a CSR,
 * and nothing is issued until an admin clicks Approve, a valid enrollment
 * token was presented, or this server itself imaged that host recently
 * enough that the deploy counts as the approval.
 *
 * Two questions are kept apart on purpose. WHICH host is this is answered by
 * the SMBIOS tuple and the MACs, the same evidence PXE boot uses, through the
 * same resolver. IS it that host is answered by the certificate this class
 * issues, and only after one of the three approvals. The identity is
 * discoverable by anyone on the network, so it may resolve but never
 * authenticate; that is why a known host with no agent still waits for a
 * click unless a token or a deploy vouches for it -- otherwise anyone able
 * to spoof its firmware values could collect its desired state, which may
 * carry a directory-join credential.
 *
 * Signing follows nodecert.php exactly: the CSR is staged to disk and a
 * root-owned helper signs it through sudo. This class never reads a CA key
 * (ADR 0036).
 *
 * @category Enrollment
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Enrollment extends FOGBase
{
    const PROTOCOL = 1;

    const REASON_UNKNOWN = 'unknown-host';
    const REASON_KNOWN = 'known-host-no-agent';
    const REASON_REBIND = 'rebind';
    const REASON_CONFLICT = 'identity-conflict';
    const REASON_REISSUE = 'reissue';
    const REASON_NO_MAC = 'no-mac';

    const VIA_TOKEN = 'token';
    const VIA_DEPLOY = 'deploy';
    const VIA_ADMIN = 'admin';

    /**
     * Seconds the agent is told to wait before repeating a pending request.
     */
    const RETRY_AFTER = 300;

    /**
     * Agent JSON key => inventory field, in SmbiosIdentity::FIELDS terms.
     *
     * @var array
     */
    const IDENTITY_MAP = [
        'system_uuid' => 'sysuuid',
        'system_serial' => 'sysserial',
        'board_serial' => 'mbserial',
        'chassis_asset' => 'caseasset'
    ];

    /**
     * Handles one enroll request.
     *
     * @param array  $body     the decoded JSON body
     * @param string $remoteIP the caller's address, for the row and the log
     *
     * @return array [int HTTP code, array JSON payload]
     */
    public static function handle(array $body, $remoteIP)
    {
        if ((int)($body['protocol'] ?? 0) !== self::PROTOCOL) {
            return [426, ['status' => 'unsupported', 'reason' => 'protocol']];
        }
        $csr = (string)($body['csr_pem'] ?? '');
        $fingerprint = self::fingerprint($csr);
        if (null === $fingerprint) {
            return [400, ['status' => 'error', 'reason' => 'csr']];
        }
        $identity = is_array($body['identity'] ?? null) ? $body['identity'] : [];
        $macs = self::_macs($identity['macs'] ?? []);
        $token = trim((string)($body['token'] ?? ''));

        $ids = Route::getIds('agentenrollment', ['fingerprint' => $fingerprint], 'id');
        $Row = count($ids) ? new AgentEnrollment((int)array_shift($ids)) : null;
        if ($Row && !$Row->isValid()) {
            $Row = null;
        }
        $now = self::niceDate()->format('Y-m-d H:i:s');

        // A repeat while the answer already exists. The agent polls the same
        // request every few minutes; the row is the memory of what was
        // decided about that key, so the decision is answered from it and
        // never re-derived from the identity, which the caller controls.
        if ($Row) {
            $Row->set('updated', $now)
                ->set('remoteIP', (string)$remoteIP)
                ->set('agentVersion', (string)($body['agent_version'] ?? ''));
            switch ($Row->get('state')) {
                case AgentEnrollment::STATE_DENIED:
                    $Row->save();
                    return [403, ['status' => 'denied', 'reason' => 'admin']];
                case AgentEnrollment::STATE_ISSUED:
                    $cert = (string)$Row->get('cert');
                    if ($cert !== '') {
                        // Approved since the last poll. Hand it over once.
                        $Row->set('cert', '')->save();
                        return [200, self::_issuedPayload($Row, $cert)];
                    }
                    // Same key, certificate already collected: the agent lost
                    // its certificate but kept its key. Not automatic -- an
                    // admin looks at it, the same as a rebind.
                    $Row->set('state', AgentEnrollment::STATE_PENDING)
                        ->set('reason', self::REASON_REISSUE)
                        ->save();
                    self::_audit($Row, 'waiting: ' . self::REASON_REISSUE);
                    return [202, self::_pendingPayload($Row)];
                default:
                    // Still pending. The automatic paths are re-checked: a token
                    // may be valid now, or the deploy that vouches for this host
                    // may have finished since the agent first asked.
                    $Row->save();
                    $via = self::_autoApproval($Row, $token);
                    if ($via) {
                        return self::_issueNow($Row, $via, $remoteIP);
                    }
                    return [202, self::_pendingPayload($Row)];
            }
        }

        // First contact for this key. Resolve the host the way boot does.
        list($hostID, $reason) = self::_resolve($identity, $macs);

        $Row = (new AgentEnrollment())
            ->set('fingerprint', $fingerprint)
            ->set('csr', $csr)
            ->set('identity', json_encode($identity))
            ->set('hostname', (string)($body['hostname'] ?? ''))
            ->set('os', (string)($body['os'] ?? ''))
            ->set('arch', (string)($body['arch'] ?? ''))
            ->set('agentVersion', (string)($body['agent_version'] ?? ''))
            ->set('remoteIP', (string)$remoteIP)
            ->set('hostID', $hostID)
            ->set('reason', $reason)
            ->set('state', AgentEnrollment::STATE_PENDING)
            ->set('cert', '')
            ->set('created', $now)
            ->set('updated', $now);

        if (0 === $hostID && self::REASON_UNKNOWN === $reason) {
            // Nobody has seen this machine. It becomes a pending host, the
            // same thing the current client's auto-registration produces,
            // so the admin sees it in the one place they already look.
            // Needs a MAC: a host without a primary MAC is not a host FOG
            // can address.
            if (empty($macs)) {
                $Row->set('reason', self::REASON_NO_MAC)->save();
                self::_audit($Row, 'waiting: no MAC address reported');
                return [202, self::_pendingPayload($Row)];
            }
            $hostID = self::_createPendingHost($Row, $identity, $macs);
            $Row->set('hostID', $hostID);
        }
        $Row->save();

        $via = self::_autoApproval($Row, $token);
        if ($via) {
            return self::_issueNow($Row, $via, $remoteIP);
        }
        self::_audit($Row, 'waiting: ' . $Row->get('reason'));
        return [202, self::_pendingPayload($Row)];
    }

    /**
     * An admin approves a pending request: the CSR is signed and the host
     * bound. The agent collects the certificate on its next poll.
     *
     * @param int    $id the enrollment row
     * @param string $by the approving user's name, for the row and the audit
     *
     * @throws \RuntimeException with an HTTP code when it cannot be done
     *
     * @return AgentEnrollment
     */
    public static function approve($id, $by)
    {
        $Row = new AgentEnrollment((int)$id);
        if (!$Row->isValid()) {
            throw new \RuntimeException('no such enrollment', 404);
        }
        if (AgentEnrollment::STATE_PENDING !== $Row->get('state')) {
            throw new \RuntimeException('enrollment is not pending', 409);
        }
        if ((int)$Row->get('hostID') < 1) {
            throw new \RuntimeException('enrollment is not bound to a host', 409);
        }
        $cert = self::_issue($Row, self::VIA_ADMIN, (string)$by);
        $Row->set('cert', $cert)->save();
        return $Row;
    }

    /**
     * An admin denies a pending request. The key stays denied: repeats of
     * the same request are answered from the row without re-deciding.
     *
     * @param int    $id the enrollment row
     * @param string $by the denying user's name
     *
     * @throws \RuntimeException with an HTTP code when it cannot be done
     *
     * @return AgentEnrollment
     */
    public static function deny($id, $by)
    {
        $Row = new AgentEnrollment((int)$id);
        if (!$Row->isValid()) {
            throw new \RuntimeException('no such enrollment', 404);
        }
        if (AgentEnrollment::STATE_PENDING !== $Row->get('state')) {
            throw new \RuntimeException('enrollment is not pending', 409);
        }
        $Row->set('state', AgentEnrollment::STATE_DENIED)
            ->set('decided', self::niceDate()->format('Y-m-d H:i:s'))
            ->set('decidedBy', (string)$by)
            ->set('decidedVia', self::VIA_ADMIN)
            ->set('cert', '')
            ->save();
        self::_audit($Row, 'denied by ' . $by, (string)$by);
        return $Row;
    }

    /**
     * The sha256 of the CSR's SubjectPublicKeyInfo, hex. Null when the CSR
     * does not parse or carries no usable key.
     *
     * The public key, not the CSR bytes: the same key in two differently
     * encoded requests must land on the same row, and the certificate a
     * client later presents is matched to the host by this same value.
     *
     * @param string $csrPEM the request
     *
     * @return string|null
     */
    public static function fingerprint($csrPEM)
    {
        if (strpos($csrPEM, '-----BEGIN CERTIFICATE REQUEST-----') === false) {
            return null;
        }
        // Same bytes Principal::verify() hashes out of the issued
        // certificate, so the binding survives from CSR to client cert.
        return Principal::spkiFingerprint(@openssl_csr_get_public_key($csrPEM));
    }

    /**
     * Resolves the host the request is about.
     *
     * Firmware first, under the same setting boot uses (FOG_HOST_IDENTIFY_SMBIOS
     * off means MAC only), then the MAC list. Both answering with different
     * hosts is reported, not guessed at: the firmware's answer is kept, as
     * enforce mode would, and the admin sees the conflict as the reason.
     *
     * @param array $identity the identity block from the request
     * @param array $macs     validated MACs
     *
     * @return array [int hostID, string reason]
     */
    private static function _resolve(array $identity, array $macs)
    {
        $smbiosID = null;
        if ('off' !== FOGCore::getSetting('FOG_HOST_IDENTIFY_SMBIOS')) {
            $ids = [];
            foreach (self::IDENTITY_MAP as $key => $field) {
                $ids[$field] = SmbiosIdentity::canonicalize(
                    (string)($identity[$key] ?? '')
                );
            }
            $smbiosID = HostManager::resolveHostBySmbios($ids);
        }
        $macID = 0;
        if (!empty($macs)) {
            try {
                (new HostManager())->getHostByMacAddresses($macs);
                if (self::$Host->isValid() && !self::$Host->get('pending')) {
                    $macID = (int)self::$Host->get('id');
                }
            } catch (\Exception $e) {
                $macID = 0;
            }
        }
        $hostID = (int)($smbiosID ?: $macID);
        if ($hostID < 1) {
            return [0, self::REASON_UNKNOWN];
        }
        if ($smbiosID && $macID && (int)$smbiosID !== $macID) {
            return [(int)$smbiosID, self::REASON_CONFLICT];
        }
        $Host = new Host($hostID);
        if ('' !== (string)$Host->get('agentFingerprint')) {
            return [$hostID, self::REASON_REBIND];
        }
        return [$hostID, self::REASON_KNOWN];
    }

    /**
     * The two approvals that need no click. Returns the via, or '' when
     * the request has to wait for an admin.
     *
     * @param AgentEnrollment $Row   the request
     * @param string          $token the token presented, if any
     *
     * @return string
     */
    private static function _autoApproval(AgentEnrollment $Row, $token)
    {
        if ('' !== $token && self::_consumeToken($token)) {
            return self::VIA_TOKEN;
        }
        // A deploy vouches only for the host it imaged, and only for a
        // request that is a first binding or a rebind of that host. It never
        // resolves a conflict and never creates a host.
        $hostID = (int)$Row->get('hostID');
        if ($hostID > 0
            && in_array($Row->get('reason'), [self::REASON_KNOWN, self::REASON_REBIND], true)
            && self::_recentDeploy(new Host($hostID))
        ) {
            return self::VIA_DEPLOY;
        }
        return '';
    }

    /**
     * Did this server complete a deploy to the host recently enough?
     *
     * hostLastDeploy is written when an imaging task completes, so it is
     * exactly "this server put an operating system on that machine". The
     * window is FOG_AGENT_ENROLL_DEPLOY_WINDOW hours; 0 turns the path off.
     *
     * @param Host $Host the host
     *
     * @return bool
     */
    private static function _recentDeploy(Host $Host)
    {
        $hours = (int)FOGCore::getSetting('FOG_AGENT_ENROLL_DEPLOY_WINDOW');
        $deployed = (string)$Host->get('deployed');
        if ($hours < 1 || !self::validDate($deployed)) {
            return false;
        }
        $when = strtotime($deployed);
        return $when !== false && (time() - $when) <= $hours * 3600;
    }

    /**
     * Validates a token and consumes one use. Only the hash is stored.
     *
     * @param string $token the token as presented
     *
     * @return bool
     */
    private static function _consumeToken($token)
    {
        $hash = hash('sha256', $token);
        $ids = Route::getIds('agentenrolltoken', ['hash' => $hash], 'id');
        if (!count($ids)) {
            return false;
        }
        $Token = new AgentEnrollToken((int)array_shift($ids));
        if (!$Token->isValid()) {
            return false;
        }
        $expires = (string)$Token->get('expires');
        if (self::validDate($expires) && strtotime($expires) < time()) {
            return false;
        }
        $uses = (int)$Token->get('uses');
        if (0 === $uses) {
            return false;
        }
        if ($uses > 0) {
            $Token->set('uses', $uses - 1)->save();
        }
        return true;
    }

    /**
     * Creates the pending host an unknown machine becomes, with an
     * inventory row carrying the firmware identity so the next boot or
     * request resolves to it.
     *
     * @param AgentEnrollment $Row      the request
     * @param array           $identity the identity block
     * @param array           $macs     validated MACs, at least one
     *
     * @return int the new host id
     */
    private static function _createPendingHost(AgentEnrollment $Row, array $identity, array $macs)
    {
        $name = (string)$Row->get('hostname');
        $Probe = new Host();
        if ('' === $name || !$Probe->isHostnameSafe($name)) {
            // Fifteen characters, the NetBIOS bound isHostnameSafe enforces.
            $name = 'agent-' . substr((string)$Row->get('fingerprint'), 0, 8);
        }
        $base = $name;
        $n = 1;
        while ((new HostManager())->exists($name)) {
            $suffix = '-' . $n++;
            $name = substr($base, 0, 15 - strlen($suffix)) . $suffix;
        }
        $Host = (new Host())
            ->set('name', $name)
            ->set('description', _('Pending Registration created by FOG_AGENT'))
            ->set('imageID', null)
            ->set('pending', '1')
            // The default modules, as Boot\Registration and the host add
            // form attach them: Resolver::resolveModules() has no default
            // tier, so a host created without these has every capability
            // off until an admin visits its Modules tab. Found when the
            // first agent-created host polled and got no capabilities.
            ->set('modules', Route::getIds('module', ['isDefault' => 1]))
            ->addPriMAC(array_shift($macs));
        $Host->save();
        $hostID = (int)$Host->get('id');
        // After save, not before: addMAC() writes the hostMAC rows
        // immediately with whatever id the object holds, and an unsaved
        // host holds none -- the batch guard in FOGManagerController then
        // rejects the empty hostID and the whole enroll dies with a 500.
        // addPriMAC() is different: it only stages the primary, and
        // Host::save() writes that row itself once the id exists.
        if (!empty($macs)) {
            $Host->addMAC($macs);
        }

        $ids = [];
        foreach (self::IDENTITY_MAP as $key => $field) {
            $ids[$field] = SmbiosIdentity::canonicalize((string)($identity[$key] ?? ''));
        }
        $usable = SmbiosIdentity::usable($ids);
        if (!empty($usable)) {
            $Inventory = (new Inventory())->set('hostID', $hostID);
            foreach ($usable as $field => $value) {
                $Inventory->set($field, $value);
            }
            $Inventory->save();
        }
        return $hostID;
    }

    /**
     * An enrolled agent renews its certificate over its own mTLS session.
     *
     * Same key only. The presented certificate proved the caller holds the
     * key bound to this host, and the request is signed for that same key,
     * so nothing an admin decided changes: the binding, the host, the
     * subject. A different key is a new claim on the machine and goes
     * through enroll, where it pends as a rebind for an admin. The old
     * certificate is not revoked -- it binds to the same key and expires on
     * its own -- and there is nothing to revoke it with; the binding is the
     * only thing the server checks.
     *
     * @param Host   $Host   the principal the gate bound
     * @param string $csrPEM the request, for the bound key
     *
     * @throws \RuntimeException with an HTTP code when refused
     *
     * @return string the leaf followed by the issuing chain, PEM
     */
    public static function renew(Host $Host, $csrPEM)
    {
        $fingerprint = self::fingerprint((string)$csrPEM);
        if (null === $fingerprint) {
            throw new \RuntimeException('csr_pem is not a certificate request', 400);
        }
        if (!hash_equals((string)$Host->get('agentFingerprint'), $fingerprint)) {
            throw new \RuntimeException('the request is not for the key this certificate proved', 400);
        }
        list($leaf, $chain) = self::_sign((string)$csrPEM, (int)$Host->get('id'));
        $parsed = openssl_x509_parse($leaf);
        $notAfter = is_array($parsed) && isset($parsed['validTo_time_t'])
            ? gmdate('Y-m-d H:i:s', (int)$parsed['validTo_time_t'])
            : null;
        // The manager rather than Host::save(), as agentPoll does: a save
        // rewrites the MAC association, and renewal is a routine call.
        (new HostManager())->update(
            ['id' => (int)$Host->get('id')],
            '',
            [
                'agentNotAfter' => $notAfter,
                'agentCheckin' => self::niceDate()->format('Y-m-d H:i:s')
            ]
        );
        Audit::record(
            [
                'type' => 'agent.enroll',
                'subjectType' => 'host',
                'subjectID' => (int)$Host->get('id'),
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'text' => sprintf(
                    'certificate renewed to %s, key %s',
                    (string)$notAfter,
                    substr($fingerprint, 0, 16)
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
        return $leaf . $chain;
    }

    /**
     * Issues on an automatic path and answers the agent in the same
     * request.
     *
     * @param AgentEnrollment $Row      the request
     * @param string          $via      token or deploy
     * @param string          $remoteIP the caller
     *
     * @return array [int, array]
     */
    private static function _issueNow(AgentEnrollment $Row, $via, $remoteIP)
    {
        try {
            $cert = self::_issue($Row, $via, '');
        } catch (\RuntimeException $e) {
            error_log(
                sprintf(
                    'FOG agent enroll: signing for host %d from %s failed: %s',
                    (int)$Row->get('hostID'),
                    $remoteIP,
                    $e->getMessage()
                )
            );
            return [503, ['status' => 'error', 'reason' => 'signing']];
        }
        // Collected in this response; nothing left in the row to hand out.
        $Row->set('cert', '')->save();
        return [200, self::_issuedPayload($Row, $cert)];
    }

    /**
     * Signs the stored CSR for the bound host, binds the fingerprint to the
     * host, marks the row issued and audits it. Returns the PEM the agent
     * gets: the leaf followed by the issuing chain.
     *
     * A pending host approved this way stops being pending: approving the
     * agent is approving the machine.
     *
     * @param AgentEnrollment $Row the request
     * @param string          $via token, deploy or admin
     * @param string          $by  the admin, or '' on an automatic path
     *
     * @throws \RuntimeException when the helper refuses
     *
     * @return string
     */
    private static function _issue(AgentEnrollment $Row, $via, $by)
    {
        $hostID = (int)$Row->get('hostID');
        $Host = new Host($hostID);
        if (!$Host->isValid()) {
            throw new \RuntimeException('host no longer exists', 409);
        }
        list($leaf, $chain) = self::_sign((string)$Row->get('csr'), $hostID);
        $parsed = openssl_x509_parse($leaf);
        $notAfter = is_array($parsed) && isset($parsed['validTo_time_t'])
            ? gmdate('Y-m-d H:i:s', (int)$parsed['validTo_time_t'])
            : null;
        $now = self::niceDate()->format('Y-m-d H:i:s');

        $Host->set('agentFingerprint', (string)$Row->get('fingerprint'))
            ->set('agentNotAfter', $notAfter)
            ->set('agentVersion', (string)$Row->get('agentVersion'))
            ->set('agentCheckin', $now);
        if ($Host->get('pending')) {
            $Host->set('pending', '0');
        }
        $Host->save();

        $Row->set('state', AgentEnrollment::STATE_ISSUED)
            ->set('decided', $now)
            ->set('decidedBy', (string)$by)
            ->set('decidedVia', $via)
            ->save();
        self::_audit(
            $Row,
            sprintf('issued via %s, key %s', $via, substr((string)$Row->get('fingerprint'), 0, 16)),
            (string)$by
        );
        return $leaf . $chain;
    }

    /**
     * The staging-and-sudo handshake nodecert.php uses, for the agent type.
     * The helper builds the subject from the host id it reads out of the
     * staged file; nothing in the CSR's subject is used.
     *
     * @param string $csr    the request, PEM
     * @param int    $hostID the host to name in the certificate
     *
     * @throws \RuntimeException when the helper refuses
     *
     * @return array [leaf PEM, chain PEM]
     */
    private static function _sign($csr, $hostID)
    {
        $staging = FOG_BASE_DIR . DS . 'nodecert-staging';
        if (!is_dir($staging) || !is_writable($staging)) {
            throw new \RuntimeException('agent certificate issuance is not configured', 503);
        }
        $reqid = bin2hex(openssl_random_pseudo_bytes(16));
        $csrfile = $staging . DS . $reqid . '.csr';
        $hostfile = $staging . DS . $reqid . '.agent';
        $outfile = $staging . DS . $reqid . '.pem';
        $chainfile = $staging . DS . $reqid . '.chain';
        file_put_contents($csrfile, $csr);
        file_put_contents($hostfile, (int)$hostID . "\n");
        $cmd = 'sudo -n '
            . escapeshellarg(rtrim(FOG_BASE_DIR, DS) . '/bin/fog-sign-node-cert')
            . ' agent ' . escapeshellarg($reqid) . ' 2>&1';
        $output = shell_exec($cmd);
        $leaf = file_exists($outfile) ? file_get_contents($outfile) : '';
        $chain = file_exists($chainfile) ? file_get_contents($chainfile) : '';
        foreach ([$csrfile, $hostfile, $outfile, $chainfile] as $tmp) {
            if (file_exists($tmp)) {
                unlink($tmp);
            }
        }
        if (!$leaf) {
            throw new \RuntimeException(trim((string)$output) ?: 'signing failed', 503);
        }
        return [$leaf, $chain];
    }

    /**
     * Validated, lower-cased, de-duplicated MACs from the request.
     *
     * @param mixed $macs whatever the agent sent
     *
     * @return array
     */
    private static function _macs($macs)
    {
        $out = [];
        foreach ((array)$macs as $mac) {
            $mac = strtolower(trim((string)$mac));
            if (filter_var($mac, FILTER_VALIDATE_MAC)) {
                $out[$mac] = $mac;
            }
        }
        return array_values($out);
    }

    /**
     * The 202 body.
     *
     * @param AgentEnrollment $Row the request
     *
     * @return array
     */
    private static function _pendingPayload(AgentEnrollment $Row)
    {
        return [
            'status' => 'pending',
            'reason' => (string)$Row->get('reason'),
            'retry_after' => self::RETRY_AFTER
        ];
    }

    /**
     * The 200 body.
     *
     * @param AgentEnrollment $Row  the request
     * @param string          $cert leaf plus chain, PEM
     *
     * @return array
     */
    private static function _issuedPayload(AgentEnrollment $Row, $cert)
    {
        $Host = new Host((int)$Row->get('hostID'));
        return [
            'status' => 'issued',
            'host_id' => (int)$Row->get('hostID'),
            'certificate_pem' => $cert,
            'not_after' => (string)$Host->get('agentNotAfter')
        ];
    }

    /**
     * Every decision leaves a row. An agent that was let in without a
     * trail is the thing an admin cannot answer questions about later.
     *
     * @param AgentEnrollment $Row  the request
     * @param string          $text what happened
     * @param string          $by   the admin, or '' for the agent itself
     *
     * @return void
     */
    private static function _audit(AgentEnrollment $Row, $text, $by = '')
    {
        $hostID = (int)$Row->get('hostID');
        $label = $hostID > 0 ? (string)(new Host($hostID))->get('name') : (string)$Row->get('hostname');
        $row = [
            'type' => 'agent.enroll',
            'subjectType' => 'host',
            'subjectID' => $hostID,
            'subjectLabel' => $label,
            'renderable' => 1,
            'text' => $text
        ];
        if ('' === $by) {
            $row['authSource'] = Audit::SOURCE_ANONYMOUS;
        }
        Audit::record($row);
    }
}
