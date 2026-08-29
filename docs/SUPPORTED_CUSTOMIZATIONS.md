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
the web tree is rebuilt, and copied back afterward. That directory is
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
>**`--no-secure-boot` declines enrollment, not signatures.** It stops the server
>publishing `MOK.der` and the `PK`/`KEK`/`db` variable updates, and with them the
>*Enroll Secure Boot Key* PXE menu entry, which is gated on `MOK.der` existing.
>The signing key is still generated and the binaries are still signed.
>
>That is deliberate: an appended PE signature is inert on a machine booting with
>Secure Boot **off** — which is every machine on a server that passed this flag —
>so signing costs nothing. Leaving the binaries unsigned instead would only mean
>that the day you do enroll, or move one of these files onto a machine that
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
contents are its top level, and holds one folder per boot route —
`fog-ipxe\`, `secureboot-upstream\`, `secureboot-fog\`, `refind\`, plus
`-customca\` variants where this server rebuilt iPXE with its own CA.

**How to choose a folder, boot it, and enroll this server's certificate is
documented at [Local ESP boot](https://docs.fogproject.org/local-esp-boot)**, with
the capability matrix per Secure Boot state. This page covers only what the
installer does to these files, which is the part that concerns preservation.

>[!note]
>Where the `zip` package is missing the installer falls back to `.tar.gz`.
>`manifest.json` always names the file that was actually produced, so fetch the
>name it gives rather than assuming an extension.

**This is not a Secure Boot feature and does not need Secure Boot keys.** Local
ESP boot predates Secure Boot by years; Secure Boot only added the requirement
for a signature. The archives are published either way — a server with no key
publishes the same set unsigned, which is what every machine booting with Secure
Boot **off** needs anyway.


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
set that no longer exists, and named every file by bare basename;
`contents[].name` is the path **relative to the archive root**, so
`fog-ipxe/`, `secureboot-upstream/`, `secureboot-fog/` and `refind/` files are
named as such. Schema 2 carried a `root` key naming a wrapper directory inside
each archive — that wrapper is gone, so the key is gone with it, and every folder
was renamed to say what it holds. If you script against this, both changes affect
the paths you build; the absence of `root` is the signal that there is nothing to
strip.

One `role` value is worth knowing about if you consume this: a file named
`secureboot-fog/ipxe.efi` carries `role: "fog-ipxe-as-shim-stage"` and
`origin: "fog"`, not `upstream-loader`. It wears an upstream filename so that the
shim beside it will load it, but the bytes are FOG's build — matching on the
basename alone would misclassify it.

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
