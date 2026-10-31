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

## Acceptance boundaries

Isolated PHP/Go, fake LDAP/native, standard-updater and Windows mutex tests are
separate from real AD/Windows acceptance. Previous ten-case hardware results
belong to their recorded binaries and are not transferred to this integration.
Multiple-DC semantics, real upgrade request-drain evidence and new-binary
hardware acceptance remain separate gates. The long-duration Netlogon/PDC1311
cause and `msDS-KeyCredentialLink` behavior remain documented observations.
This feature adds neither SID generalization nor Sysprep, and does not change
FOS or Multicast behavior.
