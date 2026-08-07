# Web vhost cert separation + Let's Encrypt scaffolding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin (1) set/override the web vhost's primary and extra server names non-interactively, and (2) automate leaf-certificate renewal against an already-imported `--external-ca` CA via `acme.sh`.

**Architecture:** Three additive, independent pieces on top of unchanged existing behavior: a `--hostname` flag reusing the existing `$hostname` machinery, a new `--extra-server-name` (repeatable) flag with its own persistence and vhost/CSR wiring, and a new `bin/setupacme.sh` script that bootstraps `acme.sh` against a CA already imported via `--external-ca`. `acme.sh`'s own installer sets up its own renewal cron job — FOG does not add a second scheduling mechanism.

**Tech Stack:** Bash (installer/updater scripts), OpenSSL (CSR/cert generation), `acme.sh` (ACME client), MySQL (`globalSettings` mirror).

## Global Constraints

- No CI/test framework exists for this repo's shell scripts (confirmed: only `fogproject-install-validation`'s end-to-end distro matrix). Every task's "test" step is a manual invocation + assertion on real output, not a unit-test suite. Always run `bash -n <file>` after every edit to a shell script before anything else.
- Every new CLI value that reaches a file write (vhost config, OpenSSL config, `.fogsettings`) must be validated first — never interpolated unchecked. This repo already treats this as a real security boundary (see `--git-path`'s absolute-path check, `--fogprogramdir`'s check).
- Follow the existing staging-variable convention exactly: a new flag sets an `s`-prefixed variable during `getopt` parsing (e.g. `shostname`), which is applied to the real variable (`hostname`) only *after* `.fogsettings` has been sourced, in the `# evaluation of command line options` block (`bin/installfog.sh:615-638`) — never before, or an upgrade's persisted value would be silently blanked before it's even read.
- `bin/updatefog.sh` never runs `installfog.sh` interactively (always `-Y`) — any new flag added there must be passed straight through to the child `bash installfog.sh -Y ...` invocation, the same way `$updateVhostFlag` already is (`bin/updatefog.sh:222`).
- This branch (`1013-ipxe-crosscert-doc-fix`) is rebased onto `1012-vhost-tftp-warnings-custom-branch-sticky-channel` — `bin/updatefog.sh` already has `--branch`, `--overwrite-vhost`, `$updateVhostFlag`, and `gitUpdateToBranch()` from that branch. Do not reintroduce or duplicate any of that.
- Design doc: `docs/superpowers/specs/2026-08-07-cert-separation-letsencrypt-design.md`. Read it if anything below is ambiguous — the plan follows it exactly, with one correction: `acme.sh --install` already sets up its own renewal cron job, so `setupacme.sh` does **not** write a `/etc/cron.d` entry itself (the spec's mention of matching `setupFogReporting()`'s cron pattern is superseded by this finding).

---

### Task 1: `--hostname` flag (non-interactive server name override)

**Files:**
- Modify: `lib/common/functions.sh` — add a `validhostname()` function near `validip()` (`lib/common/functions.sh:442-454`).
- Modify: `bin/installfog.sh` — add the flag to `longopts` (`bin/installfog.sh:167`), add a case-statement branch modeled on `--fogprogramdir` (`bin/installfog.sh:215-227`), apply the staging var in the command-line-evaluation block (`bin/installfog.sh:615-638`), add it to `usage()`'s help text.
- Modify: `bin/updatefog.sh` — add `--hostname` to `longopts` (`bin/updatefog.sh:61`), a case-statement branch, a pass-through variable, forward it on the child invocation (`bin/updatefog.sh:222`), document it in `usage()`.

**Interfaces:**
- Produces: `validhostname("<value>")` — echoes `0` if `<value>` is a syntactically valid hostname (RFC-1123-style: labels of alphanumerics/hyphens, no leading/trailing hyphen per label, dot-separated, no other characters), `1` otherwise. Same calling convention as `validip()` (`[[ $(validhostname "$x") -ne 0 ]]` means invalid).
- Consumes: nothing new — reuses the existing `$hostname` variable, already in `writeUpdateFile()`'s `managedKeys` (`lib/common/functions.sh:3109`) and already used by `createSSLCA()`'s vhost/CSR generation.

- [ ] **Step 1: Add `validhostname()` to `lib/common/functions.sh`**

Insert immediately after `validip()` (which ends at line 454):

```bash
# Same calling convention as validip(): echo 0/1, checked via
# [[ $(validhostname "$x") -ne 0 ]]. RFC-1123-ish: dot-separated labels of
# alphanumerics/hyphens, no leading/trailing hyphen per label. Needed because
# --hostname/--extra-server-name are the first NON-interactive entry point for
# this value -- the interactive prompt in lib/common/newinput.sh has never
# validated what an admin types, but a CLI flag's value reaches a vhost config
# and an OpenSSL CSR config file unchecked, so it must be checked before either.
validhostname() {
    local h=$1
    [[ $h =~ ^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$ ]]
    echo $?
}
```

- [ ] **Step 2: Syntax-check**

Run: `bash -n lib/common/functions.sh`
Expected: no output, exit 0.

- [ ] **Step 3: Manually verify `validhostname()`**

Run:
```bash
cd bin && . ../lib/common/functions.sh
for h in "fog.example.com" "fogserver" "fog_server" "fog server" "-badstart.com" "fine-name.co.uk"; do
    echo "$h -> $(validhostname "$h")"
done
```
Expected output:
```
fog.example.com -> 0
fogserver -> 0
fog_server -> 1
fog server -> 1
-badstart.com -> 1
fine-name.co.uk -> 0
```

- [ ] **Step 4: Add `--hostname` to `installfog.sh`**

In `bin/installfog.sh:167`, add `hostname:` to `longopts` (anywhere in the comma list, e.g. right after `fogprogramdir:`):
```
longopts="help,uninstall,purge-db,purge-images,purge-snapins,purge-ssl,purge-user,purge-all,dry-run,force,mysqldbname:,ssl-path:,oldcopy,no-vhost,no-defaults,no-upgrade,no-htmldoc,force-https,no-force-https,recreate-keys,recreate-CA,recreate-Ca,recreate-cA,recreate-ca,external-ca,ca-cert:,ca-key:,ca-root:,autoaccept,file:,docroot:,webroot:,backuppath:,startrange:,endrange:,no-exportbuild,exitFail,no-tftpbuild,list-packages,fogprogramdir:,secure-boot-key:,secure-boot-cert:,no-secure-boot,hostname:"
```

Add a case branch right after the `--fogprogramdir)` block (`bin/installfog.sh:215-227`):
```bash
        --hostname)
            if [[ -n "${2}" ]] && [[ $(validhostname "${2}") -eq 0 ]]; then
                shostname="${2}"
            else
                echo "Error: --hostname requires a valid hostname"
                usage
                exit 9
            fi
            shift 2
            ;;
```

- [ ] **Step 5: Apply the staging var after `.fogsettings` is sourced**

In the "# evaluation of command line options" block (`bin/installfog.sh:615-638`), add a line alongside the others there (e.g. after `[[ -n $shttpproto ]] && httpproto=$shttpproto` on line 616):
```bash
[[ -n $shostname ]] && hostname=$shostname
```

- [ ] **Step 6: Add `--hostname` to `usage()`**

Find the `--fogprogramdir` line in `usage()` and add directly below it:
```
	echo -e "\t      --hostname\t\tOverride the vhost/cert hostname"
	echo -e "\t                \t\tdefaults to \`hostname -f\`, remembered in .fogsettings"
```

- [ ] **Step 7: Syntax-check**

Run: `bash -n bin/installfog.sh`
Expected: no output, exit 0.

- [ ] **Step 8: Add `--hostname` pass-through to `updatefog.sh`**

In `bin/updatefog.sh:61`, add `hostname:` to `longopts`:
```
longopts="help,channel:,branch:,git-path:,no-revert,overwrite-vhost,yes,hostname:"
```

Add a case branch alongside `--git-path` (`bin/updatefog.sh:87-95`):
```bash
        --hostname)
            if [[ -n "${2}" ]]; then
                supdatehostname="${2}"
            else
                echo "Error: --hostname requires a value"
                usage
            fi
            shift 2
            ;;
```

(Validation happens once, in the child `installfog.sh`, via Step 4's check — no need to duplicate the regex here.)

- [ ] **Step 9: Forward it on the child invocation**

Change `bin/updatefog.sh:222` from:
```bash
(cd "$fog_git_path/bin" && bash installfog.sh -Y $updateVhostFlag >>$error_log 2>&1)
```
to:
```bash
(cd "$fog_git_path/bin" && bash installfog.sh -Y $updateVhostFlag ${supdatehostname:+--hostname "$supdatehostname"} >>$error_log 2>&1)
```

- [ ] **Step 10: Add `--hostname` to `updatefog.sh`'s `usage()`**

Add a line next to the `--git-path` help line:
```
    echo -e "\t      --hostname\tOverride the vhost/cert hostname for this update"
```

- [ ] **Step 11: Syntax-check**

Run: `bash -n bin/updatefog.sh`
Expected: no output, exit 0.

- [ ] **Step 12: Manually verify end to end (requires a Linux box with a FOG checkout — cannot be run from this Windows dev machine; run on a test VM)**

Run: `./installfog.sh -Y --hostname fog-test.example.com`
Then inspect the generated vhost (`$etcconf` — e.g. `/etc/httpd/conf/extra/fog.conf` on RedHat, or the nginx equivalent) and confirm `server_name`/`ServerName`/`ServerAlias` contains `fog-test.example.com`, and that `grep hostname /opt/fog/.fogsettings` shows `hostname='fog-test.example.com'`.
Expected: both true.

Run again without `--hostname`: `./installfog.sh -Y`
Expected: `.fogsettings` still shows `hostname='fog-test.example.com'` (persisted value survives, matching `fog_update_channel`'s existing behavior).

- [ ] **Step 13: Commit**

```bash
git add lib/common/functions.sh bin/installfog.sh bin/updatefog.sh
git commit -m "Add --hostname flag for non-interactive vhost/cert hostname override"
```

---

### Task 2: `--extra-server-name` flag (additive extra vhost/cert names)

**Files:**
- Modify: `lib/common/functions.sh`:
  - `writeUpdateFile()`'s `managedKeys` array (`lib/common/functions.sh:3108-3146`) — add `extraServerNames`.
  - `createSSLCA()` — add a shared suffix variable right before `case $webserver in` (`lib/common/functions.sh:3492-3493`), use it in the three nginx `server_name` lines (`lib/common/functions.sh:3528`, `3579`, `3646`) and Apache's `vhostaliases` (`lib/common/functions.sh:3732`).
  - The CSR SAN block (`lib/common/functions.sh:3428-3434` for the `sanentries` IP loop, and both heredocs at `3448-3460` and `3471-3477`) — add extra `DNS.N` entries.
- Modify: `bin/installfog.sh` — repeatable flag parsing (array), staging-var application, `usage()`.
- Modify: `bin/updatefog.sh` — repeatable pass-through, `usage()`.

**Interfaces:**
- Produces: `$extraServerNames` — a space-joined string of extra names (mirrors how `$ipaddresses` is already a space/newline-joined string consumed via unquoted `for` word-splitting elsewhere in this file, e.g. `lib/common/functions.sh:3430`). Persisted in `.fogsettings` as a managed key.
- Consumes: `validhostname()` from Task 1.

- [ ] **Step 1: Add `extraServerNames` to `managedKeys`**

In `lib/common/functions.sh:3145`, change:
```bash
        fog_git_path fog_update_channel
    )
```
to:
```bash
        fog_git_path fog_update_channel
        # A genuine persisted preference like fog_update_channel above, not a
        # RECORD like fogprogramdir/fog_git_path -- an admin's extra vhost/cert
        # name(s) must carry forward on every upgrade, not just the run they
        # were set on.
        extraServerNames
    )
```

- [ ] **Step 2: Syntax-check**

Run: `bash -n lib/common/functions.sh`
Expected: no output, exit 0.

- [ ] **Step 3: Add the shared suffix variable and use it in the vhost writers**

In `lib/common/functions.sh`, immediately after line 3492
(`[[ $httpproto == https ]] && sslenabled=" (Forced SSL)" || sslenabled=" (normal)"`)
and before line 3493 (`case $webserver in`), insert:

```bash
    # $extraServerNames is a space-joined string (see --extra-server-name).
    # Computed once here and reused by both the nginx server_name lines below
    # and Apache's vhostaliases, so an admin's extra name(s) reach every vhost
    # block this function writes, not just one.
    extraServerNamesSuffix=""
    for extraname in $extraServerNames; do
        extraServerNamesSuffix="${extraServerNamesSuffix} ${extraname}"
    done
```

Change all three occurrences of (lines 3528, 3579, 3646):
```bash
                    echo "    server_name $ipaddresses $hostname;" >> "$etcconf"
```
to:
```bash
                    echo "    server_name $ipaddresses $hostname${extraServerNamesSuffix};" >> "$etcconf"
```
(preserve each line's original indentation — 3528 and the other two have different indent levels in the file, only the content changes).

Change line 3732 from:
```bash
                    vhostaliases=$(echo $ipaddresses | awk '{for (i = 2; i <= NF; i++) printf " %s", $i}')
```
to:
```bash
                    vhostaliases=$(echo $ipaddresses | awk '{for (i = 2; i <= NF; i++) printf " %s", $i}')
                    vhostaliases="${vhostaliases}${extraServerNamesSuffix}"
```
(this one line feeds all three `ServerAlias ${hostname}${vhostaliases}` occurrences at 3752/3800/3898 — do not edit those three lines directly).

- [ ] **Step 4: Add extra SAN entries to both CSR configs**

In `lib/common/functions.sh`, immediately after the existing `sanentries` IP loop (lines 3430-3434):
```bash
    for ip in $ipaddresses; do
        sancount=$((sancount + 1))
        [[ -n $sanentries ]] && sanentries="${sanentries}"$'\n'
        sanentries="${sanentries}IP.${sancount} = ${ip}"
    done
```
add:
```bash
    dnscount=1
    dnsSanEntries=""
    for extraname in $extraServerNames; do
        dnscount=$((dnscount + 1))
        dnsSanEntries="${dnsSanEntries}"$'\n'"DNS.${dnscount} = ${extraname}"
    done
```

Change both heredocs' `DNS.1 = $hostname` lines (3459 and 3476) from:
```
DNS.1 = $hostname
```
to:
```
DNS.1 = $hostname$dnsSanEntries
```
(both occurrences — the `req.cnf` heredoc ending at line 3460 and the `ca.cnf` heredoc ending at line 3477).

- [ ] **Step 5: Syntax-check**

Run: `bash -n lib/common/functions.sh`
Expected: no output, exit 0.

- [ ] **Step 6: Add repeatable `--extra-server-name` to `installfog.sh`**

In `bin/installfog.sh:167`, add `extra-server-name:` to `longopts` (alongside `hostname:` from Task 1):
```
...,secure-boot-key:,secure-boot-cert:,no-secure-boot,hostname:,extra-server-name:
```

Add a case branch after the `--hostname)` block from Task 1:
```bash
        --extra-server-name)
            if [[ -n "${2}" ]] && [[ $(validhostname "${2}") -eq 0 ]]; then
                sextraServerNames+=("${2}")
            else
                echo "Error: --extra-server-name requires a valid hostname"
                usage
                exit 9
            fi
            shift 2
            ;;
```

Declare the array before the `getopt`/`while` loop starts (near the top of the file, alongside other pre-loop variable init — e.g. right before the `shortopts=`/`longopts=` lines at 166-167):
```bash
sextraServerNames=()
```

- [ ] **Step 7: Apply the staging array after `.fogsettings` is sourced**

In the same block as Task 1 Step 5 (`bin/installfog.sh:615-638`), add:
```bash
[[ ${#sextraServerNames[@]} -gt 0 ]] && extraServerNames="${sextraServerNames[*]}"
```
(only overwrites the persisted value if the flag was actually given at least once this run — same "override only if given" convention as every other staging var here).

- [ ] **Step 8: Add `--extra-server-name` to `usage()`**

Add directly below the `--hostname` help line from Task 1:
```
	echo -e "\t      --extra-server-name\tAdd an extra vhost/cert name (repeatable)"
	echo -e "\t                       \t\talongside the primary hostname and detected IPs"
```

- [ ] **Step 9: Syntax-check**

Run: `bash -n bin/installfog.sh`
Expected: no output, exit 0.

- [ ] **Step 10: Add repeatable pass-through to `updatefog.sh`**

In `bin/updatefog.sh:61`, add `extra-server-name:` to `longopts`.

Declare the array before the `getopt`/`while` loop (near line 60):
```bash
supdateExtraServerNames=()
```

Add a case branch alongside `--hostname` from Task 1:
```bash
        --extra-server-name)
            if [[ -n "${2}" ]]; then
                supdateExtraServerNames+=("${2}")
            else
                echo "Error: --extra-server-name requires a value"
                usage
            fi
            shift 2
            ;;
```

- [ ] **Step 11: Forward it on the child invocation**

Change `bin/updatefog.sh:222` (already modified by Task 1 Step 9) from:
```bash
(cd "$fog_git_path/bin" && bash installfog.sh -Y $updateVhostFlag ${supdatehostname:+--hostname "$supdatehostname"} >>$error_log 2>&1)
```
to:
```bash
extraServerNameArgs=()
for extraname in "${supdateExtraServerNames[@]}"; do
    extraServerNameArgs+=(--extra-server-name "$extraname")
done
(cd "$fog_git_path/bin" && bash installfog.sh -Y $updateVhostFlag ${supdatehostname:+--hostname "$supdatehostname"} "${extraServerNameArgs[@]}" >>$error_log 2>&1)
```
(built as an array, not a bare string, so a name containing a space is still passed as one argument to the child — same reasoning as quoting everywhere else in this file).

- [ ] **Step 12: Add `--extra-server-name` to `updatefog.sh`'s `usage()`**

Add directly below the `--hostname` help line from Task 1:
```
    echo -e "\t      --extra-server-name\tAdd an extra vhost/cert name for this update (repeatable)"
```

- [ ] **Step 13: Syntax-check**

Run: `bash -n bin/updatefog.sh`
Expected: no output, exit 0.

- [ ] **Step 14: Manually verify end to end (on a test Linux box)**

Run: `./installfog.sh -Y --hostname fog-test.example.com --extra-server-name fog-legacy.internal --extra-server-name fog-alt.internal`
Confirm:
- The vhost's `server_name`/`ServerAlias` line includes `fog-test.example.com`, `fog-legacy.internal`, and `fog-alt.internal`, alongside the auto-detected IPs.
- `openssl x509 -in <sslpubcert> -noout -text | grep -A2 "Subject Alternative Name"` lists `DNS:fog-legacy.internal` and `DNS:fog-alt.internal` alongside `DNS:fog-test.example.com` and the `IP:` entries.
- `grep extraServerNames /opt/fog/.fogsettings` shows `extraServerNames='fog-legacy.internal fog-alt.internal'`.

Run again without either flag: `./installfog.sh -Y`
Expected: both persisted values survive unchanged in `.fogsettings` and the vhost/cert.

- [ ] **Step 15: Commit**

```bash
git add lib/common/functions.sh bin/installfog.sh bin/updatefog.sh
git commit -m "Add repeatable --extra-server-name flag for additive vhost/cert names"
```

---

### Task 3: Mirror `FOG_EXTRA_SERVER_NAMES` into `globalSettings`

**Files:**
- Modify: `lib/common/functions.sh` — extend `recordGitUpdateSettings()` (`lib/common/functions.sh:153-158`).

**Interfaces:**
- Consumes: `$extraServerNames` from Task 2.
- Produces: nothing new consumed elsewhere — this is a leaf, GUI-visibility-only mirror, same as the existing `FOG_GIT_PATH`/`FOG_UPDATE_CHANNEL` rows it sits next to.

- [ ] **Step 1: Extend `recordGitUpdateSettings()`**

Change `lib/common/functions.sh:153-158` from:
```bash
recordGitUpdateSettings() {
    dots "Recording fog_git_path/update channel"
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_GIT_PATH', 'Filesystem path of the FOG git checkout on this server. Recorded automatically by installfog.sh/updatefog.sh -- editing it here has no effect on the next update.', \"$fog_git_path\", 'FOG Update') ON DUPLICATE KEY UPDATE settingValue=\"$fog_git_path\"" $mysqldbname >>$error_log 2>&1
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_UPDATE_CHANNEL', 'Update channel this server tracks: stable, staging, or dev.', \"$fog_update_channel\", 'FOG Update') ON DUPLICATE KEY UPDATE settingValue=\"$fog_update_channel\"" $mysqldbname >>$error_log 2>&1
    errorStat $?
}
```
to (adding a third `INSERT` line and updating the `dots` message and function comment):
```bash
# Mirrors fog_git_path/fog_update_channel/extraServerNames into globalSettings
# so the GUI can show them without SSH. Like fogprogramdir's mirror into
# /etc/fog/fog.conf (GH-850), these are RECORDS, not controls: .fogsettings
# stays the source of truth, and the next installfog.sh/updatefog.sh run
# overwrites whatever an admin may have hand-edited here through the generic
# Settings tab.
recordGitUpdateSettings() {
    dots "Recording fog_git_path/update channel/extra server names"
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_GIT_PATH', 'Filesystem path of the FOG git checkout on this server. Recorded automatically by installfog.sh/updatefog.sh -- editing it here has no effect on the next update.', \"$fog_git_path\", 'FOG Update') ON DUPLICATE KEY UPDATE settingValue=\"$fog_git_path\"" $mysqldbname >>$error_log 2>&1
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_UPDATE_CHANNEL', 'Update channel this server tracks: stable, staging, or dev.', \"$fog_update_channel\", 'FOG Update') ON DUPLICATE KEY UPDATE settingValue=\"$fog_update_channel\"" $mysqldbname >>$error_log 2>&1
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_EXTRA_SERVER_NAMES', 'Extra vhost/certificate name(s) this server answers to, beyond the primary hostname and detected IPs. Set via --extra-server-name -- editing it here has no effect on the next update.', \"$extraServerNames\", 'FOG Update') ON DUPLICATE KEY UPDATE settingValue=\"$extraServerNames\"" $mysqldbname >>$error_log 2>&1
    errorStat $?
}
```

- [ ] **Step 2: Syntax-check**

Run: `bash -n lib/common/functions.sh`
Expected: no output, exit 0.

- [ ] **Step 3: Manually verify (on a test Linux box with a running FOG install)**

Run: `./installfog.sh -Y --extra-server-name fog-legacy.internal`
Then in the FOG web UI, go to Settings, find the **FOG Update** category, and confirm a `FOG_EXTRA_SERVER_NAMES` row shows `fog-legacy.internal`.
Also confirm directly: `mysql fog -e "SELECT settingValue FROM globalSettings WHERE settingKey='FOG_EXTRA_SERVER_NAMES'"` returns `fog-legacy.internal`.

- [ ] **Step 4: Commit**

```bash
git add lib/common/functions.sh
git commit -m "Mirror FOG_EXTRA_SERVER_NAMES into globalSettings for GUI visibility"
```

---

### Task 4: `bin/setupacme.sh` — bootstrap ACME leaf renewal against `--external-ca`

**Files:**
- Create: `bin/setupacme.sh`

**Interfaces:**
- Consumes: the CA files `validateExternalCA()` imports (`lib/common/functions.sh:3256-3258`, `3297-3303`) — specifically `$sslpath/CA/.fogCA.pem` and `$sslpath/CA/.fogCA.key` — and `$sslpubcert`/`$sslprivkey`'s on-disk locations (same `.fogsettings` keys `createSSLCA()` already persists: `sslpath`, `sslpubcert`, `sslprivkey`), and `$webserver` (already a managed key, `lib/common/functions.sh:3115`) to pick the right reload command.
- Produces: nothing consumed by other tasks — this is a leaf script, run directly by an admin, same as `updatefog.sh`.

- [ ] **Step 1: Write `bin/setupacme.sh`**

```bash
#!/bin/bash
#
#  FOG is a computer imaging solution.
#  Copyright (C) 2007  Chuck Syperski & Jian Zhang
#
#   This program is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#    any later version.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# Bootstraps acme.sh to issue and renew the web vhost's LEAF certificate
# against a CA already imported via installfog.sh --external-ca. Never
# touches the imported intermediate/root -- only the leaf -- so fog-client's
# pinned CA never changes across a renewal. acme.sh's own installer sets up
# its own renewal cron job; this script does not add a second one. See
# docs/superpowers/specs/2026-08-07-cert-separation-letsencrypt-design.md and
# FOGProject/fogproject#1013.
bindir=$(dirname $(readlink -f "$BASH_SOURCE"))
cd $bindir
workingdir=$(pwd)

if [[ ! $EUID -eq 0 ]]; then
    echo "setupacme.sh must be run as root user"
    exit 1
fi

usage() {
    echo -e "Usage: $0 [-h?] --directory-url <url> (--http01 | --dns <acme.sh-plugin>) -d <domain>"
    echo -e "\t-h -? --help\t\tDisplay this info"
    echo -e "\t      --directory-url\tACME server directory URL (public Let's Encrypt or"
    echo -e "\t                     \tan internal ACME CA such as step-ca)"
    echo -e "\t      --http01\t\tUse HTTP-01 validation (acme.sh's --webroot mode against"
    echo -e "\t               \t\tthis server's own vhost docroot)"
    echo -e "\t      --dns\t\tUse DNS-01 validation via the named acme.sh DNS plugin --"
    echo -e "\t           \t\tthe plugin's own provider credentials must already be set"
    echo -e "\t           \t\tup in this shell's environment; setupacme.sh never stores them"
    echo -e "\t-d\t\t\tDomain to issue the certificate for (repeatable)"
    exit 0
}

shortopts="h?d:"
longopts="help,directory-url:,http01,dns:"
optargs=$(getopt -o $shortopts -l $longopts -n "$0" -- "$@")
[[ $? -ne 0 ]] && usage
eval set -- "$optargs"

domains=()
while :; do
    case $1 in
        -h | -\? | --help)
            usage
            ;;
        --directory-url)
            directoryUrl="$2"
            shift 2
            ;;
        --http01)
            validationMethod="http01"
            shift
            ;;
        --dns)
            validationMethod="dns"
            dnsPlugin="$2"
            shift 2
            ;;
        -d)
            domains+=("$2")
            shift 2
            ;;
        --)
            shift
            break
            ;;
        *)
            echo "Error: unhandled option '$1'."
            exit 10
            ;;
    esac
done

[[ ! -d ./error_logs/ ]] && mkdir -p ./error_logs >/dev/null 2>&1
error_log="${workingdir}/error_logs/fog_setupacme_error.log"
: > "$error_log"

if [[ -z $directoryUrl ]]; then
    echo " * --directory-url is required (a public Let's Encrypt endpoint, or an internal ACME CA such as step-ca)."
    usage
fi
if [[ -z $validationMethod ]]; then
    echo " * Pass either --http01 or --dns <plugin>."
    usage
fi
if [[ ${#domains[@]} -eq 0 ]]; then
    echo " * At least one -d <domain> is required."
    usage
fi

exitFail=1
. ../lib/common/functions.sh

[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
fogprogramdir="${fogprogramdir%/}"

if [[ ! -r "$fogprogramdir/.fogsettings" ]]; then
    echo " * No existing FOG install found at $fogprogramdir (.fogsettings missing)."
    echo " * setupacme.sh configures an EXISTING install -- run installfog.sh first."
    exit 1
fi
. "$fogprogramdir/.fogsettings"

# Precondition: --external-ca must already have imported a CA. These are
# exactly the files validateExternalCA() (lib/common/functions.sh) writes.
if [[ ! -e "$sslpath/CA/.fogCA.pem" || ! -e "$sslpath/CA/.fogCA.key" ]]; then
    echo " * No external CA found at $sslpath/CA/ -- run installfog.sh --external-ca first."
    echo " * setupacme.sh only ever renews a LEAF against a CA you already imported;"
    echo " * it does not create or manage a CA itself."
    exit 1
fi

dots "Checking for acme.sh"
if [[ ! -x "$HOME/.acme.sh/acme.sh" ]]; then
    dots "Installing acme.sh"
    curl -s https://get.acme.sh | sh -s email=root@localhost >>$error_log 2>&1
    errorStat $?
else
    echo "Found"
fi
acmesh="$HOME/.acme.sh/acme.sh"

case $webserver in
    nginx)
        reloadcmd="systemctl reload nginx"
        ;;
    httpd|apache*)
        reloadcmd="systemctl reload $webserver"
        ;;
    *)
        echo " * Unrecognized \$webserver ($webserver) -- cannot pick a reload command."
        exit 1
        ;;
esac

domainArgs=()
for domain in "${domains[@]}"; do
    domainArgs+=(-d "$domain")
done

dots "Issuing certificate via acme.sh"
case $validationMethod in
    http01)
        "$acmesh" --issue --server "$directoryUrl" "${domainArgs[@]}" --webroot "$docroot" >>$error_log 2>&1
        ;;
    dns)
        "$acmesh" --issue --server "$directoryUrl" "${domainArgs[@]}" --dns "$dnsPlugin" >>$error_log 2>&1
        ;;
esac
issueStatus=$?
# acme.sh's own exit code 2 means "already valid, no renewal needed yet" --
# not a failure of this run.
if [[ $issueStatus -ne 0 && $issueStatus -ne 2 ]]; then
    echo " * acme.sh --issue failed (exit $issueStatus). See $error_log."
    exit $issueStatus
fi
echo "Done"

dots "Installing certificate"
"$acmesh" --install-cert "${domainArgs[@]}" \
    --cert-file "$sslpubcert" \
    --key-file "$sslprivkey" \
    --reloadcmd "$reloadcmd" >>$error_log 2>&1
errorStat $?

echo " * setupacme.sh complete. acme.sh's own installer already scheduled its"
echo "   own renewal cron job -- no further action is needed for renewals."
```

- [ ] **Step 2: Make it executable and syntax-check**

Run: `chmod +x bin/setupacme.sh && bash -n bin/setupacme.sh`
Expected: no output, exit 0.

- [ ] **Step 3: Verify `usage()` and argument validation without root/network (safe on any machine)**

Run: `bash bin/setupacme.sh --help`
Expected: usage text printed, exit 0.

Run: `bash bin/setupacme.sh -d example.com` (as non-root, or on any machine)
Expected: `setupacme.sh must be run as root user` printed if not root, exit 1. If run as root without `--directory-url`/validation method, expected: `--directory-url is required...` then usage, matching the order the checks appear in the script.

- [ ] **Step 4: Manually verify end to end (on a test Linux box with FOG installed and `--external-ca` already configured against a local step-ca instance)**

Stand up a local `step-ca` (per `docs/EXTERNAL_CA_AND_LETSENCRYPT.md`'s own recommended setup), install FOG with `--external-ca` pointed at it, then run:
```bash
./setupacme.sh --directory-url https://step-ca.internal/acme/acme/directory --http01 -d fog-test.example.com
```
Confirm:
- `acme.sh` is installed at `$HOME/.acme.sh/acme.sh` if it wasn't already.
- The vhost's cert file (`$sslpubcert`) is replaced with the newly-issued leaf: `openssl x509 -in "$sslpubcert" -noout -issuer` shows step-ca's intermediate, not FOG's own self-signed CA.
- The web server actually reloaded (check its access/error log timestamp, or `systemctl status <webserver>` shows a recent reload).
- `fog-client` on a test machine that already registered against this server still authenticates successfully (the pinned intermediate didn't change).
- `crontab -l` (root's) now has an `acme.sh --cron` entry, confirming `acme.sh`'s own installer set up its own renewal scheduling.

- [ ] **Step 5: Commit**

```bash
git add bin/setupacme.sh
git commit -m "Add bin/setupacme.sh for ACME leaf renewal against --external-ca"
```

---

### Task 5: Document the new flags/script

**Files:**
- Modify: `docs/EXTERNAL_CA_AND_LETSENCRYPT.md` — add a section documenting `setupacme.sh` as the automation for the "Recommended: internal ACME CA (step-ca)" flow's step 3/4 (`docs/EXTERNAL_CA_AND_LETSENCRYPT.md`, section `## Recommended: internal ACME CA (step-ca)`).

**Interfaces:**
- Consumes: nothing — documentation only, no code interface.

- [ ] **Step 1: Add a "Automating renewal with setupacme.sh" subsection**

In `docs/EXTERNAL_CA_AND_LETSENCRYPT.md`, immediately after the "Recommended: internal ACME CA (step-ca)" section's existing numbered steps (ending "...(a renewal hook — see [Renewal and rotation](#renewal-and-rotation))."), add:

```markdown
`bin/setupacme.sh` automates steps 3 and 4 above: it installs `acme.sh` if
needed, issues the leaf against the ACME directory URL you give it, installs
it where the vhost reads it, and wires up `acme.sh`'s `--reloadcmd` to reload
FOG's web server. It never touches the CA `--external-ca` already imported --
only the leaf -- so a renewal never breaks fog-client's pinning.

```bash
./setupacme.sh --directory-url https://step-ca.internal/acme/acme/directory \
    --http01 -d fog.example.com
```

Use `--dns <acme.sh-plugin-name>` instead of `--http01` for DNS-01 validation
(needed for public Let's Encrypt without exposing this server on port 80) --
`setupacme.sh` never stores DNS provider credentials itself; whatever
`acme.sh` DNS plugin you name must already have its own credentials configured
in this shell's environment.

`acme.sh`'s own installer sets up its own daily renewal cron job the first
time it's installed -- `setupacme.sh` does not add a second one.
```

- [ ] **Step 2: Commit**

```bash
git add docs/EXTERNAL_CA_AND_LETSENCRYPT.md
git commit -m "Document bin/setupacme.sh in EXTERNAL_CA_AND_LETSENCRYPT.md"
```
