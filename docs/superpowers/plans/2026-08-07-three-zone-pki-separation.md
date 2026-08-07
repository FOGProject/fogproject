# Three-zone PKI separation (Web TLS / Client Communication / Secure Boot) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace today's flat certificate setup — one self-signed CA that
both signs the web vhost leaf and is pinned by fog-client, plus a separate
self-signed Secure Boot leaf that is *itself* the enrolled MOK — with a
Root CA issuing **three** independent intermediates (Web, Client
Communication, Secure Boot), each independently replaceable by an admin's own
PKI. Secure Boot's intermediate is what firmware enrolls, so code-signing
leaves can be rotated, revoked, or issued per storage node **without a
firmware re-enrollment trip to every machine**. Additionally: split
`$netbootproto` from `$httpproto` so a private-CA install can serve a trusted
HTTPS web UI while keeping iPXE netboot on HTTP (avoiding the iPXE rebuild
that forfeits the signed Secure Boot shim), and remove `bin/setupacme.sh` —
ACME/Let's Encrypt automation is not a FOG-managed feature going forward.

The split PKI is the **default** for fresh installs; today's flat setup
remains available as a permanent, explicit `--legacy-pki` option. Existing
installs are never silently switched, and — critically — an already-enrolled
Secure Boot MOK is **never** regenerated or replaced by this work.

**Architecture:** See `docs/superpowers/specs/2026-08-07-three-zone-pki-separation-design.md`.

**Phasing at a glance:**
- **Phase 0** (do first — two independent verifications, each gating one
  Phase 1 task): **0.1** verifies the fog-client pinning mechanism against
  `zazzles` source (gates Task 1.4); **0.2** verifies shim accepts a
  CA-in-MokList with an `--addcert` chain, on real hardware (gates Task
  1.8). Neither is a code change; both are cheap relative to what they
  de-risk. 0.1 moved earlier than originally scoped because `split` is now
  the *default* for fresh installs — every fresh install would otherwise
  create a Client Communication intermediate under an unverified assumption.
  0.2 is a hard gate on the Secure Boot zone specifically: a negative answer
  costs that one zone (it stays flat), not the design.
- **Phase 1** (Task 1.4 blocked on 0.1, Task 1.8 blocked on 0.2; the rest
  have no external dependency): the new PKI functions across all three
  zones, `pkiMode=split` as the **default** on a fresh install (no flag or
  prompt answer needed), `--legacy-pki` as the permanent, fully supported
  opt-out reproducing today's flat behavior byte-for-byte, the
  `$netbootproto` split, existing servers provably unchanged, and removal of
  `bin/setupacme.sh`.
- **Phase 2** (blocked on Phase 0's answer, same as before): the
  existing-server migration path — dual-trust-window rollout, snapin-based
  client re-pinning, cutover.
- **Phase 3** (no hard blocker, but low value until Phase 1/2 have real
  users): root-key offlining helper, `--external-ca` flag
  deprecation-timeline decision, docs consolidation.

No single PR boundary in Phase 1 leaves an existing server in a
half-migrated state: `pkiMode`'s default is computed from `caCreated` (Task
1.1), so a server with cert material predating this feature always resolves
to `flat` — its existing PKI is never silently restructured, regardless of
what a fresh install now defaults to. Only a genuinely fresh install (no
prior CA) resolves to the new `split` default.

## Global Constraints

- No CI/test framework exists for this repo's shell scripts beyond
  `fogproject-install-validation`'s distro matrix. Every task's "test" step is
  a manual invocation + assertion, not a unit-test suite. Run `bash -n
  <file>` after every shell edit before anything else.
- Follow the existing staging-variable convention exactly: a new flag sets an
  `s`-prefixed variable during `getopt` parsing, applied to the real variable
  only *after* `.fogsettings` is sourced, in the "# evaluation of command
  line options" block (`bin/installfog.sh:615-656` currently) — never before.
- `bin/updatefog.sh` never runs `installfog.sh` interactively (always `-Y`);
  any new flag added there must be passed straight through to the child
  invocation, same as `$updateVhostFlag`/`--hostname`/`--extra-server-name`
  already are.
- Every new value that reaches a file write (vhost config, OpenSSL config
  file, subject string) must be validated first — this repo already treats
  that as a real security boundary (`--git-path`'s absolute-path check,
  `validhostname()`).
- `writeUpdateFile()`'s `managedKeys` array (`lib/common/functions.sh:3122`
  ff.) is the single source of truth for what persists across an update —
  every new variable this plan introduces that needs to survive an upgrade
  must be added there, in the same task that introduces it, not as an
  afterthought.
- This plan assumes the `1013`-branch state already merged
  (`--hostname`/`--extra-server-name` already present in `createSSLCA()`) —
  do not reintroduce or duplicate any of that. It also assumes
  `docs/superpowers/plans/2026-08-07-customization-preservation.md`'s Task 3
  (the FOG-managed vhost block / `spliceManagedBlock()`) either has already
  landed or is understood to land before/alongside this plan's Task 1.3 —
  that task's `createSSLCA()` extraction should be built against the
  already-spliced vhost-writing code, not duplicate work against it.
- Design doc: `docs/superpowers/specs/2026-08-07-three-zone-pki-separation-design.md`.
  Read it if anything below is ambiguous.

---

## Phase 0: Verify the fog-client pinning mechanism (blocks Phase 2 only)

### Task 0.1: Get a falsifiable answer from `zazzles`/fog-client source or the maintainer

**Files:** None in this repo — this is a research task against an external
repository/person, tracked here so **both Phase 1's Task 1.4 and Phase 2**
have a documented go/no-go gate. (Originally scoped to gate only Phase 2;
moved earlier because `split` is now the default for fresh installs, so
Task 1.4's comm-certificate delivery path is no longer a low-stakes
"opt-in only" decision — see the design doc's Open Risks #8.)

- [ ] **Step 1:** Locate the fog-client (`zazzles`) source's TLS/cert-pinning
  code (likely in a `Communication`/`Certificate` class per the `#!ihc`
  handling referenced in `fogpage.class.php:2870`'s comment — `Zazzles'
  Communication.Post() calls HttpWebRequest::GetResponse()`).
- [ ] **Step 2:** Answer, in writing, and attach to the tracking issue:
  - Does the client compare the downloaded `ca.cert.der`'s **bytes**, its
    **CN string**, or both, against a stored/expected value?
  - Does it re-download and re-validate `ca.cert.der` (and whatever it uses
    as the comm-encryption public key) **before every `authorize()` attempt**,
    or only at registration/install time?
  - Does it use `ca.cert.der`'s own key material directly for
    `certEncrypt`/`certDecrypt`-style payload crypto, or does it separately
    fetch/expect a distinct leaf cert for that purpose?
- [ ] **Step 3:** Update
  `docs/superpowers/specs/2026-08-07-three-zone-pki-separation-design.md`'s
  Open Risks #1-#3 with the confirmed answer, and note which of Phase 2's
  tasks below change as a result (most likely: whether Task 2.2's snapin step
  is still necessary at all, or whether the migration collapses to "just
  rotate and wait one checkin cycle").
- [ ] **Step 4: Commit** (docs-only)
  ```bash
  git add docs/superpowers/specs/2026-08-07-three-zone-pki-separation-design.md
  git commit -m "Record verified fog-client pinning behavior (Phase 0 finding)"
  ```

### Task 0.2: Verify shim accepts a CA-in-MokList with an `--addcert` chain

**Files:** None — hardware verification, gating Task 1.8 (the Secure Boot
zone) specifically. **This is a hard gate:** the entire "rotate signing
leaves without re-enrolling firmware" premise depends on the answer. A
negative result does not sink the design — the Secure Boot zone simply stays
on today's self-signed-leaf model while Web and Client still split — but it
must be answered before Task 1.8 is written, not after.

- [ ] **Step 1:** Build a throwaway two-level chain by hand on a test box:
  ```bash
  # intermediate (would be MOK.der)
  openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 3650 \
      -subj "/CN=Test SB CA/" -addext "basicConstraints=critical,CA:TRUE" \
      -keyout sbca.key -out sbca.pem
  # code-signing leaf issued by it
  openssl req -new -nodes -newkey rsa:2048 -sha256 -subj "/CN=Test SB Signer/" \
      -keyout leaf.key -out leaf.csr
  openssl x509 -req -in leaf.csr -CA sbca.pem -CAkey sbca.key -CAcreateserial \
      -days 365 -sha256 -out leaf.pem \
      -extfile <(printf 'basicConstraints=critical,CA:FALSE\nextendedKeyUsage=codeSigning\n')
  ```
- [ ] **Step 2:** Sign a real FOS kernel with the leaf, bundling the
  intermediate — this `--addcert` flag is the whole mechanism under test:
  ```bash
  sbsign --key leaf.key --cert leaf.pem --addcert sbca.pem \
      --output bzImage.signed bzImage
  sbverify --list bzImage.signed   # confirm BOTH certs are present
  ```
- [ ] **Step 3:** Enroll **only** `sbca.pem` (converted to DER) as a MOK via
  `mokutil --import` on a real UEFI machine with Secure Boot **enabled**,
  reboot through MokManager, and attempt to boot `bzImage.signed` through
  the shim FOG ships (`downloadipxesecureboot()`'s staged binaries).
- [ ] **Step 4:** Record the result in the design doc's Open Risks #4 and
  Testing sections. If it boots: Task 1.8 proceeds as written. If it does
  not: mark the Secure Boot zone as staying flat, and note which shim
  version/firmware was tested — do not silently downgrade the plan without
  recording what was actually observed.
- [ ] **Step 4a: Repeat the whole test via the Setup Mode / db path**, not
  just MokManager — they are verified by different code (shim's own logic vs.
  the UEFI firmware's). Put a machine in Setup Mode, enroll FOG's
  PK/KEK/db.auth with the **intermediate** in `db`, and confirm a
  leaf-signed kernel boots. Then rotate the leaf and confirm it still boots
  with no db update pushed. A pass here is what proves the rotation promise
  holds on both enrollment routes; a failure means `db` still needs the leaf
  and Setup-Mode clients keep today's re-enrollment cost.
- [ ] **Step 5:** If possible, repeat on arm64. Record if untested rather
  than assuming parity with x86_64.
- [ ] **Step 6: Commit** (docs-only)
  ```bash
  git add docs/superpowers/specs/2026-08-07-three-zone-pki-separation-design.md
  git commit -m "Record shim CA-in-MokList chain verification result (Phase 0 finding)"
  ```

---

## Phase 1: New PKI functions, opt-in `pkiMode=split`, fresh installs only

### Task 0.3: Confirm where fog-client fetches the server's encryption certificate (gates Task 1.4)

**Files:** None — inspection of a **live, working** FOG server plus a
`zazzles` source read. Determines whether decoupling the comm certificate
from the web vhost needs any fog-client change at all.

**Already settled, do not re-litigate:** `.srvprivate.key` exists (every
file in `$sslpath` is a dotfile, so a bare `ls` makes the directory look
empty — `ls -la` shows it), it is the **web leaf's** key, and it is what
`certDecrypt()` uses on every client handshake. The coupling is confirmed
real and the fix is settled: the FOG Server CA issues its own communication
TLS certificate, never shared with the vhost.

What remains is purely a delivery question: fog-client must obtain the
public half of whatever key the server decrypts with. If it fetches
`management/other/ssl/srvpublic.crt`, then publishing the comm leaf at that
same path is a **server-side-only** change and no client work is needed.

- [ ] **Step 1:** Establish the baseline on a live server — confirm which
  certificate `.srvprivate.key` currently backs (expected: `srvpublic.crt`,
  *not* `ca.cert.pem`; equal modulus hashes mean equal keypair):
  ```bash
  openssl rsa  -noout -modulus -in /opt/fog/snapins/ssl/.srvprivate.key                 | md5sum
  openssl x509 -noout -modulus -in /var/www/html/fog/management/other/ssl/srvpublic.crt | md5sum
  openssl x509 -noout -modulus -in /var/www/html/fog/management/other/ca.cert.pem       | md5sum
  ```
  If `.srvprivate.key` does **not** match `srvpublic.crt`, stop — check
  `grep sslprivkey /opt/fog/.fogsettings`, since the path was overridden and
  the rest of this task's assumptions need re-checking against that path.
- [ ] **Step 2:** Find, in `zazzles`/fog-client source, the code that
  obtains the server's public key before encrypting `sym_key`/`token`
  (likely near the `RSA`/`Authentication` handling that produces `#!ihc`).
  Answer: which URL/path does it request, and does it use that certificate's
  public key directly, or derive one from `ca.cert.der`?
- [ ] **Step 3:** Cross-check against the server's access log — the client's
  own requests are the ground truth, and this needs no source access:
  ```bash
  grep -E 'ca\.cert\.(der|pem)|srvpublic\.crt' /var/log/httpd/*access* /var/log/nginx/*access* 2>/dev/null | tail -40
  ```
  A client that fetches `srvpublic.crt` at handshake time confirms the
  server-side-only path.
- [ ] **Step 4:** Sanity-check the storage node's configured SSL path, since
  that — not `$sslpath` — is what `certDecrypt()` actually resolves
  (`fogbase.class.php:2019-2023`), and Task 1.4 must write the comm key
  where that column points:
  ```bash
  mysql fog -e "SELECT ngmID, ngmSSLPath FROM nfsGroupMembers;"
  ```
- [ ] **Step 5:** Record the answer in the design doc's Open Risks and note
  which Task 1.4 path applies: comm leaf published at the existing path
  (no client change), or Client CA doubling as the comm keypair (fallback,
  also no client change), or a genuine client-side change required (the only
  outcome that blocks on the `zazzles` repo).
- [ ] **Step 6: Commit** the finding (docs-only)
  ```bash
  git add docs/superpowers/specs/2026-08-07-three-zone-pki-separation-design.md
  git commit -m "Record how fog-client obtains the server encryption cert (Phase 0 finding)"
  ```

---

### Task 1.1: `pkiMode` managed key + zone-aware directory scaffolding

**Files:**
- Modify: `lib/common/functions.sh` — `writeUpdateFile()`'s `managedKeys`
  array (`functions.sh:3122` ff.): add `pkiMode fogClientCACN`.
- Modify: `lib/common/functions.sh` — add a small `_pkiZoneDir(zone)` helper
  (`root`|`web`|`client`|`client/comm` → `$sslpath/CA/<zone>`), used by every
  function in Tasks 1.2-1.4 instead of each one string-concatenating
  `$sslpath/CA/...` independently — this is what keeps the CN-assumption
  blast radius to one place, per the design doc's Open Risk #1: if the target
  directory shape ever needs to change, it changes in one function.

**Interfaces:**
- Produces: `$pkiMode` (`split` default on a fresh install, `flat` default on
  a server with pre-existing cert material, either overridable by a flag —
  see Step 3 below), `$fogClientCACN`
  (default `"FOG Server CA"`, overridable — see Task 1.5's `--client-ca-cn`
  escape hatch for when Phase 0 finds the real requirement is a different
  string).
- Consumes: nothing new.

- [ ] **Step 1:** Add the two keys to `managedKeys` with a comment explaining
  why (mirrors the `secureBootKey`/`secureBootCert`/`secureboot` comment
  block already there at `functions.sh:3131-3136` — same "opt-in that must
  not silently revert on upgrade" reasoning applies to `pkiMode`).
- [ ] **Step 2:** Add `_pkiZoneDir()`:
  ```bash
  # Single source of truth for the split-PKI directory layout under $sslpath.
  # Every split-mode function asks this for a path rather than
  # string-concatenating $sslpath/CA/... itself -- see the design doc's Open
  # Risk #1 for why this matters: if the Client zone's shape needs to change
  # later (e.g. drop the .commLeaf sub-leaf per Phase 0's finding), it changes
  # here once, not in every caller.
  _pkiZoneDir() {
      case "$1" in
          root)        echo "$sslpath/CA/root" ;;
          web)         echo "$sslpath/CA/web" ;;
          client)      echo "$sslpath/CA/client" ;;
          client/comm) echo "$sslpath/CA/client/comm" ;;
      esac
  }
  ```
- [ ] **Step 3:** Default `pkiMode` based on whether this server already has
  cert material, not just "is it unset" — a server with `caCreated == yes`
  predates this feature and must default to `flat`, never `split`, no
  matter how new the installer binary is; a server with no CA yet gets the
  new `split` default:
  ```bash
  if [[ -z $pkiMode ]]; then
      if [[ $caCreated == yes ]]; then
          pkiMode="flat"
      else
          pkiMode="split"
      fi
  fi
  [[ -z $fogClientCACN ]] && fogClientCACN="FOG Server CA"
  ```
  Place alongside the other such defaults near `createSSLCA()`'s top
  (`functions.sh:3418` area) — **after** `.fogsettings` has been sourced (so
  an existing `caCreated`/`pkiMode` value is already loaded) and **before**
  any `--legacy-pki`/`--restructure-pki` staging-variable override (Task
  1.5) is applied, so an explicit flag always wins over either default.
- [ ] **Step 4:** `bash -n lib/common/functions.sh` — expect clean.
- [ ] **Step 5: Commit**
  ```bash
  git add lib/common/functions.sh
  git commit -m "Add pkiMode/fogClientCACN scaffolding for opt-in split PKI"
  ```

### Task 1.2: `createRootCA()`

**Files:** Modify `lib/common/functions.sh` (new function, placed
immediately before `createSSLCA()`).

**Interfaces:**
- Produces: `$rootCAKey`/`$rootCAPem` on success; a `--root-ca-key/--root-ca-cert`
  admin-supplied pair (Task 1.5) skips generation entirely, mirroring
  `_ensureSecureBootKeys()`'s "admin-supplied pair always wins" pattern.
- Consumes: `_pkiZoneDir root`.

- [ ] **Step 1:** Write `createRootCA()` modeled directly on
  `createSSLCA()`'s existing self-signed branch (`functions.sh:3429-3442`),
  changed to: `CN=FOG Server ROOT CA`, `-days 7300`, and an extfile passing
  `basicConstraints=critical,CA:TRUE,pathlen:1` (today's flat CA passes no
  extfile at all for its own self-signed cert — the intermediates need
  `pathlen:1` so nothing can chain a further CA underneath them, mirroring
  the shape a real offline-root setup expects). Guard with `[[ ! -f
  "$(_pkiZoneDir root)/.fogRootCA.key" ]]` — like the root's key, this
  NEVER regenerates once present (same reasoning, borrow the comment,
  from `_ensureSecureBootKeys()`'s doc header nearly verbatim: a fresh root
  silently invalidates every intermediate signed from the old one).
- [ ] **Step 2:** `bash -n lib/common/functions.sh`.
- [ ] **Step 3:** Manual verify (test VM): call the function directly after
  sourcing `functions.sh`, then `openssl x509 -in
  $sslpath/CA/root/.fogRootCA.pem -noout -subject -ext basicConstraints`
  shows `CN = FOG Server ROOT CA` and `CA:TRUE, pathlen:1`.
- [ ] **Step 4: Commit**
  ```bash
  git add lib/common/functions.sh
  git commit -m "Add createRootCA() for opt-in split PKI"
  ```

### Task 1.3: `createWebIntermediateCA()` (extraction + repoint, not new logic)

**Files:** Modify `lib/common/functions.sh`.

**Interfaces:**
- Produces: everything `createSSLCA()`'s back half already produces
  (`$sslprivkey`, `$sslcsr`, `$sslpubcert`, the vhost config) — this task
  moves that code, it does not rewrite it.
- Consumes: `createRootCA()`'s output when `pkiMode == split` and no
  `--web-ca-*` import was given; otherwise `validateExternalCA web` (Task 1.5).

**Note:** if `docs/superpowers/plans/2026-08-07-customization-preservation.md`'s
Task 3 (the FOG-managed vhost block splice) has already landed, the vhost
write sites this task's `if [[ $pkiMode != split ]]` wrap around already
write into a temp file and call `spliceManagedBlock` — leave that mechanism
completely alone here; this task only touches the CA-selection lines that
run *before* the CSR/leaf/vhost-writing code, never the vhost-writing code
itself.

- [ ] **Step 1:** In `createSSLCA()`, wrap the existing CA-selection block
  (`functions.sh:3424-3444`: the `if [[ $externalca == yes ]] ... else ...
  fi` that sets `$sslcakey`/`$sslcapem`/`$sslcachain`) in `if [[ $pkiMode !=
  split ]]; then ... existing code, byte-for-byte ... fi`, and add an `else`
  branch that calls `createRootCA()` then either `createWebIntermediateCA()`
  (self-signed-from-root path) or `validateExternalCA web` (import path) —
  both setting the same three variables (`$sslcakey`/`$sslcapem`/`$sslcachain`)
  so **everything below this point in `createSSLCA()` — the CSR, the SAN
  loop, the leaf signing, the vhost writer — needs zero changes**. This is
  the key design property that keeps this task small: the flat/split branch
  point is a single `if`, and only the CA-selection few lines differ; the
  leaf/vhost machinery is shared, unmodified code either way.
- [ ] **Step 2:** `createWebIntermediateCA()` itself is `_issueIntermediateCA
  "FOG Web CA" "$(_pkiZoneDir web)" .fogWebCA.key .fogWebCA.pem`, followed by
  writing `.fogWebCAchain.pem` as root+intermediate concatenated (same
  concat-into-a-chain-file shape `validateExternalCA()` already uses for
  `.fogCAchain.pem`, `functions.sh:3325`).
- [ ] **Step 3:** Write `_issueIntermediateCA(cn, outdir, keyfile, certfile)`
  as the shared helper both `createWebIntermediateCA()` and
  `createClientIntermediateCA()` (Task 1.4) call — `openssl genrsa` + `openssl
  req -new -subj "/CN=$cn/"` + `openssl x509 -req -CA "$rootCAPem" -CAkey
  "$rootCAKey" -CAcreateserial -extensions v3_intermediate_ca -extfile
  <(printf 'basicConstraints=critical,CA:TRUE\n')`.
- [ ] **Step 4:** `bash -n lib/common/functions.sh`.
- [ ] **Step 5:** Manual verify (test VM): `./installfog.sh -Y` with **no
  PKI-related flags at all** on a **fresh** install (no existing
  `.fogsettings`) → confirm `split` happens **by default**: `openssl verify
  -CAfile $sslpath/CA/root/.fogRootCA.pem -untrusted
  $sslpath/CA/web/.fogWebCA.pem $sslpubcert` succeeds, and the vhost/site
  loads over HTTPS exactly as a `flat`-mode install would.
- [ ] **Step 6:** Legacy-opt-out check: `./installfog.sh -Y --legacy-pki` on
  a **separate fresh** install → confirm `$sslpath/CA/root|web|client` **do
  not exist at all**, and `openssl x509 -in $sslpath/CA/.fogCA.pem -noout
  -subject` shows the unchanged flat CN, matching a pre-this-patch install
  byte-for-byte.
- [ ] **Step 7:** Existing-server regression check (the highest-value test
  in this whole plan, per the design doc's Testing section): against a
  server with a **real prior** `.fogsettings` (`caCreated == yes`, no
  `pkiMode` key — simulating an install that predates this feature), run
  `./installfog.sh -Y` (update path, no flags) → confirm it resolves to
  `flat` and behaves identically to before this patch — `$sslpath/CA/root|web|client`
  still do not exist, nothing about its existing CA changes.
- [ ] **Step 8: Commit**
  ```bash
  git add lib/common/functions.sh
  git commit -m "Extract createWebIntermediateCA(); gate createSSLCA()'s CA-selection on pkiMode"
  ```

### Task 1.4: `createClientIntermediateCA()` + the `certDecrypt()`/`certEncrypt()` repoint

**Files:**
- Modify: `lib/common/functions.sh` — new function.
- Modify: `packages/web/lib/fog/fogbase.class.php` — `certDecrypt()`'s
  `.srvprivate.key` filename resolution (`fogbase.class.php:2027-2032`).

**Interfaces:**
- Produces: `ca.cert.der`/`ca.cert.pem` under `$webdirdest/management/other/`
  — same export mechanics as `createSSLCA()`'s existing two lines
  (`functions.sh:3520-3521`), same public path fog-client already downloads
  from, just sourced from `.fogClientCA.pem` instead of the flat `$sslcapem`
  when `pkiMode == split`.
- Consumes: `createRootCA()`'s output, or `validateExternalCA client` (Task
  1.5); `$fogClientCACN` (Task 1.1).

**Design settled; one delivery detail from Task 0.3.** The Client CA issues
its own communication TLS certificate — never shared with the web vhost.
Task 0.3 determines only *where that certificate is published* so fog-client
finds it, not whether it exists.

- [ ] **Step 1:** `createClientIntermediateCA()` generates
  `.fogClientCA.{key,pem}` from the Root via `_issueIntermediateCA` with
  `CN=$fogClientCACN`, then issues a **communication leaf** into
  `$(_pkiZoneDir client/comm)` — `.commLeaf.{key,pem}`, `CA:FALSE`, RSA
  4096 to match today's `$sslprivkey` size (`functions.sh:3478`), since the
  client chunks its RSA payload by modulus size (`fogbase.class.php:2056`)
  and a smaller key would silently change that framing.
  `ca.cert.der`/`ca.cert.pem` continue to be exported from
  `.fogClientCA.pem` exactly as today (`functions.sh:3520-3521`), just from
  the new source file.
- [ ] **Step 2:** Publish the comm leaf's **public** certificate where
  fog-client fetches it — per Task 0.3's finding, expected to be the
  existing `$webdirdest/management/other/ssl/srvpublic.crt` path, which
  today holds the web vhost's leaf. Publishing the comm leaf there is what
  decouples the two roles without moving anything the client looks for.
  The web vhost's own certificate stays at `$sslpubcert` under the Web zone
  and is no longer the file served at that path. **Only the public
  certificate is published — `.commLeaf.key` never leaves `$sslpath`**, and
  must be readable by the web user (it is what `certDecrypt()` opens) while
  not being web-*served*; mirror the existing `chown $apacheuser` +
  non-web-root placement `$sslpath` already provides.
- [ ] **Step 3:** `bash -n lib/common/functions.sh`.
- [ ] **Step 4:** In `fogbase.class.php`, change `certDecrypt()`'s
  `.srvprivate.key` literal (`:2034-2042`) to resolve `.commLeaf.key`
  instead when `pkiMode == split` — read the mode through whatever
  mechanism this class already uses for install-time config (check
  `config.class.php`'s generated constants; this class already reads
  settings that way elsewhere). **In `flat` mode this branch must not exist
  at all — the existing `.srvprivate.key` line stays completely untouched as
  the `else`.** This is the one PHP change in this entire plan; keep the
  diff minimal and reversible. Note the directory it resolves against is the
  storage node's `sslpath` **database column**, not `$sslpath` from
  `.fogsettings` — the comm key must be written where that column points
  (confirmed in Task 0.3 Step 4).
- [ ] **Step 5:** Manual verify (test VM, fresh install, no flags needed —
  `split` is the default): register a real fog-client against the split-mode
  server, confirm `authorize()` succeeds (check `error_log`/task-scheduler
  logs for a clean `#!ok`/token exchange, not `#!ihc`).
- [ ] **Step 6:** The decoupling proof — the test that shows this task
  actually solved the problem it exists for: on that same split-mode server,
  replace the **web vhost's** certificate and key (simulate an ACME renewal
  by regenerating `$sslpubcert`/`$sslprivkey`, or run `installfog.sh -Y
  --recreate-keys`), then confirm an already-registered fog-client still
  authenticates. On a `flat`-mode server the identical action breaks client
  auth — run it both ways and record the difference, since that contrast is
  the whole justification for the Client zone.
- [ ] **Step 7:** Regression check: same registration test against a
  `flat`-mode fresh install, confirm identical success — `certDecrypt()`'s
  `else` branch is exercised, matching pre-patch behavior.
- [ ] **Step 8: Commit**
  ```bash
  git add lib/common/functions.sh packages/web/lib/fog/fogbase.class.php
  git commit -m "Add createClientIntermediateCA(); decouple certDecrypt()'s key from the web TLS leaf in split mode"
  ```

### Task 1.5: `validateExternalCA(zone)` + new CLI flags

**Files:**
- Modify: `lib/common/functions.sh` — parameterize `validateExternalCA()`
  by zone (`web`|`client`|`root`), writing into `_pkiZoneDir "$1"` instead of
  the hardcoded `$sslpath/CA/`; add the CN-mismatch warning for `zone ==
  client`.
- Modify: `bin/installfog.sh` — new flags: `--legacy-pki` (fresh install
  only — explicit opt-out of the new `split` default, reproduces today's
  flat behavior byte-for-byte; see Task 1.1), `--restructure-pki`
  (existing-server-only from this point forward — Task 1.1's default
  already puts a fresh install into `split` with no flag needed, so this
  flag's only remaining job is Phase 2's confirmation-gated migration of an
  already-installed `flat` server; still accepted as a harmless no-op on a
  fresh install for anyone scripted around the old opt-in behavior, but no
  longer documented as the fresh-install opt-in),
  `--web-ca-cert:`/`--web-ca-key:`/`--web-ca-root:` (aliases that, when
  `pkiMode == split`, mean exactly what `--ca-cert`/`--ca-key`/`--ca-root`
  mean today — see design doc's non-goal on not deprecating the old flags
  yet: **both spellings work simultaneously**, old ones implicitly target
  the Web zone), `--client-ca-cert:`/`--client-ca-key:`/`--client-ca-root:`,
  `--client-ca-cn:` (Task 1.1's escape hatch), `--root-ca-cert:`/`--root-ca-key:`.
- Modify: `bin/updatefog.sh` — pass-through for all of the above, same
  pattern as `--hostname`/`--extra-server-name`.

**Interfaces:**
- Produces: nothing new consumed elsewhere.
- Consumes: Task 1.1's `_pkiZoneDir`.

- [ ] **Step 1:** Change `validateExternalCA()`'s signature to
  `validateExternalCA(zone)`, replace its four hardcoded `$sslpath/CA/...`
  writes (`functions.sh:3323-3329`) with `_pkiZoneDir "$zone"`-based paths,
  and replace its three hardcoded `$extcacert`/`$extcakey`/`$extcaroot`
  reads with zone-prefixed variable names (`$webExtCACert`/... for `web`,
  `$clientExtCACert`/... for `client`, `$rootExtCACert`/... for `root`) —
  **existing callers in `flat` mode call it as `validateExternalCA web` and
  it reads `$extcacert`/`$extcakey`/`$extcaroot` exactly as before** (keep
  the old variable names as the `web` zone's names for backward
  compatibility with anyone's existing `.fogsettings`/scripts referencing
  them).
- [ ] **Step 2:** Add the CN check right after the existing chain-verify
  check (`functions.sh:3315-3320`), only when `zone == client`:
  ```bash
  if [[ $zone == client ]]; then
      local actualCN
      actualCN=$(openssl x509 -in "$extcert" -noout -subject -nameopt multiline 2>/dev/null | awk -F'= *' '/commonName/{print $2}')
      if [[ "$actualCN" != "$fogClientCACN" ]]; then
          echo "  WARNING: imported Client Communication CA's CN ('$actualCN')"
          echo "  does not match the expected value ('$fogClientCACN')."
          echo "  fog-client's exact requirement here is unverified as of this"
          echo "  release (see the design doc's Open Risks) -- this may or may"
          echo "  not matter for your fog-client version. Proceeding anyway."
      fi
  fi
  ```
- [ ] **Step 3:** Add the new `installfog.sh` flags following the exact
  pattern of the existing `--ca-cert`/`--ca-key`/`--ca-root` case branches
  (`bin/installfog.sh:292-320`) — one staging var per flag, applied in the
  command-line-evaluation block. `--legacy-pki` is the simplest of the new
  flags — no argument, just `slegacyPki=1`, applied as `[[ $slegacyPki -eq 1
  ]] && pkiMode="flat"` in the same block, positioned to run *after* Task
  1.1's `caCreated`-based default so it can override that default, and
  *before* nothing else needs to check it since `pkiMode` is fully resolved
  by this point.
- [ ] **Step 4:** `bash -n lib/common/functions.sh && bash -n
  bin/installfog.sh && bash -n bin/updatefog.sh`.
- [ ] **Step 5:** Manual verify (test VM): re-run today's existing
  `--external-ca`/`--ca-cert`/`--ca-key`/`--ca-root` test from
  `docs/EXTERNAL_CA_AND_LETSENCRYPT.md` unchanged — confirm identical
  behavior (this exercises `validateExternalCA web` in `flat` mode, proving
  the parameterization didn't change anything for existing users).
- [ ] **Step 6:** Manual verify split mode: `installfog.sh -Y --client-ca-cert
  ... --client-ca-key ... --client-ca-root ...` on a fresh install (no
  `--restructure-pki` needed — `split` is already the default) against a
  locally-minted CN-mismatched test CA → confirm the warning prints and the
  install still completes.
- [ ] **Step 7: Commit**
  ```bash
  git add lib/common/functions.sh bin/installfog.sh bin/updatefog.sh
  git commit -m "Parameterize validateExternalCA() by zone; add --client-ca-*/--web-ca-*/--root-ca-* flags"
  ```

### Task 1.6: Remove `bin/setupacme.sh` — ACME/Let's Encrypt is not FOG-managed

**Files:**
- Delete: `bin/setupacme.sh`.
- Modify: `docs/EXTERNAL_CA_AND_LETSENCRYPT.md` — remove the "Automating
  renewal with setupacme.sh" subsection (added by the already-merged PR
  #1014); replace with a short pointer to `docs/PKI_ZONES.md` (Task 1.8)
  for the current self-service guidance.

**Interfaces:**
- Consumes: nothing.
- Produces: nothing — this is a pure removal, no other task in this plan
  depends on `bin/setupacme.sh` existing (Task 1.5's Web-zone CA-import flags
  are independent of it; an admin who wants ACME automation now runs
  `certbot`/`acme.sh` themselves entirely outside FOG).

**Rationale (from the design doc's Non-goals):** on reflection, FOG should
not own any ACME client integration at all, even in the narrow,
CA-never-touched form `bin/setupacme.sh` already had. It's simple enough for
an admin to run their own ACME client and drop the result into
`$sslpubcert`/`$sslprivkey` — a safe drop-in once the paired
customization-preservation plan's vhost managed-block has landed. A future
GUI-level plugin is plausible later, not designed toward now.

- [ ] **Step 1:** `git rm bin/setupacme.sh`.
- [ ] **Step 2:** In `docs/EXTERNAL_CA_AND_LETSENCRYPT.md`, remove the
  "Automating renewal with setupacme.sh" subsection in full (the one added by
  commit `deae3d41f`), and replace it with:
  ```markdown
  FOG does not automate ACME renewal itself. Run `certbot`/`acme.sh` (or
  whatever ACME client you prefer) yourself, on your own schedule, and
  install the renewed leaf at the Web zone's existing paths (`$sslpubcert`/
  `$sslprivkey`, from `.fogsettings`) followed by a web server reload. See
  `docs/PKI_ZONES.md` for the full self-service pattern once the three-zone
  PKI split has landed.
  ```
- [ ] **Step 3:** Grep the repo for any remaining reference to `setupacme`
  (`grep -rn setupacme .` from the repo root) and remove/update any hits
  (e.g. other doc cross-links, `bin/installfog.sh`'s `usage()` if it
  mentions it).
- [ ] **Step 4:** Manual verify: confirm `bin/setupacme.sh` no longer exists
  and `docs/EXTERNAL_CA_AND_LETSENCRYPT.md` reads sensibly with the
  subsection removed (no dangling cross-references, no orphaned heading
  numbering if the doc uses a table of contents).
- [ ] **Step 5: Commit**
  ```bash
  git add -A docs/EXTERNAL_CA_AND_LETSENCRYPT.md
  git rm bin/setupacme.sh
  git commit -m "Remove bin/setupacme.sh -- ACME/Let's Encrypt is not a FOG-managed feature"
  ```

### Task 1.7: Interactive PKI-scenario prompt for fresh installs

**Files:**
- Modify: `lib/common/newinput.sh` — new prompt block, modeled on the
  existing `hostname` prompt (`newinput.sh:17-35`).
- Modify: `bin/installfog.sh` — ensure the staging variables the prompt
  populates (`pkiMode` and the per-zone `--*-ca-*` values) are the *same*
  variables Task 1.5's flags populate, applied in the same
  post-`.fogsettings` evaluation block — no new variables, no second code
  path.

**Interfaces:**
- Produces: `$pkiMode` and, if scenario (c) is chosen, the per-zone CA
  cert/key/root paths — identical in shape to what Task 1.5's flags already
  produce.
- Consumes: nothing new; reuses `validateExternalCA(zone)` from Task 1.5 for
  scenario (c)'s path validation.

- [ ] **Step 1:** In `lib/common/newinput.sh`, add a new prompt block
  immediately after the existing `hostname` prompt loop, gated the same way:
  only runs when `[[ -z $pkiMode ]]` (not already set by a flag or persisted
  `.fogsettings`) **and** the script is running interactively (i.e. the same
  condition that already skips this whole file under `-Y`/a loaded update —
  see `bin/installfog.sh:719`'s existing `[[ ! $doupdate -eq 1 ||
  ! $fogupdateloaded -eq 1 ]] && . ../lib/common/input.sh` gate, which this
  new block relies on unchanged).
- [ ] **Step 2:** Prompt text and choices (plain numbered menu, matching this
  file's existing prompt style), with the new default pre-selected:
  ```
  How should FOG manage its certificates?
    1) Split PKI (recommended, default) -- separate FOG-generated
       Root/Web/Client CAs; lets you replace the web certificate
       independently of what fog-client trusts. [Press Enter for this]
    2) Split PKI, bring your own CA(s) -- same as (1), but you supply your
       own certificate(s) for one or more zones (e.g. an internal AD CS
       sub-CA)
    3) Legacy -- one self-signed CA for everything (today's pre-split
       behavior; simpler, lower overhead, a permanent supported option)
  ```
  Pressing Enter with no answer, or explicitly choosing `1`, sets
  `pkiMode=split` with no further prompts (Task 1.2-1.4's generation path
  runs entirely automatically) — matching the non-interactive default from
  Task 1.1. Selection `2` sets `pkiMode=split` and then prompts per zone
  ("Bring your own CA for the Web zone? Client Communication zone? Root?"),
  each "yes" answer prompting for cert/key/root file paths, stored into the
  exact same staging variables Task 1.5's
  `--web-ca-*`/`--client-ca-*`/`--root-ca-*` flags populate. Selection `3`
  sets `pkiMode=flat` — the same value `--legacy-pki` sets
  non-interactively — reproducing today's pre-split behavior byte-for-byte.
- [ ] **Step 3:** Each entered path is validated with the same check Task
  1.5's flag parsing already runs (file exists and is readable) before being
  accepted — reject and re-prompt on a bad path, do not silently proceed with
  the previous run's or an empty value.
- [ ] **Step 4:** `bash -n lib/common/newinput.sh && bash -n bin/installfog.sh`.
- [ ] **Step 5:** Manual verify (test VM, real interactive terminal — this
  cannot be tested under `-Y`, which skips this file entirely by design):
  run `./installfog.sh` with no PKI-related flags, confirm the new prompt
  appears after the hostname prompt, and that pressing Enter (or choosing
  option 1) produces the same `$sslpath/CA/root|web|client` tree Task
  1.3/1.4's default `split` path already produces with no flags at all.
  Confirm choosing option 3 (legacy) produces zero difference from running
  with `--legacy-pki` — no new directories under `$sslpath/CA/`,
  `pkiMode=flat` in `.fogsettings`, matching pre-this-design flat behavior
  byte-for-byte.
- [ ] **Step 6: Commit**
  ```bash
  git add lib/common/newinput.sh bin/installfog.sh
  git commit -m "Add interactive PKI-scenario prompt for fresh installs"
  ```

### Task 1.8: Secure Boot intermediate CA + leaf signing (gated on Task 0.2)

**Files:**
- Modify: `lib/common/functions.sh` — new `createSecureBootIntermediateCA()`;
  `pkiMode` gate at the top of `_ensureSecureBootKeys()` (`functions.sh:4625`)
  keeping its entire current body as the `flat` branch; `--addcert` in
  `_resignKernels()` (`functions.sh:5080-5081`); `$secureBootMokCert` swap in
  `_publishSecureBootKit()` (`functions.sh:4793-4795`); add
  `secureBootMokCert` to `writeUpdateFile()`'s `managedKeys`.
- Modify: `bin/installfog.sh` — `--secureboot-ca-cert:`/`--secureboot-ca-key:`
  for the bring-your-own case (an admin supplying their own SB intermediate),
  alongside the existing `--secure-boot-key`/`--secure-boot-cert` (which keep
  their current meaning: supply the *leaf*).

**Interfaces:**
- Produces: `$secureBootMokCert` — **what gets enrolled in firmware**. In
  `flat` mode it is assigned the same path as `$secureBootCert` (today's
  behavior, no downstream branching); in `split` mode it is the intermediate.
- Consumes: `createRootCA()` and `_issueIntermediateCA()` from Tasks 1.2/1.3.

**Blocked on Task 0.2.** Do not start until shim's chain-validation behavior
is confirmed. If Task 0.2 came back negative, skip this task entirely and
record why.

- [ ] **Step 1:** Add `createSecureBootIntermediateCA()`, calling
  `_issueIntermediateCA "FOG Secure Boot CA"` into
  `${fogprogramdir}/secureboot/ca/`, then issuing this server's code-signing
  leaf into `${fogprogramdir}/secureboot/leaf/` with the **same extension
  profile `_ensureSecureBootKeys()` already writes** (`functions.sh:4660-4673`
  — `basicConstraints=critical,CA:FALSE`, `extendedKeyUsage=codeSigning`,
  `subjectKeyIdentifier=hash`), just signed by the intermediate rather than
  self-signed. Reuse that exact `mok.cnf` heredoc rather than writing a new
  one — the OpenSSL-1.0.2-compat reason it exists (`-addext` unavailable on
  older RHEL) applies identically here. Leaf `-days 365`; intermediate
  `-days 7300`. Preserve the directory's `0700 root:root` and the key's
  `0600` — the `fog-sign-kernel` sudo helper's whole separation model
  depends on the web user never being able to read these.
- [ ] **Step 2:** Gate `_ensureSecureBootKeys()`. Its existing body becomes
  the `flat` branch **verbatim** — do not refactor it, do not "improve" it;
  its never-regenerate guarantee (`functions.sh:4620-4624`) is what protects
  every already-enrolled machine in the field. Add at the top:
  ```bash
  # split mode: firmware enrolls the INTERMEDIATE, kernels are signed by a
  # short-lived leaf issued from it. Rotating that leaf then costs nothing,
  # where today rotating the (self-signed, directly-enrolled) key means a
  # physical MokManager trip to every machine.
  if [[ $pkiMode == split && ${secureboot:-1} != 0 ]]; then
      createSecureBootIntermediateCA
      return 0
  fi
  ```
  and, in the `flat` path, set `secureBootMokCert="$secureBootCert"` so both
  modes leave the same two variables populated for downstream consumers.
- [ ] **Step 3:** `_resignKernels()` — add the chain. Change
  `functions.sh:5080-5081` from:
  ```bash
  if sbsign --key "$secureBootKey" --cert "$certpem" \
          --output "$kpath" "${kpath}.unsigned" >>$error_log 2>&1; then
  ```
  to add `--addcert` when the enrolled cert differs from the signing cert
  (i.e. split mode), building the argument as an array so `flat` mode passes
  no extra flag at all and its command line is byte-identical to today:
  ```bash
  local addcert=()
  [[ -n $secureBootMokCert && "$(readlink -f "$secureBootMokCert")" != "$(readlink -f "$certpem")" ]] \
      && addcert=(--addcert "$secureBootMokCert")
  if sbsign --key "$secureBootKey" --cert "$certpem" "${addcert[@]}" \
          --output "$kpath" "${kpath}.unsigned" >>$error_log 2>&1; then
  ```
  Leave the `sbverify --cert "$certpem"` idempotency check (`:5073`)
  untouched — it verifies against the signing leaf, which is still what
  produced the signature.
- [ ] **Step 4:** `_publishSecureBootKit()` — change the three
  `$secureBootCert` references in the DER-conversion block
  (`functions.sh:4784`, `4793`, `4795`) to `$secureBootMokCert`. Nothing else
  in that function changes; in `flat` mode the two variables are the same
  path, so its output is identical to today.
- [ ] **Step 4a: The Setup Mode / db path — do not skip this.** MokManager is
  only one of two enrollment routes. `_publishSecureBootAuthVars()` builds a
  `db` containing Microsoft's CAs plus **FOG's signing certificate**
  (`packages/secureboot/fog-build-sb-authvars:163`). If `MOK.der` becomes the
  intermediate while `db` keeps the leaf, rotating a leaf silently strands
  every Setup-Mode-enrolled client while appearing to work for
  MokManager-enrolled ones. Changes:
  - `functions.sh:5271-5272` (the `.fog-secureboot` writer): add
    `SECUREBOOT_MOK_CERT=${secureBootMokCert}` alongside the existing
    `SECUREBOOT_KEY`/`SECUREBOOT_CERT`.
  - `packages/secureboot/fog-build-sb-authvars`: read `SECUREBOOT_MOK_CERT`
    and use it for `fosCert` (line 63/163) so the **intermediate** lands in
    `db`. Fall back to `SECUREBOOT_CERT` when unset, so an existing
    `.fog-secureboot` from a `flat` install keeps working unchanged.
  - `packages/secureboot/fog-sign-kernel`: this is the sudo helper behind the
    web UI's Kernel Update page — a signing path entirely separate from
    `_resignKernels()`, and easy to miss. It must pass
    `--addcert "$SECUREBOOT_MOK_CERT"` when that differs from
    `SECUREBOOT_CERT` (line 85), or a kernel downloaded through the GUI is
    signed with no chain attached and fails to boot on exactly the clients
    this design serves.
  - Putting a CA in `db` is the standard UEFI model, not a workaround —
    Microsoft's own `db` entries are CAs and Windows validates by chaining to
    them. Verify as part of Task 0.2 on the same hardware: enroll via Setup
    Mode rather than MokManager, then confirm a leaf-signed kernel boots and
    still boots after the leaf is rotated.
- [ ] **Step 5:** `bash -n lib/common/functions.sh && bash -n bin/installfog.sh`.
- [ ] **Step 6:** Manual verify, split mode (test VM + a real UEFI client):
  fresh `./installfog.sh -Y` → confirm
  `openssl verify -CAfile $sslpath/CA/root/.fogRootCA.pem -untrusted
  $fogprogramdir/secureboot/ca/.fogSBCA.pem $fogprogramdir/secureboot/leaf/sign.pem`
  succeeds; `sbverify --list $webdirdest/service/ipxe/bzImage` lists **both**
  the leaf and the intermediate; `MOK.der` published under
  `$webdirdest/service/secureboot/` is the **intermediate** (`openssl x509
  -in MOK.der -inform der -noout -subject` shows `CN = FOG Secure Boot CA`);
  and a client that enrolled that MOK boots the signed kernel.
- [ ] **Step 7:** The payoff test — rotate the leaf without re-enrolling:
  delete `$fogprogramdir/secureboot/leaf/`, re-run `installfog.sh -Y` (a new
  leaf is issued from the same intermediate), and confirm the **same**
  already-enrolled client still boots with no firmware interaction. This is
  the single test that proves the whole point of this task.
- [ ] **Step 8:** Regression, flat mode: on a server with an existing
  `MOK.key`/`MOK.pem`, run `./installfog.sh -Y` → confirm the existing
  keypair is untouched (compare checksums before/after), `MOK.der` published
  is still the same self-signed cert, `sbsign` was invoked with **no**
  `--addcert`, and an already-enrolled client boots exactly as before.
- [ ] **Step 9: Commit**
  ```bash
  git add lib/common/functions.sh bin/installfog.sh
  git commit -m "Issue Secure Boot code-signing leaves from a FOG Secure Boot CA intermediate"
  ```

---

### Task 1.9: Certificate path indirection (canonical paths + symlinks)

**Files:**
- Modify: `lib/common/functions.sh` — fix the two mismatched symlink guards
  (`functions.sh:3497-3498`); add a `_linkCanonical()` helper; apply it to
  every path this design introduces.
- Modify: `bin/installfog.sh` — `usage()` text noting that any `--*-ca-*`
  path may live outside FOG's directories.

**Interfaces:**
- Produces: `_linkCanonical(realpath, canonicalpath)` — ensures
  `canonicalpath` resolves to `realpath`, as a no-op when they are already
  the same file. Every FOG consumer (vhost, `_resignKernels()`,
  `_publishSecureBootKit()`, `certDecrypt()`) reads only canonical paths.
- Consumes: nothing new.

**Why this matters beyond tidiness:** it is what lets an admin keep certs in
`/etc/letsencrypt/live/...` or `/etc/pki/...` without the vhost ever
changing — and, combined with the paired customization-preservation plan's
managed-block vhost, means relocating a certificate stops being a config
edit at all.

- [ ] **Step 1:** Add the helper, next to the existing link block:
  ```bash
  # Canonical-path indirection: FOG's own consumers (vhost, sbsign,
  # certDecrypt) only ever reference the canonical path, so the real file may
  # live anywhere -- /etc/pki, /etc/letsencrypt/live, a mounted secret. This
  # is why relocating a certificate never requires a vhost rewrite.
  #
  # Guarded against ln -sf X X: on a default install the "real" path IS the
  # canonical one, and GNU ln refuses a self-link with an error into the log.
  _linkCanonical() {
      local real="$1" canon="$2"
      [[ -z $real || -z $canon ]] && return 0
      [[ "$(readlink -f "$real")" == "$(readlink -f "$canon")" ]] && return 0
      ln -sf "$real" "$canon" >>$error_log 2>&1
  }
  ```
- [ ] **Step 2:** Replace `functions.sh:3497-3500` with four
  `_linkCanonical` calls, fixing the two guards that currently test
  `$sslpath/.fogCA.key`/`.fogCA.pem` while linking to
  `$sslpath/CA/.fogCA.key`/`.pem`:
  ```bash
  _linkCanonical "$sslcakey"   "$sslpath/CA/.fogCA.key"
  _linkCanonical "$sslcapem"   "$sslpath/CA/.fogCA.pem"
  _linkCanonical "$sslcsr"     "$sslpath/fog.csr"
  _linkCanonical "$sslprivkey" "$sslpath/.srvprivate.key"
  ```
  Behavior is unchanged on a default install (all four are no-ops); on an
  install with any of those variables pointed elsewhere, the canonical path
  now actually resolves, which is what the original code intended.
- [ ] **Step 3:** Apply the same helper to the paths introduced by Tasks
  1.2/1.3/1.4/1.8 (root, web, client, SB intermediate and leaves) so
  bring-your-own paths behave identically across all zones.
- [ ] **Step 4:** `bash -n lib/common/functions.sh`.
- [ ] **Step 5:** Manual verify (test VM): install normally, confirm nothing
  changed (`ls -la $sslpath` shows real files, no new symlinks, no `ln`
  errors in `$error_log` — the last of which is an *improvement*, since
  today's mismatched guards log one every run). Then move
  `.srvprivate.key` to `/etc/pki/fogtest/`, set `sslprivkey` in
  `.fogsettings` accordingly, re-run, and confirm the canonical path becomes
  a working symlink, the vhost is unchanged, and both the web UI and a
  fog-client checkin still work.
- [ ] **Step 6:** Document the two caveats in `docs/PKI_ZONES.md` (Task
  1.10): SELinux labels follow the symlink *target*, so a relocated cert on
  a RHEL-family box may need `restorecon`/`semanage fcontext`; and a private
  key relocated into a world-readable directory silently defeats the
  `0600 root:root` separation the `fog-sign-kernel` sudo helper depends on.
- [ ] **Step 7: Commit**
  ```bash
  git add lib/common/functions.sh bin/installfog.sh
  git commit -m "Add canonical-path symlink indirection so certs can live outside FOG's directories"
  ```

---

### Task 1.10: Split `$netbootproto` from `$httpproto`

**Files:**
- Modify: `lib/common/functions.sh` — new `$netbootproto` default logic
  alongside `pkiMode`'s (Task 1.1); `configureDefaultiPXEfile()`
  (`functions.sh:1037-1042`); the vhost HTTP→HTTPS redirect branches
  (`functions.sh:3571` nginx, `:3814` Apache); add `netbootproto` to
  `writeUpdateFile()`'s `managedKeys`.
- Modify: `bin/installfog.sh` — `--netboot-proto <http|https>` override.

**Interfaces:**
- Produces: `$netbootproto` — the protocol iPXE uses to reach `boot.php`.
  Defaults to `http` when the web certificate comes from a private CA (FOG
  PKI or an imported internal CA), and follows `$httpproto` when it comes
  from a public CA.
- Consumes: `$pkiMode`, `$httpproto`, `$externalca`.

**Note — no PHP change is expected here.** `FOGBase::$httpproto`
(`packages/web/lib/fog/fogbase.class.php:481-483`) is derived from the
*current request's* `$_SERVER['HTTPS']`, so every boot-menu URL
`bootmenu.class.php` emits (`:286`, `:292`, `:458`) already inherits
whatever protocol iPXE connected with. Verify this empirically in Step 5
before concluding no PHP work is needed — the whole task's economy rests on
it.

- [ ] **Step 1:** Default `$netbootproto` next to Task 1.1's `pkiMode`
  block:
  ```bash
  # iPXE can only validate a PUBLIC chain (via its ca.ipxe.org crosscert
  # fallback). A FOG-PKI or internal-CA web certificate is fine for browsers,
  # fog-client and the API -- but netboot fetches from the pre-boot
  # environment have no path to that root, so they stay on HTTP rather than
  # forcing the iPXE rebuild that forfeits the signed Secure Boot shim.
  if [[ -z $netbootproto ]]; then
      if [[ $httpproto == https && $pkiMode != split && $externalca != yes ]]; then
          netbootproto="$httpproto"
      elif [[ $httpproto == https ]]; then
          netbootproto="http"
      else
          netbootproto="$httpproto"
      fi
  fi
  ```
  (An admin who imported a genuinely public cert into the web zone can pass
  `--netboot-proto https` explicitly; there is no reliable way to detect
  "this CA is publicly trusted" from the certificate alone, so this defaults
  conservatively and documents the override.)
- [ ] **Step 2:** `configureDefaultiPXEfile()` — change the `chain
  ${httpproto}://...boot.php` in the generated `default.ipxe`
  (`functions.sh:1040`) to `${netbootproto}`. This is the only occurrence
  in that function.
- [ ] **Step 3:** Exclude the netboot paths from the vhost's HTTP→HTTPS
  redirect, in both branches, **only when `$netbootproto != $httpproto`**
  (so a public-CA install's config is unchanged from today):
  - nginx: in the port-80 server block that currently issues the redirect,
    add a preceding `location ^~ ${webroot}service/ipxe/ { ... }` that
    serves normally instead of redirecting.
  - Apache: guard the existing redirect with a negative match on the same
    prefix (e.g. a `RewriteCond %{REQUEST_URI} !^${webroot}service/ipxe/`
    ahead of the redirect rule).
  This is the fiddliest part of the change and the most likely to differ
  between distro layouts — test both families rather than one.
- [ ] **Step 4:** `bash -n lib/common/functions.sh && bash -n bin/installfog.sh`.
- [ ] **Step 5:** Manual verify (test VM, `pkiMode=split`, FOG-PKI web cert):
  - The web UI loads over HTTPS.
  - `grep chain $tftpdirdst/default.ipxe` shows `http://`.
  - `curl -sI http://<server>${webroot}service/ipxe/boot.php` returns **200**,
    not a 301/302 to HTTPS.
  - **The key assumption check:** the body of that same `boot.php` response
    contains `http://` kernel/init URLs, confirming `$httpproto`'s
    request-derived behavior carries through with no PHP change.
  - A real client PXE-boots end to end and images successfully.
- [ ] **Step 6:** Regression: a public-CA install with `--netboot-proto https`
  (or `pkiMode=flat` + `httpproto=https`) still redirects everything to HTTPS
  exactly as today, with no `service/ipxe/` exclusion emitted into the vhost.
- [ ] **Step 7: Commit**
  ```bash
  git add lib/common/functions.sh bin/installfog.sh
  git commit -m "Split netbootproto from httpproto so private-CA installs keep HTTPS web with HTTP netboot"
  ```

---

### Task 1.11: Document Phase 1

**Files:**
- New: `docs/PKI_ZONES.md` (cross-linked from
  `docs/EXTERNAL_CA_AND_LETSENCRYPT.md`'s "How FOG uses certificates" table
  and from Task 1.6's replacement text).

- [ ] **Step 1:** Write the three-zone model, the directory layout, the new
  flags and the interactive prompt, and explicitly the **certDecrypt()
  finding** (design doc's Context section) — this is the one piece of
  institutional knowledge most likely to get re-lost if it isn't written
  down plainly for the next person touching `createSSLCA()`. State plainly,
  near the top, that `split` is the **default** for fresh installs as of
  this feature, and that `--legacy-pki`/the prompt's legacy choice is a
  **permanent, fully supported** alternative, not a deprecated fallback —
  an admin reading this doc after an upgrade should not have to guess which
  mode is "the real one." Include the self-service ACME/Let's Encrypt
  guidance (Task 1.6) as its own clearly labeled section: admin runs their
  own ACME client, drops the result into `$sslpubcert`/`$sslprivkey`, FOG
  has no role in and no visibility into the process. Reuse the Mermaid
  diagrams from `docs/superpowers/specs/2026-08-07-three-zone-pki-separation-design.md`
  (Diagram 1 — default FOG PKI; Diagram 2 — drop-in options; and the
  scenario-decision flowchart) rather than redrawing them — copy the fenced
  ```mermaid blocks verbatim.
- [ ] **Step 1b:** Document the Secure Boot rotation story explicitly, since
  it is the least obvious payoff of the whole design: enrolling the
  intermediate once means signing leaves can be rotated or revoked with no
  firmware trip, and an admin bringing their own PKI issues a Secure Boot
  intermediate from their root and hands FOG a leaf. Include the honest
  limits: existing servers keep their self-signed MOK (no migration is
  offered), and per-node leaves are Phase 3.
- [ ] **Step 1c:** Document the protocol matrix from the design doc's
  "Protocol selection" section as a table — which PKI choice yields HTTPS
  where, that iPXE only trusts public chains, that a FOG/internal-PKI cert
  can carry IP and DNS-alias SANs for browser/client trust but still won't
  serve netboot over HTTPS, and that Let's Encrypt works for netboot **only**
  on an FQDN in a domain you own (not the short hostname, not an IP) with
  `FOG_WEB_HOST` set to match.
- [ ] **Step 2: Commit**
  ```bash
  git add docs/PKI_ZONES.md docs/EXTERNAL_CA_AND_LETSENCRYPT.md
  git commit -m "Document the three-zone PKI split (Phase 1)"
  ```

---

## Phase 2: Existing-server migration (blocked on Task 0.1's answer)

Written for the **pessimistic** Phase 0 outcome (fog-client caches its pin at
registration time only, requiring full re-registration to change it). If
Phase 0's answer is optimistic, Task 2.2 collapses to "nothing — wait one
checkin cycle," which is a strictly easier version of the same task
breakdown, not a different one.

### Task 2.1: `--restructure-pki` on an already-installed server — generate-only, no cutover

**Files:** Modify `bin/installfog.sh` (the confirmation gate described in the
design doc's Error Handling section), `lib/common/functions.sh`
(`createClientIntermediateCA()` gains a `--dont-activate` mode that generates
`$sslpath/CA/client/.fogClientCA.pem` and publishes it to a **side-by-side**
path, e.g. `$webdirdest/management/other/ca.cert.new.der`, without touching
the live `ca.cert.der`/`ca.cert.pem` fog-client already trusts).

- [ ] **Step 1:** Add the confirmation gate: `--restructure-pki` against a
  server where `$caCreated == yes` (i.e., not a fresh install) requires
  either an interactive "type YES to confirm" prompt or the explicit
  `--i-understand-this-will-require-client-repinning` flag, even under `-Y`.
- [ ] **Step 2:** Add the side-by-side publish path, gated on a new
  `--dont-activate-client-ca` flag (default when restructuring an *existing*
  server; irrelevant/no-op on a fresh install, which has no live pin to
  protect).
- [ ] **Step 3:** Manual verify (test VM with an already-registered
  fog-client): run `--restructure-pki --dont-activate-client-ca` → confirm
  the existing fog-client's next checkin still succeeds unmodified (the live
  `ca.cert.der` never changed), and `ca.cert.new.der` is now downloadable at
  the new path.
- [ ] **Step 4: Commit**

### Task 2.2: Push the new pin to the existing fleet via a snapin (reuse, not invent)

**Files:** None in this repo — this is an **admin-authored FOG snapin**,
using the existing `SnapinManager`/`SnapinTask` mechanism
(`packages/web/lib/fog/snapin*.class.php`) exactly as any other snapin is
created and assigned today. No new server-side task-scheduler code is
needed — this reuses the existing pull-based snapin delivery
(`packages/service/FOGSnapinReplicator`), which already runs with elevated
privileges and already supports "fetch a file, then run a script against
it," which is exactly this task's shape.

- [ ] **Step 1:** Author (as an admin action, documented in Task 2.4, not
  built into the installer) a snapin whose payload: downloads
  `https://<fogserver>/fog/management/other/ca.cert.new.der` over the
  **still-currently-trusted** channel (this works precisely because Task
  2.1 never touched the live pin), then installs it at whatever path
  fog-client re-reads its pinned cert from. **This exact path is the
  cross-repo unknown flagged in the design doc — do not guess at it here;
  confirm against zazzles source or the fog-client installer's own
  documentation before writing this snapin's script for real.**
- [ ] **Step 2:** Assign the snapin to "All Hosts" (or a pilot group first).
  It is pulled at each client's next normal checkin — no new transport, no
  new scheduling.
- [ ] **Step 3:** Monitor via the existing Snapin Job success/fail reporting
  (`snapinjob.class.php`) until fleet coverage is judged sufficient. This is
  a self-service, admin-paced rollout, not an atomic cutover — no code in
  this repo needs to "know" when it's done.

### Task 2.3: Cutover — activate the new Client CA

**Files:** Modify `bin/installfog.sh`/`lib/common/functions.sh` — a
`--activate-client-ca` flag that copies `ca.cert.new.der`/`.pem` over the
live `ca.cert.der`/`.pem`.

- [ ] **Step 1:** Implement the copy-over, gated the same way Task 2.1's
  generation was (explicit confirmation).
- [ ] **Step 2:** **Do not** bulk-trigger `clearAES()`/"Reset Encryption
  Data" as part of this step. Per the design doc's traced-through
  `authorize()` logic, the CA pin and the `pub_key`/`sec_tok` handshake are
  independent subsystems — a client that already re-pinned in Task 2.2 keeps
  its existing session state and needs no reset. `clearAES()` remains
  available (unchanged, existing admin action) as a manual remedy for any
  individual host that gets stuck, exactly as it is today for unrelated
  causes.
- [ ] **Step 3:** Manual verify: confirm hosts that ran Task 2.2's snapin
  keep working uninterrupted through cutover; confirm a host that did *not*
  yet run it fails its next `authorize()` (expected, pessimistic-case
  disruption for stragglers) and recovers once it does receive the snapin or
  gets manually re-registered.
- [ ] **Step 4: Commit**

### Task 2.4: Document the full migration runbook

**Files:** Extend `docs/PKI_ZONES.md` (Task 1.8) with a "Migrating an
existing server" section covering Tasks 2.1-2.3 as a runbook, explicitly
including the "confirm the client-side pin file path against your fog-client
version before writing the snapin" caveat.

---

## Phase 3: Root offlining, flag consolidation, deprecation timeline

No hard external blocker, but low value in isolation — sequence after Phase
1/2 have real usage to learn from.

### Task 3.1: `--export-root-ca-and-wipe` helper

Exports `.fogRootCA.key` to an admin-given path, then either `chmod 000`s or
(with an explicit `--shred` flag) securely deletes the on-server copy. Model
the confirmation flow on Task 2.1's/2.3's "explicit confirmation required"
pattern.

### Task 3.2: Decide and execute an `--external-ca`/`--ca-cert`/`--ca-key`/`--ca-root` deprecation timeline

Not started until Phase 1 has shipped for at least one release cycle. Options
range from "never deprecate, `--web-ca-*` is just an alias forever" (lowest
risk, recommended default) to "warn-then-remove" (only worth it if the
alias proves confusing in practice). This is a product decision, not an
engineering one — flag for the maintainer, don't pre-decide it in this plan.

### Task 3.3: Per-storage-node Secure Boot signing leaves

Task 1.8 makes this *possible* (each node gets its own leaf from the shared
intermediate, so a compromised node doesn't hand over the fleet's signing
key) but does not build it — issuing a leaf per node needs a CSR round-trip
through the storage-node install and node-registration flow, which doesn't
exist today. Until then nodes serve kernels signed by the master's leaf,
which works but delivers none of the per-node isolation the structure now
permits.

Sketch: node install generates a keypair + CSR locally → registers the CSR
through the existing node-registration path
(`packages/web/maintenance/create_update_node.php`, already used by
`functions.sh:145`) → master signs from `secureboot/ca/` → node stores its
leaf under `secureboot/nodes/`. Requires deciding how the master
authenticates a node's CSR, which is the real design question here, not the
signing itself.

### Task 3.4: Per-location PKI/protocol via the Location plugin

The Location plugin already carries a per-location `protocol` override
(`packages/web/lib/plugins/location/hooks/changeitems.hook.php:118`), which
Task 1.9's `$netbootproto` work should stay compatible with. Extending that
to per-location certificates/signing leaves is a natural follow-on to Task
3.3 but needs its own design pass — not scoped here.

### Task 3.5: Revisit a GUI-level Let's Encrypt plugin (separate design)

Only once Phase 1/2 have real-world usage. Per the design doc's Open Risks
#5 and Task 1.6's removal of `bin/setupacme.sh`: a plugin providing a
GUI-level "enable Let's Encrypt" toggle is plausible, but needs its own
design pass, not a revival of the removed script's approach.

---

### Critical Files for Implementation

- `lib/common/functions.sh` — the bulk of every task: `_pkiZoneDir()`,
  `createRootCA()`, `_issueIntermediateCA()`, the three
  `create*IntermediateCA()` functions, `validateExternalCA(zone)`, the
  `pkiMode` gate in `_ensureSecureBootKeys()` (`:4625`), `--addcert` in
  `_resignKernels()` (`:5080`), `$secureBootMokCert` in
  `_publishSecureBootKit()` (`:4793`), `configureDefaultiPXEfile()`
  (`:1040`), the vhost redirect branches (`:3571` nginx / `:3814` Apache),
  and `writeUpdateFile()`'s `managedKeys` (`:3122`).
- `lib/common/newinput.sh` — the interactive PKI-scenario prompt (Task 1.7).
- `bin/installfog.sh` — every new flag, plus the option-evaluation block.
- `bin/updatefog.sh` — pass-through for the new flags.
- `bin/setupacme.sh` — **deleted** by Task 1.6.
- `packages/web/lib/fog/fogbase.class.php` — `certDecrypt()`'s key-path
  repoint (Task 1.4); also the *reference* for why Task 1.9 needs no PHP
  change (`$httpproto` is request-derived, `:481-483`).
- `packages/web/lib/fog/bootmenu.class.php` — read-only reference for Task
  1.9's protocol verification (`:286`, `:292`, `:458`).
- `docs/EXTERNAL_CA_AND_LETSENCRYPT.md` — setupacme.sh section removed
  (Task 1.6).
- `docs/PKI_ZONES.md` — new, created by Task 1.10.
