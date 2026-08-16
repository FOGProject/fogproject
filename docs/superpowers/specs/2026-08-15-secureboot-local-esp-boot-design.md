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
`_resignRefind()` already uses, and expose the tree over HTTP through one
non-browsable symlink beside `MOK.der`.**

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

### 2. HTTP exposure

`Add-ipxeEfi`-style client tooling needs these binaries over HTTP; `/tftpboot` is
not web-served. Today that gap is filled by hand-made symlinks in
`/var/www/html`, which every admin has to recreate.

In `_publishSecureBootKit()` (7431), beside `MOK.der`:

```
ln -sfn "$tftpdirdst" "${kitdir}/signed-pxe-boot-files"
```

One link rather than per-file copies: it cannot drift, it costs nothing, and the
whole variant matrix is reachable through it.

Ordering is already correct — `_publishSecureBootKit` runs from `downloadfiles()`
(6927), and it already assumes `$tftpdirdst` is populated, since it copies
`mmx64.efi` out of `${tftpdirdst}/secureboot`.

`chown -R` on the kit dir is safe: GNU `chown -R` defaults to `-P` and does not
traverse symlinks, so it changes the link and not `/tftpboot`.

### 3. The link must not be browsable

`Options +FollowSymLinks` is stated explicitly on `$webdirdest`
(`functions.sh:6153-6156`) and the comment there records that FOG already relies
on symlinks in the web tree. Per the GH-529 note directly below it, Apache
matches `<Directory>` on the **unresolved** path, so a link under
`service/secureboot/` stays inside that block and is followed.

But `+FollowSymLinks` merges with inherited options, and the same comment records
that distro stock config grants `Options Indexes FollowSymLinks` on
`/var/www/html`. Left alone, the link would expose a browsable index of
`/tftpboot`. Suppress it explicitly, so the behaviour is deterministic rather
than dependent on the distro's base config.

**Apache** — emit alongside the existing `<Directory $webdirdest>` blocks. Note
there are three vhost-writing sites (6153, 6190, 6260); all three need it:

```apache
<Directory ${webdirdest}/service/secureboot/signed-pxe-boot-files>
    Options -Indexes +FollowSymLinks
</Directory>
```

**nginx** — FOG generates nginx config too (5778-5910), with
`location ^~ ${webroot}service/ipxe/` (5895) as the precedent:

```nginx
location ^~ ${webroot}service/secureboot/signed-pxe-boot-files/ {
    autoindex off;
}
```

`autoindex` is off by default in nginx, so this is belt-and-braces — state it
anyway so neither server depends on a default that a hardened or customised base
config might have changed.

## Rejected alternatives

| Alternative | Why not |
| --- | --- |
| Always build and embed the CA | 10-25 min per install, no warm path, aarch64 cross-compiler as a hard prereq on every distro. Undoes the release-asset speedup for a benefit the release asset already provides. |
| A `--build-ipxe` flag for the force-build case | The only gap it serves is a site serving netboot over HTTP that wants its ESP file to reach a private-CA HTTPS server. The better answer there is an FQDN and a public cert. Revisit if it turns up in practice. |
| A dedicated `ipxescript-localboot` embed that skips `next-server` | Less flexible, not more — breaks multi-FOG-server deployments, and proxy DHCP already covers the failsafe case. Would also force a fog-ipxe change and `$ipxeVer` coordination. |
| Sign only a hand-picked local-boot subset | The subset has to be guessed up front, and the variants exist precisely because hardware needs differ. |
| Gate signing behind a flag | Signing was never the expensive part. A flag means a Secure Boot site that wants local ESP boot has to know it exists. |
| Publish signed copies into `service/secureboot/` | Duplicates the whole matrix and lets the copies drift from `/tftpboot`. |
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
- **The three Apache blocks were not refactored into a helper.** They were
  already three byte-identical copies; matching that pattern keeps the diff
  surgical and reviewable rather than mixing a refactor into a feature. The
  helper is still worth extracting — six copies is past the point where it pays
  — but as its own change.
- **The `find` prune expression is verified**, against a mock tree of the real
  shape: 45 `.efi` matched (5 names × 3 arches × 3 embed variants), zero from
  `secureboot/`, zero non-PE artifacts (`.kpxe`/`.lkrn`/`.usb`/`.iso`/`.ipxe`),
  and correct with a trailing slash on `$tftpdirdst`.
- **`ln -sfn` is verified idempotent** — two runs leave one link in the kit dir
  and nothing nested inside `$tftpdirdst`. Without `-n`, the second run would
  have created the link *inside* the directory the first one points at.

## Remaining risks

- **The index suppression depends on Apache matching `<Directory>` on the
  unresolved path.** That is what the GH-529 comment at `functions.sh:6158-6163`
  records, and the block is written on that basis — but it is load-bearing here,
  because if Apache resolved the link instead, the block would not match and the
  inherited `Indexes` would leak a `/tftpboot` listing. Verification step 3 is
  the check that matters. Untestable without a running Apache.
- **`sbsign` across 45 files** is expected to take seconds, and there is a `dots`
  line so it is not silent — but it has not been timed on real hardware. If it
  turns out slow, the progress line is already in place to build on.
- **Exposing `/tftpboot` over HTTP is new surface**, though not new exposure: the
  same tree is already served by TFTP with no authentication. Worth a reviewer's
  eye anyway, since HTTP reaches further than TFTP typically does.

## Verification

1. On a test server with Secure Boot keys present, run the installer and confirm
   `sbverify --cert <anchor> /tftpboot/ipxe.efi` succeeds, and that
   `/tftpboot/secureboot/snponly-shimx64.efi` is **untouched** — still carrying
   only upstream's signature.
2. Re-run the installer; confirm nothing is re-signed (the `sbverify` guard hits)
   and no second signature is stacked.
3. `curl` a binary through `https://<server>/fog/service/secureboot/signed-pxe-boot-files/ipxe.efi`
   and confirm it downloads; `curl` the directory itself and confirm it does
   **not** return an index.
4. Assemble an ESP on an already-enrolled machine — shim, snponly as `ipxe.efi`,
   the two-line `autoexec.ipxe`, the signed build as `localipxe.efi` — set the
   boot manager path to the shim, and confirm it reaches the FOG menu with Secure
   Boot on.
5. Confirm an un-enrolled machine still netboots to the menu and can still run
   *Enroll Secure Boot Key*. This is the regression that matters most.
