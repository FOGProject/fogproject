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
| Primary hostname | Use `--hostname`; remembered in `.fogsettings` |
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

## The TFTP tree is reachable over HTTP

**Not a customization point — a property to know about before you put anything
there.**

Your TFTP directory (`/tftpboot`, or `/var/lib/tftpboot`, `/srv/tftp`,
`/var/tftpboot` depending on the distro) is published under the web root as a
symlink:

```
<webroot>/service/secureboot/signed-pxe-boot-files  ->  /tftpboot
```

That is how a machine with no PXE boot option in its firmware fetches a signed
iPXE binary for its own EFI System Partition — those machines cannot get a boot
file over the network any other way. The link is recreated on every install.

Directory listing is switched off for it (`Options -Indexes` on Apache,
`autoindex off` on nginx), so the contents are not browsable, but **any file in
that tree can be downloaded by anyone who can reach your web server**. Nothing
FOG puts there is sensitive — iPXE binaries, `default.ipxe`, `autoexec.ipxe`,
and upstream's signed Secure Boot binaries, all of which were already served
unauthenticated over TFTP. But the exposure applies to the whole directory, now
and in future.

**So do not use the TFTP directory as a scratch or staging area.** Anything you
drop in there — a backup, a key, a capture, a config you were mid-way through
editing — becomes web-reachable. HTTP reaches considerably further than TFTP
does in most networks. Put working files anywhere else.

If you would rather not publish the tree at all, delete the symlink after each
install; nothing but local-ESP boot depends on it. Bear in mind it comes back
on the next run.

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
