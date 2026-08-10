# Context: cert identity (CN/O/OU/SAN defaults), install-time name prompt, efitools build-from-source

Continuation notes for porting three related changes into `devbranch-pki-additive`
in a new session. Companion to `docs/superpowers/CONTEXT-1013-pki-and-customizations.md`
in this same worktree, which explains what this branch already has and what it
still lacks relative to the 1.6 line.

## Where the reference implementation lives

Both changes below were implemented and verified against **`pki-additive-intermediates`**
(worktree `C:\Users\jfullmer\git\fog-git-path-update-script`) and ported from there to
**`working-1.6`** (worktree `C:\Users\jfullmer\git\working-1.6`). As of this writing
**neither is committed yet** -- the diffs are sitting in each worktree's working tree
(`git status` shows `lib/common/functions.sh` modified in both, plus
`packages/pki/fog-sign-node-cert` in the additive worktree). Check whether they've
been committed by the time you read this; if not, `git diff` in those two worktrees
is the authoritative reference, and the snippets below are copied from that diff so
this doc is self-contained either way.

**Do not blind cherry-pick.** `pki-additive-intermediates` has capabilities dev-branch
does not (`--external-ca`, the managed vhost block, `_pkiZoneDir`/canonical symlinks),
and `createSSLCA` here hardcodes `/opt/fog/snapins/ssl/` where the additive branch
parameterizes it. CONTEXT-1013 already flagged this as "a port rather than a
cherry-pick" for the PKI hierarchy itself; the same caution applies to these
follow-on changes. Verified good news: **the two functions this actually touches
already exist here with the same names and the same shape** (checked directly
against this worktree's `lib/common/functions.sh` while writing this doc):

| Thing | Line (approx, will drift) | State here |
|---|---|---|
| `_nameConstraints()` | 2981 | **Already exists.** Reads `$extraServerNames $internalDomains` at line 2997. |
| `_resolveRootCA()` | 3116 | Exists, not yet split into path-only + create (see Part A). |
| `_issueIntermediateCA()` | 3185 | Exists, 5-arg signature (no OU param yet). |
| `createWebIntermediateCA()` | 3243 | Exists, calls `_issueIntermediateCA "FOG Web CA" ...`. |
| `_createWebLeaf()` | 3383 | Exists. CN is still `$certip` via heredoc (see Part A). |
| `createSSLCA()` SAN block | ~3556-3576 | **Only `DNS.1 = $hostname`, no per-name loop at all.** `extraServerNames` is read by `_nameConstraints()` but never reaches the leaf's own SAN today -- this is the exact SAN/constraint-drift bug class CONTEXT-1013 warns about, already latent here. |
| `createSecureBootIntermediateCA()` | 4389 | Exists, called from `downloadfiles()` (4519) **before** `createSSLCA()` runs -- same ordering hazard as the other two branches. |
| SB signing leaf / flat MOK CN | 4446, 4568 | Both `CN = FOG Project Secure Boot Signing`, matches the other branches exactly. |
| `_publishSecureBootAuthVars` / PK / KEK / `cert-to-efi-sig-list` | -- | **Does not exist here at all.** Confirmed by grep: zero matches. This is the "not the db/KEK enrolment task (1.6 only)" gap CONTEXT-1013 already flagged. |

That last row matters: **Part C (efitools) has nothing to attach to until the PK/KEK/db
auto-enrollment feature itself is ported here.** Don't wire `_ensureEfitools()` into a
`_publishSecureBootAuthVars` that doesn't exist yet -- see Part C's note.

---

## Part A: cert identity -- CN, default SAN names, O/OU

### A1. Shared name helper, feeding both SAN and the existing `_nameConstraints()`

Insert just above `_nameConstraints()` (~line 2980), matching the version already
verified (with a standalone test harness) on `pki-additive-intermediates`:

```bash
# The names every web leaf carries, and the floor every Web/SB intermediate's
# nameConstraints must permit. Single source of truth: SAN generation
# (createSSLCA) and permitted-set generation (_nameConstraints, below) must
# never derive $hostname/$extraServerNames separately -- a name added to only
# one of them issues a leaf whose SAN carries a name its own CA doesn't
# permit, which signs cleanly and then fails every openssl verify, silently.
#
# fogserver/fog-server are DEFAULT names, not detected ones: they are the
# fog-client installer's default value for "FOG Server Address", so any
# admin who points that literal name at this box needs a leaf that covers
# it, whether or not they ever pass --extra-server-name.
#
# A bare (undotted) name paired with every configured --internal-domain gets
# an automatic FQDN form too, so an admin who types "fogdev" plus
# --internal-domain domain.com gets fogdev.domain.com without listing it
# twice. Already-dotted names (a detected FQDN hostname, or an admin-supplied
# FQDN) are left alone -- they're not paired again.
_defaultServerNames() {
    local -a names=() bases=()
    local seen="" n short dom fqdn

    for n in $hostname fogserver fog-server $extraServerNames; do
        [[ -z $n ]] && continue
        [[ " $seen " == *" $n "* ]] && continue
        seen="$seen $n"
        names+=("$n")
        bases+=("$n")
    done
    short="${hostname%%.*}"
    if [[ -n $short && " $seen " != *" $short "* ]]; then
        seen="$seen $short"
        names+=("$short")
        bases+=("$short")
    fi

    for n in "${bases[@]}"; do
        [[ $n == *.* ]] && continue
        for dom in $internalDomains; do
            [[ -z $dom ]] && continue
            fqdn="${n}.${dom}"
            [[ " $seen " == *" $fqdn "* ]] && continue
            seen="$seen $fqdn"
            names+=("$fqdn")
        done
    done
    printf '%s\n' "${names[@]}"
}
```

Then change `_nameConstraints()`'s existing `for n in $hostname` loop to read from
this helper instead, and drop `$extraServerNames` from its second loop (it's folded
into the helper now; `$internalDomains` stays in the second loop -- that one grants
the bare domain itself, which is never a SAN entry on this leaf):

```bash
for n in $(_defaultServerNames); do
    dnsnames+=("$n")
    d="${n#*.}"
    [[ $d != "$n" && -n $d ]] && dnsnames+=("$d")
done
for n in $internalDomains; do
    [[ -z $n ]] && continue
    dnsnames+=("$n")
done
```

**Verify with a standalone harness before wiring it in** -- source `functions.sh`
with fake `hostname`/`extraServerNames`/`internalDomains`/`internalSubnets`/`ipaddresses`
values and call `_defaultServerNames` / `_nameConstraints` directly. This is exactly
how the bug this fixes was caught the first time (CONTEXT-1013's "Lessons that cost
real bugs": test the caller's ordering, not just the function). Expected shape,
confirmed on `pki-additive-intermediates`, given `hostname=fog-dev.arrowheaddental.com`,
`extraServerNames=fogdev`, `internalDomains=domain.com`:

```
fog-dev.arrowheaddental.com
fogserver
fog-server
fogdev
fog-dev
fogserver.domain.com
fog-server.domain.com
fogdev.domain.com
fog-dev.domain.com
```

### A2. Wire the SAN loop into `createSSLCA()` (this branch has none today)

Around the `ca.cnf`/`req.cnf` heredocs (~3556-3576), add the loop *before* them and
append `$dnsSanEntries` to `DNS.1`:

```bash
dnscount=1
dnsSanEntries=""
while IFS= read -r extraname; do
    [[ -z $extraname || $extraname == "$hostname" ]] && continue
    dnscount=$((dnscount + 1))
    dnsSanEntries="${dnsSanEntries}"$'\n'"DNS.${dnscount} = ${extraname}"
done < <(_defaultServerNames)
```

Then `DNS.1 = $hostname` becomes `DNS.1 = $hostname$dnsSanEntries` in **both**
`ca.cnf` and `req.cnf` heredocs.

### A3. Web leaf CN: `$certip` (IP) -> `$hostname`, plus O/OU

In `_createWebLeaf()` (~3383), the CSR is currently built with a heredoc feeding
`$certip` through `req.cnf`'s shared `[req_distinguished_name]`. Swap to `-subj` on
just this command, so the comm leaf's CSR (which still needs `CN` to be an IP --
`createSSLCA` hard-exits on `validip $certip` failing) is untouched:

```bash
openssl req -new -sha512 -key "$sslprivkey" -out "${webdir}/.webLeaf.csr" \
    -config "$sslpath/req.cnf" \
    -subj "/CN=${hostname}/O=FOG Project/OU=FOG Web UI" >>$error_log 2>&1 || st=1
```

(drop the `<< EOF $certip EOF` heredoc it replaces).

### A4. O/OU on every other cert/CA in this file

Add `O = FOG Project` plus a role `OU` to each. **Do not change any existing CN** --
verified safe (RFC 6125's CN-fallback matching reads the CN attribute only) but
worth re-confirming for the two Secure Boot ones per the note already in this
file's own comments (~4446/4568: that CN is deliberately not hostname-shaped, or a
DNS-name-constraint fallback could evaluate it as a hostname).

| Where | CN (unchanged) | Add |
|---|---|---|
| Root CA cnf, `[ req_dn ]` (near line 3158) | `FOG Server CA` | `O = FOG Project` / `OU = FOG Root CA` |
| `_issueIntermediateCA()` (3185) -- add a 6th `ou` param, used in its own `int.cnf`'s `[ req_dn ]` | `${cn}` | `O = FOG Project` / `OU = ${ou}` |
| `createWebIntermediateCA()`'s call (3254) | -- | pass `"FOG Web UI"` as the new 6th arg |
| `createSecureBootIntermediateCA()`'s call (4413) | -- | pass `"FOG Secure Boot"` as the new 6th arg |
| comm leaf's `req.cnf`, `[req_distinguished_name]` (3570-3571) | `$certip` | `O = FOG Project` / `OU = FOG Client Communication` |
| SB signing leaf cnf, `[ req_dn ]` (~4446) | `FOG Project Secure Boot Signing` | `O = FOG Project` / `OU = FOG Secure Boot` |
| Flat MOK cnf, `[ req_dn ]` (~4568) | `FOG Project Secure Boot Signing` | `O = FOG Project` / `OU = FOG Secure Boot` |
| PK/KEK `-subj` (if/when ported -- see Part C note; not present here yet) | `${subject} Platform Key` / `${subject} Key Exchange Key` | append `/O=FOG Project/OU=FOG Secure Boot` |

### A5. Ask once, before minting -- `_collectPkiNames()`

Because `createSecureBootIntermediateCA()` runs from `downloadfiles()` **before**
`createSSLCA()` here too (confirmed: line 4519 call site vs. wherever `createSSLCA`
is invoked later), this has to be reachable from both entry points, guarded to only
actually ask once. It also can't call `_resolveRootCA()` to "just check" -- that
function *creates* the root the moment it finds one missing. Split it the same way
as the other two branches:

```bash
# Path resolution only -- no creation. Split out of _resolveRootCA() so
# something (_collectPkiNames) can ask "does a root exist yet" without
# triggering the mint that _resolveRootCA() does the moment it finds one
# missing.
_resolveRootCAPath() {
    _resolveSslPath
    [[ ! -d $sslpath/CA ]] && mkdir -p "$sslpath/CA" >>$error_log 2>&1
    if [[ -z $rootCAPem ]]; then
        if [[ -f $sslpath/CA/.fogCA.pem ]]; then
            rootCAPem="$sslpath/CA/.fogCA.pem"
            rootCAKey="$sslpath/CA/.fogCA.key"
        elif [[ $caCreated == yes && -n $sslcapem && -f $sslcapem ]]; then
            rootCAPem="$sslcapem"
            rootCAKey="$sslcakey"
        else
            rootCAPem="$sslpath/CA/.fogCA.pem"
            rootCAKey="$sslpath/CA/.fogCA.key"
        fi
    fi
    [[ -z $rootCAKey ]] && rootCAKey="${rootCAPem%.pem}.key"
}
_resolveRootCA() {
    _resolveRootCAPath
    # ... unchanged from here down (the recreateCA / "if -f $rootCAPem return 0" /
    # "Creating FOG Server CA" body stays exactly as it is today)
}
```

```bash
# Asked once, whichever entry point reaches it first -- Secure Boot's or the
# web zone's -- because a CA's name constraints are fixed at the moment it is
# minted and widening them later means the admin has to notice and ask for it
# explicitly (rm -rf the CA directory, re-run). Skipped entirely once every
# CA this run could mint already exists, and skipped if the admin already
# answered on the command line: --extra-server-name/--internal-domain ARE
# the answer to this question, just given up front instead of interactively.
#
# Bounded to 3 minutes under -Y/--autoaccept: that flag exists for unattended
# runs, and a prompt nobody is there to answer must not hang the install
# forever. A normal interactive run waits like every other prompt in this
# installer.
_collectPkiNames() {
    [[ -n $_pkiNamesCollected ]] && return 0
    _pkiNamesCollected=1
    _resolveRootCAPath
    local needRoot=0 needWeb=0 needSB=0
    [[ ! -f $rootCAPem ]] && needRoot=1
    [[ ! -f "$(_pkiZoneDir web)/.fogWebCA.pem" ]] && needWeb=1
    [[ ${secureboot:-1} != 0 && ! -f "${fogprogramdir}/secureboot/ca/.fogSBCA.pem" ]] && needSB=1
    [[ $needRoot -eq 0 && $needWeb -eq 0 && $needSB -eq 0 ]] && return 0
    [[ -n $extraServerNames || -n $internalDomains ]] && return 0

    echo
    echo "  This run will mint a new FOG PKI CA. A CA's name constraints are"
    echo "  fixed at the moment it's issued -- widening them later means"
    echo "  re-issuing it (rm -rf the CA directory, then re-run)."
    local ans domainAns
    if [[ -n $autoaccept ]]; then
        echo "  Extra hostnames for this server, space-separated (3 min, blank = none):"
        read -t 180 -r -p "  > " ans
        echo "  Internal domain, e.g. example.local (3 min, blank = none):"
        read -t 180 -r -p "  > " domainAns
    else
        read -r -p "  Extra hostnames for this server, space-separated (blank = none): " ans
        read -r -p "  Internal domain, e.g. example.local (blank = none): " domainAns
    fi
    [[ -n $ans ]] && extraServerNames="${extraServerNames} ${ans}"
    [[ -n $domainAns ]] && internalDomains="${internalDomains} ${domainAns}"
    extraServerNames="${extraServerNames# }"
    internalDomains="${internalDomains# }"
}
```

Call `_collectPkiNames` from the top of `createSecureBootIntermediateCA()` (right
after its own `_resolveSslPath` call, before `_resolveRootCA`) and from the top of
`createSSLCA()` (before its own `_resolveRootCA` call).

**Verify `bin/installfog.sh` on this branch actually has `--extra-server-name` and
`--internal-domain` flags before assuming `$extraServerNames`/`$internalDomains`
exist as variables.** They're already read by `_nameConstraints()` here (line 2997),
so they almost certainly exist -- but confirm the flag-parsing side
(`sextraServerNames`/`sinternalDomains` staging vars, the `longopts=` entry, the
"evaluation of command line options" section) is complete, the way it was double
-checked while writing this doc, rather than assumed from the read-side reference
alone.

### Verification checklist for Part A (repeat the checks already run on the other two branches)

1. `bash -n lib/common/functions.sh`.
2. Standalone harness: source `functions.sh`, set fake vars, call `_defaultServerNames`
   and `_nameConstraints`, confirm the fogdev/fog-dev/fogserver/fog-server +
   `.domain.com` pairing shape shown above.
3. Fresh install: `openssl x509 -in .webLeaf.pem -noout -subject -ext subjectAltName`
   shows `CN = <hostname>, O = FOG Project, OU = FOG Web UI` and the full SAN set.
4. `openssl verify -CAfile .fogCA.pem -untrusted .fogWebCA.pem .webLeaf.pem` passes.
5. SB signing leaf / flat MOK: CN unchanged, O/OU present; re-run whatever
   hostname-shaped-CN-rejection test this branch already has (if none exists here
   yet, this is worth adding -- CONTEXT-1013 flags it as the release gate and it
   is NOT verified against `nameConstraints` on real hardware yet).
6. Second run, no flag changes: leaf not re-signed, prompt does not re-appear.

---

## Part B: (nothing to port -- included above)

(Numbering kept aligned with the sibling doc set; there is no separate Part B here.)

## Part C: efitools has no package on RHEL/Rocky/Alma/CentOS Stream 9

**Do this part only once `_publishSecureBootAuthVars` / PK / KEK exist on this
branch.** Confirmed by grep: this branch has none of that yet (see the table at the
top). If you're porting the PK/KEK/db auto-enrollment feature in the same session,
port this alongside it; if not, skip this part for now and just note it as follow-up.

### What was found (verified, not guessed)

- **`gnu-efi-utils` is NOT a substitute for `efitools`.** It's the *gnu-efi* project's
  own debugging/dev utilities (confirmed via its Fedora package description) and
  contains none of `cert-to-efi-sig-list`/`sign-efi-sig-list`/`sbvarsign`.
- **`efitools` is absent from EPEL 9** (checked the EPEL9 `Everything/x86_64/Packages/e/`
  listing directly -- not there). It exists only in AlmaLinux/Rocky's `devel` repos,
  which are build infrastructure, not something to enable on a production server.
  The existing comment in `installPackages()` claiming it's "named efitools on every
  distro this installer supports" is wrong for EL9.
- `fog-build-sb-authvars` only ever calls **`cert-to-efi-sig-list`** and
  **`sign-efi-sig-list`** -- not `sbvarsign` (that one's part of `sbsigntools`,
  already a baseline dependency here for kernel signing).
- Canonical upstream is `git.kernel.org/pub/scm/linux/kernel/git/jejb/efitools.git`
  (not a GitHub mirror) -- the same source Fedora/AlmaLinux build from. Current
  release: **1.9.2**.
- The Makefile has real per-binary targets (`cert-to-efi-sig-list: cert-to-efi-sig-list.o lib/lib.a`),
  so `make cert-to-efi-sig-list sign-efi-sig-list` builds only what's needed and
  skips the default `all` target entirely, which additionally wants sample PK/KEK/db
  certs this install has no reason to generate.
- Build deps beyond what's already baseline (C toolchain, `sbsigntools`):
  `gnu-efi-devel`, `help2man`.

### The fix, once there's a `_publishSecureBootAuthVars` to attach it to

Add this function (verbatim from `pki-additive-intermediates`/`working-1.6`) right
before `_publishSecureBootAuthVars`, and call it as the first line inside that
function, before its own `command -v cert-to-efi-sig-list` check:

```bash
_ensureEfitools() {
    command -v cert-to-efi-sig-list >/dev/null 2>&1 && \
        command -v sign-efi-sig-list >/dev/null 2>&1 && return 0
    command -v curl >/dev/null 2>&1 || return 1

    local ver="1.9.2"
    local url="https://git.kernel.org/pub/scm/linux/kernel/git/jejb/efitools.git/snapshot/efitools-${ver}.tar.gz"
    local work
    work=$(mktemp -d) || return 1

    dots "Building efitools (no package for this distro)"
    $packageinstaller gcc make gnu-efi-devel help2man >>$error_log 2>&1
    if ! curl -fsSL "$url" -o "${work}/efitools.tar.gz" >>$error_log 2>&1; then
        echo "Failed"
        echo " * Could not download efitools ${ver} from ${url}."
        rm -rf "$work" >>$error_log 2>&1
        return 1
    fi
    tar -xzf "${work}/efitools.tar.gz" -C "$work" >>$error_log 2>&1
    (cd "${work}/efitools-${ver}" && make cert-to-efi-sig-list sign-efi-sig-list) >>$error_log 2>&1
    if [[ ! -x "${work}/efitools-${ver}/cert-to-efi-sig-list" || \
          ! -x "${work}/efitools-${ver}/sign-efi-sig-list" ]]; then
        echo "Failed"
        echo " * efitools ${ver} did not build; see ${error_log}."
        rm -rf "$work" >>$error_log 2>&1
        return 1
    fi
    install -o root -g root -m 0755 \
        "${work}/efitools-${ver}/cert-to-efi-sig-list" \
        "${work}/efitools-${ver}/sign-efi-sig-list" \
        /usr/local/bin/ >>$error_log 2>&1
    rm -rf "$work" >>$error_log 2>&1
    echo "Done"
}
```

Update the existing (or, if porting PK/KEK fresh, the newly-ported)
`_publishSecureBootAuthVars` warning message to say "could not be built from
source" rather than just "is not installed", pointing at `$error_log`.

Also fix `installPackages()`'s `packages="$packages efitools"` comment the same way
as the other two branches: it should say this is absent on EL9-family distros
specifically (confirmed against EPEL9), not "every distro this installer supports."

### Verification for Part C

1. `bash -n lib/common/functions.sh`.
2. On a distro where `efitools` genuinely doesn't resolve (or by temporarily
   renaming `/usr/bin/cert-to-efi-sig-list` etc. aside on one that does), run an
   install with PK/KEK configured and confirm `_ensureEfitools` builds the two
   binaries into `/usr/local/bin` and `_publishSecureBootAuthVars` proceeds instead
   of skipping.
3. Confirm a distro that already has native `efitools` is unaffected (the
   `command -v` short-circuit at the top of `_ensureEfitools` returns immediately).
