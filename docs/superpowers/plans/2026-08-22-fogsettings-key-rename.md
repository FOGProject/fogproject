# `.fogsettings` Key Rename and Unification Plan

> **For agentic workers:** the agreed model is recorded in the comments on
> [FOGProject/fogproject#1120](https://github.com/FOGProject/fogproject/issues/1120).
> Where this document and the issue disagree, the **final comment on #1120 wins** —
> but verify every key against the `managedKeys` array in `writeUpdateFile()`
> itself before trusting either.

> **Status: implemented** on `claude/fogsettings-key-rename-dgdp8i`. 79 → 66 as
> planned, all 79 verified against the `managedKeys` array itself. Test suite
> 101 → 114 (103 on the branch, plus the 11 that arrived with `working-1.6`), all
> passing. **Outcome** at the end of this document records where the finished work
> diverged from the plan and why.

**Goal:** Give every `.fogsettings` key a category prefix
(`UPPERCASE_CATEGORY_lower_snake_case`, nine categories), collapse the duplicate
certificate keys, and rewrite the shell logic to match — so that a setting can no
longer be silently lost, and changing one thing can no longer break an unrelated
thing. **79 managed keys → 66.**

**Architecture:** `.fogsettings` is *sourced*, so a key name **is** a shell
variable name — the rename is necessarily a codebase-wide variable rewrite
(753 references across just 25 of the 79 keys; expect 1000+ in total). That
rewrite is the deliverable, not a cost to minimise. The organising idea is
`_linkCanonical()` (`lib/common/functions.sh`), already documented as *"Make $2
resolve to $1, so FOG can keep referencing a fixed path while the real file lives
wherever the admin keeps it."* Each setting names a **canonical path in FOG's own
tree**; the file there is FOG's own or a symlink out to Let's Encrypt / ADCS /
step-ca. Today the setting holds the admin's path and the canonical path is the
symlink — the change inverts that. Once a slot is one canonical path, the
parallel "imported CA" and "ACME" keys are duplicates of the slot they shadow.

**Tech Stack:** Bash (`bin/installfog.sh`, `lib/common/*.sh`,
`lib/{redhat,ubuntu,alpine,arch}/config.sh`), plus two PHP touch points
(`packages/web/lib/router/route.class.php`, `packages/web/lib/fog/openapi.class.php`).
Tests are standalone shell harnesses under `tests/`, run via `tests/run-all.sh`.

## Global Constraints

- Do not add `declare(strict_types=1)`; match each file's existing idioms.
- The pre-commit hook auto-runs `php-cs-fixer` (PSR-2), regenerates translations,
  and bumps `system.class.php` — expected, don't revert.
- `deprecatedKeys` in `writeUpdateFile()` **only strips lines and carries no
  value.** A rename therefore needs a seed block as well; see Migration.
- Every flag must write an `s`-prefixed shadow applied *after* `.fogsettings` is
  sourced. Writing the real variable directly is silently discarded on upgrade —
  that bug has shipped at least three times (`-E`, `-s`/`-e`, `-S`).
- Adding a key to `managedKeys` turns a hand-set key into a managed one, so the
  admin's value starts being overwritten. That is a behaviour change even when it
  looks like documentation.
- Related: `docs/FOGSETTINGS.md` (mechanics and the four key kinds),
  `docs/adr/0015-install-settings-are-independent-keys.md`,
  `docs/adr/0016-ipxe-enforces-x509-name-constraints.md`, `docs/PKI_ZONES.md`.

---

## The 66 keys

### `PKI_` — 20 (from 29)

Canonical paths, emitted under a `## Derived — do not edit` marker:

| Key | Absorbs |
|---|---|
| `PKI_root_ca_cert` | `rootCAPem` |
| `PKI_root_ca_key` | `rootCAKey` |
| `PKI_web_ca_cert` | `sslcapem` + `extcacert` + `webExtCACert` |
| `PKI_web_ca_key` | `sslcakey` + `extcakey` + `webExtCAKey` |
| `PKI_web_external_root_cert` | `extcaroot` + `webExtCARoot` — **empty on a normal install** |
| `PKI_web_trust_chain` | `sslcachain` |
| `PKI_web_vhost_cert` | `sslpubcert` + `webCertFile` |
| `PKI_web_vhost_key` | `sslprivkey` + `webKeyFile` |
| `PKI_client_encrypt_cert` | **new** — `.srvpublic.crt`, currently a local (`commLeafPem`) |
| `PKI_client_encrypt_key` | **new** — `.srvprivate.key`, currently a local (`commLeafKey`) |
| `PKI_sb_ca_cert` | `secureBootMokCert` — the certificate enrolled in firmware |
| `PKI_sb_codesign_cert` | `secureBootCert` |
| `PKI_sb_codesign_key` | `secureBootKey` |

Inputs and policy: `PKI_client_cert_dir` (`sslpath`) · `PKI_sb_enabled`
(`secureboot`) · `PKI_web_cert_publicly_trusted` (`publicWebCert`) ·
`PKI_allowed_domain_names` (`internalDomains`) · `PKI_internal_subnets`
(`internalSubnets`) · `PKI_san_ip_addresses` (`ipaddresses`) ·
`PKI_san_dns_names` (`extraServerNames`)

**Retired (6):** `sslcsr` · `acmeLeaf` · `externalca` · `catrust` · `caCreated` ·
`sbNameConstraints`

### The rest — 46

| | |
|---|---|
| `NET_` (4) | `NET_interface` · `NET_fog_server_ip` · `NET_subnet_mask` · `NET_hostname` |
| `DHCP_` (7) | `DHCP_enabled` (`dodhcp`+`bldhcp`) · `DHCP_engine` · `DHCP_service_name` · `DHCP_router` (`routeraddress`+`plainrouter`) · `DHCP_dns_server_ip` · `DHCP_range_start` · `DHCP_range_end` |
| `WEB_` (6) | `WEB_server_engine` · `WEB_docroot` · `WEB_root` · `WEB_php_version` · `WEB_url_proto` · `WEB_https_redirect` |
| `BOOT_` (7) | `BOOT_url_proto` · `BOOT_url_proto_forced` · `BOOT_rebuild_ipxe_with_my_ca` · `BOOT_dhcp_delay_seconds` · `BOOT_external_tftp_server` · `BOOT_tftp_options` · `BOOT_kernel_backups_kept` |
| `DB_` (6) | `DB_name` · `DB_host` · `DB_user` · `DB_password` · `DB_external` · `DB_backup_path` |
| `STORAGE_` (2) | `STORAGE_image_share_path` · `STORAGE_rebuild_nfs_exports` |
| `SVC_` (3) | `SVC_user` · `SVC_password` · `SVC_firewall_control` |
| `FOG_` (11) | `FOG_install_type` · `FOG_os_id` · `FOG_os_name` · `FOG_packages` · `FOG_install_lang` · `FOG_send_reports` · `FOG_installed` · `FOG_copy_back_old` · `FOG_update_channel` · `FOG_program_dir` · `FOG_git_path` |

### Decisions

| | |
|---|---|
| Secure Boot | Folded fully into `PKI_` — no `SB_` prefix |
| Derived records | Kept, under a `## Derived — do not edit` marker |
| `externalca` | Prompt-scoped, never persisted |
| `caCreated` | Retired. `osid` kept — but its original reason has lapsed, see below |
| `.fogsettings.pub` | Renamed too, including the `/api/whoami` response — breaking change accepted for 1.6 stable |
| SB name constraints | Off for the SB zone; existing CAs never re-minted |
| Combined cert+key PEMs | **Never** |
| Web zone shape | cert + root + chain, all three kept |
| `DHCP_router` | Not `gateway` — matches `option routers` in ISC and Kea |

---

## What this has to make impossible

Each of these has actually bitten someone. The model only pays off if the code
stops having places where they can recur.

| Scenario | What went wrong | What closes it |
|---|---|---|
| Customise the vhost with your own certificate, then upgrade | FOG regenerated the leaf from the original CSR against a key it no longer owned | Managed-block markers already protect edits outside FOG's region; the symlink test on `PKI_web_vhost_cert` — resolves outside `_pkiZoneDir()` ⇒ externally managed, do not regenerate — closes the rest and replaces `acmeLeaf` |
| Change the snapin SSL certificates | **Broke client communication.** `$sslpath` *is* `$snapindir/ssl`, and `.srvprivate.key`/`.srvpublic.crt` sit directly in it beside `ca.cnf`, `fog.csr`, `dhparam.pem` and legacy CA material | `PKI_client_encrypt_cert`/`_key` name the keypair instead of leaving it implied by a directory. Move the real files into `pki/client/leaf/`; leave `PKI_client_cert_dir` a compatibility symlink for snapins |
| Replace the web certificate (ACME renewal, purchased cert, `-K`) | One file was both the web TLS key and the client-comm key, so a valid new certificate silently broke client auth | Split by `_separateCommKey()`; the model now names both so they cannot be re-conflated |
| `-K` / `-C` | Regenerates `.srvprivate.key` with **no warning at all** — every registered client stops authenticating, symptom arrives later | `_warnClientRepin()`, fired on a fingerprint change against the deployed `srvpublic.crt` |
| Bring your own web CA | Trust anchor lost — the vhost chain terminates in the admin's root while the FOG root is still `rootCAPem` | `_resolveTrustAnchor()` anchors both, deduped by fingerprint (already tested: `tests/trust-anchor.test.sh` Case B) |
| Pass a flag on an upgrade | Silently discarded — the sourced `.fogsettings` overwrote it | The `s`-prefix shadow convention, applied uniformly rather than per-flag |

## Implementation notes

- **Settle two conventions before starting:** both `${var}` and `$var` forms
  appear, and the `s`-prefix shadow becomes e.g. `$sNET_hostname`. Mechanical,
  but pick the spelling up front rather than halfway through.
- **Retiring `acmeLeaf` needs its replacement in the same change** — the symlink
  test above. Without it `createSSLCA()` regenerates from the stale CSR and the
  web server will not start.
- **Swapping the client certificate must warn, never refuse.** New
  `--client-cert`/`--client-key`, required together. Generalise the existing
  boxed warning in `validateExternalCA()` into `_warnClientRepin()`. Note it is
  gated on `$caCreated`, which this change retires — re-key it to a file test or
  it silently stops firing.
- **iPXE embeds the Web CA now.** Point `_resolveIpxeTrust()` at
  `PKI_web_ca_cert` instead of the chain. Narrows iPXE's trust to the
  name-constrained, serverAuth-only intermediate and makes bring-your-own-CA work
  without chaining to the FOG root. Expect **one forced rebuild on upgrade** —
  the build stamp hashes this file.
- **`PKI_web_external_root_cert` is empty on a normal install**, and the Secure
  Boot zone gets no counterpart on purpose: firmware trusts the enrolled
  certificate directly (the bring-your-own path sets
  `secureBootMokCert="$cert"`), so there is no chain above it and an external
  root would never be read.
- **`PKI_web_ca_cert` and `PKI_root_ca_cert` can be the same file.** When the
  root carries `pathlen:0` or `CA:FALSE` it cannot issue an intermediate, so the
  web leaf is signed directly from it. Two slots, one file, and it is correct —
  the certificate-management page (#1121) must render that rather than flag it,
  and it is why `_resolveTrustAnchor()` dedupes by fingerprint rather than path.
- **Never combine cert and key in one PEM.** Would save 5 keys and leaks private
  keys two ways: FOG *publishes* three of these certs (`srvpublic.crt`,
  `ca.cert.pem`/`.der`, `MOK.der`), and chain files are built with `cat` into a
  bundle nginx serves. Also blocked by the 0600/0644 split, and by httpd 2.4.6
  where a concatenated `SSLCertificateFile` silently serves only the first
  certificate.
- **Why `PKI_web_trust_chain` survives at all:** of its consumers, three could
  use `PKI_web_ca_cert` alone and two need *the root* — nothing wants a bundle as
  input, and both writers build it with `cat`. It stays because a **storage node**
  has no web CA of its own (the master hands it `.nodeChain.pem`), and because
  `_resolveTrustAnchor()` reads the external root out of it.

## Migration

Both halves already exist in the tree; use them together.

1. **Value carry-over** — the `httpproto`→`httpsRedirect` seed block in
   `bin/installfog.sh` is the pattern: runs after `.fogsettings` is sourced,
   *before* the flag shadows, guarded on the new key being unset so it fires
   exactly once. One `[[ -z $NEW ]] && NEW="$old"` per pair, in one marked
   section.
2. **Old line removal** — `deprecatedKeys` in `writeUpdateFile()`. It only
   strips and carries no value, which is why step 1 cannot be skipped.

One installer run is the whole migration. Consider stripping in the release
*after* the one that starts writing new names, since third-party scripts source
this file.

## `.fogsettings.pub` and `/api/whoami`

Renamed too — a breaking API change, accepted on the way to calling 1.6 stable.
No mapping shim.

Smaller than it looks: `Route::WHOAMI_KEYS` and the pub-file loop in
`writeUpdateFile()` are the only two places that enumerate the five keys, and the
OpenAPI spec does not pin them (`/whoami`'s response schema is a bare
`['type' => 'object']`). Good moment to tighten that schema. Keep the two
enumerations in step; there is no test binding them.

**This reopens `osid`.** It was kept because it was a published API field and
retiring it would break `/whoami`. That reason no longer applies. `osid` is a
numeric second encoding of `osname` that has already broken once — `3` was Arch
in 1.5 and is Alpine in 1.6 — so if the response is changing anyway, dropping it
is back on the table. Not decided; flagged.

---

## Sequencing across sessions

Real parallelism is narrow: the rename is concentrated in `functions.sh` and
`installfog.sh`, and most behaviour changes land in `functions.sh` too. Splitting
the rename by category would have every session fighting the same file.

| Track | Files | When |
|---|---|---|
| 1 — `-K`/`-C` client break | `functions.sh`, one function | **Merge before track 2 branches** |
| 2 — mechanical rename | `functions.sh`, `installfog.sh`, `newinput.sh`, distro configs, tests | Alone |
| 3 — behaviour changes | same | After 2 merges |
| 4 — fog-docs | different repo | Fully parallel |
| 5 — PHP whoami + OpenAPI | `route.class.php`, `openapi.class.php` | Parallel; contract is the five new pub-file names |

Track 1 must merge first rather than run alongside track 2, because track 2's
sweep has to reach the variables *inside* track 1's new `_warnClientRepin()` — a
rename sweep colliding with new code needing the same sweep, not a clean two-hunk
conflict. Alternatively stack track 2 on track 1's branch, or drop track 1 as a
separate PR and fold it into track 3, where it is in scope anyway. It earns its
own PR only if the fix should ship on its own merits ahead of the rename.

## Files

- `lib/common/functions.sh` — `managedKeys`/`deprecatedKeys` in
  `writeUpdateFile()`; the `_linkCanonical()` inversion; `validateExternalCA()`
  loses its `${webExtCACert:-$extcacert}` resolution and writes slots directly;
  `_sbNameConstraints()` deleted; `_resolveIpxeTrust()`; `createSSLCA()` gains the
  symlink check replacing `acmeLeaf`; the re-pin warning generalised into
  `_warnClientRepin()` and called from the comm-key regeneration path.
- `bin/installfog.sh` — rename-seed block; `--web-ca-*`/`--ca-*` write the same
  slots; new `--client-cert`/`--client-key`; `--no-ca-trust` and
  `--no-sb-name-constraints` removed.
- `lib/common/newinput.sh` — `externalca` becomes prompt-scoped.
- `packages/web/lib/router/route.class.php` — `WHOAMI_KEYS`, in step with the
  pub-file loop.
- `packages/web/lib/fog/openapi.class.php` — enumerate `/whoami`'s response
  properties now that they are being renamed.
- `tests/` — `install-settings-resolution`, `pki-idempotence`, `trust-anchor`,
  `ipxe-build-stamp`, `ipxe-tree-preservation`, `external-cert-detection` all
  reference the old variable names.
- `FOGProject/fog-docs` — `docs/management/server/install-fogsettings.md`, and
  `docs/installation/server/command-line-options.md`, which is **stale**: it still
  shows the pre-1.6 option list, missing `--install-mode`, `--netboot-proto`,
  `--public-web-cert` and `--rebuild-ipxe-with-my-ca` entirely.

## Deferred

- **[#1279](https://github.com/FOGProject/fogproject/issues/1279)** —
  `fog_update_channel` values (`stable`/`staging`/`dev`) collide with
  `FOG_CHANNEL` (`Patches`/`Beta`/`Release Candidate`/`Feature`). The key is
  untouched here pending that.
- **External web root distribution to clients.** `_resolveTrustAnchor()` already
  anchors both roots server-side, but nothing puts an admin's external web root
  into client trust stores — `ca.cert.der` is still the FOG root. Belongs with
  #1121 or its own issue.
- **Client-encryption cert issuance** — issued from the root today for upgrade
  compatibility; whether it should come from the Web CA or a dedicated
  intermediate constrained to client encryption belongs in the fog-client repo.

## Verification

```sh
tests/run-all.sh
```

`tests/install-settings-resolution.test.sh` matters most — it asserts the
`httpproto`/`httpsRedirect`/`netbootproto` resolution order and exercises the
`caCreated` trap directly, so a botched seed block surfaces there.
`tests/trust-anchor.test.sh` Case B covers the external-CA two-root anchor.

Then confirm no old spelling survives outside the migration block:

```sh
git grep -nE '\$(sslcapem|sslcakey|sslcachain|sslprivkey|sslpubcert|sslpath|rootCAPem|snmysqlpass|extraServerNames)\b' -- bin lib
```

And that the key list reconciles: **79 in, 66 out** — 6 retired, 9 absorbed into
slots they duplicated, 2 promoted from local variables.


---

## Outcome

Landed as three commits plus a `working-1.6` merge. ADR
[0024](../../adr/0024-fogsettings-unified-key-model.md) records the model itself;
`docs/FOGSETTINGS.md` was rewritten for the mechanics. The plan held up — the
model, the two-part migration and the `_linkCanonical()` inversion all shipped as
designed. What follows is only where reality differed.

### Two bugs the plan would have shipped

**The external-CA merge, as specified, breaks every install.** Absorbing
`extcacert`/`webExtCACert` into `PKI_web_ca_cert` reads clean, but `externalca`
derives from *"is an import path set"* — and `PKI_web_ca_cert` names FOG's **own**
Web CA on every ordinary install. The derivation would have declared every
install an external-CA install.

The import paths therefore needed names the plan never gave them: they are
run-scoped inputs (`importWebCACert` / `importWebCAKey` / `importWebCARoot`)
written by both flag spellings *and* the prompt, with `validateExternalCA()`
setting the canonical slots once the import validates. That is also what finally
closes the bug R3 was filed for — anything typed at the prompt was discarded
whenever the flags were also given — rather than renaming around it.

**The sweep's file list was wrong.** `utils/FOGBackup/FOGBackup.sh` and
`utils/reporting/report.sh` source `.fogsettings` and read `DB_*`,
`STORAGE_image_share_path`, `WEB_docroot` and `WEB_root`. Neither is in `bin/` or
`lib/`, and both would have broken on the first upgrade.

What found them was classifying every remaining match **by form** after the sweep
— `$v` / `${v}` / `v=` versus a bare word — rather than trusting a file list. Worth
repeating for any future rename: the bare-word bucket is where directory paths and
prose hide, and the variable-form bucket is where the misses hide.

### `$fogprogramdir` had to leave the sweep entirely

The plan had `FOG_program_dir` as a record written from the live variable, which
is what shipped. What it did not anticipate is that the variable must then be
**excluded from the mechanical rename altogether**: its 236 references are the
live control variable, and `/etc/fog/fog.conf` still emits `fogprogramdir=` on
every existing server. Only the `managedKeys` entry and one explicit assignment
moved.

`settingLine()` resolving `${!key}` is why that assignment is mandatory rather
than tidy — a managed key naming no live variable emits an empty line, silently.
The same applies to `PKI_client_encrypt_cert`/`_key`.

### `writeUpdateFile()` needed a third path

Not in the plan, and not cosmetic. The in-place `awk` merge rewrites managed keys
*in the position they already occupy* and appends the ones it did not find — so on
the migration run, with every old key deprecated and every new key absent, it
strips all 79 lines and appends 66 at the end. The category blocks and the
`## Derived — do not edit` marker would describe nothing, and the file would read
as a pile of appended keys after its own footer.

So a recognizable file carrying only pre-rename keys now gets a **one-time
canonical rewrite**, which also carries every *unrecognised* line through. That
second half matters as much: hand-set keys (`inetConnectTimeout`,
`storageLocationCapture`, `ftppasvmin`/`max`, `mcastportmin`/`max`) and an admin's
own comments survive only because something preserves what it does not manage,
and a plain fresh write does not.

### The rename could not be mechanical

Only `$v` / `${v}` / assignment forms were rewritten automatically; every bare
word was triaged by hand. The measurements are the argument:

- **265 of 278 `secureboot` matches are the `packages/secureboot/` directory
  path** and test filenames. Thirteen are the variable.
- **222 of 398 `packages` matches** are the `packages/` path in PHP tests.
- Three tests assert on **source text** — `grep -q 'httpproto == https'`,
  `grep -q 'acmeLeaf != yes'`, a regex-escaped `${webroot}` — and break silently
  under an otherwise-correct rename.

One site is deliberately left un-renamed and commented: the POST field names in
the `create_update_node.php` call (`sslpath=`, `interface=`, `webroot=`) are that
endpoint's own field names, mapping to `storageNode` DB columns. Only the values
moved.

### Verification came out stronger than planned

The plan's `git grep` check was run once and **not** kept as a test: its output is
~940 hits, of which ~820 are directory paths, prose and the deliberately-unrenamed
`$fogprogramdir`. A test asserting zero would have to encode that exception list
and would fail on the next unrelated comment mentioning a retired key. The
membership half of what it was for is covered properly instead:

- `install-settings-resolution.test.sh` asserts all 66 are in `managedKeys`, all
  79 in `deprecatedKeys`, and none in both — reading both arrays out of the real
  source **with comments stripped**, because the arrays carry prose that names
  keys and a raw grep passes for one merely mentioned.
- `tests/fogsettings-migration.test.sh` (new) **extracts the seed block from the
  installer and evaluates it** rather than replaying it by hand, then runs
  `writeUpdateFile()` over a synthesized pre-rename file. A hand-copied replay is
  how a test passes while the behaviour is wrong.
- `tests/whoami-keys-in-step.test.php` (new) binds `Route::WHOAMI_KEYS` to the
  pub-file loop — `docs/FOGSETTINGS.md` said in as many words that nothing did.

Both new tests were negative-controlled: deliberately broken, observed to fail,
restored.

**Writing the migration test surfaced the GH-850 hazard live.** The fixture
contains `fogprogramdir='/opt/fog'`, as a real pre-rename file does. Sourcing it
relocated the install mid-test and wrote a real `/opt/fog/.fogsettings` on the
host, which the assertions then read — so the carry-over checks failed for a
reason unrelated to the carry-over code. That is exactly what
`resolvedfogprogramdir` exists to prevent. The test now mirrors the installer and
says why.

### Docs, and one thing not to attempt

ADR 0024 added; `docs/FOGSETTINGS.md` rewritten; ADRs 0015, 0002, 0016 and 0018
updated — 0015 and 0002 with a Status note rather than a silent edit, since in
both the key names are part of the decision. ADR 0008's matches turned out to be
directory paths and the unrenamed `$fogprogramdir`, so it needed nothing.

**A mechanical pass over `docs/` must not be attempted.** Most `password`,
`hostname` and `interface` matches in this tree are user passwords, *client*
hostnames and PHP `interface` declarations. Every hit needs reading; the remaining
in-repo docs were swept per-hit on that basis.

The admin-facing fog-docs pages, and the still-stale `command-line-options.md`,
remain a separate change.
