# Secure Boot: signed iPXE for local ESP boot

## Problem

Some machines cannot netboot. Two cases, both long-standing and neither new:

1. **Firmware with no PXE boot option at all.** These have been imaged for years
   by placing an iPXE `.efi` on the ESP and pointing the boot manager at it.
2. **Task deploys that need a boot-order change.** The commoner problem in the
   wild: a task is queued, but the machine has to be persuaded to netboot on the
   next reboot. An iPXE binary already on the ESP, already first in the boot
   order, removes the step entirely.

The new piece is Secure Boot. With it enabled, the ESP chain has to start at a
Microsoft-signed shim, and every stage after it has to carry a signature the
shim will accept. FOG's own iPXE binaries are unsigned, so the chain has
nowhere to land:

```
bcdedit {bootmgr}
  -> EFI\snponly-shimx64.efi    upstream signed shim          OK
  -> EFI\ipxe.efi               upstream signed snponly       OK
  -> EFI\autoexec.ipxe          plain text, read off the ESP  OK
  -> EFI\fogipxe.efi          FOG's own build               UNSIGNED -> stops here
```

**Neither of upstream's signed binaries can finish the job.** This is the part
worth reading twice, because the obvious objection — "upstream already signs an
all-drivers build, just use that" — looks correct and is not.

`snponly.efi` binds the firmware's UEFI SNP protocol, and this class of hardware
frequently does not provide one, which is the same reason its firmware has no PXE
option in the first place.

`ipxe.efi` — the second chain `secureboot/stage.sh` stages, reached through
`ipxe-shimx64.efi` — *is* built with iPXE's own NIC drivers, and the natural
reading is that it therefore covers the SNP-less case. **Tested, and it does
not: booted locally off an ESP, it does not load its drivers.** So both upstream
paths dead-end on exactly the hardware this feature exists for, and the chain has
to reach FOG's own build.

That result is not derivable from the source tree or from either project's
documentation — it took hardware to find, so it is recorded here rather than
left to be rediscovered. It also means the two `ipxe-shim*` entries upstream
ships are only ever a first-and-second stage that chainloads onward, never the
binary that actually drives the NIC.

Once shim has run, its security policy override stays installed for the rest of
the boot, so a MOK-signed binary loads. FOG already publishes and enrolls a MOK
(`_publishSecureBootKit`, `service/secureboot/MOK.der`). Nothing is missing but
a signature on the binaries FOG already ships.

## Non-goals

**This must never be on the netboot path.** The enrollment workflow depends on an
un-enrolled machine reaching the FOG menu: upstream signed shim, upstream signed
snponly, `autoexec.ipxe`, `default.ipxe`, then *Enroll Secure Boot Key* →
MokManager. A MOK-signed binary anywhere in that chain cannot load, because the
MOK is not enrolled yet. Putting one there trades a working bootstrap for a
chicken-and-egg problem.

The artifact specified here is for a machine that is **already enrolled**, or one
being set up by an admin who has enrolled it by other means.

Also out of scope:

- Changing `next-server` handling or hardcoding a server address. Multiple FOG
  servers is a real deployment, proxy DHCP already covers the failsafe, and
  FOG's existing embedded script handles both.
- Reducing the variant matrix. The compatibility variants (`snp`/`snponly`/
  `ipxe`/`intel`/`realtek`, `10secdelay/`, `i386-efi/`, `arm64-efi/`,
  `autoexec/`) exist because different hardware needs different ones.
- Any change to fog-ipxe. This is a fogproject-only change, so it needs no
  `$ipxeVer` coordination.

## Decision

**Sign FOG's own iPXE binaries in place under `$tftpdirdst`, on the same trigger
`_resignRefind()` already uses, and publish copies of a curated subset into a
non-browsable `service/localboot/` directory.**

Signing is gated on Secure Boot keys; publishing is not. Local ESP boot is an
older, plainer feature that Secure Boot only added a signature requirement to, so
a server with no keys publishes the same directory unsigned and it works on every
machine booting with Secure Boot off.

No new install flag. No forced build.

### Why no build

The release asset is not a vanilla iPXE build. `fog-ipxe`'s
`.github/workflows/build.yml:43` runs `./buildipxe.sh` with no arguments and
line 76 tars that script's own `output/` directory verbatim, so
`fog-ipxe-${ipxeVer}.tar.gz` already contains:

- FOG's embedded boot scripts — `src/ipxescript`, `src-efi/ipxescript`, and the
  `ipxescript10sec` variants
- FOG's config overlays — `general.h`, `settings.h`, `console.h`,
  `config/local/usb.h`
- The full variant matrix, including the EMBED-less `autoexec/` set

The only difference from a local build is `CERT=`/`TRUST=`, exactly as
`buildipxe.sh`'s own header states: *"The only per-site input to an iPXE build is
the CA certificate… Everything else is identical for every FOG server, which is
why these binaries are published as release assets."*

So signing the staged binaries yields a fully FOG-flavoured local boot file. The
one thing signing cannot add is a private CA — and a private-CA HTTPS install
already compiles locally today (`functions.sh:1722-1735`), so those binaries get
signed too, with their CA already baked in. Per the Let's Encrypt path, a site
with a publicly-trusted cert and an FQDN needs no embedded CA at all.

Forcing a build would cost, on every install that ran it:

- **Two clones and eight `make` invocations** — BIOS tree (`EMBED=ipxescript`,
  `EMBED=ipxescript10sec`) and a separate EFI tree (i386+x86_64 and arm64, each
  ×3 for embed / 10sec / EMBED-less).
- **No warm path.** The re-run branch does `git clean -fd; git reset --hard`
  (`buildipxe.sh:61-63`, `118-120`). `bin*/` is untracked, so every run is a cold
  compile; only the clone is cached.
- **An aarch64 cross-compiler on every install**, plus binutils/mtools/xz/perl
  and iso tooling. `CROSS_COMPILE=aarch64-linux-gnu-` is unconditional and the
  script exits 82/93/97 when it fails.

Estimated 10-25 minutes cold on a 4-8 core server — but the dependency footprint
is the real objection, not the minutes, and it would undo the release-asset work
that made the default install fast.

## Design

### 1. `_signLocalIpxe()`

New function in `lib/common/functions.sh`, modeled directly on
`_resignRefind()` (7915) — same shape, same guards, same failure messaging.

```
_signLocalIpxe()
  return 0 unless $secureBootKey && $secureBootCert
  return 0 unless -d $tftpdirdst
  return 0 unless sbsign && sbverify present     # _resignKernels already warned
  certpem   = _secureBootCertPem()               # signs with the leaf
  anchorpem = _secureBootAnchorPem()             # verifies against the anchor
  addcert   = --addcert $secureBootMokCert       # split PKI only
  for each *.efi under $tftpdirdst, EXCLUDING $tftpdirdst/secureboot/:
      sbverify --cert $anchorpem  -> already ours, skip
      sbsign --output $f.signing $f
      chown/chmod --reference=$f, then mv -f
```

Points that carry over from `_resignRefind` for the same reasons:

- **Verify against the anchor, not the signing cert.** This is what stops a
  re-run stacking a second signature, and what leaves an admin's own
  already-signed copy alone.
- **Temp file then `mv`.** `sbsign` reads its input while writing its output, so
  input and `--output` must differ. Create the temporary in the same directory
  so it inherits the SELinux context, and take the original's ownership.
- **Not fatal.** A failure warns and returns 0. Unsigned binaries cost nothing to
  any client that boots today.

Points specific to this function:

- **`$tftpdirdst/secureboot/` is excluded.** That directory holds upstream's
  Microsoft-signed shim and iPXE-signed loader, delivered as a *separate* release
  asset (`fog-ipxe-secureboot-${ipxeVer}.tar.gz`, staged by
  `secureboot/stage.sh`). Re-signing them is pointless and risks disturbing the
  signatures the whole chain depends on.
- **BIOS artifacts are skipped.** `.kpxe`/`.lkrn`/`.usb`/`.iso` are not PE images
  and Secure Boot does not apply to them. Match `*.efi` only.
- **Signing is in place, under the binaries' usual names.** Every compatibility
  variant stays exactly where it is and stays valid for netboot — an appended PE
  signature does not affect a client booting with Secure Boot off.

**Call site:** `bin/installfog.sh`, immediately after `_resignRefind` (1132).
That is after `configureTFTPandPXE` (1121) has populated `$tftpdirdst` and after
`restorePreservedCustomizations` (1126), which is the same ordering argument
`_resignRefind` documents — sign what actually ends up on disk, not what was
there before a restore overwrote it.

### 2. `_publishLocalBootFiles()`

The machines this exists for cannot fetch a boot file over the network — that is
the whole problem — so the binaries have to be reachable over HTTP to get onto
an ESP at all. The TFTP tree is not web-served, which today means every admin
hand-rolling symlinks into the document root.

A new function copies a **curated list** of binaries from `$tftpdirdst` into
`${webdirdest}/service/localboot/`, preserving relative layout so `i386-efi/`,
`arm64-efi/`, `10secdelay/` and `secureboot/` keep their meaning.

**Curated, not a sweep.** The tree carries 45 FOG binaries — 5 names × 3
architectures × 3 embed variants — and publishing all of them says nothing about
which one an admin should reach for. The list is an explicit array in
`functions.sh`, 25 entries, ~12MB:

- Per architecture: `ipxe.efi` (iPXE's own drivers — the primary, and the stage
  the Secure Boot chain has to reach), `snp.efi` (firmware SNP, for hardware
  iPXE's drivers do not cover), `intel.efi` / `realtek.efi` (single-vendor
  fallbacks), and `10secdelay/ipxe.efi` (the STP/power-save link-up workaround,
  primary only).
- `secureboot/` whole — ten files, each a stage of a chain. `shim.c` rewrites its
  own `-shim<arch>.efi` suffix to `.efi` to pick its second stage, so
  `snponly-shimx64.efi` loads `snponly.efi` and `ipxe-shimx64.efi` loads
  `ipxe.efi`; the pairs must travel together. Included here although
  `_signLocalIpxe()` prunes them from signing — the chain starts at upstream's
  shim, so a client needs them even though FOG must not re-sign them.

Excluded, with reasons: **`snponly.efi`** binds only the device iPXE was loaded
from, and booted off an ESP that device is the disk, so it never finds a NIC —
right for netboot, wrong here, and exactly the kind of mistake an uncurated
directory invites. (Upstream's `secureboot/snponly.efi` is a different case: it
only reads `autoexec.ipxe` off the same ESP and chains onward, needing no NIC of
its own.) **`autoexec/`** are the EMBED-less builds, which fetch an
`autoexec.ipxe` this does not publish, so they would arrive inert. BIOS artifacts
(`.kpxe`/`.lkrn`/`.usb`/`.iso`) are not PE images and an ESP cannot boot them.

**Not gated on Secure Boot.** Local ESP boot predates Secure Boot by years —
firmware with no PXE option, or a queued task that would otherwise need a
boot-order change. Secure Boot only added the requirement for a *signature*. So
the directory is published whether or not the server holds signing keys; without
them the binaries are simply unsigned, which is what every machine booting with
Secure Boot off needs anyway. That is also why it sits at `service/localboot/`
rather than under `service/secureboot/`: `_publishSecureBootKit()` `rm -rf`s its
whole kit directory when there is no MOK, which would take this with it on
precisely the servers that still want it.

Called from `installfog.sh` immediately after `_signLocalIpxe`, as a **separate**
call rather than folded into it, because the two do not share a trigger: signing
needs keys, publishing does not. `configureHttpd` also rebuilds the web root from
scratch on every run, so publishing has to happen even on a run that signed
nothing.

Browsability is handled by an `index.php` returning 404, the identical stub
`_publishSecureBootKit()` and `service/ipxe` already use — written into **every**
directory, not just the top one. `DirectoryIndex index.php` is already emitted in
every vhost variant, but it only suppresses a listing where an `index.php`
actually exists, and `mod_autoindex` is live on a stock `/var/www/html` because
the `Options +FollowSymLinks` emitted there *merges* with the distro's own
`Options Indexes FollowSymLinks` rather than replacing it. With a stub per
directory, **no web server config changes are needed at all**.

### 3. Why copies rather than a symlink

A symlink to `$tftpdirdst` was the first design. Three things killed it:

1. **SELinux.** `setSELinuxContext()` labels the TFTP tree `tftpdir_t`, and
   `httpd_t` has no rule permitting it to read that type — so the link 403s on
   every enforcing host (Alma/Rocky/RHEL/Fedora), while working fine on
   Debian/Ubuntu/Arch/Alpine, which ship no SELinux. That is the same failure
   GH-963 fixed in the other direction, where `tftpd_t` could not read
   `default_t`. Relabelling to `public_content_t` would fix it, but widens the
   tree to ftpd, rsync and samba to serve a feature needing none of them, and
   means editing a recent security fix. Files created under `$webdirdest`
   inherit the web root's own label and need no policy change.
2. **It deleted the whole vhost diff.** The symlink needed `Options -Indexes`
   in all three Apache variants plus `autoindex off` in three nginx blocks —
   about 40 lines across nine sites in the most delicate function in the
   installer. A real directory needs none of it.
3. **It removed a dependency that fails open.** The symlink rested on Apache
   matching `<Directory>` against the *unresolved* path (GH-529). If that were
   ever wrong the block would not match, the inherited `Indexes` would apply, and
   a listing of the entire TFTP tree would be exposed. It was also the one risk
   that could not be verified without a running Apache.

A fourth benefit fell out: publishing a fixed list is **narrower** than
publishing the tree. Nothing an admin later drops into `$tftpdirdst` becomes
web-reachable, so there is no standing rule against using that directory. It also
made curation possible at all — a link publishes whatever is there, whereas a
list can leave out the variants that cannot work from an ESP.

The cost is ~12MB duplicated on a server that stores images in tens of
gigabytes, and copies that could drift if someone hand-edits the TFTP tree
between installs — weak, since the installer rebuilds both every run and the copy
happens immediately after signing.

### 4. `esp/` — the ready-to-copy kit

§2 publishes a *menu*. This publishes a *folder to copy*, and the two are not
the same job. The chain, as tested on hardware:

```
boot manager -> snponly-shimx64.efi   upstream shim
             -> snponly.efi           upstream loader, in the SAME folder
                                      (MOK trust is established from here on)
             -> autoexec.ipxe         read from the binary's own directory
             -> fogipxe.efi         FOG's build, MOK-signed, FOG script embedded
```

**Two of those names are not free choices**, which is the whole reason the kit
exists. shim picks its second stage by rewriting its own `-shim<arch>.efi` suffix
to `.efi`, so `snponly-shimx64.efi` will load `snponly.efi` and nothing else, and
it has to be upstream's copy because that is what shim's embedded certificate
vouches for. That reserves `snponly.efi` — and `ipxe.efi` for the other pair. So
FOG's own builds cannot keep their natural names on an ESP; they ship as
`fogipxe.efi`, `fogsnp.efi`, `fogintel.efi`, `fogrealtek.efi`. Not
cosmetic: dropping FOG's `ipxe.efi` beside `ipxe-shimx64.efi` would have shim try
to load an image it cannot verify.

If the admin renamed on copy instead, a slip would be a silent boot failure — and
the script hardcodes the names, so it could not adapt. Publishing them pre-named
removes the step.

x86_64 and arm64 only: those are the two architectures upstream signs a shim for.
i386 has no signed shim, so there is no chain to assemble — an i386 machine
booting with Secure Boot off takes `i386-efi/ipxe.efi` from §2's menu and needs
none of this.

**`autoexec.ipxe` is static, not a template.** `default.ipxe` is generated per
install because it embeds the server address; this has no per-server content at
all. It chains a sibling by relative filename, and FOG's build carries the
DHCP/next-server script compiled in, so it finds the server itself. Generated by
`_publishLocalBootFiles()` rather than shipped from `fog-ipxe`, to keep this
change fogproject-only with no `$ipxeVer` coordination — see Non-goals.

**Its fallback ladder covers which files were copied, not which driver works**,
and the docs must say so in those words. `chain X || goto Y` branches only when
the image fails to *load* — absent, malformed, or rejected by verification. Once
an image loads and runs, control never returns: FOG's embedded script ends its
failure path with `prompt … && shell || reboot`, so a binary that starts but
binds no NIC stops there and the next branch is never reached. This is exactly
where it differs from the `net0`/`net1`/`net2` ladder in the netboot script,
which works because it stays inside one iPXE instance instead of handing off.
Still worth the four lines: one kit then boots whether the admin copied the whole
folder or only the variant their hardware needs.

## Rejected alternatives

| Alternative | Why not |
| --- | --- |
| Always build and embed the CA | 10-25 min per install, no warm path, aarch64 cross-compiler as a hard prereq on every distro. Undoes the release-asset speedup for a benefit the release asset already provides. |
| A `--build-ipxe` flag for the force-build case | The only gap it serves is a site serving netboot over HTTP that wants its ESP file to reach a private-CA HTTPS server. The better answer there is an FQDN and a public cert. Revisit if it turns up in practice. |
| A dedicated `ipxescript-localboot` embed that skips `next-server` | Less flexible, not more — breaks multi-FOG-server deployments, and proxy DHCP already covers the failsafe case. Would also force a fog-ipxe change and `$ipxeVer` coordination. |
| Sign only a hand-picked local-boot subset | Signing is in place under `$tftpdirdst` and costs nothing per file, so restricting it would only make a variant fetched by hand off TFTP useless. *Publishing* is curated — see Design §2 — because there the list is advice, not just bytes. |
| Publish every `*.efi` in the tree | The first implementation. 55 files and 27MB, including `snponly.efi`, which cannot work from an ESP, and the `autoexec/` builds, which arrive inert without an `autoexec.ipxe` beside them. A directory that offers a wrong answer next to the right one is worse than a shorter directory. |
| Gate signing behind a flag | Signing was never the expensive part. A flag means a Secure Boot site that wants local ESP boot has to know it exists. |
| Symlink `service/secureboot/…` to `$tftpdirdst` | The original design. 403s under SELinux, needed ~40 lines of vhost changes across nine sites, and depended on GH-529 behavior that fails open. Replaced by copies — see Design §3. |
| Relabel the TFTP tree `public_content_t` | Would make the symlink work, but widens the tree to ftpd/rsync/samba for a feature needing none of them, and means editing GH-963's recent fix. |
| ~~Defer the ESP `autoexec.ipxe`~~ | **Reversed.** Testing showed it is load-bearing, not a convenience: the chain does not work without it, so a published directory lacking it is an incomplete kit. Now shipped as part of `esp/` — see Design §4. |
| Drop upstream's `ipxe-shimx64.efi` + `ipxe.efi` as redundant | Raised in review on the grounds that both shim pairs only ever chain onward, so `snponly` alone would do. Rejected: shim resolves its second stage from its own filename, so each pair is indivisible, and an admin whose boot manager is already pointed at one of them needs that one present. Ten files is not worth the sharp edge. |

## Resolved during implementation

- **`restorePreservedCustomizations` does not touch `/tftpboot`.** It only ever
  works inside `${webdirdest}service/ipxe`. So unlike `_resignRefind`,
  `_signLocalIpxe` has no restore dependency — it is placed alongside the other
  signing calls for cohesion, not for correctness, and its comment says so. What
  it does depend on is `configureTFTPandPXE`'s copy loop and `downloadfiles()`'
  key setup, both of which have run by that point.
- **Nothing stamps or verifies `/tftpboot` contents**, so no re-stamping is
  needed. `_stampFogSum` is only ever applied to `${webdirdest}/service/ipxe/*`,
  which is why `_resignKernels` had to re-stamp and this does not.
- **No web server config changes are needed.** This started as vhost edits in
  nine sites; moving from a symlink to copies removed all of them, so the three
  byte-identical Apache `<Directory>` blocks stay as they were. Extracting them
  into a helper is still worth doing, but as its own change.
- **The `find` prune expression is verified**, against a mock tree of the real
  shape: 45 `.efi` matched (5 names × 3 arches × 3 embed variants), zero from
  `secureboot/`, zero non-PE artifacts (`.kpxe`/`.lkrn`/`.usb`/`.iso`/`.ipxe`),
  and correct with a trailing slash on `$tftpdirdst`.
- **The publish loop is verified against a mock tree built from the generators
  themselves** — `buildipxe.sh`'s own `cp` lines and `secureboot/stage.sh`'s
  `install` lines — which reproduces the 55 `.efi` the earlier run measured
  against the real release assets. Against it the curated list publishes **25
  files**, with zero FOG `snponly.efi`, zero `autoexec/`, and nothing that is not
  a `.efi` or an `index.php`. Also checked: re-running is byte-identical, a
  trailing slash on `$tftpdirdst` is handled, an HTTPS install with no
  `secureboot/` staged publishes 15 files and still reports success, and an empty
  tree reports failure rather than silently publishing nothing.
- **Every directory gets the 404 stub, not just the top one.** `DirectoryIndex`
  only suppresses a listing where an `index.php` exists, and the Apache
  `Options +FollowSymLinks` this installer emits *merges* with a stock
  `/var/www/html`'s `Options Indexes FollowSymLinks` rather than replacing it —
  so `arm64-efi/`, `i386-efi/`, `10secdelay/` and `secureboot/` would each have
  listed. Caught in review of the first implementation.
- **The published set is curated rather than swept.** See Design §2. The first
  implementation published all 55 `.efi`, which put `snponly.efi` — unusable from
  an ESP — directly beside the binary an admin actually wants.
- **The ESP `autoexec.ipxe` is not optional and the kit needs pre-named
  binaries.** Both follow from shim resolving its second stage from its own
  filename, which reserves `snponly.efi`/`ipxe.efi` on any ESP. Testing the real
  chain is what surfaced it; reading the file list would not have. See Design §4.
- **The SELinux gap was found by reading, before any test run.** A symlink would
  have 403'd on every enforcing host, and the default test distro is
  `ubuntu:24.04` — which ships no SELinux and would have passed. See Design §3.
- **Upstream's signed all-drivers `ipxe.efi` does not load its drivers when
  booted locally off an ESP** — tested on hardware. This closes the one question
  that could have collapsed the whole design: if that binary worked, FOG would
  need to sign nothing and could ship upstream's pair plus a two-line
  `autoexec.ipxe`. It does not, so the chain genuinely has to reach FOG's own
  build. Recorded in the Problem section because nothing in either source tree
  predicts it.

## Remaining risks

- **`sbsign` across 45 files** is expected to take seconds, and there is a `dots`
  line so it is not silent — but it has not been timed on real hardware. If it
  turns out slow, the progress line is already in place to build on. Note the
  asymmetry: signing still covers the whole tree, because it is in place and
  costs nothing per file, so a variant fetched by hand off TFTP is still signed.
  Only publishing is curated.
- **The published directory adds ~12MB to the web root.** Bounded and known, but
  it is disk that previous releases did not use.
- **The curated list names files by path**, so a rename in `fog-ipxe` drops an
  entry silently — a missing source file is skipped, not reported, because that
  is also how an HTTPS install's absent `secureboot/` has to behave. The "nothing
  was copied" branch catches a total break, not a single lost variant.
- **Exposing the binaries over HTTP** — researched below and cleared.

## Security review of the HTTP exposure

Researched rather than assumed, because "it is already on TFTP" is only half an
argument — reachability changes even when content sensitivity does not.

**Everything published is public by nature.** The set is FOG's own iPXE binaries
and upstream's signed shim and loader. The latter are downloadable from
fog-ipxe's release assets regardless; the former are already served
unauthenticated over TFTP.

**Not a new class of exposure for FOG, which is the decisive point.**
`_resignKernels()` signs `bzImage`, which lives at `$webdirdest/service/ipxe/`
and is fetched by every client over `$_booturl` — `self::$httpproto`,
`bootmenu.class.php:462` — with no authentication. MOK-signed boot artifacts over
unauthenticated HTTP is already how FOG works.

**Nothing sensitive can reach the published directory.** Files are copied by
name from an explicit list, so `default.ipxe`, `autoexec.ipxe`, the `MANIFEST`
and the BIOS artifacts stay out — not because a filter excludes them, but because
nothing names them. `TFTP_FTP_PASSWORD` lives in
`$webdirdest/lib/fog/config.class.php`, never in the TFTP tree. Because the set is
a fixed list rather than a view of a directory, anything an admin later drops into
`$tftpdirdst` is **not** published — the main advantage the copy model has over
the symlink it replaced, and the reason there is no standing rule about what may
live in the TFTP directory.

**No traversal, no symlink semantics.** The published directory is a plain
directory of regular files. Nothing depends on `FollowSymLinks`, and the Apache
symlink risk — a shared-hosting problem where an untrusted tenant links to
another tenant's files — never enters into it.

**No write path.** Apache serves read-only; `in.tftpd -s` without `-c` already
was.

**HTTP is the recommended transport here**, not a downgrade — iPXE's own docs
favor it over TFTP, and UEFI HTTP Boot is a firmware standard. No CVE exists for
exposing PXE artifacts over HTTP; FOG's actual CVEs are elsewhere (command
injection in `export.php`, auth bypass, world-readable `.fogsettings`).

**The one real delta is reach.** TFTP is UDP/69 and LAN-scoped in practice; HTTP
goes as far as the web server does. Content sensitivity is unchanged.
`docs/SUPPORTED_CUSTOMIZATIONS.md` documents the directory, notes it is rebuilt
every run, and tells an admin who does not want it published to delete it.

## Verification

1. On a test server with Secure Boot keys present, run the installer and confirm
   `sbverify --cert <anchor> /tftpboot/ipxe.efi` succeeds, and that
   `/tftpboot/secureboot/snponly-shimx64.efi` is **untouched** — still carrying
   only upstream's signature.
2. Re-run the installer; confirm nothing is re-signed (the `sbverify` guard hits)
   and no second signature is stacked.
3. `curl` a binary through
   `https://<server>/fog/service/localboot/ipxe.efi` and confirm it downloads and
   is a PE image; `curl` the directory itself and confirm the `index.php` stub
   404s rather than returning a listing. Confirm
   `secureboot/snponly-shimx64.efi` is fetchable from there too, since the ESP
   chain starts with it.
3b. `curl` **each subdirectory** — `arm64-efi/`, `i386-efi/`, `10secdelay/`,
   `10secdelay/i386-efi/`, `10secdelay/arm64-efi/`, `secureboot/`,
   `secureboot/arm64-efi/` — and confirm every one 404s rather than listing. This
   is the check the first implementation would have failed; the stub was only
   written at the top level.
3c. Repeat on an SELinux-enforcing distro (Alma/Rocky). Copies should need no
   policy change, but this is the case the symlink design failed, so it is worth
   proving rather than assuming.
4. Assemble an ESP on an already-enrolled machine — shim, snponly as `ipxe.efi`,
   the two-line `autoexec.ipxe`, the signed build as `fogipxe.efi` — set the
   boot manager path to the shim, and confirm it reaches the FOG menu with Secure
   Boot on.
5. Confirm an un-enrolled machine still netboots to the menu and can still run
   *Enroll Secure Boot Key*. This is the regression that matters most.
6. **On a server with no Secure Boot keys at all**, confirm `service/localboot/`
   is still published, holds the same 25 files unsigned, and that one of them
   boots a machine from its ESP with Secure Boot off. This is the case the first
   implementation skipped entirely — it gated publishing on `$secureBootMokCert`,
   so a non-Secure-Boot server got nothing.

---

## Superseded: the published layout is now archives (2026-08-17)

Everything above about *why* these binaries exist, which names shim reserves, and
what the hardware testing established still holds and is still the reason the
feature is shaped as it is. **What changed is Design §2 and §4 — what gets
published and how.** Recorded here rather than edited in above, so the reasoning
that led to the first shape stays readable.

Reworks GH-1117. Prompted by looking at the tree on a server running the result:

```
service/localboot:
  10secdelay  arm64-efi  esp  i386-efi  index.php
  intel.efi  ipxe.efi  realtek.efi  secureboot  snp.efi
```

### What was wrong

§2 published a *menu* and §4 published a *kit*, and they were the same bytes
twice — `localbootfiles` under the TFTP names at the root, `localbootespfiles`
under `esp/` with the `fog*` names. Consequences, all visible in that listing:

- **arm64 in four places**: `arm64-efi/`, `10secdelay/arm64-efi/`,
  `esp/arm64-efi/`, `secureboot/arm64-efi/`.
- **The delay set was inconsistent between the two lists.** The menu published
  only `10secdelay/ipxe.efi`; the kit published all four 10sec variants. Neither
  list was wrong on its own terms; together they said two different things.
- **Nothing told a downloader which file to take**, and with listing off the only
  way to enumerate the directory was to already know the filenames.

The premise that the two lists served two audiences did not survive contact.
The *only* reason the kit needed different filenames is that shim reserves
`snponly.efi` and `ipxe.efi` on an ESP — and those names are the strictly safer
choice everywhere. So one correctly-named folder serves both the admin
assembling an ESP and the admin who wants one binary out of it.

### What replaced it

Six archives and a `manifest.json`, and nothing else. One archive per
(architecture × delay variant), each holding a single top-level directory named
after itself.

**The delay variant is its own archive.** §4 shipped both binary sets plus
`autoexec.ipxe` and `autoexec-10sec.ipxe` in one folder, and choosing meant
renaming one over the other — because iPXE runs exactly the file called
`autoexec.ipxe` and cannot be asked which set you want. Choosing at *download*
time deletes the step: the binaries in a `-10sec` archive carry the plain names
and its single `autoexec.ipxe` is already correct. It costs each variant its own
copy of the upstream shim set, which is the only reason the total is not smaller
than the tree it replaced.

**`manifest.json` is what makes curation honest.** §2 curated by *omission* —
which is how FOG's `snponly.efi` came to be unavailable at all, and how an
archive-less directory ended up saying nothing about which of five binaries to
reach for. A manifest carries the same advice per file (`role`, `origin`,
`fogSigned`, a prose `note`) without having to withhold anything to give it. It
is a static file written at install time, deliberately not a PHP endpoint that
lists the directory on request — that would be a directory listing with a
traversal surface, to save writing a file that changes only when the installer
runs.

### Reversals

| Was | Now | Why |
| --- | --- | --- |
| FOG's `snponly.efi` excluded | published as `fogsnponly.efi`, tried **last** in the ladder | The exclusion was only forced because `snponly.efi` is a shim-reserved name. Under the `fog` prefix there is no collision, the manifest note carries the disk-not-NIC caveat, and there is hardware where it works. |
| `MOK.der` not in the kit | in every archive | MokManager enrolls by browsing the ESP for a certificate. Shipping MokManager without one is still a dead end — it just fails one screen later. §4 missed this. |
| No `.auth` blobs in the kit | `PK`/`KEK`/`db.auth` in every archive | They are signed EFI variable updates a machine in Setup Mode writes to put this server's certificate straight into `db`, after which firmware verifies a signed `fogipxe.efi` **directly** — no shim, no MokManager, no MOK. |
| "i386 has no Secure Boot chain to assemble" (§4) | **wrong, and corrected** | True that upstream signs no shim for ia32. But the `db` route needs no shim, so i386 Secure Boot local boot works. The conclusion followed from treating the shim chain as the only route. |
| Publishing gated on nothing; **signing** gated on `--no-secure-boot` | signing always runs; the opt-out declines **enrollment** | A PE signature is inert with Secure Boot off, so signing costs nothing, while an unsigned binary is useless the day anyone enrolls with nothing on the server able to fix it. The flag now stops `MOK.der`, the `.auth` blobs, and so the PXE enroll entry — applied in `_ensureSecureBootPlatformKeys` and `_publishSecureBootKit` instead of by blanking `$secureBootKey`. |
| ~~Curated list of loose files~~ | archives only | See the trade below. |

### Traded away, deliberately

**No individual binary has a URL any more**, so nothing published here can be a
UEFI HTTP Boot target or an iPXE `chain` destination — which `service/secureboot/mmx64.efi`
demonstrates is a real use (the PXE menu chains that single file over HTTP, and
that is exactly why it is copied there). Accepted because the archive serves the
"prepare an ESP" case that this feature exists for, and publishing the loose set
alongside is purely additive if the other case ever turns up.

### Bug found in adjacent code

`_copyIpxeTree()` records a sha256 of every file it lays down in
`${tftpdirdst}/.fog-ipxe-manifest`, so a later run can tell FOG's copy from one
the admin replaced. `_signLocalIpxe()` re-signs those `.efi` **in place**
afterwards and never re-stamped. On the *second* install of a Secure Boot server
every `.efi` compares unequal: FOG stops updating its own binaries and reports
all 45 as admin-modified, every run, permanently.

The note under *Resolved during implementation* above — "nothing stamps or
verifies `/tftpboot` contents, so no re-stamping is needed" — was true when
written and stopped being true when `.fog-ipxe-manifest` landed. Fixed by
`_restampIpxeManifest()`, which rewrites only the lines for files actually
signed; an entry deliberately carrying the *original* sum for a file the admin
really did replace is copied through untouched, so that file stays protected.

### Rejected during the rework

| Alternative | Why not |
| --- | --- |
| Keep the menu/kit split, dedupe with symlinks | Preserves the two-tree mental model, which is the thing that was confusing. A symlink inside the web root would work — same SELinux label, `Options +FollowSymLinks` already emitted, so the objection that killed the TFTP-tree symlink does not apply — but archiving symlinks is a trap and the split was the problem, not the copying. |
| Loose files *and* archives | ~8MB more and, more to the point, it keeps the Secure Boot material as a third loose copy of what `service/secureboot/` already holds. The single-file URL it buys is real but hypothetical here. |
| Bundle `bzImage`/`init.xz` per arch | 60–80MB per architecture, and it would not produce a working local boot on its own: FOS reads per-host, per-task kernel arguments that `boot.php` generates. Listed in the manifest's `kernels` section instead — no bytes moved, and the manifest becomes the single index. |
| Grab `.usb`/`.iso` if present | A coherent but *different* feature (boot iPXE off a USB stick without touching the ESP), with its own layout question. Not published, and not silently dropped — say so if it is wanted. |
| Build the manifest with `jq` | `jq` is in the install list, so it is normally there — but a missing package would mean publishing no manifest at all, which is the whole feature. Hand-written through a `_jsonStr` escaper instead: everything encoded here is content this file produced, so the escaping problem is bounded. Same reasoning as the `tar` fallback for `zip`. |
| Symlink the enrollment material from `../secureboot/` | `_publishSecureBootKit()` `rm -rf`s that directory when there is no MOK, so a link inside an archive could dangle. Copies keep each archive self-contained, and the files are a few KB. |

### Verification (in addition to the list above)

`tests/localboot-publish.test.sh`, 62 assertions. Every mock file's **content is
its own path in the tree**, so provenance is read out of the file rather than
inferred — which is what pins the failure that is otherwise invisible on the
server: a `-10sec` archive whose `fogipxe.efi` came from the plain tree shows up
only as "the delay does nothing" on a switch running STP.

Also covered: the manifest parses as JSON under an independent implementation
(python, not the shell that wrote it) with every sha256 matching the published
bytes; no BIOS artifact and no EMBED-less `autoexec/` build leaks in; upstream
keeps the two shim-reserved names; the HTTPS-only shape publishes six archives
and correctly omits an `autoexec.ipxe` no loader could read; a server with no
enrollment material still succeeds; an empty tree reports `Failed` rather than
publishing silently; a re-run is stable; and the `.fog-ipxe-manifest` regression
is pinned end to end.

Steps 3/3b of the original verification list are replaced by: fetch
`manifest.json`, check each archive's sha256 against it, and confirm the
directory itself 404s. Step 4 becomes: extract `fog-esp-x86_64.zip` and point the
boot manager at `snponly-shimx64.efi`. New step: enroll `db.auth` from
`fog-esp-i386.zip` through firmware Setup Mode and boot `fogipxe.efi` directly,
with no shim — the path this spec originally said did not exist.

---

## Superseded again: EMBED-less iPXE forces two scripts, and kills the delay set (2026-08-19)

The archives above stopped booting a day after they were designed, and nothing in
the installer noticed. Recorded here rather than edited in above for the same
reason as the last block: the shape only makes sense alongside the shape it
replaced.

Fixes GH-1195, and lands GH-1185 with it because both turn on the same layout
question.

### What changed underneath

fog-ipxe `v2.0.0-fog.8` (fog-ipxe#7) removed `EMBED=` from every EFI target. The
`fog*.efi` builds these archives publish no longer carry FOG's
DHCP/proxyDHCP/`next-server` logic — they read it from a file called
`autoexec.ipxe`, which iPXE resolves against the directory the running binary was
itself loaded from.

That invalidated two things this spec asserted:

1. **§4's flat archive could not work.** Its single `autoexec.ipxe` was the chain
   ladder, written for upstream's driverless loader, and it sat in the same
   directory as the binaries it chained. A `fog*.efi` launched from the firmware
   boot manager — the Secure Boot OFF route, and the Setup Mode route this spec
   was proud of adding — read that ladder and executed `chain fogipxe.efi`:
   itself. Via shim the binary came up with no FOG script at all, so no multi-NIC
   walk, no proxyDHCP, no `next-server` prompt.

2. **The `-10sec` archives were being published empty of FOG binaries.**
   `_espKitFiles()` sourced them from `10secdelay/`, which is BIOS-only now, and
   which `_retireStaleEfiPaths()` actively clears of `.efi` files. The copy loop
   treats a missing source as fine by design (an HTTPS install stages no
   `secureboot/`), and `copied` stayed non-zero from the shim set, so three
   archives were staged, checksummed and advertised in `manifest.json` holding a
   shim, MokManager, enrollment material, and a README telling the admin to boot a
   file that was not in them.

### What replaced it

**FOG's builds move into `local/` with their own `autoexec.ipxe`.** Two scripts,
two directories, and neither binary can reach the other's:

```
fog-esp-x86_64/
  autoexec.ipxe          the ladder: chain local/fog*.efi in order
  snponly-shim*.efi snponly.efi ipxe-shim*.efi ipxe.efi mm*.efi
  MOK.der PK.auth KEK.auth db.auth  README.txt  MANIFEST.json
  local/
    autoexec.ipxe        FOG's real boot script; the delay lives here
    fogipxe.efi fogsnp.efi fogintel.efi fogrealtek.efi fogsnponly.efi
  refind/
    refind.efi refind.conf
```

The upstream set stays at the top level, non-negotiably: shim derives its second
stage *and* MokManager from its own directory by name. FOG's builds keep the `fog`
prefix even though the subdirectory has made the collision impossible — §4's
reason for the prefix is gone, but the names are in the README, the docs and every
bug report since GH-1117, and renaming buys nothing.

The root ladder is still gated on an upstream loader being present.
`local/autoexec.ipxe` is **not** — the binary that reads it is FOG's own, so an
i386 archive and an HTTPS-only install both need it. That inverts §2's gate,
which asked "is there something here that reads a script" and answered by looking
for the loader.

**Three archives, not six.** With the script on disk the delay is two lines of
text, so `local/autoexec.ipxe` ships them commented out and `--boot-delay` writes
them live inside the same `# FOG-BOOT-DELAY-BEGIN`/`-END` sentinels
`_applyBootDelay()` uses on the TFTP copy. So one option now covers netboot and
ESP boot, where before it covered netboot and the ESP needed a different download.

This directly reverses the note at §4 and in `SUPPORTED_CUSTOMIZATIONS.md` that
"the delay has to live in the binary, not the script — `sleep` is an optional iPXE
command that FOG's own builds enable but upstream's signed loader may not". True
as far as it went, and beside the point: the loader only chains. The `sleep` runs
in FOG's build, which enables it, immediately before the DHCP it is delaying.

**rEFInd ships in `refind/`** (GH-1185). `FOG_EFI_BOOT_EXIT_TYPE` defaults to
`refind_efi`, so rEFInd is what a UEFI host chainloads on the way out of the boot
menu, and an ESP assembled from this kit had no local-boot chainloader on it at
all. It is the first thing published here that comes from the **web** tree rather
than `$tftpdirdst` — rEFInd has never existed in the TFTP tree, which is exactly
why `_publishLocalBootFiles()` never saw it. `_resignRefind()` has already signed
it by the time this runs (`installfog.sh:1266` vs `:1302`), so nothing needed
reordering. Its own subdirectory because rEFInd reads `refind.conf` from the
directory it was loaded from. x86_64 follows `bootmenu.class.php`'s
`refind.efi`-over-`refind_x64.efi` preference so the ESP and the netboot path
agree on which binary is canonical.

### The iPXE behavior this rests on

`efi_autoexec_filesystem()` tries `file:autoexec.ipxe` — resolved against the
directory of the running image's `FilePath` — and then `file:/autoexec.ipxe` at
the volume root (`interface/efi/efi_autoexec.c`, `efi_local.c`). A binary loaded
from an ESP therefore reads the `autoexec.ipxe` beside it, at any depth. That is
the whole mechanism.

**Worth writing down because the source reads the other way.** For a binary that
another iPXE `chain`ed, `efi_image_exec()` builds a synthetic device handle and
installs iPXE's own `EFI_SIMPLE_FILE_SYSTEM_PROTOCOL` on it
(`image/efi_image.c`, `interface/efi/efi_file.c`); `efi_local_open_volume()`
resolves through `LocateDevicePath()`, which matches the longest device-path
prefix and so lands on that synthetic handle. That virtual filesystem serves
registered images by flat name only — no directories, no passthrough — and
`image_exec()` unregisters the running script for the duration, so on paper the
chained binary should find nothing. Observed behavior on hardware is the sibling
read, for the chained case as well as the firmware-loaded one.

Hardware wins, and the layout is built on the observed behavior. Do not
"correct" it on the strength of the source read. If a client ever does dead-end
on the chained route specifically — reaching iPXE's bare `autoboot()` instead of
FOG's script — that synthetic handle is where to look, and the fix would be for
the root ladder to register the script under the name the child looks for
(`imgfetch local/autoexec.ipxe`) before chaining.

### Manifest

`schema` goes to `2`. `variant` is gone from each archive, and
`contents[].name` is the path relative to the archive root
(`local/fogipxe.efi`), because `_espKitContentsJson()` now recurses.

That last part matters more than it looks. It used `find -maxdepth 1` and stored
basenames; left alone it would have omitted `local/` and `refind/` **silently**,
and every consumer — including the test harness's per-file checksum loop, which
walks the manifest rather than the directory — would have gone on passing. An
under-reporting manifest reads exactly like a correct one.

### Verification

`tests/localboot-publish.test.sh` grew from 62 to 87 assertions. The mock tree's
"every file's content is its own path" mechanism carried over unchanged and is
what proves rEFInd came from the web tree and matches its archive's architecture.
All 50 of the assertions touching the new behavior were confirmed to fail against
the pre-fix `functions.sh` before being relied on.

Hardware step still owed, and the only one that cannot be faked: boot an ESP
assembled from an archive by both routes — shim → upstream loader → chain, and
boot manager → `local\fogipxe.efi` directly — and confirm `Checking net0 for
DHCP...` appears each time rather than iPXE's bare autoboot.
