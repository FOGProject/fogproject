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

FOG does not write files to disk for this and does not manage
`pxelinux.cfg/`. It answers a question; getting the board to ask it is yours,
and it is a few lines of U-Boot:

```
setenv fogurl http://<fogserver>/fog/service/uboot/boot.php?mac=${ethaddr}
dhcp
wget ${loadaddr} ${fogurl}
sysboot ${loadaddr} any ${filesize}
```

Put that in `bootcmd`, or in a `boot.scr` of your own, or type it at the U-Boot
prompt to try it once. Substitute the load address your board actually uses.

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
  and has no TLS, so the URL is `http://`, not `https://`. If FOG is
  HTTPS-only, that is the thing to solve first.
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

## What is not proven

The emitted config is pinned byte-for-byte by
`tests/bootmenu-uboot-output.test.php`, and the decisions behind it are shared
with the iPXE path and pinned by `tests/bootmenu-ipxe-output.test.php`. What
no test here can establish is that a real U-Boot on a real board consumes the
document — that needs hardware. If you get it working, or it fails, say so on
the forums so this page can be corrected.
