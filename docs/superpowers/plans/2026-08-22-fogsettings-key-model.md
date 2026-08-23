# `.fogsettings` unified key model — 79 keys to 66

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development
> (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps
> use checkbox (`- [ ]`) syntax for tracking.

**Design record:** [issue #1120](https://github.com/FOGProject/fogproject/issues/1120) —
the model is settled across the last four comments; [comment
5382375472](https://github.com/FOGProject/fogproject/issues/1120#issuecomment-5382375472)
supersedes the others where they differ.

## Context

`.fogsettings` lives at `$fogprogramdir/.fogsettings` and is **sourced as shell**, so
every key in it *is* a shell variable name. Over ~15 years it accreted 79 managed keys
with no naming scheme: `snmysqlpass` is a database password (`sn` implies "storage node"
though full servers use it too); `sslpath` no longer holds FOG's CAs; `password` and
`snmysqlpass` are two different secrets, one generically named; `noTftpBuild` is a
negative whose flag `-T/--no-tftpbuild` is also negative; five different boolean
encodings are in use. Six external-CA keys hold three values, reached two ways, and that
duplication already caused a live bug where anything typed at the prompt was silently
discarded whenever the flags were also given (`newinput.sh:149-155`).

Issue #1120 settled a unified model — `CATEGORY_lower_snake_case`, ten categories, the
first `_` being the category boundary, Secure Boot folded into `PKI_sb_*`. The model is
recorded across the last four comments; [comment
5382375472](https://github.com/FOGProject/fogproject/issues/1120#issuecomment-5382375472)
supersedes the others where they differ.

**The refactor is the deliverable, not a cost to minimise.** Because a key name is a
variable name, the model only pays off once the code stops having places where a setting
can be silently lost or where changing one thing breaks an unrelated thing. So the merges
and retirements land here rather than being deferred, and the reference sweep is the
expected work. Measured on the branch: **2563 true variable references** (`$v` 1533,
`${v}` 399, assignments 631) plus **1005 bare-word occurrences** to judge individually —
3568 total across `bin/`, `lib/`, `tests/`, concentrated in `lib/common/functions.sh`
(1984) and `bin/installfog.sh` (263).

**Outcome:** one installer run migrates a server. Values carry via a one-shot seed block,
old lines are stripped by `deprecatedKeys`, and the file is rewritten in category blocks
with a `## Derived — do not edit` marker over the records.

---

## Decisions taken (confirmed)

| Question | Decision |
|---|---|
| SAN keys | **`PKI_`** — `PKI_san_ip_addresses`, `PKI_san_dns_names`. Final comment wins on precedence. `NET_` therefore holds 4 keys, `PKI_` 20. |
| `acmeLeaf` | **Retire and invert now.** The symlink-resolution replacement is load-bearing and lands in the same change. |
| `/api/whoami` | **Renames too, no shim.** Breaking change accepted for 1.6; tighten the `/whoami` OpenAPI schema while there. |
| Docs | **Code + ADRs in this repo.** `FOGProject/fog-docs` is a separate session. |
| Boolean encodings (R6) | **Out of scope.** Value normalisation is a follow-up; this pass carries values across unchanged so the migration stays a pure copy. Two exceptions are forced by merges — see Task 5. |
| `fog_update_channel` values | Untouched, pending #1279. |

---

## The model — all 79 keys accounted for

Verified programmatically against the `managedKeys` array itself
(`lib/common/functions.sh:5519`), not against the issue prose: 79 in, every one mapped,
none invented, none dropped. **79 − 6 retired − 9 absorbed by merge + 2 promoted = 66.**

### `PKI_` — 29 → 20

Canonical paths (records, under `## Derived — do not edit`):

| New key | From |
|---|---|
| `PKI_root_ca_cert` / `PKI_root_ca_key` | `rootCAPem` / `rootCAKey` |
| `PKI_web_ca_cert` | `sslcapem` — absorbs inputs `extcacert`, `webExtCACert` |
| `PKI_web_ca_key` | `sslcakey` — absorbs inputs `extcakey`, `webExtCAKey` |
| `PKI_web_external_root_cert` | `extcaroot` + `webExtCARoot` — **separate from the FOG root** |
| `PKI_web_trust_chain` | `sslcachain` |
| `PKI_web_vhost_cert` / `PKI_web_vhost_key` | `sslpubcert` + `webCertFile` / `sslprivkey` + `webKeyFile` |
| `PKI_client_encrypt_cert` / `_key` | **promoted** from locals `commLeafPem` / `commLeafKey` (`functions.sh:6699-6700`) |
| `PKI_sb_ca_cert` | `secureBootMokCert` (the cert enrolled in firmware) |
| `PKI_sb_codesign_cert` / `_key` | `secureBootCert` / `secureBootKey` |

Inputs and policy: `PKI_client_cert_dir` (`sslpath`) · `PKI_web_cert_publicly_trusted`
(`publicWebCert`) · `PKI_allowed_domain_names` (`internalDomains`) ·
`PKI_internal_subnets` · `PKI_sb_enabled` (`secureboot`) · `PKI_san_ip_addresses`
(`ipaddresses`) · `PKI_san_dns_names` (`extraServerNames`)

The two SAN keys reach well past certificates, which is why the `san` token stays in the
name even under `PKI_`: `ipaddresses` also writes the nginx maintenance `allow` list in
**three** vhost arms (`functions.sh:7911`, `:7993`, `:8156`), and `extraServerNames` is
mirrored into `globalSettings` as `FOG_EXTRA_SERVER_NAMES` (`:600`).

**Retired (6):** `sslcsr` · `acmeLeaf` · `externalca` · `catrust` · `caCreated` · `sbNameConstraints`

`PKI_web_external_root_cert` and `PKI_web_trust_chain` are the final comment's renames of
`PKI_web_ca_root` / `PKI_web_ca_chain`: the web CA normally chains to `PKI_root_ca_cert`,
so "web CA root" implied something false; and the chain is the zone's *trust path*, not
what the vhost serves — `$sslfullchain`/`$sslchainonly` stay derived and unpersisted.

### The other 46

| Cat | Mapping |
|---|---|
| `NET_` (4) | `interface`→`NET_interface` · `ipaddress`→`NET_fog_server_ip` · `submask`→`NET_subnet_mask` · `hostname`→`NET_hostname` |
| `DHCP_` (9→7) | `dodhcp`+`bldhcp`→`DHCP_enabled` · `dhcpengine`→`DHCP_engine` · `dhcpd`→`DHCP_service_name` · `routeraddress`+`plainrouter`→`DHCP_router` · `dnsaddress`→`DHCP_dns_server_ip` · `startrange`→`DHCP_range_start` · `endrange`→`DHCP_range_end` |
| `WEB_` (6) | `webserver`→`WEB_server_engine` · `docroot`→`WEB_docroot` · `webroot`→`WEB_root` · `php_ver`→`WEB_php_version` · `httpproto`→`WEB_url_proto` · `httpsRedirect`→`WEB_https_redirect` |
| `BOOT_` (7) | `netbootproto`→`BOOT_url_proto` · `netbootProtoForced`→`BOOT_url_proto_forced` · `rebuildIpxeWithMyCA`→`BOOT_rebuild_ipxe_with_my_ca` · `bootdelay`→`BOOT_dhcp_delay_seconds` · `noTftpBuild`→`BOOT_external_tftp_server` · `tftpAdvOpts`→`BOOT_tftp_options` · `kernelBackupGenerations`→`BOOT_kernel_backups_kept` |
| `DB_` (6) | `mysqldbname`→`DB_name` · `snmysqlhost`→`DB_host` · `snmysqluser`→`DB_user` · `snmysqlpass`→`DB_password` · `snmysqlexternal`→`DB_external` · `backupPath`→`DB_backup_path` |
| `STORAGE_` (2) | `storageLocation`→`STORAGE_image_share_path` · `blexports`→`STORAGE_rebuild_nfs_exports` |
| `SVC_` (3) | `username`→`SVC_user` · `password`→`SVC_password` · `fwconfigure`→`SVC_firewall_control` |
| `FOG_` (11) | `installtype`→`FOG_install_type` · `osid`→`FOG_os_id` · `osname`→`FOG_os_name` · `packages`→`FOG_packages` · `installlang`→`FOG_install_lang` · `sendreports`→`FOG_send_reports` · `fogupdateloaded`→`FOG_installed` · `copybackold`→`FOG_copy_back_old` · `fog_update_channel`→`FOG_update_channel` · `fogprogramdir`→`FOG_program_dir` · `fog_git_path`→`FOG_git_path` |

`BOOT_external_tftp_server` keeps `noTftpBuild`'s polarity deliberately, so the migration
copies the value across with **no inversion** — and it still reads correctly against the
firewall behaviour (external TFTP server ⇒ 69/udp stays closed, `functions.sh:3617`).

---

## Conventions, settled once and applied uniformly

1. **Braced form everywhere: `${NET_hostname}`, never `$NET_hostname`.** Today it is
   1533 unbraced against 399 braced. Prefixed names are longer and several sites
   concatenate directly onto the value — `"${docroot}fog/"` → `"${WEB_docroot}fog/"`,
   where the unbraced spelling would silently resolve a variable named `WEB_docrootfog`.
   Every site is being edited anyway, so uniform bracing is nearly free and removes a
   whole class of silent breakage.
2. **Shadows keep `s` in front of the whole new name:** `--hostname` → `$sNET_hostname`,
   `--web-ca-cert` → `$sPKI_web_ca_cert`, and `skernelBackupCount` →
   `$sBOOT_kernel_backups_kept` (fixing a shadow whose name never matched its key). Per
   `docs/FOGSETTINGS.md`, a handler writing the real variable directly is silently
   discarded on upgrade — that bug has shipped three times, so this is load-bearing.
3. **Flag spellings do not change.** `--hostname`, `--web-ca-cert`, `-T` and the rest keep
   working; only the variables behind them move. Exception: `--no-ca-trust` goes with
   `catrust` (Task 6).

---

## Global constraints

- No `declare(strict_types=1)` anywhere new; match the surrounding style in each file.
- The pre-commit hook auto-runs `php-cs-fixer` (PSR-2), regenerates gettext catalogs and
  bumps `system.class.php`. Expect those extra files in commits; do not revert them.
  Commit `docs/` and non-`packages/web` files separately if isolation matters.
- Author = maintainer, co-author = agent, per `CLAUDE.md:68-90`:
  `git config user.name "JJ Fullmer"`,
  `git config user.email "7743340+darksidemilk@users.noreply.github.com"`, and end every
  message with `Co-Authored-By: Claude <noreply@anthropic.com>`. **No model name in the
  trailer or anywhere else in a commit, PR title/body, or code comment.**
- `settingLine()` resolves values by **indirect expansion** (`val="${!key}"`), so every
  `managedKeys` entry must be the exact name of a live variable. This is why Task 1 needs
  one explicit assignment (below) and why a typo in the array is silent, not fatal.
- **`/etc/fog/fog.conf` keeps emitting `fogprogramdir=`.** That file exists solely to
  break the circularity that `.fogsettings` cannot locate itself, and existing servers
  already carry the line. So `$fogprogramdir` stays the live control variable and
  `FOG_program_dir` is a **record** written from it — one explicit
  `FOG_program_dir="$fogprogramdir"` in `writeUpdateFile()`, alongside the existing
  `fogupdateloaded` special case. Renaming the fog.conf contract is a separate change.

---

## Tasks

> **Line numbers in issue #1120 have drifted** — spot-checking found `WHOAMI_KEYS` at
> `:6884` not `:6827`, the `/whoami` schema at `:1623` not `:1405`, and the nginx `allow`
> list in three arms rather than one at `:7802`. Every citation below was re-derived
> against `d6598906e`, but re-grep before editing rather than trusting either source.
> `functions.sh` grows by hundreds of lines per beta.

### Task 1 — `managedKeys` → 66 names in category blocks, plus the derived marker

Files: `lib/common/functions.sh` (`writeUpdateFile()`, :5503-5758)

- [ ] Rewrite `managedKeys` as the 66 new names grouped in category order
      (`FOG_ NET_ DHCP_ DB_ WEB_ PKI_ BOOT_ STORAGE_ SVC_`), preserving every existing
      explanatory comment against its renamed key — those comments are the record of why
      each key is a preference or a record and must not be lost in the move.
- [ ] Add `FOG_program_dir="$fogprogramdir"` near the top of the function (see constraints).
- [ ] Emit `## Derived — do not edit` in the **fresh-write** path immediately before the
      record-only block, and a matching blank-line-separated `## <Category>` comment per block.

**The trap that makes this more than a list edit.** The in-place `awk` merge rewrites each
managed key *in the position it already occupies* and appends absent ones at the end. On
the migration run **every** old key is deprecated and **every** new key is absent, so the
merge degenerates: it strips all 79 lines and appends 66 at the end, orphaning the comment
structure and putting the derived block nowhere near its marker. So:

- [ ] Add a one-time full-rewrite branch: when the file is recognizable **but contains no
      new-scheme key**, rewrite it canonically *while carrying every unrecognised line
      through* into a trailing hand-set section. Unrecognised lines must survive —
      `docs/FOGSETTINGS.md` records that hand-set keys (`inetConnectTimeout`,
      `inetMaxTime`, `storageLocationCapture`, `ftppasvmin`/`max`, `mcastportmin`/`max`)
      exist *only* because the merge preserves them. The plain fresh-write path does not.

### Task 2 — one-shot rename-seed block in `bin/installfog.sh`

Files: `bin/installfog.sh`

Modelled exactly on the `httpproto`/`httpsRedirect` precedent at **:835-863**. Ordering in
that file is: `:809` sources `.fogsettings` → `:822-828` upgrade-only shadows → **`:835-863`
seed block** → `:864-870` `_applyInstallMode` → `:872-919` main shadow block. The new block
goes in the same slot, after the existing seed, before `_applyInstallMode`.

- [ ] One `[[ -z ${NEW} ]] && NEW="${old}"` per pair, in one clearly marked section,
      guarded on the new key so it fires exactly once.
- [ ] Merge pairs need an explicit source, chosen for a reason, not alphabetically:
      - `DHCP_enabled` ← `bldhcp` (every decision reads it: `functions.sh:1530`, `:2878`,
        `:3634`, `:3651`, `:12056`, `installfog.sh:1049`; `dodhcp` is read only by the
        prompt loop that writes it). Keeps `bldhcp`'s `1`/`0` encoding.
      - `DHCP_router` ← `plainrouter`, falling back to `routeraddress` only when that is
        not the `"#   No router address added"` sentinel (`input.sh:206`).
      - `PKI_web_external_root_cert` ← `${webExtCARoot:-$extcaroot}` (value-carrying: this
        root is fed to the chain file only, `functions.sh:5863`, and `_resolveTrustAnchor()`
        reads it back).
      - `PKI_web_vhost_cert` ← `${webCertFile:-$sslpubcert}`, `_key` ← `${webKeyFile:-$sslprivkey}`.
      - `PKI_web_ca_cert` ← `$sslcapem`, `PKI_web_ca_key` ← `$sslcakey`. **Not** from the
        import paths: `validateExternalCA()` already imports the bytes into the canonical
        location, so the canonical slot is the right value and `extcacert`/`webExtCACert`
        remain pure run-scoped inputs behind the unchanged flags.
- [ ] Convert the `:822-828` upgrade-only shadow block to the new key names. Safe before
      the seed, because the seed is guarded on the new name — a flag that already set it
      correctly wins over the persisted value.
- [ ] Rename every shadow at `:233-649` (assignment) and `:746`, `:822-828`, `:873-919`,
      `:993` (application). Note `sfogprogramdir` (:746, before `config.sh`) and
      `smysqldbname` (:993) are applied **outside** the main block — both must move with it.

### Task 3 — every old name into `deprecatedKeys`

Files: `lib/common/functions.sh:5656`

- [ ] Extend the array to all 79 old spellings plus the 7 already there
      (`storageftpuser storageftppass bootfilename notpxedefaultfile php_verAdds pkiMode
      fogClientCACN`) = 86 entries, grouped and commented by category.
- [ ] Comment that `deprecatedKeys` **only strips and carries no value** — Task 2 is what
      carries it, and cannot be skipped. This is the single most important comment in the
      change: dropping Task 2 while keeping Task 3 silently wipes every setting on upgrade.
- [ ] Case-sensitivity is doing real work here: `fog_git_path`/`FOG_git_path` and
      `fog_update_channel`/`FOG_update_channel` are distinct keys to both `awk` and the
      shell. Intended, but worth the comment.

### Task 4 — sweep `bin/`, `lib/`, `tests/`

**Replace longest-name-first.** These substring pairs make naive order destructive:
`ipaddresses` before `ipaddress` · `netbootProtoForced` before `netbootproto` ·
`secureBootMokCert`/`secureBootCert`/`secureBootKey` before `secureboot` ·
`webExtCACert`/`webExtCAKey`/`webExtCARoot` before `extcacert`/`extcakey`/`extcaroot` ·
`snmysqlpass` before `password` · `sslcapem`/`sslcakey`/`sslcachain` as whole words only.
Use `(?<![A-Za-z0-9_])name(?![A-Za-z0-9_])` boundaries, never bare substring.

**Do not touch these bare-word occurrences** — measured, not guessed:

| Key | Bare hits | What they actually are |
|---|---|---|
| `secureboot` | 265 of 278 | the `packages/secureboot/` **directory path** and test filenames. Only ~13 are the variable. |
| `packages` | 222 of 398 | the `packages/` directory path in `*.test.php`. |
| `password` | 92 of 141 | prose about password hashes/FTP secrets in PHP tests. |
| `dhcpd` | 23 | `/etc/dhcpd.conf`, `$dhcpconfig`, `$dhcpconfigother`. |
| `webroot` | some | `ngmWebroot` DB column (`tests/fixtures/route-column-contract.txt:484`). |
| `osid` | 27 | `"osid 3"` prose in the Alpine tests. |

**Tests that assert on source text and will break silently otherwise:**
`tests/installer-tls-verification.test.sh:205` greps `'httpproto == https'`;
`tests/external-cert-detection.test.sh:100` greps `'acmeLeaf != yes'`;
`tests/routed-query-string.test.php:47,74` match regex-escaped `\$\{webroot\}` against
generated vhost content. Grep for every old spelling inside test *string literals*, not
just as variables.

- [ ] `lib/common/functions.sh` (1984 refs) — the bulk.
- [ ] `bin/installfog.sh` (263), `bin/updatefog.sh` (35), `bin/restorekernel.sh` (21).
- [ ] `lib/common/`: `input.sh` (87), `uninstall.sh` (62), `newinput.sh` (51),
      `config.sh` (36), `utils.sh` (28).
- [ ] `lib/{alpine,arch,ubuntu,redhat}/config.sh` (42/30/30/28) — mostly `$packages`.
- [ ] `tests/` — 20+ files; heaviest are `localboot-publish` (88),
      `install-settings-resolution` (82), `pki-idempotence` (61),
      `client-repin-warning` (43), `netboot-host` (43).
- [ ] Comments and prose in the swept files: renaming the code and leaving comments naming
      `httpproto` makes the file lie. The 1005 bare-word hits are mostly this.

### Task 5 — the two value decisions the merges force

- [ ] `DHCP_router` / `DHCP_dns_server_ip` hold a **clean value or empty**; the
      `"#   No router address added"` / `"#   No dns added"` comment strings move into the
      config writers (`functions.sh:11937` Kea, `:12136` ISC, and the `validip` gates at
      `:11965`, `:12039`, `:12091`, `:12140`). `plainrouter` existed only to hold the clean
      value for display (`installfog.sh:1052`); with one key there is nothing to hold.
- [ ] `DHCP_enabled` carries `bldhcp`'s `1`/`0`. `input.sh:157-174` currently sets both
      keys from one prompt; it now sets one.

### Task 6 — the six retirements

- [ ] **`catrust`** → always anchor FOG's CA in the host trust store. Remove the
      `--no-ca-trust` flag (`installfog.sh:570`, `:900`), the `config.sh:81` default, and
      the gate at `functions.sh:5060`.
- [ ] **`caCreated`** → both uses already pair it with an existence check on the very file
      it stands in for; drop to the `-e`/`-f` test alone (`:5873`, `:6232`), and to a
      file test at `newinput.sh:49`. Delete the assignment at `:8690`. The explanatory
      comment at `:6387-6388` stays, rewritten.
- [ ] **`sbNameConstraints`** → delete `_sbNameConstraints()` (`:6172`); its call site
      (`:9522`) drops to `extendedKeyUsage = codeSigning` alone. Keep the comment,
      rewritten to record why the Secure Boot zone carries none — firmware is a verifier
      FOG cannot patch, unlike iPXE (ADR 0016). Existing `.fogSBCA.pem` files are never
      re-minted (`:9385` gates on `[[ ! -f ]]`), so nothing needs migrating.
- [ ] **`externalca`** → becomes prompt-scoped, never persisted. It is already derived at
      `installfog.sh:924`; keep it as a local driving `newinput.sh:120-145` prompt flow.
- [ ] **`sslcsr`** → use the canonical `$PKI_client_cert_dir/fog.csr` already linked at
      `:7806`. Touches `:6783`, `:7699`, `:7701`, `:7734`.
- [ ] **`acmeLeaf`** → the inversion, and the largest piece here. Replace the persisted
      key with a derived test: `PKI_web_vhost_cert` resolving **outside** `_pkiZoneDir()`
      (`:6000`) means the leaf is externally managed. `_detectExternalCertManagement()`
      (`:7470`) stops recording `acmeLeaf=yes`+`webCertFile`/`webKeyFile` and instead
      calls `_linkCanonical()` (`:5987`) to point the canonical path at the admin's file;
      every later run reads the symlink. Also touches `_warnExternalCertTooling()`, the
      `createSSLCA()` block at `:7601-7630`, `_hardenPkiPermissions`, `:5035`, `:5175-5182`,
      `:7081-7084`, and the operator-facing text at `:861`, `:1030`, `:7572`.

      **This replacement is not optional.** Without it `createSSLCA()` regenerates the
      leaf from the original CSR while an ACME private key sits on disk, producing a
      cert/key mismatch that stops the web server — the exact failure `acmeLeaf` was added
      to prevent, and it fails silently under `-y`.

### Task 7 — promote the client-encryption pair

- [ ] `commLeafPem`/`commLeafKey` (`functions.sh:6699-6700`) become managed keys
      `PKI_client_encrypt_cert`/`_key`. The client zone is currently the only one an
      admin cannot point elsewhere, and it holds the one certificate every registered
      client pins.
- [ ] The **canonical filenames are not free**: `fogbase.class.php:2402` builds
      `/.srvprivate.key` with the name hardcoded, taking the directory from the
      storage-node record. These keys name a canonical path whose *target* may move; the
      canonical name must not. `_separateCommKey()` (`:6892`) already handles the symlink
      case for the key, and `_warnClientRepin()` (`:6839`) already exists — no new
      warning machinery needed.
- [ ] The web-served copy (`management/other/ssl/srvpublic.crt`, `:7705`) stays derived.

### Task 8 — `.fogsettings.pub` and `/api/whoami`

- [ ] `writeUpdateFile()`'s pub loop (`functions.sh:~5750`) emits the new spellings:
      `NET_fog_server_ip`, `NET_hostname`, `FOG_os_id`, `FOG_os_name`, `FOG_install_type`.
- [ ] `Route::WHOAMI_KEYS` (`packages/web/lib/router/route.class.php:6884`) and the loop
      that consumes it (`:6936`), plus the `.fogsettings` fallback parse, move to the same
      names. `docs/FOGSETTINGS.md` records that the array and the pub-file loop have no
      test binding them — add one (Task 9).
- [ ] Tighten `/whoami`'s OpenAPI schema (`openapi.class.php:1623`) to name the five
      fields. Per `CLAUDE.md:194-224` a route change without an `openapi.class.php` change
      needs justifying in the message; here it needs the edit instead.
- [ ] Note in the PR body: this is a **breaking** API change, and `darksidemilk/FogApi`
      consumes `/whoami`.

### Task 9 — verification tests, then the docs this change overrides

- [ ] Extend `tests/install-settings-resolution.test.sh`. It already parses the real array
      (`:186`) and replays the migration block inline (`:50-60`) — both patterns extend
      directly:
      - all 66 new names are in `managedKeys`;
      - all 79 old names are in `deprecatedKeys`;
      - the rename-seed block replayed, asserting each carry **and** that a second run
        does not overwrite a value the admin since changed (the one-shot property);
      - update the `caCreated`/`externalca` assertions at `:151-153`, which reference
        retired keys.
- [ ] New `tests/fogsettings-key-model.test.sh`: greps `bin/ lib/` for every old spelling
      and fails on any hit outside the seed block and `deprecatedKeys`. This is the
      "nothing survives" check, mechanised.
- [ ] New `tests/whoami-keys-in-step.test.php`: binds `Route::WHOAMI_KEYS` to the pub-file
      loop — the gap `docs/FOGSETTINGS.md` names explicitly.
- [ ] Rewrite `docs/FOGSETTINGS.md`: the four kinds' examples, the `writeUpdateFile()`
      section (both paths plus the new one-time rewrite branch), the `.fogsettings.pub`
      table, the reader table, and the `s`-prefix section.
- [ ] New ADR `docs/adr/0024-fogsettings-unified-key-model.md`: the ten categories, the
      tie-break rule (*the category is the subsystem that owns the value, not every
      subsystem that reads it*), why `WEB_`/`BOOT_` stay separate namespaces, why the
      migration needs both halves, and what was deliberately deferred (R6 boolean
      encodings, #1279, `FOG_os_id`'s retirement, `fog.conf`'s variable name).
- [ ] Amend ADR 0015's key table to the new spellings, and correct key names in
      `docs/PKI_ZONES.md` and `docs/EXTERNAL_CA_AND_LETSENCRYPT.md`. Leave the
      admin-facing fog-docs page to its own session, and say so in the PR body.

---

## Verification

Run from a real checkout of the branch (not an archive — the PHP tests need `packages/`):

```bash
git checkout -B claude/fogsettings-key-rename-dgdp8i origin/working-1.6
sh tests/run-all.sh            # baseline BEFORE any edit — capture it
# ... implement ...
sh tests/run-all.sh            # must be no worse; new tests must pass
bash tests/install-settings-resolution.test.sh   # the one that matters most
```

`run-all.sh` needs no DB, no root and no network; it runs `*.test.php` under `php` and
`*.test.sh` under `bash`, one line each, exit 1 if any fail.

Then, mechanically:

```bash
# 1. every old spelling is gone outside the seed block and deprecatedKeys
for k in $(cat old-keys.txt); do
  git grep -nE "(?<![A-Za-z0-9_])$k(?![A-Za-z0-9_])" -- bin lib tests
done
# 2. the 66 new names and 79 old names agree with the real arrays
sed -n '/local -a managedKeys=(/,/^    )/p'   lib/common/functions.sh
sed -n '/local -a deprecatedKeys=(/p'          lib/common/functions.sh
# 3. no unbraced references to a new key survive
git grep -nE '\$(FOG|NET|DHCP|DB|WEB|PKI|BOOT|STORAGE|SVC)_' -- bin lib tests
```

**End-to-end migration check**, which no unit test covers — synthesize a pre-change
`.fogsettings` holding all 79 old keys plus two hand-set ones
(`inetConnectTimeout`, `storageLocationCapture`) and an unrecognised admin line, run
`writeUpdateFile()` against it in a sandbox, and assert: every value landed on its new
key; no old key remains; the hand-set and unrecognised lines survived; the
`## Derived — do not edit` marker sits above the records; and a second run is a no-op.
This is the check that would catch the degenerate-merge trap in Task 1.

## Out of scope, stated so it is not silently dropped

R6 boolean/polarity normalisation · `fog_update_channel`'s values (#1279) ·
`FOG_os_id`'s retirement · client-encryption cert issuance from the Web CA ·
`/etc/fog/fog.conf`'s variable name · the `fog-docs` admin pages.
