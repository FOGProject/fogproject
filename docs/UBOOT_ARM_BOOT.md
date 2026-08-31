# Booting ARM boards with U-Boot

FOG's boot menu speaks iPXE. Most ARM boards cannot run iPXE — the firmware
hands off to **U-Boot**, which has its own PXELINUX-derived config format. This
document covers the endpoint FOG serves to those boards, and the parts of the
chain that are yours rather than FOG's.

Background: forums topic 18229, and `fos` ADR-0015 (arm64 platform support) and
ADR-0017 (arm64 display / vc4), which between them made a Raspberry Pi able to
run FOS at all.

## What FOG serves

```
http://<fogserver>/fog/service/uboot/boot.php?mac=<the board's MAC>
```

It answers with an `extlinux.conf`-style document — plain text, one `label`,
computed live from the database. Fetch it with `curl` and read it; that is the
whole debugging story, and it is why FOG emits text rather than a `boot.scr`
(an mkimage-wrapped binary you cannot inspect without unwrapping it).

Three answers are possible:

| Situation | What comes back |
|---|---|
| The host has an imaging task queued | `kernel` / `initrd` / `append` for FOS |
| The host has no task, or FOG has never seen it | `localboot 0` |
| The MAC is on the imaging-ignore list | `localboot 0`, with the reason as a comment |

`localboot` is U-Boot's own directive for "I am not booting anything" — the
`pxe` command returns and your `bootcmd` falls through to whatever comes next,
normally the disk. There is no interactive menu: the boards this serves usually
have no keyboard and no screen.

The kernel and init are the ARM pair from FOG Settings —
`FOG_TFTP_PXE_KERNEL_ARM` (default `arm_Image`) and `FOG_PXE_BOOT_IMAGE_ARM`
(default `arm_init.cpio.gz`) — unless the host or its group overrides them.

## What you provide

For a board whose U-Boot has `CONFIG_CMD_WGET` (see below for the ones that
don't), FOG answers a question over HTTP; getting the board to ask it is
yours, and it is a few lines of U-Boot:

```
dhcp
setenv pxefile_addr_r 0x02000000
wget ${pxefile_addr_r} http://<fogserver>/fog/service/uboot/boot.php?mac=${ethaddr}
pxe boot ${pxefile_addr_r}
```

Put that in `bootcmd`, or in a `boot.scr` of your own, or type it at the U-Boot
prompt to try it once. Substitute the addresses your board actually uses.

`pxe boot` is the command that interprets a config **already in memory**, which
is what `wget` just put there. `sysboot` is the other one you will see in
extlinux documentation, and it is not this: it reads the config off a
filesystem on a block device. Picking `sysboot` here is the most likely reason
for "it fetched something and then did nothing".

`pxe boot` also expects `kernel_addr_r`, `ramdisk_addr_r` and `fdt_addr_r` to
be set. Most board configs set all three already; `printenv` is the check.

Older U-Boot builds have a `wget` that takes `<server-ip>:<path>` rather than a
full URL, and some have no `wget` at all (it needs `CONFIG_CMD_WGET`). Check
with `help wget` at the U-Boot prompt; `Unknown command 'wget'` means the
second path below.

### No `wget`: the TFTP fallback

A board with no `wget` can still speak TFTP -- every U-Boot can -- and `pxe
get` is the PXELINUX-derived convention for fetching a config that way. Unlike
`wget`, it is not configurable: it always fetches
`pxelinux.cfg/01-<mac, dash-separated, lowercase>` (falling back through a
sequence of IP-address-derived names FOG does not populate) from whatever TFTP
server and directory the board's `serverip`/`bootfile` point at.

```
dhcp
pxe get
pxe boot
```

That is the whole boot script; there is no URL to substitute, because the
filename is not yours to choose. Point `serverip` at the same host FOG's
`FOG_TFTP_HOST` setting names (Settings, FTP/TFTP submenu -- it is the same
host and credentials FOG already uses to upload ARM kernels there).

Getting FOG to keep a real file at that path is `UbootTftpSync`
(`packages/web/src/Boot/UbootTftpSync.php`), not something you maintain
yourself. See "The `pxelinux.cfg` file this endpoint's live answer avoids"
below for what it writes, when, and what has to be configured for it to work
at all.

Three things stay yours because they are properties of the board, not of FOG:

- **U-Boot itself.** Installing it, and whatever your board needs to reach it
  (on a Pi, a `config.txt` and the VideoCore firmware files on the SD card).
- **Load addresses.** `${loadaddr}` and friends differ per board.
- **The device tree.** FOG emits no `fdt` or `fdtdir` directive, so U-Boot
  boots with the tree already at `${fdtcontroladdr}`. On a Pi that is the tree
  the VideoCore firmware just built for the exact board revision it is running
  on — better than anything FOG could serve. If your board needs a different
  one, load it in your own boot script before `sysboot`.

## Things that will catch you out

- **`wget` needs `httpd` reachable, not TFTP.** U-Boot's `wget` is HTTP-only
  and has no TLS, so the URL is `http://`, not `https://`. FOG's installer
  already exempts `service/uboot/` from its HTTP-to-HTTPS redirect for exactly
  this reason -- but only when netboot is configured for HTTP. If your netboot
  transport is HTTPS, U-Boot cannot reach this endpoint at all, and that is the
  thing to solve first.

- **`${ethaddr}` must be set.** Some boards populate it from the firmware,
  some do not. If it is empty, FOG gets no MAC, finds no host, and correctly
  answers `localboot`. Check with `printenv ethaddr` before blaming FOG.
- **Memtest is x86.** Task type 4 uses `memdisk`, a 16-bit x86 real-mode
  loader, and memtest86+ is an x86 image. Neither has an aarch64 build. FOG
  answers a memtest task on this endpoint by saying so in a comment and
  booting locally, rather than emitting a chain that would fail in U-Boot with
  nothing on screen to explain it.
- **A quiet Pi is not necessarily a hung Pi.** Before ADR-0017 an arm64 FOS
  had no console on a Pi at all, so a healthy boot and an early hang looked
  identical. If you are on an older init, that is the first thing to rule out.

## The `pxelinux.cfg` file this endpoint's live answer avoids

`service/uboot/boot.php` computes its answer fresh on every HTTP request --
nothing is stored, so a task that gets canceled a second after it was queued
is simply never seen. That design does not survive contact with a board that
cannot make the HTTP request at all. `UbootTftpSync` exists for exactly that
gap, and only that gap: boards that speak `wget` never touch it.

It writes the same content `boot.php` would have answered with, as a real
file, to every MAC a host is registered under, named by the same `01-<mac>`
convention `pxe get` already expects. It does this at the moments that matter
-- a task is queued, completed, canceled, or fails -- so a board rebooting
right after a task is queued finds a file waiting rather than racing FOG's own
database write. A periodic reconcile pass (`Service/TaskScheduler.php`, same
cycle as the rest of scheduled-task housekeeping) re-derives the whole
directory from the database independently and corrects whatever the
per-event calls missed -- a crash between the save and the write, a host
deleted mid-task -- so staleness here is bounded to one reconcile cycle, never
permanent. It only ever touches files matching its own naming pattern; nothing
else in that directory is its business.

Two things this depends on that the direct-`wget` path does not:

- **`FOG_TFTP_HOST` / `FOG_TFTP_FTP_USERNAME` / `FOG_TFTP_FTP_PASSWORD` must be
  set** (Settings, FTP/TFTP submenu). This is the same SSH/SFTP connection
  FOG's kernel-upload feature already uses to reach a TFTP root that may not
  be the web server itself -- if kernel uploads to ARM boards already work,
  these are already configured.
- **Failures here are silent by design.** An unreachable TFTP host must never
  block queuing, canceling, or completing a task, so every failure is logged
  through FOG's fault log rather than surfaced to whoever triggered the task.
  If a wget-less board keeps getting `file not found` from `pxe get`, check
  the fault log before assuming the task itself did not queue.

## What is not proven

The emitted config is pinned byte-for-byte by
`tests/bootmenu-uboot-output.test.php`, and the decisions behind it are shared
with the iPXE path and pinned by `tests/bootmenu-ipxe-output.test.php`. The
TFTP-sync file naming and the SSH handshake it depends on are pinned by
`tests/uboot-tftp-sync.test.php`. What no test here can establish is that a
real U-Boot on a real board consumes either path -- that needs hardware, and
the TFTP fallback in particular has had no field validation at all yet: it was
built in response to forums topic 18229, whose board this document's `wget`
section already covers, so the board that actually needs `pxe get` has not
been confirmed. If you get it working, or it fails, say so on the forums so
this page can be corrected.
