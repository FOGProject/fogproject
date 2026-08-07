# Install-time customization preservation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move customization backup/restore *into* `installfog.sh` itself (so
it protects any run, not only ones that went through `bin/updatefog.sh`),
make `bin/updatefog.sh` a thin git-sync-then-invoke wrapper, replace the
vhost's all-or-nothing regenerate/skip choice with a FOG-managed-block
convention, make the iPXE background mechanism setting-driven, add bounded
versioned kernel/init backups, add an optional custom-PXE-script hook, close
the admin-supplied Secure Boot key persistence gap, and document all of it.

**Architecture:** See `docs/superpowers/specs/2026-08-07-customization-preservation-design.md`.
Six independent, additive pieces, each mergeable on its own.

**Tech Stack:** Bash (installer/updater scripts), MySQL (`globalSettings`
query), OpenSSL/`sbsign` (unchanged), iPXE script syntax (`.ipxe` files).

## Global Constraints

- No CI/test framework exists for this repo's shell scripts. Every task's
  "test" step is a manual invocation + assertion on real output. Always run
  `bash -n <file>` after every edit before anything else.
- Follow the existing staging-variable convention exactly: a new flag sets an
  `s`-prefixed variable during `getopt` parsing, applied to the real variable
  only *after* `.fogsettings` has been sourced, in the "evaluation of command
  line options" block (`bin/installfog.sh:615-681`).
- Every new file-path-shaped value must be validated before it reaches a file
  write — same posture as `--git-path`/`--fogprogramdir`.
- `bin/updatefog.sh` never runs `installfog.sh` interactively (always `-Y`) —
  any new flag consumed there must be passed straight through.
- This plan assumes the `--hostname`/`--extra-server-name` work from
  `docs/superpowers/plans/2026-08-07-cert-separation-letsencrypt.md` is
  already merged (`extraServerNamesSuffix`, `$dnsSanEntries`, etc. already
  exist in `createSSLCA()`) — Task 3 below builds directly on top of it and
  does not reintroduce it.
- Design doc: `docs/superpowers/specs/2026-08-07-customization-preservation-design.md`.
  Read it if anything below is ambiguous.

---

### Task 1: Setting-driven iPXE background backup/restore

**Files:**
- Modify: `lib/common/functions.sh` — new `backupPreservedCustomizations()`
  and `restorePreservedCustomizations()` functions (place near
  `backupReports()`, `functions.sh:72`, since they play the same role).
- Modify: `bin/installfog.sh` — add both calls to the master-install sequence
  (`bin/installfog.sh:938-963`).

**Interfaces:**
- Consumes: `$sqloptionsuser`/`$snmysqlpass`/`$mysqldbname` (set by
  `configureMySql`), `$webdirdest`, `$fogprogramdir`, `$username`,
  `$apacheuser`.
- Produces: `${fogprogramdir}/customizations/ipxe-bg/<name>`,
  `${fogprogramdir}/customizations/ipxe-legacy/`.

- [ ] **Step 1: Add `backupPreservedCustomizations()`**

Insert into `lib/common/functions.sh`, near `backupReports()`:

```bash
# Backs up whatever is actually customized under $webdirdest/service/ipxe/
# BEFORE configureHttpd()'s rm -rf $webdirdest destroys it. Lives outside
# $webdirdest (under $fogprogramdir) so it survives that wipe by
# construction, the same way Secure Boot keys already do. Called from every
# installfog.sh run directly -- this used to only happen via
# bin/updatefog.sh's backupCustomizations(), which meant a bare installfog.sh
# re-run got none of it. See docs/superpowers/specs/2026-08-07-customization-preservation-design.md.
backupPreservedCustomizations() {
    dots "Backing up customizations before rebuilding the web tree"
    local custdir="${fogprogramdir}/customizations"
    local ipxedir="${webdirdest}service/ipxe"
    local st=0
    mkdir -p "$custdir/ipxe-bg" "$custdir/ipxe-legacy" >>$error_log 2>&1 || st=1

    # FOG_IPXE_BG_FILE is a real globalSettings row (see
    # packages/web/commons/schema.php) -- read the ACTUAL value rather than
    # assuming "bg.png". On a first-ever install globalSettings does not
    # exist yet; the query errors into $error_log and $bgfile stays empty,
    # which is treated identically to "nothing customized."
    bgfile=$(mysql $sqloptionsuser --password="${snmysqlpass}" -N -B \
        --execute="SELECT settingValue FROM globalSettings WHERE settingKey='FOG_IPXE_BG_FILE'" \
        $mysqldbname 2>>$error_log)
    if [[ -n $bgfile && -f "${ipxedir}/${bgfile}" ]]; then
        cp -f "${ipxedir}/${bgfile}" "${custdir}/ipxe-bg/${bgfile}" >>$error_log 2>&1 || st=1
    fi

    local f
    for f in refind.conf refind.efi refind_x64.efi refind_ia32.efi refind_aa64.efi; do
        [[ -f "${ipxedir}/${f}" ]] && { cp -f "${ipxedir}/${f}" "${custdir}/ipxe-legacy/${f}" >>$error_log 2>&1 || st=1; }
    done
    errorStat $st
}
```

- [ ] **Step 2: Add `restorePreservedCustomizations()`**

Insert directly after it:

```bash
# Restores what backupPreservedCustomizations() saved, AFTER
# configureTFTPandPXE()'s downloadfiles() has re-laid the default-named
# kernel/init set. Deliberately does NOT restore the six default kernel/init
# names here (bzImage, bzImage32, arm_Image, init.xz, init_32.xz,
# arm_init.cpio.gz) -- an update should pick up the latest kernel. That is
# what Task 4's versioned backup exists to provide a manual restore path for.
restorePreservedCustomizations() {
    dots "Restoring customizations"
    local custdir="${fogprogramdir}/customizations"
    local ipxedir="${webdirdest}service/ipxe"
    local st=0

    if [[ -n $bgfile && -f "${custdir}/ipxe-bg/${bgfile}" ]]; then
        cp -f "${custdir}/ipxe-bg/${bgfile}" "${ipxedir}/${bgfile}" >>$error_log 2>&1 || st=1
    fi
    local f
    for f in refind.conf refind.efi refind_x64.efi refind_ia32.efi refind_aa64.efi; do
        [[ -f "${custdir}/ipxe-legacy/${f}" ]] && { cp -f "${custdir}/ipxe-legacy/${f}" "${ipxedir}/${f}" >>$error_log 2>&1 || st=1; }
    done
    chown -R ${username}:${apacheuser} "$ipxedir" >>$error_log 2>&1
    errorStat $st
}
```

(`$bgfile` is deliberately a plain global here, not `local` in the backup
function, so the restore function -- called later in the same script -- reuses
the exact name that was actually backed up. Matches the existing convention
of cross-function shared state in this file, e.g. `$updatePrevCommit`.)

- [ ] **Step 3: Syntax-check**

Run: `bash -n lib/common/functions.sh`. Expected: no output, exit 0.

- [ ] **Step 4: Wire both calls into `installfog.sh`'s master-install sequence**

In `bin/installfog.sh`, in the `[Nn]` (master install) branch of the big
`case $installtype in` (around line 938), change:
```bash
                    writeUpdateFile
                    backupReports
                    configureHttpd
```
to:
```bash
                    writeUpdateFile
                    backupReports
                    backupPreservedCustomizations
                    configureHttpd
```
and change:
```bash
                    configureTFTPandPXE
                    configureFTP
```
to:
```bash
                    configureTFTPandPXE
                    restorePreservedCustomizations
                    configureFTP
```

- [ ] **Step 5: Syntax-check**

Run: `bash -n bin/installfog.sh`. Expected: no output, exit 0.

- [ ] **Step 6: Manually verify (test Linux box with a running FOG install)**

In the FOG GUI, set `FOG_IPXE_BG_FILE` to a distinct name (e.g.
`custom-bg.png`) and place a real file under
`$webdirdest/service/ipxe/custom-bg.png`. Run `bash installfog.sh -Y`
**directly** (not via `updatefog.sh`). Confirm:
- `custom-bg.png` still exists and is unchanged after the run.
- `ls $fogprogramdir/customizations/ipxe-bg/` shows `custom-bg.png`.
- `FOG_IPXE_BG_FILE` is still `custom-bg.png` in the GUI.

Run once more with `FOG_IPXE_BG_FILE` left at the stock `bg.png` and no
custom file present — confirm no errors, `$bgfile` resolves to `bg.png`, and
nothing unexpected appears under `customizations/ipxe-bg/`.

- [ ] **Step 7: Commit**

```bash
git add lib/common/functions.sh bin/installfog.sh
git commit -m "Move iPXE background backup/restore into installfog.sh, keyed to the actual FOG_IPXE_BG_FILE value"
```

---

### Task 2: Secure Boot admin-supplied key/cert persistence

**Files:**
- Modify: `lib/common/functions.sh` — new `preserveSecureBootAdminFiles()`.
- Modify: `bin/installfog.sh` — call it right after the existing
  `--secure-boot-key`/`--secure-boot-cert` pair validation.

**Interfaces:**
- Consumes: `$secureBootKey`, `$secureBootCert`, `$fogprogramdir`.
- Produces: possibly-reassigned `$secureBootKey`/`$secureBootCert`, now always
  pointing under `${fogprogramdir}/secureboot/`.

> **Correction applied during implementation — do not revert.** An earlier
> draft of this task copied the admin's pair to
> `${fogprogramdir}/secureboot/MOK.key`/`MOK.pem`. That is the exact path
> `_ensureSecureBootKeys()` uses for **FOG's own generated pair**, which it
> never regenerates precisely because every client that already enrolled it
> would be stranded. Copying over it would destroy an enrolled key with no
> backup and no way back. The implementation therefore writes
> `admin-MOK.key`/`admin-MOK.pem` instead, and skips entirely when the
> configured path already resolves to somewhere under
> `${fogprogramdir}/secureboot/`. Continuity across later runs comes from
> `.fogsettings` recording the new path, not from reusing the filename.

- [ ] **Step 1: Add `preserveSecureBootAdminFiles()`**

Insert into `lib/common/functions.sh`, directly before `_ensureSecureBootKeys()`
(`functions.sh:4625`):

```bash
# Closes a real gap: an admin-supplied --secure-boot-key/--secure-boot-cert
# pair is persisted verbatim in .fogsettings (see writeUpdateFile's
# managedKeys) and _ensureSecureBootKeys() trusts that path forever
# (`[[ -n $secureBootKey && -n $secureBootCert ]] && return 0`) without ever
# copying it anywhere. If that path happens to be inside $webdirdest (or
# anywhere else this installer deletes/regenerates), configureHttpd()'s
# rm -rf $webdirdest deletes it before downloadfiles() -> _resignKernels()/
# _publishSecureBootKit() ever read it -- in the SAME run the admin first
# passed the flags. Copying into $fogprogramdir/secureboot/ gives the
# admin-supplied pair the same "outside $webdirdest, survives every wipe by
# construction" guarantee FOG's own generated keys already have, without
# changing _ensureSecureBootKeys()'s "admin-supplied pair always wins, never
# regenerated" contract -- the ORIGINAL file the admin pointed at is still
# never modified; this only decides which COPY gets used and persisted.
# Idempotent: once the copy exists and .fogsettings points at it, this is a
# no-op on every later run, until the admin passes the flags again.
preserveSecureBootAdminFiles() {
    [[ -z $secureBootKey || -z $secureBootCert ]] && return 0
    local keydir="${fogprogramdir}/secureboot"
    local key="${keydir}/MOK.key"
    local cert="${keydir}/MOK.pem"
    local st=0

    mkdir -p "$keydir" >>$error_log 2>&1
    chown root:root "$keydir" >>$error_log 2>&1
    chmod 0700 "$keydir" >>$error_log 2>&1

    if [[ "$(readlink -f "$secureBootKey")" != "$(readlink -f "$key")" ]]; then
        dots "Preserving admin-supplied Secure Boot signing key"
        cp -f "$secureBootKey" "$key" >>$error_log 2>&1 || st=1
        chown root:root "$key" >>$error_log 2>&1
        chmod 0600 "$key" >>$error_log 2>&1
        secureBootKey="$key"
        errorStat $st
    fi
    if [[ "$(readlink -f "$secureBootCert")" != "$(readlink -f "$cert")" ]]; then
        cp -f "$secureBootCert" "$cert" >>$error_log 2>&1 || st=1
        chown root:root "$cert" >>$error_log 2>&1
        chmod 0644 "$cert" >>$error_log 2>&1
        secureBootCert="$cert"
        errorStat $st
    fi
}
```

- [ ] **Step 2: Syntax-check**

Run: `bash -n lib/common/functions.sh`. Expected: no output, exit 0.

- [ ] **Step 3: Call it from `installfog.sh`, right after the existing pair
  validation**

In `bin/installfog.sh`, directly after the existing block (currently ending
around line 681):
```bash
        unset sbfile
    fi
```
add:
```bash
        unset sbfile
    fi
    preserveSecureBootAdminFiles
```
(This must run before `configureMySql`/`configureHttpd` — it already does,
since the option-evaluation block runs near the very top of the script,
long before the `case $doupdate`/main sequence.)

- [ ] **Step 4: Syntax-check**

Run: `bash -n bin/installfog.sh`. Expected: no output, exit 0.

- [ ] **Step 5: Manually verify (test Linux box)**

Generate a throwaway key/cert pair somewhere *inside* what will become
`$webdirdest` (the worst-case gap this closes), e.g.
`/var/www/html/fog/service/ipxe/my.key`/`my.pem`. Run:
```bash
./installfog.sh -Y --secure-boot-key /var/www/html/fog/service/ipxe/my.key \
                    --secure-boot-cert /var/www/html/fog/service/ipxe/my.pem
```
Confirm:
- `/opt/fog/secureboot/MOK.key`/`MOK.pem` now exist and match the originals
  (`diff` them before the run finishes wiping the source, or compare
  checksums captured beforehand).
- `grep secureBootKey /opt/fog/.fogsettings` shows the `/opt/fog/secureboot/...`
  path, not the original `/var/www/html/...` path.
- Kernels under `service/ipxe/` are correctly signed with this key
  (`sbverify --cert /opt/fog/secureboot/MOK.pem service/ipxe/bzImage`).

Run `./installfog.sh -Y` again with no flags: confirm no errors, and the
persisted `/opt/fog/secureboot/...` path is unchanged (no-op copy).

- [ ] **Step 6: Commit**

```bash
git add lib/common/functions.sh bin/installfog.sh
git commit -m "Preserve admin-supplied Secure Boot key/cert outside \$webdirdest"
```

---

### Task 3: FOG-managed vhost block

**Files:**
- Modify: `lib/common/functions.sh` — new `spliceManagedBlock()`; modify
  `createSSLCA()`'s nginx branch (`functions.sh:3536` ff.) and Apache branch
  (`functions.sh:3751` ff.) to write into a temp file and call it instead of
  writing `$etcconf` directly.
- Modify: `bin/updatefog.sh` — flip `$updateVhostFlag`'s default.

**Interfaces:**
- Produces: `spliceManagedBlock(conffile, contentfile)` — general-purpose,
  usable by any future FOG-owned-region-in-an-admin-editable-file need, not
  just this one.
- Consumes: `$etcconf`, `$novhost`, `$timestamp` (all already exist).

- [ ] **Step 1: Add marker constants and `spliceManagedBlock()`**

Insert into `lib/common/functions.sh`, directly before `createSSLCA()`
(`functions.sh:3412`):

```bash
FOG_MANAGED_BEGIN='# === FOG MANAGED BLOCK -- DO NOT EDIT BETWEEN THESE LINES (see docs/SUPPORTED_CUSTOMIZATIONS.md) ==='
FOG_MANAGED_END='# === END FOG MANAGED BLOCK ==='

# Replaces only the FOG-owned region of $1 with $2's content, leaving
# anything an admin added outside that region untouched. If $1 doesn't exist,
# or exists but has no markers yet, this still writes something sane (a
# fresh single-block file, or an appended block onto existing content) -- see
# docs/superpowers/specs/2026-08-07-customization-preservation-design.md,
# Architecture #1, for why this replaces whole-file regeneration instead of a
# separate template-file mechanism.
spliceManagedBlock() {
    local conffile="$1" contentfile="$2"
    if [[ ! -f "$conffile" ]]; then
        { echo "$FOG_MANAGED_BEGIN"; cat "$contentfile"; echo "$FOG_MANAGED_END"; } > "$conffile"
        return $?
    fi
    if grep -qF "$FOG_MANAGED_BEGIN" "$conffile" && grep -qF "$FOG_MANAGED_END" "$conffile"; then
        local tmp="${conffile}.fogsplice.$$"
        awk -v b="$FOG_MANAGED_BEGIN" -v e="$FOG_MANAGED_END" -v cf="$contentfile" '
            $0 == b { print; while ((getline line < cf) > 0) print line; close(cf); skip=1; next }
            $0 == e { print; skip=0; next }
            !skip { print }
        ' "$conffile" > "$tmp" && mv -f "$tmp" "$conffile"
        return $?
    fi
    # No markers found -- an admin's own file, or an upgrade from before this
    # feature existed. Append, never overwrite existing content.
    { echo "$FOG_MANAGED_BEGIN"; cat "$contentfile"; echo "$FOG_MANAGED_END"; } >> "$conffile"
}
```

- [ ] **Step 2: Syntax-check**

Run: `bash -n lib/common/functions.sh`. Expected: no output, exit 0.

- [ ] **Step 3: Redirect the nginx branch's generation into a temp file**

In `createSSLCA()`'s nginx branch, change the first write (currently, per
line 3567-3568):
```bash
                    mv -fv "${etcconf}" "${etcconf}.${timestamp}" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    echo "server {" > "$etcconf"
```
to:
```bash
                    mv -fv "${etcconf}" "${etcconf}.${timestamp}" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    fogvhosttmp="${etcconf}.fogblock.$$"
                    echo "server {" > "$fogvhosttmp"
```
Then change every remaining `>> "$etcconf"` line in this branch (both the
plain-HTTP and the HTTPS/redirect sub-branches -- everything between this
point and the branch's closing `;;`) to `>> "$fogvhosttmp"`. This is a
mechanical find-and-replace within the branch's existing lines; do not change
their *content*, only the redirect target. At the very end of the branch
(after its last `echo "}" >> "$fogvhosttmp"` or equivalent), add:
```bash
                    spliceManagedBlock "$etcconf" "$fogvhosttmp"
                    rm -f "$fogvhosttmp"
                    diffconfig "${etcconf}"
```
(`diffconfig`'s existing call site, if any, at the end of this branch should
be removed if duplicated -- keep exactly one call.)

- [ ] **Step 4: Repeat for the Apache branch**

Same mechanical change in the `httpd|apache*)` branch (`functions.sh:3751`
ff.): redirect every `>> "$etcconf"` (and the branch's initial `>` if any) to
a `$fogvhosttmp` temp file, then call `spliceManagedBlock "$etcconf"
"$fogvhosttmp"` once at the end of the branch, followed by `diffconfig`.
This branch has three `ServerAlias` write sites in sequence (all within the
same `$etcconf`/temp file) -- all three simply redirect to the same
`$fogvhosttmp`, since they all belong in the same single managed block.

- [ ] **Step 5: Syntax-check**

Run: `bash -n lib/common/functions.sh`. Expected: no output, exit 0.

- [ ] **Step 6: Manually verify — fresh file (test Linux box)**

Remove any existing vhost file, run `./installfog.sh -Y`. Confirm the file
now contains `$FOG_MANAGED_BEGIN`/`$FOG_MANAGED_END` markers wrapping exactly
the same content it would have contained before this change (diff against a
pre-change run's output, ignoring the two marker lines).

- [ ] **Step 7: Manually verify — preserves admin additions**

Hand-append a distinctive block after the managed block's `END` marker (e.g.
a comment + a harmless extra `Alias`/`location` directive). Run
`./installfog.sh -Y --extra-server-name fog-test2.internal`. Confirm:
- The hand-appended block is still present, unchanged, after the `END` marker.
- The managed block's `server_name`/`ServerName`/`ServerAlias` now includes
  `fog-test2.internal`.

- [ ] **Step 8: Manually verify — no-markers-yet file gets appended to, not
  replaced**

Simulate an upgrade from before this feature: hand-write a vhost file with no
markers at all (arbitrary content). Run `./installfog.sh -Y`. Confirm the
original content is still present, with a new managed block appended after it
containing FOG's usual content.

- [ ] **Step 9: Verify `--overwrite-vhost` and `-F`/`--no-vhost` still behave
  as documented**

`--overwrite-vhost` (via `updatefog.sh`, or by removing the vhost file
manually and re-running `installfog.sh`) discards everything and writes a
fresh single-block file. `-F`/`--no-vhost` leaves the file 100% untouched,
including not adding markers.

- [ ] **Step 10: Flip `updatefog.sh`'s default `$updateVhostFlag`**

In `bin/updatefog.sh`, change:
```bash
updateVhostFlag="-F"
```
to:
```bash
# Splicing the FOG-managed block (see spliceManagedBlock, functions.sh) is
# always safe now -- it only ever touches FOG's own marked region, never an
# admin's own additions outside it. -F remains available for an admin who
# wants installfog.sh to touch the vhost file not at all.
updateVhostFlag=""
```
Update the comment above the old assignment (currently explaining why `-F`
was the default) to reflect this, and update `usage()`'s `--overwrite-vhost`
help text if it references the old default.

- [ ] **Step 11: Syntax-check**

Run: `bash -n bin/updatefog.sh`. Expected: no output, exit 0.

- [ ] **Step 12: Manually verify `updatefog.sh`'s new default end to end**

On a test box with a hand-customized vhost (appended content, per Step 7),
run `./updatefog.sh -y`. Confirm the appended content survives and the
managed block still refreshes normally.

- [ ] **Step 13: Commit**

```bash
git add lib/common/functions.sh bin/updatefog.sh
git commit -m "Replace whole-file vhost regeneration with a FOG-managed block"
```

---

### Task 4: Versioned kernel/init backup + `bin/restorekernel.sh`

**Files:**
- Modify: `lib/common/functions.sh` — extend `backupPreservedCustomizations()`/
  `restorePreservedCustomizations()` (Task 1) with generation rotation; add
  `kernelBackupGenerations` to `writeUpdateFile()`'s `managedKeys`
  (`functions.sh:3122-3171`).
- Modify: `bin/installfog.sh` — new `--kernel-backup-count` flag,
  `--restore-kernel-backup` flag.
- Create: `bin/restorekernel.sh`.

**Interfaces:**
- Produces: `${fogprogramdir}/customizations/kernel-backups/gen-1..N/`.
- Consumes: `$kernelBackupGenerations` (default 3).

- [ ] **Step 1: Add generation rotation to `backupPreservedCustomizations()`**

In `lib/common/functions.sh`, extend the function added in Task 1 by
appending, before its final `errorStat $st`:

```bash
    [[ -z $kernelBackupGenerations || $kernelBackupGenerations -lt 1 ]] && kernelBackupGenerations=3
    local kbdir="${custdir}/kernel-backups"
    mkdir -p "$kbdir" >>$error_log 2>&1
    [[ -d "${kbdir}/gen-${kernelBackupGenerations}" ]] && rm -rf "${kbdir}/gen-${kernelBackupGenerations}"
    local k
    for ((k = kernelBackupGenerations - 1; k >= 1; k--)); do
        [[ -d "${kbdir}/gen-${k}" ]] && mv "${kbdir}/gen-${k}" "${kbdir}/gen-$((k + 1))"
    done
    mkdir -p "${kbdir}/gen-1" >>$error_log 2>&1
    [[ -d "$ipxedir" ]] && cp -a "${ipxedir}/." "${kbdir}/gen-1/" >>$error_log 2>&1 || st=1
```

- [ ] **Step 2: Add custom-named-file + revert-carve-out restore to
  `restorePreservedCustomizations()`**

Extend the function from Task 1, appending before its final `errorStat $st`:

```bash
    local kbdir="${custdir}/kernel-backups"
    local defaultnames="bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz"
    if [[ -d "${kbdir}/gen-1" ]]; then
        for f in "${kbdir}/gen-1"/*; do
            [[ -f $f ]] || continue
            local bn=$(basename "$f")
            local isdefault=0
            for d in $defaultnames; do [[ $bn == $d ]] && isdefault=1; done
            # Custom-named files (e.g. a per-host kernel/init override, see
            # packages/web/lib/fog/bootmenu.class.php Host->get('kernel'))
            # are restored unconditionally -- FOG never re-downloads these,
            # so nothing else will put them back.
            if [[ $isdefault -eq 0 ]]; then
                cp -f "$f" "${ipxedir}/${bn}" >>$error_log 2>&1 || st=1
            elif [[ $restoreKernelBackup -eq 1 ]]; then
                # --restore-kernel-backup (revertUpdate's re-invocation only):
                # deliberately ALSO restores the default names, matching the
                # previous _restorePreviousKernel()'s revert-only behavior.
                cp -f "$f" "${ipxedir}/${bn}" >>$error_log 2>&1 || st=1
            fi
        done
    fi
```

- [ ] **Step 3: Syntax-check**

Run: `bash -n lib/common/functions.sh`. Expected: no output, exit 0.

- [ ] **Step 4: Add `kernelBackupGenerations` to `managedKeys`**

In `writeUpdateFile()` (`functions.sh:3122-3171`), add `kernelBackupGenerations`
alongside `extraServerNames` (line 3164):
```bash
        extraServerNames
        # How many prior kernel/init generations to keep under
        # customizations/kernel-backups/ (see --kernel-backup-count).
        # Persisted so an admin's chosen retention survives future upgrades.
        kernelBackupGenerations
```

- [ ] **Step 5: Add `--kernel-backup-count` and `--restore-kernel-backup` to
  `installfog.sh`**

Add `kernel-backup-count:,restore-kernel-backup` to `longopts`
(`bin/installfog.sh:173`). Add case branches modeled on `--fogprogramdir`:
```bash
        --kernel-backup-count)
            if [[ -n "${2}" && "${2}" =~ ^[0-9]+$ && "${2}" -ge 1 ]]; then
                skernelBackupCount="${2}"
            else
                echo "Error: --kernel-backup-count requires a positive integer"
                usage
                exit 9
            fi
            shift 2
            ;;
        --restore-kernel-backup)
            restoreKernelBackup=1
            shift
            ;;
```
Apply the staging var in the option-evaluation block (`bin/installfog.sh:615-681`):
```bash
[[ -n $skernelBackupCount ]] && kernelBackupGenerations=$skernelBackupCount
[[ -z $restoreKernelBackup ]] && restoreKernelBackup=0
```
Add both to `usage()`'s help text, near `--secure-boot-*`.

- [ ] **Step 6: Syntax-check**

Run: `bash -n bin/installfog.sh`. Expected: no output, exit 0.

- [ ] **Step 7: Write `bin/restorekernel.sh`**

Model directly on `bin/setupacme.sh`'s structure -- **note:** as of this plan
`bin/setupacme.sh` no longer exists (see
`docs/superpowers/plans/2026-08-07-three-zone-pki-separation.md`, which
removes it). If that plan has already landed when this task is implemented,
model this script's root-check/`.fogsettings`-sourcing/`error_logs/` setup
boilerplate on `bin/updatefog.sh` instead -- the shape is the same either way.

```bash
#!/bin/bash
# ...license header, matching bin/updatefog.sh...
#
# Restores a prior kernel/init generation backed up by
# backupPreservedCustomizations() (lib/common/functions.sh) under
# $fogprogramdir/customizations/kernel-backups/. See
# docs/SUPPORTED_CUSTOMIZATIONS.md and
# docs/superpowers/specs/2026-08-07-customization-preservation-design.md.
bindir=$(dirname $(readlink -f "$BASH_SOURCE"))
cd $bindir
workingdir=$(pwd)

if [[ ! $EUID -eq 0 ]]; then
    echo "restorekernel.sh must be run as root user"
    exit 1
fi

usage() {
    echo -e "Usage: $0 [-h?] (--list | --generation <N>)"
    echo -e "\t-h -? --help\t\tDisplay this info"
    echo -e "\t      --list\t\tList available backup generations and their FOS release tag"
    echo -e "\t      --generation\tRestore generation N (1 = most recent) into service/ipxe/"
    exit 0
}

shortopts="h?"
longopts="help,list,generation:"
optargs=$(getopt -o $shortopts -l $longopts -n "$0" -- "$@")
[[ $? -ne 0 ]] && usage
eval set -- "$optargs"

doList=0
generation=""
while :; do
    case $1 in
        -h | -\? | --help) usage ;;
        --list) doList=1; shift ;;
        --generation) generation="$2"; shift 2 ;;
        --) shift; break ;;
        *) echo "Error: unhandled option '$1'."; exit 10 ;;
    esac
done

exitFail=1
. ../lib/common/functions.sh

[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
fogprogramdir="${fogprogramdir%/}"

if [[ ! -r "$fogprogramdir/.fogsettings" ]]; then
    echo " * No existing FOG install found at $fogprogramdir (.fogsettings missing)."
    exit 1
fi
. "$fogprogramdir/.fogsettings"

kbdir="${fogprogramdir}/customizations/kernel-backups"

if [[ $doList -eq 1 ]]; then
    for gendir in "$kbdir"/gen-*; do
        [[ -d $gendir ]] || continue
        echo "$(basename "$gendir"):"
        for f in "$gendir"/bzImage "$gendir"/init.xz; do
            [[ -f $f ]] || continue
            tag=$(attr -g tag_name "$f" 2>/dev/null)
            echo "  $(basename "$f") (${tag:-unknown release})"
        done
    done
    exit 0
fi

if [[ -z $generation ]]; then
    echo " * Pass --list or --generation <N>."
    usage
fi

gendir="${kbdir}/gen-${generation}"
if [[ ! -d $gendir ]]; then
    echo " * No such generation: $gendir"
    exit 1
fi

ipxedir="${webdirdest}service/ipxe"
echo " * Restoring generation $generation into $ipxedir"
cp -af "${gendir}/." "$ipxedir/" && chown -R ${username}:${apacheuser} "$ipxedir"

if [[ -n $secureBootKey && -n $secureBootCert ]]; then
    echo " * Re-checking Secure Boot signatures on restored kernels"
    _resignKernels
fi
echo " * Done."
```

- [ ] **Step 8: Make executable, syntax-check**

Run: `chmod +x bin/restorekernel.sh && bash -n bin/restorekernel.sh`.
Expected: no output, exit 0.

- [ ] **Step 9: Manually verify (test Linux box)**

Run `installfog.sh -Y` three times in a row (simulating three updates).
Confirm `bin/restorekernel.sh --list` shows three generations with distinct
`tag_name` values. Run `bin/restorekernel.sh --generation 2`; confirm
`service/ipxe/bzImage`'s `tag_name` xattr now matches generation 2's, and
(if Secure Boot is configured) it still verifies against the current
signing cert.

- [ ] **Step 10: Wire `--restore-kernel-backup` into `updatefog.sh`'s
  `revertUpdate()`**

In `lib/common/update.sh`, change `revertUpdate()`'s re-invocation (currently
line 116):
```bash
    (cd "$fog_git_path/bin" && bash installfog.sh -Y $updateVhostFlag >>$error_log 2>&1)
```
to:
```bash
    (cd "$fog_git_path/bin" && bash installfog.sh -Y $updateVhostFlag --restore-kernel-backup >>$error_log 2>&1)
```
and delete the now-redundant `_restorePreviousKernel` / `restoreCustomizations`
calls immediately below it (superseded — see Task 6, which removes their
definitions entirely; this step just stops calling them here first so Task 6
doesn't leave a dangling reference mid-task).

- [ ] **Step 11: Syntax-check**

Run: `bash -n lib/common/update.sh`. Expected: no output, exit 0.

- [ ] **Step 12: Commit**

```bash
git add lib/common/functions.sh bin/installfog.sh bin/restorekernel.sh lib/common/update.sh
git commit -m "Add bounded, versioned kernel/init backup and bin/restorekernel.sh"
```

---

### Task 5: Custom PXE script hook (`custom.ipxe` via `default.ipxe`)

**Files:**
- Modify: `lib/common/functions.sh` — `configureDefaultiPXEfile()`
  (`functions.sh:1037-1042`).

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed elsewhere — purely additive content in a
  generated file.

- [ ] **Step 1: Add the hook line to `configureDefaultiPXEfile()`**

Change `functions.sh:1040` from:
```bash
    echo -e "#!ipxe\nset arch \${buildarch}\niseq \${arch} i386 && cpuid --ext 29 && set arch x86_64 ||\nparams\nparam mac0 \${net0/mac}\nparam arch \${arch}\nparam platform \${platform}\nparam product \${product}\nparam manufacturer \${product}\nparam ipxever \${version}\nparam filename \${filename}\nparam sysuuid \${uuid}\nisset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\nisset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n:bootme\nchain ${httpproto}://$ipaddress${webroot}service/ipxe/boot.php##params" > "$tftpdirdst/default.ipxe"
```
to:
```bash
    # chain custom.ipxe first if an admin has placed one at the TFTP root --
    # see docs/SUPPORTED_CUSTOMIZATIONS.md. Per ipxe.org/cmd/chain, chain
    # WITHOUT --replace returns control to the next line once the chained
    # script finishes normally, so a present-and-successful custom.ipxe falls
    # straight through into :fog_default afterward with no special "resume"
    # convention needed. A missing/failed chain hits the || immediately, so
    # default boot behavior is byte-for-byte unchanged when no custom.ipxe
    # exists.
    echo -e "#!ipxe\nchain custom.ipxe || goto fog_default\n:fog_default\nset arch \${buildarch}\niseq \${arch} i386 && cpuid --ext 29 && set arch x86_64 ||\nparams\nparam mac0 \${net0/mac}\nparam arch \${arch}\nparam platform \${platform}\nparam product \${product}\nparam manufacturer \${product}\nparam ipxever \${version}\nparam filename \${filename}\nparam sysuuid \${uuid}\nisset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\nisset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n:bootme\nchain ${httpproto}://$ipaddress${webroot}service/ipxe/boot.php##params" > "$tftpdirdst/default.ipxe"
```

- [ ] **Step 2: Syntax-check**

Run: `bash -n lib/common/functions.sh`. Expected: no output, exit 0.

- [ ] **Step 3: Manually verify — absent (default, test Linux box or a PXE
  test VM)**

Run `installfog.sh -Y`. Confirm `$tftpdirdst/default.ipxe` now begins with
the `chain custom.ipxe || goto fog_default` / `:fog_default` lines. PXE-boot
a test client with no `custom.ipxe` present at the TFTP root; confirm boot
proceeds to FOG's menu/imaging exactly as before this change.

- [ ] **Step 4: Manually verify — present**

Place a minimal script at the TFTP root, e.g.:
```
#!ipxe
echo Custom hook running, pausing 10s...
sleep 10
```
(`$tftpdirdst/custom.ipxe`). PXE-boot the same test client; confirm the
message and delay appear, then normal FOG boot proceeds immediately
afterward with no further action needed in the custom script.

- [ ] **Step 5: Commit**

```bash
git add lib/common/functions.sh
git commit -m "Add optional custom.ipxe hook point ahead of FOG's default PXE boot logic"
```

---

### Task 6: Retire `lib/common/update.sh`'s superseded functions; shrink `bin/updatefog.sh`

**Files:**
- Modify: `lib/common/update.sh` — remove `_updateAssetFiles()`,
  `backupCustomizations()`, `restoreCustomizations()`,
  `_restorePreviousKernel()`.
- Modify: `bin/updatefog.sh` — remove the now-dead `backupCustomizations`/
  `restoreCustomizations` call sites.

**Interfaces:**
- Consumes: nothing (this task only removes code Tasks 1-4 made redundant).

- [ ] **Step 1: Delete the superseded functions**

In `lib/common/update.sh`, delete `_updateAssetFiles()`,
`backupCustomizations()`, `restoreCustomizations()`, and
`_restorePreviousKernel()` (lines 28-71 in the pre-this-plan file — verify
against the current file state after Task 4's Step 10 edit, which already
removed their call sites from `revertUpdate()`). Update the file's top
comment (lines 19-27) to describe its now-narrower scope (git
fetch/checkout/revert only).

- [ ] **Step 2: Remove the dead calls from `bin/updatefog.sh`**

Remove the `backupCustomizations` call (currently line 249, immediately
before `gitUpdateToBranch`) and the `restoreCustomizations` call (currently
line 264, in the success branch) — both are now handled unconditionally
inside every `installfog.sh` invocation via Tasks 1/4.

- [ ] **Step 3: Syntax-check**

Run: `bash -n lib/common/update.sh && bash -n bin/updatefog.sh`. Expected: no
output, exit 0 for both.

- [ ] **Step 4: Manually verify end to end (test Linux box)**

Run a full `updatefog.sh -y` update with a customized `bg.png`-equivalent and
a hand-appended vhost addition in place. Confirm both survive (this is now
exercised entirely through `installfog.sh`'s own logic, with `updatefog.sh`
doing nothing but the git sync and invocation).

Force a failure (e.g. temporarily break connectivity mid-update) and confirm
`revertUpdate()` still correctly reverts and restores, now via
`--restore-kernel-backup` rather than the deleted `_restorePreviousKernel`.

- [ ] **Step 5: Commit**

```bash
git add lib/common/update.sh bin/updatefog.sh
git commit -m "Remove update.sh's superseded backup/restore -- now handled unconditionally inside installfog.sh"
```

---

### Task 7: `docs/SUPPORTED_CUSTOMIZATIONS.md`

**Files:**
- Create: `docs/SUPPORTED_CUSTOMIZATIONS.md`.
- Modify: `bin/installfog.sh`'s `usage()` and `bin/updatefog.sh`'s `usage()`
  — point admins at the new doc from the relevant flags' help text
  (`--kernel-backup-count`, `--restore-kernel-backup`, `--overwrite-vhost`).

**Interfaces:**
- Consumes: nothing — documentation only.

- [ ] **Step 1: Write `docs/SUPPORTED_CUSTOMIZATIONS.md`**

Follow the outline in
`docs/superpowers/specs/2026-08-07-customization-preservation-design.md`'s
"Architecture §6" section: one `##` heading per customization category
(iPXE background, vhost, kernel/init, custom PXE scripts, Secure Boot certs),
each with a short paragraph and a small table of
`| Customization | How it's preserved | Where |`, plus a closing "What is NOT
automatically preserved" section covering in-block vhost hand edits and
signing-key rotation across a kernel restore.

- [ ] **Step 2: Cross-link from flag help text**

Add a `See docs/SUPPORTED_CUSTOMIZATIONS.md` line to `usage()` in both
`bin/installfog.sh` and `bin/updatefog.sh`, near `--kernel-backup-count`,
`--restore-kernel-backup`, `--overwrite-vhost`, and `-F`/`--no-vhost`.

- [ ] **Step 3: Commit**

```bash
git add docs/SUPPORTED_CUSTOMIZATIONS.md bin/installfog.sh bin/updatefog.sh
git commit -m "Document supported install-time customizations"
```

---

### Critical Files for Implementation

- `lib/common/functions.sh` — hosts nearly every new/changed function: `backupPreservedCustomizations()`, `restorePreservedCustomizations()`, `preserveSecureBootAdminFiles()`, `spliceManagedBlock()`, the modified `createSSLCA()` write sites, `configureDefaultiPXEfile()`'s hook line, and `writeUpdateFile()`'s `managedKeys`.
- `bin/installfog.sh` — new flags (`--kernel-backup-count`, `--restore-kernel-backup`), the sequence wiring at the master-install call chain (~lines 938-963), and the secure-boot option-evaluation call site (~line 681).
- `lib/common/update.sh` — where the superseded `_updateAssetFiles()`/`backupCustomizations()`/`restoreCustomizations()`/`_restorePreviousKernel()` are removed and `revertUpdate()` is trimmed.
- `bin/updatefog.sh` — `$updateVhostFlag`'s default flip and removal of the now-redundant backup/restore call sites.
- `packages/web/commons/schema.php` and `packages/web/lib/fog/bootmenu.class.php` — read-only references confirming `FOG_IPXE_BG_FILE`'s real semantics and the per-host `Host->get('kernel')`/`get('init')` custom-name feature; no changes needed here, but any implementer should re-read them before touching Task 1/4.
