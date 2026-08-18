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
| Primary hostname | Use `--hostname`; remembered in [`.fogsettings`](FOGSETTINGS.md) |
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

---

## Local ESP boot files

**Not a customization point — a generated directory, rebuilt on every run.**

Machines whose firmware has no PXE boot option, and machines you would rather
not have to reorder the boot menu on for every task, can be booted into FOG from
an iPXE binary on their own EFI System Partition. The installer publishes the
binaries for that here, so you can fetch one over HTTP instead of hand-rolling a
symlink from the TFTP tree into your web root:

```
<webroot>/service/localboot/
```

**This is not a Secure Boot feature and does not need Secure Boot keys.** Local
ESP boot predates Secure Boot by years; Secure Boot only added the requirement
for a signature. The directory is published either way:

- **No Secure Boot keys on the server** — the binaries are published unsigned.
  They work on any machine booting with Secure Boot **off**, which is what local
  ESP boot has always needed. Nothing else changes.
- **Secure Boot keys present** — the installer signs FOG's own iPXE builds with
  your key first, so the same binaries also work on a machine booting with
  Secure Boot **on**, provided your MOK is already enrolled on it (see
  `service/secureboot/` and the *Enroll Secure Boot Key* menu entry).

### What is published

A curated set, not the whole TFTP tree — 25 files, about 12MB. Per architecture
(x86_64 at the top level, plus `i386-efi/` and `arm64-efi/`):

| File | Use |
| --- | --- |
| `ipxe.efi` | **Start here.** Carries iPXE's own NIC drivers, all of them. |
| `snp.efi` | Uses the firmware's UEFI SNP protocol instead. For hardware iPXE's own drivers do not cover, where the firmware does provide SNP. |
| `intel.efi`, `realtek.efi` | Single-vendor builds, for when the all-drivers build misbehaves on that specific NIC. |
| `10secdelay/ipxe.efi` | `ipxe.efi` plus a 10-second pause before DHCP, which is what lets a link come up on a switch running STP or port power-save. |

Plus `secureboot/`, which is upstream's Microsoft-signed shim, the loaders it
chains to, and MokManager — the first stages of a Secure Boot chain. These are
never re-signed by FOG. They are absent on an HTTPS-only install, which does not
stage them.

>[!warning]
>`secureboot/ipxe.efi` is not a shortcut. It is upstream's signed build and it
>does carry iPXE's own NIC drivers, so it looks like the one file you need — but
>booted locally off an ESP it does not load them. Use it only as a chain stage
>that hands off to one of FOG's binaries above, never as the binary that has to
>bring up the network.

`snponly.efi` is deliberately **not** published even though TFTP serves it. It
binds only the device iPXE was loaded from, and booted off an ESP that device is
the disk — so it never finds a NIC. It is the right binary for netboot and the
wrong one here. The EMBED-less `autoexec/` builds are left out for a similar
reason: they carry no boot script and fetch `autoexec.ipxe` from wherever they
were loaded, which this directory does not provide. Both remain available over
TFTP if you are assembling something deliberately.

### `esp/` — the ready-to-copy kit

Everything above is a menu. `esp/` is the opposite: **one folder you copy onto an
EFI System Partition verbatim**, with the files already carrying the names the
Secure Boot chain requires.

```
esp/snponly-shimx64.efi   point your boot manager at EITHER shim
esp/snponly.efi             …this one loads snponly.efi
esp/ipxe-shimx64.efi      point your boot manager at EITHER shim
esp/ipxe.efi                …this one loads ipxe.efi
esp/mmx64.efi             MokManager — how you enrol the key
esp/autoexec.ipxe         chains the standard set, with fallbacks
esp/autoexec-10sec.ipxe   chains the 10-second-delay set instead
esp/fogipxe.efi           FOG's build, all drivers      ← the one that boots
esp/fogsnp.efi            FOG's build, firmware SNP
esp/fogintel.efi          FOG's build, Intel only
esp/fogrealtek.efi        FOG's build, Realtek only
esp/fogipxe10sec.efi      the same four, each waiting 10s
esp/fogsnp10sec.efi         before DHCP
esp/fogintel10sec.efi
esp/fogrealtek10sec.efi
esp/arm64-efi/            the same set, aa64 shims
```

The chain is: shim → its loader → `autoexec.ipxe` → one of the `fog*.efi`. shim
establishes MOK trust, so the FOG binary — signed with your Secure Boot key —
loads; and because it carries FOG's boot script compiled in, it finds the server
itself.

**Both shims are published; pick whichever your firmware gets along with.**
`snponly-shimx64.efi` loads `snponly.efi`, `ipxe-shimx64.efi` loads `ipxe.efi`.
Neither loader needs to drive a NIC — each only reads `autoexec.ipxe` out of the
same folder and chains onward — so the choice is about which shim your firmware
accepts, not about network hardware. One `autoexec.ipxe` serves both.

**`mmx64.efi` is not optional.** shim launches MokManager from its own directory
when it cannot verify the next stage, and that is the only way to enrol your key
— shim's `MokList` is a boot-services-only variable, so nothing in a running OS
can write it. Without MokManager beside the shim, an ESP that has not been
enrolled yet is a dead end with no route out.

**Two sets of binaries, two scripts.** The `10sec` binaries are identical except
that each waits 10 seconds before DHCP, which is what lets a link come up on a
switch running STP or port power-save. iPXE runs exactly the file called
`autoexec.ipxe` and has no way to ask you which set you want, so choosing means
swapping the file: rename `autoexec-10sec.ipxe` over `autoexec.ipxe`.

>[!note]
>The delay has to live in the binary, not the script — `sleep` is an optional
>iPXE command that FOG's own builds enable but upstream's signed loader may not,
>and the loader is what runs `autoexec.ipxe`. Booting a `fog*.efi` directly from
>your boot manager reads no script at all, so there the embedded delay is the
>only route to one.

**Two names are not yours to choose.** shim picks its second stage by rewriting
its own `-shim<arch>.efi` suffix to `.efi`, so `snponly-shimx64.efi` will load
`snponly.efi` and nothing else, and it must be upstream's copy — that is what
shim's embedded certificate vouches for. This is why FOG's own builds are here
under `fog` names instead of their natural ones: putting FOG's `ipxe.efi` next
to `ipxe-shimx64.efi` would have shim try to load an image it cannot verify.

>[!important]
>`autoexec.ipxe`'s fallbacks tell you **which files you copied**, not which
>driver works. They fire only when a binary is missing or fails verification.
>Once one loads and runs, control never comes back — a variant that starts but
>finds no NIC stops at its own prompt rather than falling through to the next.
>If `fogipxe.efi` doesn't drive your NIC, replace it; the script won't do it
>for you.

x86_64 and arm64 only — those are the two architectures upstream signs a shim
for. An i386 machine with Secure Boot off needs none of this; take
`i386-efi/ipxe.efi` from the list above and point the boot manager straight at it.

### Notes

Directory listing is off in every subdirectory; fetch files by name.

**The directory is deleted and rebuilt on every install**, so nothing you put in
it survives. It is a publication of files from the TFTP tree, not a place to keep
things — edit the originals under your TFTP directory instead, and they will be
re-signed and republished on the next run.

Nothing here is secret. These are the same binaries TFTP already serves
unauthenticated, and FOG already serves the signed FOS kernel over HTTP from
`service/ipxe/`. If you do not want them published, delete the directory after
an install; only local-ESP boot depends on it, and it comes back on the next run.

---

## What is NOT automatically preserved

Listed plainly so none of it is a surprise.

- **Edits inside the FOG-managed vhost block.** They are overwritten on the
  next run. Move them outside the markers.
- **Direct edits to `default.ipxe`.** Regenerated every run; there is no
  supported hook point for pre-boot customization yet.
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
