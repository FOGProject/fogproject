# Supported customizations

What FOG preserves for you across an install or update, what it deliberately
does not, and where to put things so they survive.

## How to read this document

**Automatic** means `installfog.sh` preserves it on every run with no action
from you. That includes a bare `./installfog.sh` upgrade — it is not limited
to updates driven through `bin/updatefog.sh`.

**Supported, but yours to place** means FOG will not overwrite it and provides
a defined place for it, but does not create or manage it.

**Not preserved** means exactly that. Those cases are listed at the end
rather than left for you to discover.

Everything preserved automatically is copied to
`/opt/fog/customizations/` (strictly, `$fogprogramdir/customizations`) before
the web tree is rebuilt, and copied back afterwards. That directory is
outside the web root, which is why it survives — the installer rebuilds
`/var/www/html/fog` wholesale on every run.

---

## iPXE boot menu background

**Automatic**, including when you have renamed the file.

`FOG_IPXE_BG_FILE` (Web UI → FOG Configuration → iPXE Menu Settings) names
the background image. FOG reads that setting's actual value, so a renamed
file is protected — not just the stock `bg.png`.

| Customization | How it is preserved | Where the copy lives |
|---|---|---|
| Replaced `bg.png` in place | Backed up before the web tree is rebuilt, restored after | `/opt/fog/customizations/ipxe-bg/bg.png` |
| Renamed background via `FOG_IPXE_BG_FILE` | Same, under whatever name the setting holds | `/opt/fog/customizations/ipxe-bg/<yourname>.png` |
| Legacy `refind.*` files | Backed up and restored if present | `/opt/fog/customizations/ipxe-legacy/` |

Place the image in `<webroot>/service/ipxe/` and set `FOG_IPXE_BG_FILE` to
its filename. A path is not accepted — only a filename.

> If FOG finds your background but cannot copy it to safety, the install
> **stops before** rebuilding the web tree, with your file still untouched.
> That is deliberate: aborting is recoverable, proceeding is not.

---

## Web server virtual host (Apache / nginx)

**Automatic for anything outside FOG's block. FOG owns what is between the
markers and will rewrite it every run.**

The generated vhost is wrapped in:

```
# === FOG MANAGED BLOCK -- DO NOT EDIT BETWEEN THESE LINES ... ===
   ... everything FOG generates ...
# === END FOG MANAGED BLOCK ===
```

Put your own directives **outside** those markers — above or below — and they
survive every update untouched. FOG refreshes only the inside, which is how
you keep getting its cipher, header and rewrite-rule fixes without losing
your own configuration.

| Customization | How it is preserved |
|---|---|
| Extra directives, headers, `location`/`Directory` blocks | Keep them outside the markers; never touched |
| Extra hostnames / DNS aliases | Use `--extra-server-name` (repeatable) so they land in both the vhost **and** the certificate SAN |
| Primary hostname | Use `--hostname`; remembered in [`.fogsettings`](https://docs.fogproject.org/install-fogsettings) |
| Custom certificate paths | Point the vhost's cert directives at your paths **outside** the block, or replace the files FOG already references |

On the first run after upgrading into this scheme, a vhost with no markers is
**appended to**, never overwritten — your existing file is left in place with
FOG's block added after it. Review it once and move anything you want FOG to
stop managing outside the markers.

`--no-vhost` (installfog.sh `-F`) still skips the vhost entirely. It is
rarely what you want now: it also skips FOG's own security fixes to the parts
it owns.

---

## Kernels and inits (`bzImage`, `init.xz`, …)

**Default-named files are replaced on purpose. Custom-named files are
preserved automatically. Previous versions are kept for you to roll back to.**

Picking up a newer kernel is the point of an update, so `bzImage`,
`bzImage32`, `arm_Image`, `init.xz`, `init_32.xz` and `arm_init.cpio.gz` are
always replaced with the release being installed.

| Customization | How it is preserved |
|---|---|
| Per-host custom kernel/init (a host's **Host Kernel** / **Host Init** fields) | Restored automatically — FOG never re-downloads a file it did not ship |
| Previously working kernel set | Kept as a numbered generation; restore with `bin/restorekernel.sh` |

```bash
./restorekernel.sh --list             # what is stored, and which release each came from
./restorekernel.sh --generation 1     # roll back to the set from before the last update
```

`gen-1` is the most recent snapshot, taken at the **start** of the last
install — so it holds what was running *before* it. Three generations are
kept by default; change that with `installfog.sh --kernel-backup-count N`.

Restoring re-signs the kernels if Secure Boot is configured, since a restored
kernel carries its old signature and the signing key may have rotated.

---

## Secure Boot certificates

**Automatic**, for both FOG-generated and admin-supplied keys.

FOG's own signing key is generated once at
`/opt/fog/pki/secureboot/MOK.{key,pem}` (or, once a Secure Boot intermediate
CA exists, `/opt/fog/pki/secureboot/leaf/sign.{key,pem}`) and **never
regenerated**, because a new key silently invalidates enrollment on every
machine that already trusted the old one.

| Customization | How it is preserved |
|---|---|
| FOG's generated signing key | Lives outside the web root; nothing in the installer deletes it |
| Your own key via `--secure-boot-key` / `--secure-boot-cert` | Copied to `/opt/fog/pki/secureboot/admin-MOK.{key,pem}` and used from there |
| Platform keys (PK/KEK) | Same; generated once, never regenerated |

Supplying your own pair does **not** overwrite FOG's generated one — they sit
side by side, so you can go back. Your original file is never modified; FOG
uses a copy. This matters if you keep the pair somewhere the installer
rebuilds, such as under the web root: without the copy it would be deleted
mid-install.

>[!note]
>**`--no-secure-boot` declines enrolment, not signatures.** It stops the server
>publishing `MOK.der` and the `PK`/`KEK`/`db` variable updates, and with them the
>*Enroll Secure Boot Key* PXE menu entry, which is gated on `MOK.der` existing.
>The signing key is still generated and the binaries are still signed.
>
>That is deliberate: an appended PE signature is inert on a machine booting with
>Secure Boot **off** — which is every machine on a server that passed this flag —
>so signing costs nothing. Leaving the binaries unsigned instead would only mean
>that the day you do enrol, or move one of these files onto a machine that
>already has Secure Boot on, the file is useless and nothing on the server can
>fix it short of a re-install.

---

## Local ESP boot files

**Not a customization point — generated archives, rebuilt on every run.**

Machines whose firmware has no PXE boot option, and machines you would rather not
reorder the boot menu on for every task, can be booted into FOG from an iPXE
binary on their own EFI System Partition. The installer publishes ready-to-copy
archives for that here, so you can fetch one over HTTP instead of hand-rolling a
symlink from the TFTP tree into your web root:

```
<webroot>/service/localboot/
  manifest.json               index of everything below
  fog-esp-x86_64.zip
  fog-esp-i386.zip
  fog-esp-arm64.zip
```

One archive per architecture, and nothing else. Each is packed flat, so its
contents are its top level — **copy them onto the ESP** (`\EFI\FOG\` is a good
place), **keeping the `local\`, `secureboot\` and `refind\` subdirectories as
they are**, and point the firmware boot manager at one of the entry points below.

>[!note]
>Where the `zip` package is missing the installer falls back to `.tar.gz`.
>`manifest.json` always names the file that was actually produced, so fetch the
>name it gives rather than assuming an extension.

**This is not a Secure Boot feature and does not need Secure Boot keys.** Local
ESP boot predates Secure Boot by years; Secure Boot only added the requirement
for a signature. The archives are published either way — a server with no key
publishes the same set unsigned, which is what every machine booting with Secure
Boot **off** needs anyway.

### Why `autoexec.ipxe` appears once per directory

This is the one thing about the layout worth understanding before you rearrange
it, because moving a binary away from its script stops it booting.

Since iPXE `v2.0.0-fog.8` none of the EFI binaries here has a boot script compiled
in — neither FOG's nor upstream's. They **read** one, from a file called
`autoexec.ipxe`, and iPXE looks for that name in the directory the running binary
was itself loaded from, falling back to the volume root. So the rule is simply:

| Copy | Read by |
| --- | --- |
| `local\autoexec.ipxe` | whichever `fog*.efi` you boot |
| `secureboot\autoexec.ipxe` | upstream's signed loader, which shim hands off to |
| `autoexec.ipxe` at the top | iPXE's volume-root fallback, if you unpacked the archive at the ESP root |

**All three are byte-identical**, generated from one function in the installer.
They carry FOG's boot logic: the DHCP walk across `net0`/`net1`/`net2`, proxyDHCP,
`next-server`, then FOG's menu.

That sameness is deliberate and it is a fix. There used to be two *different*
scripts — a chain ladder at the top and the boot logic in `local\` — and the
difference is what let the archive recurse into itself; see the warning under
[What is inside](#what-is-inside). Identical copies mean it does not matter which
one a machine read, which is also one fewer variable in every bug report.

`refind\` is separate for the same class of reason: rEFInd reads `refind.conf`
from its own directory, which is also where every rEFInd installation puts it.

### Changing how it boots, and the pre-DHCP delay

`autoexec.ipxe` is a text file on the ESP. Edit it and the next boot picks the
change up — no toolchain, no rebuild, nothing to re-download. Edit the copy in the
same directory as the binary you boot; that is the only one it reads. If you
switch between binaries in different directories, edit each.

The 10-second delay that used to need its own archive is two lines in it. Some
switches take several seconds to bring a port out of STP listening or out of
powersave, and iPXE's first DHCP attempt goes out before that. The lines ship
commented out:

```
#echo Sleeping 10 seconds to wait for STP/Powersave to switchoff and on
#sleep 10
```

Uncomment them on the ESP, or install the server with `--boot-delay <seconds>`
and they are written live, bracketed by `# FOG-BOOT-DELAY-BEGIN`/`-END` — the
same sentinels `--boot-delay` uses in the TFTP copy for netboot clients, so both
paths get the same delay from one option.

>[!note]
>There used to be six archives, three of them `-10sec`. They are gone: with the
>script on disk the delay is a line of text rather than a second set of binaries,
>and the EFI builds those archives were assembled from stopped being published
>(GH-1195). The `10secdelay/` directory in the TFTP tree still exists and still
>matters — legacy BIOS has no `autoexec.ipxe` at all, so its delay is still a
>separate build.

### What is inside

The archive is packed **flat** — its contents are its top level, with no wrapper
directory named after itself. Extracting it gives you one folder, whatever your
extractor calls it.

```
autoexec.ipxe             FOG's boot script — iPXE's volume-root fallback copy
README.txt                what it is, both enrolment routes, which file to boot
MANIFEST.json             every file here with its sha256 and what it is for
local/
  autoexec.ipxe           the same script, for the binaries beside it
  fogipxe.efi             FOG's build, all of iPXE's NIC drivers   ← start here
  fogsnp.efi              FOG's build, firmware SNP protocol
  fogintel.efi            FOG's build, Intel only
  fogrealtek.efi          FOG's build, Realtek only
  fogsnponly.efi          FOG's build, SNP bound to the load device
secureboot/
  autoexec.ipxe           the same script again, for the loader beside it
  snponly-shimx64.efi   ] upstream's Microsoft-signed shim and the loader it
  snponly.efi           ]  hands off to — point the boot manager at either shim
  ipxe-shimx64.efi      ]
  ipxe.efi              ]
  mmx64.efi               MokManager
  MOK.der               ] present when the server publishes enrolment material
  PK.auth KEK.auth      ]
  db.auth               ]
  fog-enroll-mok.sh     ] enrol from a booted Linux OS via mokutil
  fog-enroll-mok.desktop]
refind/
  refind.efi              rEFInd, signed by this server — for the refind_efi exit
  refind.conf             rEFInd's config, read from its own directory
```

arm64 substitutes `snponly-shimaa64.efi`, `ipxe-shimaa64.efi` and `mmaa64.efi`,
and `refind_aa64.efi`; i386 has no shim set at all and gets `refind_ia32.efi`.
x86_64 takes `refind.efi` in preference to `refind_x64.efi` where the server has
one, which is the same preference the PXE boot menu applies — so the ESP and the
netboot path agree on which binary is canonical.
The `.auth` blobs and `MOK.der` are absent on a server that publishes no
enrolment material (`--no-secureboot`, or a run where key generation failed).

**Every `autoexec.ipxe` in the archive is byte-identical.** iPXE reads its boot
script out of the directory the running binary was loaded from, so each directory
holding a bootable binary carries a copy; the root copy is iPXE's documented
volume-root fallback. Edit the one beside the binary you boot — or all of them, if
you switch between them.

**Nothing in the archive chains anything else.** Whichever binary you point the
boot manager at reads the script beside it and boots FOG directly. If it does not
work, point the boot manager at a different one; there is no fallback chain to
wait for, by design.

>[!warning]
>The archive root used to hold a five-deep `chain local/fog*.efi` ladder while
>`local/` held different boot logic, and that arrangement could hang the
>firmware. When iPXE chains an EFI image, the chained image resolves
>`autoexec.ipxe` through the synthetic filesystem handle `efi_image_exec()`
>installs, and that handle serves registered images **by flat name** — so the
>chained binary re-read the root ladder instead of its own sibling, chained
>itself, and recursed until the firmware ran out of pool memory. Separate
>directories do not isolate the scripts, because flat-name lookup ignores
>directories. Do not reintroduce a chain here.

**Two names are not yours to choose.** shim picks its second stage by rewriting
its own `-shim<arch>.efi` suffix to `.efi`, so `snponly-shimx64.efi` will load
`snponly.efi` and nothing else, and it must be upstream's copy — that is what
shim's embedded certificate vouches for. It resolves that name in its own
directory, which is why the whole upstream set travels together in `secureboot/`.
FOG's own builds kept the `fog` prefix when they moved into `local\` — the names
are in every bug report since the archives existed, and renaming them now would
buy nothing.

**`mmx64.efi` is not optional, and neither is `MOK.der`.** shim launches
MokManager from its own directory when it cannot verify the next stage, and that
is the only way to enrol your key — shim's `MokList` is a boot-services-only
variable, so nothing in a running OS can write it. MokManager then enrols by
browsing the ESP for a certificate, which is what `MOK.der` is. Both live in
`secureboot/` beside the shim that needs them. Without them, an ESP that has not
been enrolled yet is a dead end.

>[!note]
>**How far upstream's loader gets on its own is unsettled.** This page used to
>state flatly that `ipxe.efi` here, though built with iPXE's own NIC drivers,
>does not load them off an ESP and works only as a chain stage. Measured since,
>on a VM: upstream's `snponly.efi` booted off an ESP brings up `net0` and
>netboots FOG unaided, with the whole `local/` directory deleted. Both can be
>true — the VM's firmware provides SNP, whereas firmware with no PXE boot option
>at all typically provides none, and there upstream's loader would have nothing
>to bind. That case is **untested**; no physical machine has run any of this.
>Ship and try both, which is why FOG's own builds are still here.

### Booting it

**Secure Boot off** — point the boot manager straight at `local\fogipxe.efi`.

**Secure Boot on, via shim** — point the boot manager at
`secureboot\snponly-shimx64.efi` (or `secureboot\ipxe-shimx64.efi`). The first
time on a machine, shim cannot verify FOG's binary yet, so it launches
MokManager: choose *Enroll key from disk* and select `MOK.der` from that same
`secureboot\` folder. Reboot, and it boots from then on.

**Secure Boot on, via firmware Setup Mode** — put the machine into Setup Mode in
its firmware and enrol `secureboot\PK.auth`, `secureboot\KEK.auth` and
`secureboot\db.auth`. Firmware then verifies FOG's signed binaries directly, so
you can point the boot manager at `local\fogipxe.efi` with no shim and no
MokManager at all — one image loaded and verified instead of three, which is the
shortest route this archive offers.

**Back out to the locally installed OS** — the default exit type
(`FOG_EFI_BOOT_EXIT_TYPE`, `sanboot`) hands straight back to firmware and needs
nothing on the ESP. `refind\refind.efi` is here for the `refind_efi` exit type,
which is still selectable globally and per host, and because an ESP assembled by
hand should carry every route off FOG rather than only the one this server is
configured for today. You can also point the firmware boot manager straight at
it.

>[!important]
>**The Setup Mode route is the only Secure Boot path i386 has**, and it does
>work. Upstream signs no shim for ia32, so the `i386` archives contain no shim,
>loader or MokManager — but they do contain the `.auth` blobs, and a signed
>`fogipxe.efi` verified directly against `db` needs none of that machinery.

### If `fogipxe.efi` does not bring up your network

Point the boot manager at a different binary. Try `local\fogsnp.efi`, then
`local\fogintel.efi` or `local\fogrealtek.efi`, then `local\fogsnponly.efi`.

`fogsnponly.efi` is last on purpose: it binds only the device iPXE was loaded
from, and booted off an ESP that device is the disk, so it usually finds no NIC.
It is included because it is the right binary when something chainloads it over
the network, and there is hardware where it works — but it is the least likely
of the five to be the answer here.

>[!important]
>**Nothing tries them for you.** There is no fallback chain in the archive: each
>binary reads the script beside it and boots, and if it comes up with no NIC it
>stops at its own prompt. Selecting the next one is a boot-manager action.
>
>That is not a missing feature. A chain ladder shipped here once and could
>recurse into itself and hang the firmware — see the warning further up.

>[!note]
>No prescribed order beyond the list above, because it genuinely varies. Two
>machines tested during this work disagreed about which build drove their NIC,
>one reporting SNP and the other NII. Start with `fogipxe.efi` on firmware that
>has no PXE boot option — such firmware usually provides no SNP either, so a
>binary needing one is no use to it — and work down otherwise.

>[!note]
>`secureboot\autoexec.ipxe` is present only where an upstream loader was staged:
>a script beside no binary serves nobody. The root and `local\` copies are always
>there.

### `manifest.json`

A static file written at install time, so you can script against it without
guessing filenames:

```json
{
  "schema": 3,
  "generated": "2026-08-19T14:02:11Z",
  "fogVersion": "1.6.0-beta.123",
  "ipxeVersion": "v2.0.0-fog.8",
  "archives": [
    { "path": "fog-esp-x86_64.zip", "arch": "x86_64",
      "size": 6812345, "sha256": "…",
      "contents": [ { "name": "local/fogipxe.efi", "size": 1012345, "sha256": "…",
                      "role": "fog-ipxe", "origin": "fog", "fogSigned": true,
                      "note": "FOG's build with all of iPXE's own NIC drivers…" } ] }
  ],
  "kernels": [
    { "name": "bzImage", "path": "../ipxe/bzImage", "arch": "x86_64",
      "kind": "kernel", "size": 12345678, "sha256": "…" }
  ]
}
```

`schema` is `3`. Schema 1 had a `variant` field on each archive, for the `-10sec`
set that no longer exists, and named every file by bare basename; `contents[].name`
is the path **relative to the archive root**, so `local/`, `secureboot/` and
`refind/` files are named as such. Schema 2 carried a `root` key naming the
wrapper directory inside each archive — that wrapper is gone, so the key is gone
with it, and the upstream Secure Boot set moved from the archive root into
`secureboot/`. If you script against this, both changes affect the paths you
build; the absence of `root` is the signal that there is nothing to strip.

Paths are relative to the manifest's own URL, so it resolves under whatever
hostname and webroot you reached it by. Every `sha256` is of the bytes as
published — including after signing — so it is a real integrity check.

`fogSigned` says whether **FOG's** signature is on the file. It is `false` for
the upstream shim and loaders, which carry Microsoft's and iPXE's signatures
instead; that is correct, not a gap.

`kernels` lists the FOS kernel and initrd set that is **already published** under
`service/ipxe/`. Nothing is copied — the archives do not contain a kernel. They
are listed so this manifest is a single index of everything fetchable for a local
boot. A kernel and initrd on an ESP would not boot FOG on their own in any case:
FOS reads per-host, per-task arguments that `boot.php` generates.

### Notes

Directory listing is off; fetch files by name, or read `manifest.json`.

**Everything here is an archive.** No individual `.efi` has its own URL, which
means these cannot be used as a UEFI HTTP Boot target or an iPXE `chain`
destination. If you need that, unpack an archive and serve the file yourself.

**The directory is deleted and rebuilt on every install**, so nothing you put in
it survives. It is a publication of files from the TFTP tree, not a place to keep
things — edit the originals under your TFTP directory instead, and they will be
re-signed and republished on the next run.

Nothing here is secret. These are the same binaries TFTP already serves
unauthenticated, upstream's signed shim and loader (downloadable from fog-ipxe's
release assets anyway), and certificates plus signatures over them — FOG already
serves the signed FOS kernel over HTTP from `service/ipxe/`. The private keys
never leave the PKI zone directory. If you do not want the archives published,
delete the directory after an install; only local-ESP boot depends on it, and it
comes back on the next run.

---

## Custom iPXE binaries in the TFTP tree

**Automatic**, since 1.6.

FOG ships around 45 binaries into your TFTP root (`/tftpboot` on most
distributions): `snponly.efi`, `ipxe.efi`, `undionly.kkpxe`, the `i386-efi/`
and `arm64-efi/` variants, and `10secdelay/`, which holds BIOS builds only. The
`autoexec/` tree is retired — every EFI binary in the root reads `autoexec.ipxe`
now, so the duplicate set served no purpose, and the installer removes it.

If you replace one of those with your own build, **it is no longer overwritten
on the next install or update.** FOG records the checksum of every file it
writes, in `.fog-ipxe-manifest` at the root of the tree, and skips any file
whose contents no longer match what FOG last put there. Files it skips are
listed by name at the end of the run.

>[!note]
>Protection starts from the **first run after upgrading to this version**.
>Before that there is no manifest, so a binary you replaced earlier is
>overwritten once, and protected from then on. Keep a copy elsewhere if that
>matters to you.

To go back to FOG's version, delete your file — the next run reinstalls it.

A file under a name FOG does not ship — `custom.ipxe`, your own
`myloader.efi` — has always been safe and still is. Nothing removes files from
the TFTP root.

| Customization | What happens |
|---|---|
| Replaced one of FOG's binaries | Kept; named in the run's output |
| Added a file under a new name | Kept; FOG never touches it |
| Deleted your replacement | FOG's version is reinstalled next run |

### `stock/` — the binaries FOG published

When you use `--rebuild-ipxe-with-my-ca`, FOG compiles iPXE with your CA
embedded and those builds take the normal top-level names, so your DHCP
configuration needs no change. The binaries FOG *downloaded* are kept
alongside, in `stock/`, so you can compare against them or fall back to one
without re-running the installer.

`secureboot/` is deliberately **not** copied there: those are Microsoft's and
iPXE's signed shim and loader, and a second copy outside the signing sweep's
exclusion would get a FOG signature added to it.

### Signing

Every `.efi` under the TFTP root that does not already carry this server's
signature is signed for Secure Boot, including binaries you built yourself.
`sbsign` *appends*, so your own signature survives and the binary gains one
this server's MOK also vouches for — which is what lets a custom build boot on
a machine enrolled against FOG.

`secureboot/` is excluded, always. Those two stages are what the whole chain
hangs off and are already signed by their vendors.

---

## What is NOT automatically preserved

Listed plainly so none of it is a surprise.

- **Edits inside the FOG-managed vhost block.** They are overwritten on the
  next run. Move them outside the markers.
- **Direct edits to `default.ipxe`.** Regenerated every run; there is no
  supported hook point for pre-boot customization yet. This is the one file in
  the TFTP root the manifest above does not protect, because FOG has to rewrite
  it to keep the netboot URL correct.
- **A kernel-signing key rotated after a generation was captured.** Restoring
  that generation re-signs with the *current* key, which is correct, but any
  client enrolled against the old key still needs re-enrollment.
- **More than `--kernel-backup-count` generations back.** The oldest is
  evicted on each run; the default keeps three.
- **`php.ini` / MariaDB config beyond FOG's own lines.** FOG patches only the
  specific directives it manages and leaves the rest of those files alone, so
  your edits generally survive — but they are not backed up, and are not
  restored if something else removes them.
- **Anything under the web root that FOG does not ship.** The tree is rebuilt
  wholesale on every run. Only the categories above are copied to safety
  first; a snapshot of the previous tree is left at
  `<backuppath>/fog_web_<version>.BACKUP` — `/home/` by default, settable
  with `installfog.sh -B` — for manual recovery.
