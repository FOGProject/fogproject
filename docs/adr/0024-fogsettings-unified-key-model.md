# `.fogsettings` keys are namespaced by the subsystem that owns them

## Status

accepted

## Context

`.fogsettings` is **sourced as shell**, so a key name *is* a shell variable name.
Over roughly fifteen years it accreted 79 managed keys with no naming scheme at
all, and the cost of that was not aesthetic:

- `snmysqlpass` reads as "storage node MySQL password". It is the database
  password on a full server too. `sn` was a guess about who would need it.
- `sslpath` says "where the SSL lives". It stopped holding FOG's CAs two
  restructurings ago; what it actually holds is the client-communication leaf and
  uploaded snapin material. Every doc about it had to spend a sentence saying
  what it is *not*.
- `password` and `snmysqlpass` are two different secrets in one file, and the
  generically named one is the system account whose credentials also reach the
  replication FTP server fleet-wide.
- `noTftpBuild` is a negative whose flag `-T/--no-tftpbuild` is also negative,
  so reasoning about it means resolving a double negative.
- Five different boolean encodings were in use (`Y`/`N`, `1`/`0`, `yes`/`no`,
  set/unset, and one enum).
- Six external-CA keys held three values, reached two ways
  (`extcacert`/`extcakey`/`extcaroot` from the prompt,
  `webExtCACert`/`webExtCAKey`/`webExtCARoot` from `--web-ca-*`), resolved as
  `${webExtCACert:-$extcacert}`. That duplication caused a reported bug: anything
  typed at the prompt was silently discarded whenever the flags were also given,
  and because the prompt does not run under `-y`, the flags appeared to work
  *only* with `-y`.

Two keys also stood in for facts already on disk, and both had failed. `acmeLeaf`
had to be typed in by hand to tell FOG "this web leaf is not yours"; forgetting
it meant `createSSLCA()` re-issued the leaf from the original CSR while an ACME
private key sat beside it, producing a mismatched pair and a web server that
would not start — silently, under `-y`. `caCreated` said "the CA exists" and both
of its readers already paired it with an `-e`/`-f` test on the very file it stood
in for.

Renaming this was discussed as a risk to be minimised. That framing was wrong and
is worth recording as such: because a key name is a variable name, a spot check of
25 of the 79 keys found 753 references in `bin/` and `lib/` alone. The full sweep
is around 2500 real variable references. The model only pays off once the code
stops having places where a setting can be silently lost, so the sweep is the
work rather than the cost of it.

## Decision

Every managed key is `CATEGORY_lower_snake_case`. The first `_` is the category
boundary. Nine categories: `FOG_`, `NET_`, `DHCP_`, `DB_`, `WEB_`, `PKI_`,
`BOOT_`, `STORAGE_`, `SVC_`.

**The category is the subsystem that OWNS the value, not every subsystem that
reads it.** This is the rule that settles the cases where a key plausibly belongs
in two places. The storage-node MySQL password becomes `DB_password`, not a
`STORAGE_` key: it is a database credential, and the fact that a node also uses it
is carried by `FOG_install_type='S'`, not by the key's name.

79 keys become 66: six retired, nine absorbed by merge, two promoted.

**`WEB_` and `BOOT_` stay separate namespaces.** ADR 0015 established that "the
web UI uses HTTPS" says nothing about the netboot transport. `WEB_url_proto` and
`BOOT_url_proto` are deliberately parallel names in different namespaces, so that
independence is visible in the file an admin edits rather than only in an ADR.

**PKI zones live in the name** (`root` / `web` / `client` / `sb`), matching
`PKI_ZONES.md`. Secure Boot folds in as `PKI_sb_*` rather than taking its own
top-level prefix, but its issued material is named `codesign`, not `leaf`: that
zone carries `extendedKeyUsage = codeSigning` on both the CA and the certificate
it issues, while the web zone carries `serverAuth`. Nothing in the Secure Boot
zone authenticates a server, and a shared `leaf` token would say it did.

**Certificate paths are canonical, and the filesystem carries the indirection.**
A key like `PKI_web_vhost_cert` names a fixed path that FOG always references.
To use a certificate FOG did not issue, the admin makes that path *resolve* to
their file — a symlink is enough — and FOG reads the target and leaves it alone.
This is the inverse of the old model, where the setting held the admin's real
path and FOG's own path was the symlink.

That inversion is what retires `acmeLeaf`, `webCertFile` and `webKeyFile`
together. "Is this leaf mine?" becomes `_externallyManagedLeaf()`: does
`PKI_web_vhost_cert` resolve outside `_pkiZoneDir web`. A symlink cannot disagree
with itself, whereas a persisted flag and two recorded paths could each disagree
with the vhost, and did.

### Prefer deriving state over persisting it

Six keys are retired rather than renamed, and five of them for one of two
reasons.

*It was already knowable.* `acmeLeaf` (the symlink test above), `caCreated` (an
`-e`/`-f` check its readers already did), `externalca` (derivable from "is an
import path set", now a run-scoped prompt variable), `sslcsr` (one path, re-derived
every run).

*It was an opt-out that hid the safe answer behind a flag.* `catrust`
(`--no-ca-trust`) let an admin decline having FOG anchor its own CA in the host
trust store — leaving a server unable to verify its own certificate, with the
failures surfacing far from the flag that caused them. `sbNameConstraints`
(`--no-sb-name-constraints`) turned off name constraints on the Secure Boot
intermediate, which is the one certificate UEFI and shim actually parse; a
critical extension they mishandle costs a physical trip to every machine, and a
flag nobody passes until a fleet has already failed to boot is the wrong shape
for that risk. Constraints come off that zone entirely. `_nameConstraints()` is
untouched and still serves the Web CA, where ADR 0016 made them enforceable by
patching iPXE — the distinction being that iPXE is a verifier FOG can patch and
firmware is not.

### The client-encryption zone gets keys for the first time

`PKI_client_encrypt_cert` and `PKI_client_encrypt_key` are promoted from local
variables. The client zone was the only one an admin could not point elsewhere,
and it holds the one certificate every registered client pins — the exception a
model whose premise is "say where the cert is" cannot have.

Their canonical **names** are not free: `FOGBase` builds `<dir>/.srvprivate.key`
with the filename hardcoded, taking the directory from the storage-node record
rather than from `.fogsettings`. So these keys name a canonical path whose
*target* may move while the name may not.

### Two things the rename does not touch

**`$fogprogramdir` stays.** `FOG_program_dir` is the key; `$fogprogramdir` is the
variable. `.fogsettings` lives at `$fogprogramdir/.fogsettings` and so cannot be
what locates itself; `/etc/fog/fog.conf` exists purely to break that circularity
(GH-850) and every existing server already carries `fogprogramdir=` in it.
`FOG_program_dir` is therefore a *record* written from the live variable, and
renaming the `fog.conf` contract is a separate decision.

**`create_update_node.php`'s POST field names.** `sslpath=`, `interface=` and
`webroot=` in the node-registration call are that endpoint's own field names,
mapping to `storageNode` DB columns. Only the values moved.

## Migration

One installer run, and it is **two halves that must land and stay together**:

1. A one-shot rename-seed block in `bin/installfog.sh`, modelled on the
   `httpproto`→`httpsRedirect` precedent. It runs after `.fogsettings` is sourced
   and before the flag shadows, so the order stays *explicit flag > persisted
   value > migrated value*. Each pair is guarded on the new key, so it fires
   exactly once.
2. Every old spelling in `deprecatedKeys`, which the `awk` merge strips.

**`deprecatedKeys` only strips and carries no value.** Keeping it while dropping
the seed block does not degrade the migration — it wipes every setting on every
server, silently, and under `-y` there is nobody to notice.
`tests/fogsettings-migration.test.sh` extracts the real seed block from the
installer and runs it against a synthesized pre-rename file, so the pair is
exercised end to end rather than by a hand-copied replay that can drift.

`writeUpdateFile()` gains a **one-time canonical rewrite** for a recognizable file
that still carries only old keys. The in-place merge cannot do that run: it
rewrites managed keys in the position they already occupy and appends the ones it
did not find, so with every old key deprecated and every new key absent it would
strip all 79 lines and append 66 at the end — leaving the category blocks and the
`## Derived` marker describing nothing. The rewrite carries every *unrecognised*
line through, because hand-set keys and an admin's own comments survive only
because something preserves what it does not manage.

## Consequences

- **`/api/whoami` is a breaking change.** Its five response keys are renamed
  rather than mapped back. A shim would have frozen spellings like `osid` — a
  numeric second encoding of `osname` that has already changed meaning once
  between releases — into the API for the life of 1.6. The route's OpenAPI schema
  was a bare `['type' => 'object']` and now names the five fields, so the break
  is at least documented. `darksidemilk/FogApi` consumes this route.
- **Third-party scripts that source `.fogsettings` break** on the run that
  strips the old lines. There is no way to both remove a line and keep it
  readable; this is a release-note item.
- **`.fogsettings.pub` and `Route::WHOAMI_KEYS` had to move together** for the
  first time, and nothing bound them. `tests/whoami-keys-in-step.test.php` now
  does. The drift is quiet in the worst way: `whoami()` fills a missing key with
  an empty string rather than dying, so the route stays 200 and a client reads a
  blank hostname as a fact about the server.
- The `whoami` fallback to `.fogsettings` is one notch narrower: a server whose
  web tree is new but whose installer has not re-run answers empty strings until
  it does.
- Every reference is braced (`${NET_hostname}`). Several sites concatenate
  directly onto a value, where an unbraced prefixed name would silently resolve a
  different variable.

## Deliberately not done

- **Boolean encoding and polarity normalisation.** Deferred here, and the
  encoding half has since been done — see
  [ADR 0025](0025-one-boolean-encoding-normalised-on-load.md). The reasoning
  below is what it had to answer: fixing encodings changes *values*, not names,
  and would have made this migration a translation rather than a copy.
  0025 resolves that by normalising on **load** every run rather than rewriting
  once, which leaves the migration a copy. **Polarity is still not done** —
  `BOOT_external_tftp_server` keeps `noTftpBuild`'s polarity precisely so the
  value carries across untouched and still reads correctly against the firewall
  behaviour.
- **`FOG_update_channel`'s values.** `branchToChannel()` produces
  `stable`/`staging`/`dev` while `FOG_CHANNEL` is stamped
  `Patches`/`Beta`/`Release Candidate`/`Feature`. Reconciling them touches
  released version strings and the `fog-workflows` badges — FOGProject/fogproject#1279.
- **Retiring `FOG_os_id`.** It is a numeric second encoding of `FOG_os_name` that
  has already broken once (`3` was Arch in 1.5, Alpine in 1.6). Now that the
  `/whoami` response is changing anyway the constraint that kept it is gone, but
  dropping a published field is its own decision.
- **Client-encryption certificate issuance.** Still from the root, for upgrade
  compatibility. Whether it should come from the Web CA or a dedicated
  intermediate belongs in the fog-client repo.
- **The `fog.conf` variable name**, per the Decision above.

## Alternatives rejected

**Rename 1:1 and defer the merges and retirements.** Would have needed about a
dozen transitional names invented for keys the follow-up deletes — and no name
for the six retired keys, since the model gives them none. It also leaves the
duplications in place, which is the thing the model exists to remove.

**A compatibility shim that reads both spellings.** Carries all 79 retired names
in live code indefinitely, and each one is a live shell variable after sourcing,
so a stale line keeps having effects. The whole point of `deprecatedKeys` is that
old lines stop existing.

**Combine each certificate and key into one PEM**, saving five keys. Leaks
private keys two ways: FOG *publishes* three of these certificates, and chain
files are built with `cat` into a bundle nginx serves.

**Absorb the imported web root into `PKI_root_ca_cert`.** Tried and reverted
during design. `validateExternalCA()` feeds a supplied root into the chain file
*only*; the root fog-client pins is a different certificate. Merging them would
conflate the imported web root with FOG's own pinned trust anchor — precisely the
conflation the three-zone split exists to prevent. It gets its own slot,
`PKI_web_external_root_cert`.

## References

- Tracking issue: FOGProject/fogproject#1120
- Implementation plan: `docs/superpowers/plans/2026-08-22-fogsettings-key-model.md`
- Mechanics: `docs/FOGSETTINGS.md`
- [ADR 0015](0015-install-settings-are-independent-keys.md) — install settings
  are independent keys
- [ADR 0016](0016-ipxe-enforces-x509-name-constraints.md) — why name constraints
  are enforceable in the web zone and not in Secure Boot
- `docs/PKI_ZONES.md` — the zone split the `PKI_` names follow
