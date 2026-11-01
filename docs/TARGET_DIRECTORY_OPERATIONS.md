# Target-based Windows directory operations

This change integrates the target-based directory feature with FOG
`working-1.6` at commit `5655f1094fb9641700cdef3a67c04b500ab7471d`.
Schema 447 follows the unchanged official migrations 442–446. It adds nullable
TEXT `hostDirectory.hdRejoinConfig` and `hdRejoinState`, and the internal
`directoryPlacementIntent` table. The generated expected schema and the
standard updater remain the installation mechanism.

Supported installation paths are an official FOG database upgrade and a fresh
installation. Private development schemas, including the old private 444,
have no upgrade contract. No historical provenance attestation is required
for an ordinary official installation.

## Operation selection and safety

- Host certificates and physical identity must match. Enrollment alone does
  not authorize an AD change.
- With AD management enabled, a new eligible completed Deploy task authorizes
  at most one Target Join/Rebind, including a healthy self-restore. The Task
  ID is bound to the plan, claim, result and local marker. Claim advances the
  Host's high-water mark. A Workgroup Join cannot consume the same task again
  as a subsequent Rebind.
- An ordinary name change uses `NetRenameMachineInDomain` and preserves the
  original AD object's GUID/SID. When the old name belongs to a different FOG
  Host, the original account is not renamed: Create or confirmed Existing
  Rebind selects the desired Target instead.
- Existing Rebind uses `NetJoinDomain` flags `0x421`; Create uses `0x20423`
  and requires creation permission and a successful LDAP absence lookup.
  LDAP failure is not absence. Existing Target identity must match the
  administrator's confirmation.
- Server claims, Windows thread-owned mutexes and file-locked one-shot
  markers protect dispatch. These provide fail-closed, at-most-once dispatch,
  not a promise that every native operation succeeds exactly once.
- A lost response or an uncertain native result is retained. Retry cannot
  erase protected attempts, `unknown`, `failed` or `may_have_run` markers.
  The reserved, provably undispatched marker exception and verified/lost-ACK
  recovery retain their existing contracts.

Secure Channel observations are evidence, not a password-corruption verdict.
SC=False/PDC1311 does not trigger automatic Repair or change Rename into
Create/Rebind. Postboot Join/Rebind verification still requires its success
conditions. Directory placement requires the current poll's valid observation,
matching revision and freshness; a cached True cannot replace a missing poll
observation.

## Durable OU placement

Normal legacy and protocol-2 OU placement use the same Target-specific durable
intent protection. The initial validated scope is a single DC and the same
naming context. Computer GUID/SID, destination container identity, generation
and an in-memory DispatchPermit are bound before GUID-addressed ModifyDN.
There is no DN fallback.

`MAY_HAVE_RUN` and `UNKNOWN` retain protection after a process crash, connection
loss or missing response. They are not resent, TTL-expired, or cleared because
the current OU appears correct. No replacement worker or new PDO reconstructs
a dispatch right. An administrator must preserve evidence, stop operations
affecting that Target and arrange a separately reviewed recovery. Generic
automatic recovery while preserving the original GUID is not guaranteed.

`SUCCEEDED` records the originating worker's confirmed LDAP result. It does
not assert that a concurrent facts projection won its CAS. Newer facts are
not overwritten; subsequent placement reevaluates the current Host assignment,
configuration, observations and Target. Terminal intent history is retained
when its Host is decommissioned; its owner ID is an audit reference, not a
cascading foreign key.

## Upgrade and compatibility

Use a maintenance cutover, Server first and compatible Windows Agent second.
Stop accepting all relevant web, cron, queue and plugin writers and drain old
workers before switching code. A worker's termination or a closed socket is
not proof that a previously sent LDAP request has terminated. If an old
request remains unresolved, defer the affected cutover. Rolling upgrade is
not validated by the isolated test evidence.

Keep paired database/code/config/PKI and Agent state backups. Once AD side
effects have occurred, restoring only an EXE or an old database is not a
safe rollback. Schema DDL is not transactional; restore a consistent backup
when partial recovery cannot preserve data. A recorded schema 447 with missing
directory authority is refused by the executing reconciler rather than
recreated as empty history. Ordinary pre-447 additive repair is retained.

An unsupported old Server stops the new Windows Agent; AD OFF does not permit
fallback to the old provider. New Server/old Agent combinations retain legacy
operation only where no protected directory operation requires the new
contract. Linux behavior and unrelated FOG operations are unchanged by the
Windows feature, subject to their existing protocol contracts.

## Initial configuration

Before configuration, confirm the Server uses schema 447 and exposes the MJ-1 API
feature contracts. The compatible Agent requires both `directory-join-mj1` and
`directory-task-operations-v3`.

1. In the FOG UI, open **FOG Configuration → FOG Settings → Active Directory
   Defaults**. Set `FOG_AD_DEFAULT_DOMAINNAME`, `FOG_AD_DEFAULT_USER`, and
   `FOG_AD_DEFAULT_PASSWORD`, unless valid Host-specific values will supply them.
   `FOG_AD_DEFAULT_OU` is optional. `FOG_AD_DEFAULT_PASSWORD_LEGACY` is normally
   unnecessary. Treat `FOG_ENFORCE_HOST_CHANGES` as an operational policy choice
   based on reboot and logged-in-user handling.
2. In the FOG UI, open **FOG Configuration → FOG Settings → FOG Agent -
   Directory Placement**. Configure this LDAP connection even if automatic OU
   placement will remain disabled: MJ-1 uses it for AD Target lookup and GUID/SID
   checks.
   - `FOG_DIRECTORY_BASE_DN`: for example, `DC=example,DC=com`.
   - `FOG_DIRECTORY_BIND_DN`: for example, `CN=fog-ldap,CN=Users,DC=example,DC=com`.
   - `FOG_DIRECTORY_BIND_PASSWORD`: set the secret through the configuration
     interface; do not put a password in documentation or examples.
   - `FOG_DIRECTORY_CA_CERT`: for example, `/path/to/ad-ca.crt`; the file must
     be readable by the Server, and the LDAPS server certificate must be
     correctly validated.
   - `FOG_DIRECTORY_LDAP_URI`: for example, `ldaps://dc.example.com`.
3. The LDAP bind account and Windows domain-join/Rebind account have separate
   roles and need not be the same account. Grant the Windows account the rights
   required for its Join/Rebind operations. Grant the LDAP account read
   permissions for Target lookup; grant OU-move rights only when automatic
   placement is used.

Setting `FOG_DIRECTORY_PLACEMENT_ENABLED=0` disables automatic OU placement,
not the LDAP connection or Target lookup. Target search, Create/Existing selection,
and ordinary Rename remain available.

Host-side prerequisites:

- A compatible Agent is successfully enrolled and authenticates with its
  enrollment certificate.
- AD management is enabled for the Host.
- Create permission is enabled when selecting a newly created Target.
- Existing Target selection has explicit administrator confirmation and the
  required permissions.
- Automatic image-deployment Rebind is authorized by a normally completed, eligible Deploy task.

The Server implementation attempts StartTLS for `ldap://` URIs and aborts on StartTLS failure before bind. That code path was not hardware-validated; hardware validation covered LDAPS only.

## Acceptance boundaries

The latest bounded ten-case hardware acceptance for the recorded Server/Agent
pair is **PASS WITH NOTES**. A normal GUID-addressed OU move and return is also
**PASS WITH NOTES**. These results apply only to the tested pair and scope; they
do not transfer to another build or establish general rollout safety.

The recorded `msDS-KeyCredentialLink` (KCL) change was accepted only as a
scoped exception under the lab criteria; the modifying actor was not
conclusively identified. This does not validate similar KCL changes generally.

Isolated PHP/Go, fake LDAP/native, standard-updater and Windows mutex tests are
separate from real AD/Windows acceptance. Real-AD failure injection for
UNKNOWN/LDAP response loss, legacy-Agent hardware acceptance, and multi-DC
behavior were not tested. Overlapping native execution was not demonstrated in
the short-interval hardware boot case. These results also do not establish
real upgrade request-drain evidence or the cause of long-duration
Netlogon/PDC1311 behavior.
This feature adds neither SID generalization nor Sysprep, and does not change
FOS or Multicast behavior.
