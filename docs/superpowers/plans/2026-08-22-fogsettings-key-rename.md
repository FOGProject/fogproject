# `.fogsettings` Key Rename and Unification Plan

> **For agentic workers:** the agreed model is recorded in the comments on
> [FOGProject/fogproject#1120](https://github.com/FOGProject/fogproject/issues/1120).
> Where this document and the issue disagree, the **final comment on #1120 wins** —
> but verify every key against the `managedKeys` array in `writeUpdateFile()`
> itself before trusting either.

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
