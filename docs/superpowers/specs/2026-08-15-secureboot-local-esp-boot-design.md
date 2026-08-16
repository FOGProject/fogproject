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
  -> EFI\localipxe.efi          FOG's own build               UNSIGNED -> stops here
```

Upstream's `snponly.efi` cannot finish the job itself. It binds the firmware's
UEFI SNP protocol, and this class of hardware frequently does not provide one —
which is the same reason its firmware has no PXE option. The chain has to reach
a binary carrying iPXE's own NIC drivers, and that binary is FOG's own build.

Once shim has run, its security policy override stays installed for the rest of
the boot, so a MOK-signed binary loads. FOG already publishes and enrols a MOK
(`_publishSecureBootKit`, `service/secureboot/MOK.der`). Nothing is missing but
a signature on the binaries FOG already ships.

## Non-goals

**This must never be on the netboot path.** The enrolment workflow depends on an
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
`_resignRefind()` already uses, and publish copies of every `.efi` into a
non-browsable directory beside `MOK.der`.**

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

New function in `lib/common/functions.sh`, modelled directly on
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

A new function copies every `*.efi` from `$tftpdirdst` into
`${webdirdest}/service/secureboot/local-boot/`, preserving relative layout so
`i386-efi/`, `arm64-efi/`, `10secdelay/`, `autoexec/` and `secureboot/` keep
their meaning. `secureboot/` **is** included here, unlike in `_signLocalIpxe()`
where it is pruned: the chain starts at upstream's shim, so a client needs those
binaries even though FOG must not re-sign them.

Measured against the real release assets: **55 files, 27MB** — 45 FOG binaries
plus upstream's 10.

Called from `installfog.sh` immediately after `_signLocalIpxe`, as a **separate**
call rather than folded into it. `_signLocalIpxe` returns early when there is
nothing left to sign, and `configureHttpd` rebuilds the web root from scratch on
every run — so folding them together would leave the directory missing on any
run that signed nothing.

Browsability is handled by an `index.php` returning 404, the identical stub
`_publishSecureBootKit()` and `service/ipxe` already use. `DirectoryIndex
index.php` is already emitted in every vhost variant, so **no web server config
changes are needed at all**.

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

A fourth benefit fell out: publishing a fixed set of `*.efi` is **narrower** than
publishing the tree. Nothing an admin later drops into `$tftpdirdst` becomes
web-reachable, so there is no standing rule against using that directory.

The cost is ~27MB duplicated on a server that stores images in tens of
gigabytes, and copies that could drift if someone hand-edits the TFTP tree
between installs — weak, since the installer rebuilds both every run and the copy
happens immediately after signing.

## Rejected alternatives

| Alternative | Why not |
| --- | --- |
| Always build and embed the CA | 10-25 min per install, no warm path, aarch64 cross-compiler as a hard prereq on every distro. Undoes the release-asset speedup for a benefit the release asset already provides. |
| A `--build-ipxe` flag for the force-build case | The only gap it serves is a site serving netboot over HTTP that wants its ESP file to reach a private-CA HTTPS server. The better answer there is an FQDN and a public cert. Revisit if it turns up in practice. |
| A dedicated `ipxescript-localboot` embed that skips `next-server` | Less flexible, not more — breaks multi-FOG-server deployments, and proxy DHCP already covers the failsafe case. Would also force a fog-ipxe change and `$ipxeVer` coordination. |
| Sign only a hand-picked local-boot subset | The subset has to be guessed up front, and the variants exist precisely because hardware needs differ. |
| Gate signing behind a flag | Signing was never the expensive part. A flag means a Secure Boot site that wants local ESP boot has to know it exists. |
| Symlink `service/secureboot/…` to `$tftpdirdst` | The original design. 403s under SELinux, needed ~40 lines of vhost changes across nine sites, and depended on GH-529 behaviour that fails open. Replaced by copies — see Design §3. |
| Relabel the TFTP tree `public_content_t` | Would make the symlink work, but widens the tree to ftpd/rsync/samba for a feature needing none of them, and means editing GH-963's recent fix. |
| Also publish the two-line ESP `autoexec.ipxe` | Deferred, not rejected. The client-side tooling writes it today, and it is two lines. Worth revisiting if a "copy this directory to your ESP" kit is wanted later. |

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
- **The publish loop is verified against the real release assets**, not a mock:
  unpacking `fog-ipxe-v2.0.0-fog.6.tar.gz` and the Secure Boot asset over one
  tree and running the loop produces **55 `.efi`, 27MB**, with the shim,
  `mmx64.efi`, FOG's `ipxe.efi` and the arm64 variants all present, the
  subdirectory layout preserved, and zero non-`.efi` files copied.
- **The SELinux gap was found by reading, before any test run.** A symlink would
  have 403'd on every enforcing host, and the default test distro is
  `ubuntu:24.04` — which ships no SELinux and would have passed. See Design §3.

## Remaining risks

- **`sbsign` across 45 files** is expected to take seconds, and there is a `dots`
  line so it is not silent — but it has not been timed on real hardware. If it
  turns out slow, the progress line is already in place to build on.
- **The published directory adds ~27MB to the web root.** Bounded and known, but
  it is disk that previous releases did not use.
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

**Nothing sensitive can reach the published directory.** Only `*.efi` is copied,
so `default.ipxe`, `autoexec.ipxe`, the `MANIFEST` and the BIOS artifacts stay
out. `TFTP_FTP_PASSWORD` lives in `$webdirdest/lib/fog/config.class.php`, never
in the TFTP tree. Because the set is fixed rather than a view of a directory,
anything an admin later drops into `$tftpdirdst` is **not** published — which is
the main advantage the copy model has over the symlink it replaced.

**No traversal, no symlink semantics.** The published directory is a plain
directory of regular files. Nothing depends on `FollowSymLinks`, and the Apache
symlink risk — a shared-hosting problem where an untrusted tenant links to
another tenant's files — never enters into it.

**No write path.** Apache serves read-only; `in.tftpd -s` without `-c` already
was.

**HTTP is the recommended transport here**, not a downgrade — iPXE's own docs
favour it over TFTP, and UEFI HTTP Boot is a firmware standard. No CVE exists for
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
   `https://<server>/fog/service/secureboot/local-boot/ipxe.efi` and confirm it
   downloads and is a PE image; `curl` the directory itself and confirm the
   `index.php` stub 404s rather than returning a listing. Confirm
   `secureboot/snponly-shimx64.efi` is fetchable from there too, since the ESP
   chain starts with it.
3b. Repeat on an SELinux-enforcing distro (Alma/Rocky). Copies should need no
   policy change, but this is the case the symlink design failed, so it is worth
   proving rather than assuming.
4. Assemble an ESP on an already-enrolled machine — shim, snponly as `ipxe.efi`,
   the two-line `autoexec.ipxe`, the signed build as `localipxe.efi` — set the
   boot manager path to the shim, and confirm it reaches the FOG menu with Secure
   Boot on.
5. Confirm an un-enrolled machine still netboots to the menu and can still run
   *Enroll Secure Boot Key*. This is the regression that matters most.
