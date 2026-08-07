# Install-time customization preservation

Part of the follow-up to #1005/#1012/#1013 (git-path update script, vhost/hostname
flags, cert separation): those made `updatefog.sh` a real, orchestrated updater.
This design finishes the job by moving customization preservation *into*
`installfog.sh` itself, so it protects every run, not only ones that went
through `updatefog.sh`.

## Context

Today, five different categories of "thing an admin customized" are handled
five different, inconsistent ways, and four of the five only work when the
customization was made under one specific default filename, in one specific
directory, and the run went through `bin/updatefog.sh`:

1. **iPXE background (`bg.png`)** — `FOG_IPXE_BG_FILE` is a real,
   `globalSettings`-backed, GUI-editable filename
   (`packages/web/commons/schema.php:3267-3270`, default `bg.png`; read by
   `packages/web/lib/fog/bootmenu.class.php:2100-2103`). The installer has
   zero awareness of it. `configureHttpd()` (`lib/common/functions.sh:4138`)
   `rm -rf`s `$webdirdest` (after a wholesale `.BACKUP` snapshot,
   `functions.sh:4241-4248`) and `cp -Rf $webdirsrc/* $webdirdest/`
   (`functions.sh:4287`), relaying the shipped default `bg.png` every run.
   The only protection that exists at all is `lib/common/update.sh`'s
   `_updateAssetFiles()` (line 34) hardcoding the literal string `"bg.png"` —
   never the actual setting value — and only runs via `bin/updatefog.sh`.
2. **Custom vhost content** (extra directives, headers, includes) — `createSSLCA()`
   (`functions.sh:3412`) regenerates the *entire* `$etcconf` file from scratch
   via inline `echo`/heredoc every run, for both nginx (`functions.sh:3536` ff.)
   and Apache (`functions.sh:3751` ff.). `-F`/`--no-vhost`
   (`bin/installfog.sh:436-437`) is the only escape hatch, and it is
   all-or-nothing: skip regeneration entirely, forever, or lose every hand
   edit on the next un-flagged run. `diffconfig()` (`functions.sh:5564-5573`)
   only ever informs ("Changed configurations:", `bin/installfog.sh:995-1002`)
   — it restores nothing.
3. **Kernel/init (`bzImage`, `init.xz`, etc.)** — `downloadfiles()`
   (`functions.sh:4532-4609`) unconditionally re-downloads and overwrites
   these under `${webdirdest}/service/ipxe/` every run. `_resignKernels()`
   (`functions.sh:5049-5094`) keeps a `.unsigned` sibling purely as a
   double-signing guard, not a version history. `configureTFTPandPXE()`
   (`functions.sh:1191` ff.) separately snapshots the whole `$tftpdirdst` tree
   to `${tftpdirdst}.prev` (lines 1196-1200) before copying in fresh files —
   but that snapshot is dead storage; nothing ever reads it back
   automatically, and it doesn't even hold the kernels (those are served over
   HTTP from `$webdirdest`, not TFTP — see Architecture, part 3). The only
   automatic kernel restore anywhere is `update.sh`'s `_restorePreviousKernel()`
   (lines 63-71), and only on the update-failed-so-revert path
   (`revertUpdate()`, lines 110-120) — never on success, never more than one
   generation back. Separately, FOG has a real **per-host custom kernel/init
   filename** feature (`packages/web/lib/fog/bootmenu.class.php:408-416`,
   `self::$Host->get('kernel')`/`get('init')`) that lets an admin point one
   specific host at an alternate, hand-placed file under
   `service/ipxe/` — nothing protects a custom-named file like that at all
   today, not even the narrow `_updateAssetFiles()` list, which only knows the
   six fixed default names.
4. **`autoexec.ipxe`** — sourced from the `FOGProject/fog-ipxe` release tarball
   (file lives at that repo's root; `downloadipxe()`/`fetchipxeasset()`,
   `functions.sh:1084-1122`, unpack it into `$tftpdirsrc`), then copied into
   `$tftpdirdst/autoexec/autoexec.ipxe` by `configureTFTPandPXE()`'s tree copy
   (`functions.sh:1218-1220`) **every run**, and hard-linked from there into
   `autoexec/i386-efi/`, `autoexec/arm64-efi/`, `secureboot/`, and
   `secureboot/arm64-efi/` (lines 1249-1262). The hard-linking is real and
   already works by construction — an edit to any one copy shows up in all of
   them within a run — but that protection is worthless against the *next*
   run's tree copy, which relays the fog-ipxe project's shipped default over
   whatever is there, unconditionally. A hand edit to `autoexec.ipxe` does not
   survive an update today, full stop.
5. **Secure Boot certificates** — largely already solved. FOG's own generated
   MOK/PK/KEK keys live at `${fogprogramdir}/secureboot/` — outside
   `$webdirdest`, so `configureHttpd()`'s wipe never touches them
   (`_ensureSecureBootKeys()`, `functions.sh:4625-4694`;
   `_ensureSecureBootPlatformKeys()`, `functions.sh:4713-4774`). **But** the
   admin-supplied case (`--secure-boot-key`/`--secure-boot-cert`,
   `bin/installfog.sh:444-465`) never copies the admin's files anywhere —
   `_ensureSecureBootKeys()` just trusts whatever path was given
   (`[[ -n $secureBootKey && -n $secureBootCert ]] && return 0`, line 4641)
   and that literal path is persisted forever in `.fogsettings`
   (`secureBootKey secureBootCert`, `writeUpdateFile()`'s `managedKeys`,
   `functions.sh:3142`). If that path is ever inside `$webdirdest` (or
   anywhere else this installer deletes/regenerates), `configureHttpd()`'s
   `rm -rf $webdirdest` — which runs *before* `configureTFTPandPXE()` calls
   `downloadfiles()` → `_ensureSecureBootKeys()`/`_resignKernels()`/
   `_publishSecureBootKit()` in the very same run — deletes the admin's file
   out from under itself before anything downstream ever reads it. This is a
   real, confirmed gap, not a hypothetical.

`bin/updatefog.sh`/`lib/common/update.sh`'s `backupCustomizations()`/
`restoreCustomizations()` (`update.sh:37-59`) is the **only** orchestrated
backup-then-restore sequence in the codebase today, and it is a bolt-on around
`installfog.sh`, not something `installfog.sh` itself knows about. A bare
`bash installfog.sh` (skipping `updatefog.sh`) gets none of it.

## Non-goals

- Not building a general config-file diff/merge engine. The vhost mechanism
  (Architecture §1) is deliberately "FOG owns one clearly-marked region,
  completely, always" rather than a smart merge — matching this codebase's
  existing all-or-nothing regeneration philosophy everywhere else, just scoped
  down from "the whole file" to "one block."
- Not adding a scheduling/cron layer for kernel backups — the versioned
  backup in Architecture §3 is a side effect of every `installfog.sh` run,
  not a separate timer.
- Not changing `_resignKernels()`'s existing `.unsigned`-sibling idempotency
  guard — it solves a different problem (don't double-sign) than the
  versioned backup (give me an old *signed* kernel back).
- Not moving `autoexec.ipxe`'s customization point upstream into the
  `FOGProject/fog-ipxe` repo. That would be the eventual "right" home for a
  hook line, but this design keeps the change entirely inside `fogproject`
  (Architecture §4) so it ships and is testable without a coordinated
  cross-repo release.
- Not changing storage-node installs (`installtype = [Ss]`, `bin/installfog.sh:867-892`).
  They run `configureMinHttpd`, not `configureHttpd`, and don't serve the iPXE
  boot menu/kernels/vhost the same way — none of §1-§3 apply there, and this
  design does not touch that branch.
- Not solving in-block hand edits to the vhost surviving regeneration (see
  Error Handling — this is the same class of known limitation `diffconfig`
  already has, just narrowed in scope).

## Architecture

Six additive pieces. None change behavior for an admin who customizes
nothing.

### 1. Vhost: a FOG-managed block, not a template file, not a whole-file diff

**Decision: a managed-block convention (`# === FOG MANAGED BLOCK ===`
markers), not a separate template asset with token substitution.**

Justification against this codebase's actual shape: every vhost write site
(`createSSLCA()`, `functions.sh:3536` ff. for nginx, `3751` ff. for Apache)
generates its content as a long chain of bash `echo`/heredoc lines directly
into `$etcconf` — there is no static template file anywhere in this flow, and
the branching (webserver family × OS family × SSL on/off × IPv4 vs IPv6 ×
`--extra-server-name` suffix) is all inline bash logic, not data-driven.
Introducing real template *files* with `sed`-substituted tokens would mean
extracting ~10 write sites' worth of conditionally-assembled content into
external assets and keeping two representations in sync — a much larger,
riskier refactor than this problem calls for, and a bigger deviation from
"additive, doesn't change existing behavior when unused" than necessary. A
managed-block splice can wrap the *existing, unchanged* generation code with
two marker lines and a small generic helper.

**Mechanics:**
- New helper `spliceManagedBlock(file, contentfile)` in `functions.sh`:
  - Marker constants:
    `FOG_MANAGED_BEGIN='# === FOG MANAGED BLOCK -- DO NOT EDIT BETWEEN THESE LINES (see docs/SUPPORTED_CUSTOMIZATIONS.md) ==='`,
    `FOG_MANAGED_END='# === END FOG MANAGED BLOCK ==='`. `#` is a comment
    character in both nginx and Apache conf syntax, so the markers are inert
    in either file type.
  - Every existing generation call site keeps writing exactly what it writes
    today, but into a fresh temp file (e.g. `${etcconf}.fogblock.$$`) instead
    of directly into `$etcconf`.
  - `spliceManagedBlock` then:
    - If `$etcconf` doesn't exist: write `FOG_MANAGED_BEGIN`, the temp
      content, `FOG_MANAGED_END` as the entire new file (identical *content*
      to today's fresh-install behavior, just wrapped).
    - If `$etcconf` exists and already contains both markers (a prior FOG
      run wrote them): replace only the lines between them with the fresh
      temp content (an `awk` range-replace), leaving everything before
      `FOG_MANAGED_BEGIN` and after `FOG_MANAGED_END` byte-for-byte untouched.
    - If `$etcconf` exists but has no markers (first FOG-managed run against
      a pre-existing/hand-built file, or an upgrade from before this
      feature): **append** a new marked block at the end of the existing
      file, never touching the pre-existing content. This is the one
      behavior change on an existing install's first run under this feature
      — call it out in release notes.
  - `mv -fv "${etcconf}" "${etcconf}.${timestamp}"` + `diffconfig()` still run
    exactly as today, so the "Changed configurations" notice still fires when
    the *whole file's* bytes differ — including, now, only-inside-the-managed-block
    changes, same imprecision `diffconfig` already has (see Error Handling).
- `createSSLCA()`'s nginx branch (`functions.sh:3536-3752` today) and Apache
  branch (`3751-3993` today) each change their *last* line from directly
  finishing the write to calling `spliceManagedBlock "$etcconf" "$tmpblock"`.
  Every line in between — `server_name`/`ServerAlias`, SSL cert paths,
  `--extra-server-name` suffix handling — is unchanged.
- `--overwrite-vhost` (currently `updatefog.sh`-only, forwarded as an empty
  `$updateVhostFlag`) keeps its meaning almost as-is: "regenerate as if no
  managed block existed" — i.e., delete the whole file first, then let
  `spliceManagedBlock` write a fresh single-block file. Useful for an admin who
  wants to discard accumulated cruft (old markers, stale appended content)
  and start clean.
- `-F`/`--no-vhost` keeps its exact current meaning: skip vhost writing
  entirely, don't touch the file, don't even splice. This stays the true
  "FOG, hands off" escape hatch — **it does not become obsolete**, because
  splicing is itself an opinionated action (adding markers to a
  previously-marker-free file) some admins may not want at all.
- **Consequence for `updatefog.sh`'s default:** today `updateVhostFlag="-F"`
  by default (`bin/updatefog.sh:79`) specifically because full regeneration
  used to mean "destroy any hand customization." With splicing, that's no
  longer true — the managed block is always safe to refresh; only content
  *outside* it is ever at risk, and that risk no longer exists. So this
  design flips `updatefog.sh`'s default: an update now lets `installfog.sh`
  splice the managed block by default, and `-F` remains available as the
  explicit "no, really don't touch it" opt-out. This is a real behavior
  change and needs its own callout/test (Implementation Plan, Task 3).

### 2. Generalized backup/restore living inside `installfog.sh`

Two new functions in `functions.sh`, called from the existing
`configureMySql; writeUpdateFile; backupReports; configureHttpd; ...`
sequence (`bin/installfog.sh:938-963`, the `[Nn]`/master-install branch —
this is the only branch that calls `configureHttpd`; the `[Ss]`/storage-node
branch at lines 867-892 is untouched):

```
configureMySql
writeUpdateFile
backupReports
backupPreservedCustomizations      # NEW
configureHttpd
checkWebTier
backupDB
updateDB
configureStorage
configureDHCP
configureTFTPandPXE               # downloadfiles() inside here re-lays default-named kernels/init
restorePreservedCustomizations    # NEW
configureFTP
...
```

`backupPreservedCustomizations()` runs **before** `configureHttpd()`'s
`rm -rf $webdirdest` (`functions.sh:4247`), at a point where
`configureMySql` has already run — so `$sqloptionsuser`/`$snmysqlpass`/
`$mysqldbname` are already set and a DB query is possible. It:

1. Queries the *actual* `FOG_IPXE_BG_FILE` value:
   `mysql $sqloptionsuser --password="$snmysqlpass" -N -B --execute="SELECT settingValue FROM globalSettings WHERE settingKey='FOG_IPXE_BG_FILE'" $mysqldbname 2>>$error_log`.
   On a first-ever install the `globalSettings` table doesn't exist yet
   (schema loads later, in `updateDB()`, which runs *after* `configureHttpd`)
   — the query simply errors into `$error_log` and returns empty, which this
   function treats identically to "no customization, nothing to back up."
   Same posture as every other `[[ -f ... ]]`-gated step in this file — no
   special-casing needed for "fresh install."
2. If that filename resolves to a real file at
   `${webdirdest}/service/ipxe/<name>`, copies it to
   `${fogprogramdir}/customizations/ipxe-bg/<name>` (preserves the actual
   name — this is what makes it setting-driven instead of hardcoded to
   `bg.png`).
3. Copies any of the legacy `refind.*` files present, same as today's
   `_updateAssetFiles()` list, to `${fogprogramdir}/customizations/ipxe-legacy/`.
4. Snapshots the **entire** `${webdirdest}/service/ipxe/` directory (not a
   fixed filename list) into a rotated, bounded set of generations under
   `${fogprogramdir}/customizations/kernel-backups/` — see Architecture §3.
   Because this snapshot is "everything currently there," it automatically
   captures a per-host custom-named kernel/init file
   (`bootmenu.class.php:408-416`'s `Host->get('kernel')`/`get('init')`)
   without FOG needing to query the `hosts` table to learn any custom name —
   it never needs to know the name at all.

`restorePreservedCustomizations()` runs after `configureTFTPandPXE()`
(specifically after its internal `downloadfiles()` call has re-populated the
default-named kernel/init files) and:

1. Restores the bg file from `${fogprogramdir}/customizations/ipxe-bg/<name>`
   back to `${webdirdest}/service/ipxe/<name>` — using the same name it was
   backed up under, so this works unchanged if `FOG_IPXE_BG_FILE` itself never
   changes, and also works if an admin renames it going forward (next run's
   `backupPreservedCustomizations` just backs up under the new name).
2. Restores `refind.*` the same way as today.
3. Restores, from the most recent kernel-backup generation, any file whose
   name is **not** one of the six fixed default kernel/init names (`bzImage`,
   `bzImage32`, `arm_Image`, `init.xz`, `init_32.xz`, `arm_init.cpio.gz`) —
   this is the generic mechanism that covers a per-host custom-named kernel
   without any dedicated "custom kernel name" setting existing anywhere.
   The six default names are deliberately **not** restored here — matching
   `update.sh`'s existing, deliberate "the point of an update is to pick up
   the latest kernel" comment (line 49) — that's what Architecture §3's
   generation history is *for*: an explicit restore path, not an automatic one.
4. `chown -R ${username}:${apacheuser}` the restored paths, matching today's
   `restoreCustomizations()`/`_restorePreviousKernel()` behavior.

**Secure Boot admin-key gap fix**, folded into the same "protect before the
wipe" principle but implemented as its own tiny function,
`preserveSecureBootAdminFiles()`, called immediately after the existing
`--secure-boot-key`/`--secure-boot-cert` pair validation in `installfog.sh`'s
option-evaluation block (right after `unset sbfile`, currently around line
681) — i.e., **before** `configureMySql`/`configureHttpd` ever run, which is
the earliest point `$fogprogramdir` is resolved and the only point that's
provably before any tree gets wiped:

- If `$secureBootKey`/`$secureBootCert` are both set (admin-supplied or
  already-persisted from a prior admin-supplied run) and their resolved
  absolute path is **not already** `${fogprogramdir}/secureboot/MOK.key`/
  `MOK.pem`, copy them there (`mkdir -p`, `chown root:root`, `chmod 0600`/`0644`
  matching `_ensureSecureBootKeys()`'s own generated-key permissions), then
  reassign `secureBootKey`/`secureBootCert` to the copies.
- This makes the admin-supplied case converge onto exactly the same
  "lives outside `$webdirdest`, therefore survives every wipe by construction"
  guarantee FOG's own generated keys already have — closing the gap without
  changing `_ensureSecureBootKeys()`'s explicit "an admin-supplied pair always
  wins and is never touched or overwritten" contract (line 4640): the
  *original* file the admin pointed at is still never modified; a *copy* is
  what gets used and persisted from here on.
- Idempotent by construction: once the copy exists and `.fogsettings` has been
  rewritten with the copy's path (via the next `writeUpdateFile` call), every
  later run's `[[ -n $secureBootKey && -n $secureBootCert ]] && return 0` in
  `_ensureSecureBootKeys()` (line 4641) already points at the safe copy, so
  this function's own "already at destination" check makes it a no-op forever
  after, until the admin explicitly passes `--secure-boot-key`/`--secure-boot-cert`
  again to rotate to a new pair.

### 3. Versioned kernel/init backup

**Location: `${fogprogramdir}/customizations/kernel-backups/`, not alongside
the live files under `${webdirdest}/service/ipxe/`.** This matters: unlike
`bzImage.unsigned` (a same-directory sibling that only needs to survive
*within* one run), anything living inside `$webdirdest` is destroyed by
`configureHttpd()`'s `rm -rf $webdirdest` (line 4247) on the *next* run, before
`downloadfiles()` ever gets a chance to re-populate it. A version history has
to live outside the tree that gets wiped — the same reasoning that already
makes `${fogprogramdir}/secureboot/` safe.

**Scheme:** numbered generation directories, `gen-1` (most recent) through
`gen-N` (oldest kept), bounded at `N` = 3 by default. Rotation, run inside
`backupPreservedCustomizations()` immediately before writing the new
snapshot:
```
[[ -d gen-N ]] && rm -rf gen-N
for k in $(seq $((N-1)) -1 1); do
    [[ -d gen-$k ]] && mv gen-$k gen-$((k+1))
done
cp -a "${webdirdest}/service/ipxe/." gen-1/
```
`cp -a` preserves the `attr -s version`/`attr -s tag_name` xattrs
`downloadfiles()` already stamps on every kernel/init file
(`functions.sh:4583-4599`), so each generation is self-describing (which FOS
release it came from) with no separate manifest needed.

`N` is configurable: new `--kernel-backup-count <N>` flag on `installfog.sh`
(same staging-var convention as every other flag — `skernelBackupCount` →
`kernelBackupGenerations`, applied in the option-evaluation block, added to
`writeUpdateFile()`'s `managedKeys`, default `3` if never set). Storage cost
is bounded and small — these are the same files `downloadfiles()` already
downloads every run; keeping 3 generations costs roughly 3× one release's
kernel/init set, on disk the admin already provisioned for FOG.

**Restore path:** a new leaf script, `bin/restorekernel.sh`, following the
`bin/setupacme.sh` precedent (a rare, deliberate, admin-invoked operation —
not something worth adding as an `installfog.sh` flag that would need to
short-circuit the rest of that script's pipeline):
- `--list` — prints each `gen-N` directory's contents with their `tag_name`
  xattr (`attr -g tag_name <file>`) so an admin can see which FOS release each
  generation came from before choosing.
- `--generation N` — copies `gen-N`'s contents back into
  `${webdirdest}/service/ipxe/`, `chown`s them, and if `$secureBootKey`/
  `$secureBootCert` are configured, re-runs the same signing check
  `_resignKernels()` uses (`sbverify` against the live file; re-sign only if
  it doesn't already verify) — flagged as a known edge case in Error Handling
  if the signing key has rotated since that generation was captured.

**Revert-on-failure carve-out preserved:** `update.sh`'s current behavior
deliberately restores the *default-named* kernels on the revert path (not just
on request) because a revert to an older commit should also mean older
kernels. This is preserved via a new `installfog.sh` flag,
`--restore-kernel-backup`, which `updatefog.sh`'s `revertUpdate()` passes on
its re-invocation of `installfog.sh` — it tells `restorePreservedCustomizations()`
to *also* restore `gen-1`'s default-named files, not just the non-default
("custom") ones it restores unconditionally. Normal runs never pass this flag.

### 4. Custom PXE script hook — off `default.ipxe`, reachable via `autoexec.ipxe`

**Investigated, not assumed:** `autoexec.ipxe` is not FOG-authored text —
it's unpacked from the `FOGProject/fog-ipxe` release tarball
(`fetchipxeasset()`/`downloadipxe()`, `functions.sh:1084-1122`) and its content
is owned by that separate repo. What it *does*, per that repo's own
documentation, is DHCP/proxyDHCP discovery across `net0`/`net1`/`net2`, then
**`chain` to a fixed, bare name: `default.ipxe`** on the same server — and
`default.ipxe` is not part of the tarball at all. It is generated, in full,
by `configureDefaultiPXEfile()` (`functions.sh:1037-1042`) — one `echo -e ...`
line, unconditionally overwritten every run, with no expectation today that
anyone hand-edits it. That makes `default.ipxe`, not `autoexec.ipxe` itself,
the right place to add a hook: it's the file `fogproject`'s own bash already
owns outright and already regenerates unconditionally every run (so there's
no customization-loss risk to introduce — it has none today), and it's one
hop downstream of `autoexec.ipxe`'s own chain, so "off `autoexec.ipxe`" is
still an accurate description of where the hook sits in the boot flow.

Per `ipxe.org/cmd/chain`: **without `--replace`, `chain` returns control to the
calling script once the chained script finishes executing normally** (only a
*failed* chain — file not found, parse error — triggers the `||` fallback).
This makes a safe, default-behavior-preserving hook straightforward:

```
#!ipxe
chain custom.ipxe || goto fog_default
:fog_default
set arch ${buildarch}
...(rest of today's configureDefaultiPXEfile output, byte-for-byte unchanged)...
:bootme
chain ${httpproto}://$ipaddress${webroot}service/ipxe/boot.php##params
```

- If `$tftpdirdst/custom.ipxe` doesn't exist (the overwhelming default case),
  `chain` fails immediately, `|| goto fog_default` fires, and boot proceeds
  exactly as it does today — zero behavior change when unused.
- If it exists, it's chained *before* FOG's own params/boot-menu logic runs —
  the natural place for a boot delay, custom prompt, or site-specific menu
  (the "10-sec delay that was embedded in an alternate pxe boot file
  previously," from the ask). When that script finishes normally (reaches end
  of script, or an explicit `exit`), control returns to the line immediately
  after the `chain custom.ipxe` call in `default.ipxe`, which falls straight
  through into `:fog_default` — no special "resume" convention or shared
  state variable needed, and no risk of the infinite loop a naive
  chain-back-to-`default.ipxe` design would create.
- `custom.ipxe` is never created, deleted, or touched by any part of
  `installfog.sh` — it lives at `$tftpdirdst` root, a directory that is only
  ever *snapshotted* (`.prev`, `configureTFTPandPXE()` lines 1196-1200), never
  wholesale-wiped, and the tree copy loop (lines 1218-1220) only ever copies
  files *from* `$tftpdirsrc`, never deletes anything at the destination that
  isn't in the source. So `custom.ipxe` survives every future run
  structurally, the same way Secure Boot keys survive by living outside
  `$webdirdest` — **no backup/restore mechanism is needed for this file at
  all**, only the one-line addition to `configureDefaultiPXEfile()`'s
  generated content.
- Change is confined to `configureDefaultiPXEfile()` — the whole function's
  output is already a single unconditional overwrite, so there's no
  idempotency concern (no "already has the hook, don't add it twice" check
  needed, unlike the vhost's managed-block splice).

### 5. `updatefog.sh` simplification

With §2/§3 living in `installfog.sh` itself, `lib/common/update.sh` shrinks to
just the git mechanics:

- **Removed entirely:** `_updateAssetFiles()`, `backupCustomizations()`,
  `restoreCustomizations()`, `_restorePreviousKernel()` (`update.sh:33-71`) —
  fully superseded by `backupPreservedCustomizations()`/
  `restorePreservedCustomizations()`, which now run unconditionally inside
  every `installfog.sh` invocation, including the one `revertUpdate()` makes.
- **Kept, lightly modified:** `gitUpdateToBranch()` (unchanged) and
  `revertUpdate()` (`update.sh:110-120`) — its calls to
  `_restorePreviousKernel`/`restoreCustomizations` are deleted (the re-invoked
  `installfog.sh -Y $updateVhostFlag` now does this itself); it instead adds
  `--restore-kernel-backup` to that re-invocation (Architecture §3's carve-out).
- **`bin/updatefog.sh`'s job becomes:** resolve channel/branch
  (`channelToBranch`, unchanged), confirm/`-y`, `backupCustomizations`'s
  call site deleted (nothing left to call), `gitUpdateToBranch`, invoke
  `bash installfog.sh -Y ...` (now the *sole* place backup/restore happens),
  handle revert-on-failure. The `$updateVhostFlag` default flips from `-F` to
  empty per Architecture §1's consequence — `-F` remains available via the
  same flag, just no longer the default.
- `backupCustomizations` is also removed from `updatefog.sh`'s own call
  (currently line 249, before `gitUpdateToBranch`) — there is nothing left
  for it to do once `installfog.sh` owns this.

### 6. Documentation: `docs/SUPPORTED_CUSTOMIZATIONS.md`

New top-level doc enumerating exactly what survives automatically vs. what
requires deliberate admin action. Proposed headings:

```
# Supported Customizations

## How to read this document
(one paragraph: "automatic" means installfog.sh/updatefog.sh preserve it with
no admin action every run; "manual" means the admin must re-apply it or use a
documented escape hatch)

## iPXE boot background (bg.png / FOG_IPXE_BG_FILE)
Automatic. Example row: | Customization | How it's preserved | Where |
| Renamed background file via FOG_IPXE_BG_FILE | Backed up before the web
tree is rebuilt, restored under its actual (possibly renamed) filename after |
${fogprogramdir}/customizations/ipxe-bg/ |

## Web server vhost (nginx/Apache)
Automatic for FOG's own security-relevant content (ServerName/server_name,
ServerAlias, cert paths, ciphers); manual for anything an admin adds outside
the FOG-managed block, which is preserved as-is. Example row: | Extra
ServerAlias/hostname | Set via --hostname/--extra-server-name and always
written into the managed block, not backed up | see --extra-server-name |

## Kernel / init (bzImage, init.xz, ...)
Default-named files are always replaced with the latest release (by design);
N prior generations are kept for manual restore via bin/restorekernel.sh.
Custom-named files (per-host kernel/init override) are restored automatically.
Example row: | Per-host custom kernel filename | Snapshotted/restored
automatically as part of every install/update | Host->get('kernel') |

## Custom PXE scripts (custom.ipxe hook)
Manual, but supported: place custom.ipxe at the TFTP root; FOG chains to it
before its own boot logic if present, otherwise boots exactly as before.

## Secure Boot certificates
Automatic. FOG's own generated keys, and now admin-supplied
--secure-boot-key/--secure-boot-cert pairs, are copied into and always read
from ${fogprogramdir}/secureboot/, which nothing in the installer ever wipes.

## What is NOT automatically preserved
(honest callout: hand edits made INSIDE the vhost's FOG-managed block; a
kernel-signing key rotated after a backup generation was captured)
```

## Data flow

**Install/update time:** `installfog.sh` (directly, or via `updatefog.sh`
which now just resolves the branch and invokes it) → option evaluation
(secure-boot admin-key preservation runs here, before anything else) →
`configureMySql` (DB now queryable) → `backupPreservedCustomizations` (reads
`FOG_IPXE_BG_FILE`, snapshots `service/ipxe/`) → `configureHttpd` (wipes/relays
`$webdirdest`, splices the vhost's managed block) → `configureTFTPandPXE` →
`downloadfiles` (re-lays default-named kernels/init, generates
`default.ipxe` with the `custom.ipxe` hook) → `restorePreservedCustomizations`
(bg file back under its real name, custom-named kernels back, `refind.*` back)
→ rest of the sequence unchanged.

**Boot time (steady state):** client's EFI binary → `autoexec.ipxe`
(fog-ipxe-owned, unchanged) → `chain default.ipxe` → FOG's
`custom.ipxe`-or-fallthrough hook → params/menu logic (unchanged) →
`chain boot.php`.

**Revert-on-failure:** `updatefog.sh`'s `revertUpdate()` → git reset to the
prior commit → `bash installfog.sh -Y $updateVhostFlag --restore-kernel-backup`
→ the same `backupPreservedCustomizations`/`restorePreservedCustomizations`
pair runs, with the extra flag telling it to also restore `gen-1`'s
default-named kernels this one time.

## Error handling

- **DB not queryable yet (first-ever install):** `backupPreservedCustomizations()`'s
  `SELECT` against a not-yet-created `globalSettings` table errors into
  `$error_log` and is treated as "nothing to back up" — matches every other
  `[[ -f ]]`-gated defensive step in this file.
- **Kernel backup rotation failing mid-way (disk full, permissions):** each
  `mv`/`cp` in the rotation is best-effort and logged; a failed rotation
  should not abort the install — `errorStat` is called with the accumulated
  status but the install continues (same posture `backupReports`/
  `backupCustomizations` already have today).
- **Vhost managed-block splice on a file with only one marker (corrupted by a
  prior partial run or manual edit):** treat as "no valid markers found" and
  fall back to appending a fresh block, same as the no-markers-at-all case —
  never guess or attempt a partial patch.
- **Known limitation, carried over from `diffconfig`'s existing behavior, now
  narrowed in scope:** a hand edit made *inside* the FOG-managed block is
  still lost on the next regeneration — the managed-block mechanism protects
  content *outside* the block, not inside it. `diffconfig`'s "Changed
  configurations" notice still can't distinguish "FOG changed its own block
  because a flag was passed" from "an admin's in-block edit is about to be
  lost" — both look identical. This is explicitly called out in
  `docs/SUPPORTED_CUSTOMIZATIONS.md` rather than silently accepted.
- **Secure Boot key rotation across a kernel-backup restore
  (`bin/restorekernel.sh`):** if the signing key active today differs from
  the one active when the chosen generation was captured, the restored
  kernel's existing signature won't `sbverify` against today's cert, and
  `_resignKernels()`-equivalent logic will attempt to re-sign it with
  *today's* key — correct behavior, but flagged as an edge case worth a
  console message ("re-signing restored kernel with current Secure Boot key").
- **`custom.ipxe` present but malformed:** iPXE's own script error handling
  applies (same as any malformed `.ipxe` file today) — `default.ipxe`'s
  `chain custom.ipxe || goto fog_default` only catches a *failed chain*
  (fetch/parse failure), not a script that loads fine but does something the
  admin didn't intend. Not a new failure mode this design introduces.

## Testing

No CI framework exists for this repo's shell scripts beyond
`fogproject-install-validation`'s distro matrix — same posture as every prior
plan in this repo: `bash -n` for syntax, then manual verification on a real
VM.

- **bg.png rename:** set `FOG_IPXE_BG_FILE` in the GUI to a real, differently
  named file already placed under `service/ipxe/`; run `installfog.sh`
  directly (not via `updatefog.sh`); confirm the renamed file still exists
  under its real name afterward, and `FOG_IPXE_BG_FILE`'s value is unchanged.
- **Vhost managed block:** hand-append a distinctive comment/directive after
  today's vhost content; re-run `installfog.sh -Y`; confirm the FOG-managed
  region refreshed (e.g. reflects a new `--extra-server-name`) while the
  hand-appended content is still present, byte-for-byte, after it.
- **`--overwrite-vhost`:** confirm it discards the hand-appended content
  (documented, expected).
- **Kernel generations:** run `installfog.sh` three times in a row (three
  distinct "updates"); confirm `gen-1`/`gen-2`/`gen-3` each hold a distinct
  kernel set (verify via the `tag_name` xattr), and a fourth run correctly
  evicts what was `gen-3`.
- **Custom-named kernel:** set a test host's `kernel`/`init` fields to a
  hand-placed file under `service/ipxe/`; run an update; confirm the file is
  still present and correct afterward, with no dedicated flag/setting having
  been added for it.
- **`custom.ipxe` hook, absent:** confirm PXE boot behaves identically to
  before this change when no such file exists.
- **`custom.ipxe` hook, present:** place a minimal script (e.g. `prompt`
  with a timeout) at the TFTP root; confirm it runs before FOG's own menu,
  and that normal boot resumes afterward.
- **Secure Boot admin-supplied pair:** run `installfog.sh --secure-boot-key
  ... --secure-boot-cert ...` twice in a row; confirm the second run is a
  no-op copy-wise (already at destination) and kernels remain correctly
  signed both times; separately, confirm a path *inside* `$webdirdest` is
  still copied to safety before the first wipe (regression test for the
  specific gap this design closes).
- **Revert path:** force an `updatefog.sh` failure; confirm `gen-1`'s
  default-named kernels are restored (via `--restore-kernel-backup`) in
  addition to the always-restored customizations.
- **Regression:** an install/update with none of these customizations present
  behaves identically to before this design in every other respect.

## Open risks / unknowns

- The vhost managed-block append-on-first-encounter behavior (no markers
  found → append rather than replace) is a one-time visible change for any
  *existing* install upgrading into this feature — worth a release-notes
  callout, not a design flaw, but flagged here so it isn't mistaken for a bug
  report later.
- `bin/restorekernel.sh --generation N`'s interaction with an already-running
  web server (do live-serving files need the web server briefly stopped, or
  is an atomic `cp`+`mv` into place sufficient?) needs verification on a real
  VM — not resolved by static reading of this codebase alone.
- The eventual "right" home for the `custom.ipxe` hook is arguably upstream in
  `FOGProject/fog-ipxe`'s own `autoexec.ipxe`, once that project's release
  cadence allows it — this design's placement in `default.ipxe` is chosen
  specifically to avoid that dependency, but it should be revisited if/when
  fog-ipxe adds its own hook convention, to avoid two parallel mechanisms.
- Whether `$tftpdirdst` is ever fully re-created from empty (rather than
  merged into) on some distro/path combination this reading didn't cover —
  if so, `custom.ipxe`'s "structurally safe by construction" claim in
  Architecture §4 would need re-verification on that path specifically.
