# Transport modernisation spike: boot path and image path

**Status:** investigation spike — no implementation proposed yet
**Date:** 2026-08-16
**Prompted by:** [#1043](https://github.com/FOGProject/fogproject/pull/1043)

---

## Why this exists

PR #1043 (Secure Boot signing for local ESP boot) asserted in passing that HTTP is the preferred
transport over TFTP for PXE artifacts. That raised two questions worth answering properly:

1. How much of FOG's boot chain could move to HTTP or HTTPS, and what would that cost admins?
2. Once transports are on the table at all — is the image data path, NFSv3 since forever, leaving
   performance or security on the table?

**These are independent workstreams.** They share only the word "transport". They would ship as
separate PRs, on separate schedules, and neither depends on the other. They are in one document
because they were investigated together, not because they belong together.

Nothing here is a proposal to change code. Every finding below is either verified against the
tree or explicitly flagged as an open question.

### One correction to #1043

The PR says "iPXE's docs favour it over TFTP." ipxe.org does not state that as a documented
recommendation — that was checked. The substance holds (HTTP is faster for bulk transfer and is
a large part of why iPXE exists), but the appeal to authority was thinner than written and
should not be repeated.

---

# Part A — Boot transport

## FOG is already almost entirely HTTP

This is the first thing to understand, because it reframes the whole question.

| Hop | Transport |
|---|---|
| 1. DHCP → `next-server` + `filename` | TFTP |
| 2. Firmware fetches `snponly.efi` / `undionly.kkpxe` | TFTP |
| 3. iPXE embedded script → `default.ipxe` | TFTP |
| 4. `default.ipxe` → `boot.php` | **HTTP/HTTPS already** |
| 5. Boot menu → `bzImage`, `init.xz` | **HTTP/HTTPS already** |

Hop 4 is `lib/common/functions.sh:824` (`configureDefaultiPXEfile`), which writes
`chain ${httpproto}://$ipaddress${webroot}service/ipxe/boot.php##params`. Hop 5 is
`packages/web/lib/fog/bootmenu.class.php:392` (`$_booturl`). Both already honour
`--force-https` (`bin/installfog.sh:173`). Hop 3 lives in the iPXE scripts —
`fog-ipxe/src/ipxescript:32`, `src-efi/ipxescript:32`, the two `10sec` variants, and
`autoexec.ipxe:54` — all ending in `chain tftp://${next-server}/default.ipxe`.

**The bulk payload already moves over HTTP.** Hops 1–3 carry roughly 1 MB of network bootstrap
program plus a 700-byte script. There is **no throughput argument** for moving them, and any
proposal that claims one will be measured and found wrong.

The real arguments are different, and they are good ones:

- Firmware that has HTTP Boot but no working PXE ROM — the same class of machine #1043's local
  ESP boot exists for.
- Removing a UDP service from the boot path.
- Reaching across routed and firewalled networks where UDP/69 does not go.

## Does this change boot-order options? Yes

UEFI HTTP Boot is a **separate boot-manager entry**, not a mode of the PXE entry. Vendors gate it
behind its own Network Stack toggle — on Lenovo, `Network → Network Stack Setting → IPv4 HTTP
Support` is what reveals the `HTTP Boot Configuration` page. A machine can therefore carry both a
PXE entry and an HTTP Boot entry, ordered independently.

More useful: **Dell and Lenovo both support a static boot URI configured in firmware.** Dell
calls it Manual mode; a URI entered there *overrides* whatever DHCP supplies, and leaving it
blank falls back to DHCP.

That matters more than it first appears. It means **HTTP Boot can be piloted with zero DHCP
change** — configure the URI on the handful of machines that need it, leave the TFTP scope
completely untouched, and the two coexist with no policies, no classes, no option-60 conflict.
For the machines this capability is actually aimed at (no working PXE), that may be the whole
answer.

## DHCP options, side by side

| | Legacy BIOS PXE | UEFI PXE (TFTP) | **UEFI HTTP Boot** |
|---|---|---|---|
| Opt 93 client arch | `0` | `6`, `7`, `8`, `9` (x86/x64), `11` (arm64) | `15` (x86), **`16` (x64)**, `18`/`19` (arm) |
| Opt 60 *sent by client* | `PXEClient:Arch:00000:…` | `PXEClient:Arch:00007:…` | `HTTPClient:Arch:00016:…` |
| Opt 60 *in server reply* | not needed¹ | not needed¹ | **`HTTPClient` — required** |
| Opt 66 next-server | FOG server IP | FOG server IP | **unused** |
| Opt 67 bootfile | `undionly.kkpxe` | `snponly.efi` | **full URL** — `http://fog.example.org/…/snponly.efi` |
| Wire transport | TFTP, UDP/69 | TFTP, UDP/69 | HTTP, TCP/80 |
| FOG today | ✅ emitted | ✅ emitted | ✗ not offered |

¹ Only needed as `PXEClient` when the DHCP server is colocated with WDS.

### What FOG emits today

From `_keaBaseClasses()` (`lib/common/functions.sh:5395`), mirrored in the ISC branch at
`:5698`. Every one of these is `PXEClient` plus a bare TFTP filename:

| Kea class | Arch test | Boot file |
|---|---|---|
| `FOG-Legacy-BIOS` | `00000` | `undionly.kkpxe` |
| `FOG-UEFI-32-1` / `-32-2` | `00006` / `00002` | `i386-efi/snponly.efi` |
| `FOG-UEFI-64-1` / `-2` / `-3` | `00007` / `00008` / `00009` | `snponly.efi` |
| `FOG-UEFI-ARM64` | `00011` | `arm64-efi/snponly.efi` |
| `FOG-Surface-Pro-4` | `00007:UNDI:003016` | `snponly.efi` |
| `FOG-UEFI-64-SecureBoot` | `00007` | `secureboot/snponly-shimx64.efi` — **commented out** |

**Most installs are rows 1 and 3**: BIOS `undionly.kkpxe` and x64 UEFI `snponly.efi`, both TFTP,
both keyed on `PXEClient`. An HTTP Boot addition is a **parallel set** keyed on `HTTPClient` with
a URL-valued option 67. It displaces nothing.

### Three traps

- **Arch 7 vs 9.** RFC 4578 calls `7` "EFI BC" and `9` "EFI x86-64", but essentially all 64-bit
  UEFI firmware reports `7`. dnsmasq's *names* for 7 and 9 are reversed relative to the RFC —
  already documented at `fog-docs` `proxy-dhcp.md:139-146`. Prefer bare numbers.
- **Option 60 is the coexistence blocker, not architecture.** One scope cannot answer both
  `PXEClient` and `HTTPClient`. That, and only that, is what forces classes when both must
  coexist on one scope.
- **Windows has no option 60** in its standard option list unless WDS is installed. Define it
  once at server level (`netsh dhcp server \\srv add optiondef 60 …` or
  `Add-DhcpServerv4OptionDefinition`) before anything else.

## What adopting this costs an admin

| Situation | Cost |
|---|---|
| Static URI in firmware | **zero DHCP change** |
| All clients HTTP-Boot capable | 2 scope options (+ the one-time option 60 definition on Windows) |
| Already mixed BIOS/UEFI with policies | +1 policy, and the simplest one in the set |
| Single-option today, want HTTP *and* TFTP | 0 → policies. The only real jump. |

The HTTP rule is the *cheapest* to write, because the vendor class starts with `HTTPClient`
rather than `PXEClient` — one wildcard condition catches every architecture at once, with no
`Arch:0000N` enumeration. That is the opposite of the BIOS/UEFI split, which needs a policy per
architecture (ten screenshot-heavy steps in `bios-and-uefi-co-existence.md:253-336`).

### proxyDHCP works — and here is the trick

This was the gating unknown, since proxyDHCP is a large share of FOG installs. It is answered:
**dnsmasq can serve UEFI HTTP Boot in proxyDHCP mode.** Working configuration from the
[dnsmasq-discuss thread](https://www.mail-archive.com/dnsmasq-discuss@lists.thekelleys.org.uk/msg16282.html):

```
dhcp-range=192.168.1.200,proxy
dhcp-pxe-vendor=PXEClient,HTTPClient:Arch:00016
dhcp-vendorclass=set:efihttp,HTTPClient:Arch:00016
pxe-service=tag:efihttp,x86-64_EFI,"Network Boot",<url>
dhcp-boot=tag:efihttp,<url>
dhcp-option-force=tag:efihttp,60,HTTPClient
```

Three things any documentation must carry:

1. **`dhcp-pxe-vendor` is the whole trick.** In proxy mode dnsmasq only answers vendor classes it
   recognises as PXE, and `HTTPClient` is not one by default — without this line the proxy stays
   silent and the failure looks like nothing happening at all.
2. **Both `pxe-service` and `dhcp-boot` are set.** This matches the existing warning at
   `proxy-dhcp.md:271` that for UEFI clients the `pxe-service` lines, not `dhcp-boot`, decide the
   file. Keep them in agreement.
3. **An unexplained inconsistency.** `dhcp-pxe-vendor` was reportedly *not* needed for arches
   `00007` and `00009`, only for `00016`. Nobody in the thread knows why. Document it as observed
   behaviour and tell admins to add the line regardless — it is harmless when unnecessary.

**Open:** the minimum dnsmasq version for `dhcp-pxe-vendor` (2.85 was mentioned in a related iPXE
discussion, unconfirmed). Worth pinning, since `proxy-dhcp.md:450` already carries a "compiling
dnsmasq if you need UEFI support" section for exactly this class of problem.

## File layout: should boot files move under the web root?

The question raised was whether to invert today's arrangement — boot files primary under the web
root, `/tftpboot` becoming a symlink — or to keep two duplicated copies.

**Recommendation: keep `/tftpboot` primary.** The evidence against inverting is strong and mostly
already written down in the tree:

1. **Symlinks are already rejected for TFTP-served content.** `configureTFTPandPXE` hard-links
   `autoexec.ipxe` into four arch subdirectories (`functions.sh:1031-1044`), with the comment:
   *"Not a symlink -- some TFTP daemons refuse to follow those."* Hard links cannot cross
   filesystems.
2. **SELinux labels the two trees differently by design.**
   `setSELinuxContext "$tftpdirdst" tftpdir_t tftpdir_rw_t var_t` (`:1086`) versus
   `httpd_sys_content_t` for the web tree — and `SELinux/fog.te` grants `tftpd_t` **no** access
   to `httpd_sys_content_t`. A hard link cannot hold two labels, and `restorecon -RF` (`:1695`)
   walks the TFTP tree relabelling what it finds; under a merged tree that would relabel the web
   application.
3. **`in.tftpd -s $tftpdirdst` chroots** (`:1106`). Inverting changes what is inside the chroot.
4. **The `.prev` snapshot copies the whole tree on every install** (`:984`). If that tree were
   the web app, every install would duplicate the web app.
5. **The project already answered this once.** `_publishSecureBootKit()` **copies** `mmx64.efi`
   into the web tree (`:5195-5201`) rather than linking, and says why: *"the TFTP tree may be on
   a different filesystem."*

The symlink direction #1043 chose has a real but bounded cost: `<Directory>` does not follow
symlinks, which is why the GH-529 duplicate `<Directory>` blocks exist (`:4220-4224`,
`:4278-4282`). Any `<Directory>`-scoped directive needs duplicating per published path.

Keeping #1043's symlink is still right — its reasoning holds. A link cannot drift and it brings
the entire variant matrix (~45 `.efi` files), where `_publishSecureBootKit`'s copy precedent was
for two small binaries. But it should be hardened (next section).

**A fourth option, if exposure concern ever grows:** publish a *dedicated minimal directory*
under the web root containing only the `.efi` files HTTP Boot actually needs, populated by copy.
That sidesteps whole-tree exposure entirely, at the cost of a small matrix to keep in step.

## Security finding: PHP execution through the symlink

**This belongs in #1043, not in any later HTTP Boot work.**

`functions.sh:4169` (and `:4244` for the `:443` vhost) declares the PHP handler at **VirtualHost
scope**, before `DocumentRoot` — not inside a `<Directory>`:

```apache
<VirtualHost *:80>
    <FilesMatch "\.php$">
        SetHandler "proxy:fcgi://127.0.0.1:9000/"
    </FilesMatch>
```

It matches on filename with no path component, so it **applies through symlinks**. #1043's
`signed-pxe-boot-files` link therefore makes any `.php` file in `/tftpboot` *executable*, not
merely downloadable. Nothing there is PHP today, and #1043 deliberately avoided adding an
`index.php` — but the PR's documented residual ("anything an admin later drops in becomes
web-reachable") understates it for that one extension.

**Fix:** a `<LocationMatch>` on the published path with `SetHandler None`. `LocationMatch` rather
than `Directory`, for the reason already spelled out at `:4186-4189`. Two lines, alongside the
`-Indexes` emissions #1043 already adds.

Two accuracy notes while in this area:

- `Options`, `-Indexes`, `FollowSymLinks` and `autoindex` appear **nowhere** in the current tree.
  Directory-listing behaviour is inherited from the distro's stock config today. #1043 introduces
  those directives.
- **No nginx vhost is emitted anywhere** on this branch, despite nginx-shaped comments in
  `functions.sh`. Do not claim nginx parity.

## Attack surface: TFTP versus HTTP

**The trust model does not change.** Neither TFTP nor plain HTTP authenticates or
integrity-protects anything. What stops a hostile NBP from executing is the signature check.
The security story here is Secure Boot, and it is transport-independent. Any framing of this as
"HTTP vs TFTP security" is the wrong frame.

**Where surface genuinely grows is client firmware.** UEFI HTTP Boot pulls a TCP stack, an HTTP
client, a URI parser and — with an FQDN — a DNS resolver into the pre-boot environment, running
at the highest privilege level in code that is rarely patched.
[PixieFail](https://blog.quarkslab.com/pixiefail-nine-vulnerabilities-in-tianocores-edk-ii-ipv6-network-stack.html)
(nine CVEs in EDK2's NetworkPkg, affecting AMI, Insyde, Phoenix, Intel, Microsoft) shows the
quality of that code. Two land disproportionately here:

- **CVE-2023-45236** — predictable TCP initial sequence numbers. Only matters if the boot
  transport is TCP. TFTP is UDP.
- **CVE-2023-45237** — a weak PRNG enabling DNS and DHCP poisoning. Only matters if boot involves
  DNS. TFTP takes an IP from option 66.

*Stated honestly:* Quarkslab's writeup covers PXE/TFTP and does not discuss HTTP Boot. The
reasoning that these flaws become reachable when the transport changes is inference from the
shared NetworkPkg stack, not their finding.

**Server side is roughly a wash, arguably better.** Apache already serves `bzImage` and `init.xz`
unauthenticated on every boot, so no new daemon, port, or code path is added. TFTP's UDP/69 is a
known amplification and reflection vector; TCP/80 is not. Apache access logs beat tftpd's near
silence. The one genuine delta is **reach** — UDP/69 is LAN-scoped in practice, HTTP goes as far
as the web server does.

## UEFI HTTPS Boot: out of scope, and Let's Encrypt does not change that

The intuition is that a publicly-trusted certificate should just work. It does not.
[Dell's HTTPs Boot guide](https://www.dell.com/support/manuals/en-us/bios-connect/https_ug/upload-the-ca-certificate)
requires uploading the CA in BIOS Setup from a USB stick: `.pem`, X.509 2048-bit, **exactly one
certificate, not a bundle**. Firmware ships no public root store; `TlsCaCertificate` starts
empty. ISRG Root X1 is as foreign to that firmware as a self-signed FOG CA.

So the enrolment cost is identical either way, and it is per-machine physical presence — the same
wall as ADR-0009's MOK enrolment, with worse tooling. **Recommend against pursuing.** Plain HTTP
Boot at the firmware layer remains the interesting option.

## Latent bug found along the way

Not part of this spike's scope, but it should be filed separately.

`functions.sh:985` triggers a local iPXE build for **any** `--force-https` install:

```sh
if [[ "x$httpproto" = "xhttps" ]]; then
    prepareiPXEsource || return 1
    dots "Compiling iPXE binaries trusting your SSL certificate"
    "${buildipxesrc}/buildipxe.sh" "${sslpath}CA/.fogCA.pem" …
```

Per ipxe.org, iPXE's default root CA **cross-signs Mozilla's public CA list**, and `TRUST=`
*replaces* the default root rather than extending it. So on a Let's Encrypt FOG server this
should produce a binary that trusts only the FOG CA and rejects its own web server — while also
paying for two clones and eight `make` invocations with no warm path.

It demonstrably works in production for LE users, so either `-S` was not used, or `.fogCA.pem`
was absent and `buildipxe.sh` fell through to an empty `TRUST=`. **Determine which** — if it is
the second, it works by accident.

The fix is to gate that build on whether the web certificate is publicly trusted, rather than on
`httpproto == https`. The flat claim in `fog-ipxe/src/config/general.h:92` and in
`buildipxe.sh`'s header that HTTPS installs need a local build is false for the Let's Encrypt
case.

---

# Part B — Image transport

## The decisive finding: partclone constrains nothing

partclone has no network capability and never needed any. It reads a stream on stdin and writes
with `-O <target>`; on capture it reads `-cs <part>` and writes `-O <fifo>`. FOG already isolates
it behind a FIFO — `funcs.sh writeImage()` (`:817-871`) in the `fos` repo:

```sh
mkfifo /tmp/pigz1
case $mc in
    yes) udp-receiver … >/tmp/pigz1 & ;;      # multicast
    *)   cat $file >/tmp/pigz1 & ;;           # unicast — the ONLY network step
esac
zstdmt -dc </tmp/pigz1 | partclone.restore --ignore_crc -O ${target} -Nf 1
```

**The entire unicast deploy transport is `cat $file`.** Everything downstream is protocol-blind,
and the multicast path already proves the seam works — udpcast substitutes for `cat` and nothing
else changes. An HTTP deploy would be `curl -sf "$url" >/tmp/pigz1 &` in the same `case`, with
partclone, zstd, split handling and progress reporting untouched.

Two constraints belong next to that finding:

**Do not use `dd`.** partclone is sparse-aware — it copies only used blocks. A raw `dd`-style
stream would move a 500 GB partition to transfer 50 GB of data. Keep partclone; change only how
bytes reach the FIFO.

**Metadata, not bulk data, is what binds FOG to a mounted filesystem.** Deploy also reads many
small files *by path* from `$imagePath`: `d1.fixed_size_partitions`, `d1.mbr`, `d1.grub.mbr`,
`d1.has_grub`, the sfdisk partition dumps, `.lvm` / `.lvm.vgcfg` sidecars, swap UUID files, EBRs.
Split images add a glob (`sys.img.*`, `${file}.NNN`) that `cat` expands in order — an HTTP path
would have to enumerate chunks explicitly.

The mitigating factor: per FOS `CLAUDE.md`, the `*FileName()` helpers are already the **single
source of truth** for sidecar paths (`sfdiskPartitionFileName`, `MBRFileName`, `lvmFileName`,
`swapUUIDFileName`, …). That centralisation is what makes swapping the metadata access layer
tractable rather than a scattered rewrite.

## Design frame: one default, several opt-ins

Image transport is **optional configuration**. FOG ships the best out-of-the-box default and
requires no extra infrastructure for it. Everything else is opt-in, with the admin assumed to
own the setup.

The opt-ins are not all the same cost, and they split cleanly:

**Tier 1 — opt-in against FOG's own NFS server. Client-side change only.**
NFSv4.1/4.2 and `nconnect` need nothing new on the server: the distro `nfsd` FOG already installs
serves v3 and v4 concurrently by default, and **`functions.sh:2488` already writes `fsid=0` on
`/images`** — which *is* the NFSv4 pseudo-root marker. The server is accidentally half-configured
for v4 today. All the cost sits on the client.

**Tier 2 — opt-in against infrastructure the admin brings.**
SMB3 multichannel, pNFS, iSCSI. `CONFIG_PNFS_FILE_LAYOUT`/`_BLOCK`/`_FLEXFILE_LAYOUT` are all
already `=y` in the FOS kernel and unused, but pNFS needs a pNFS-capable server (NetApp,
nfs-ganesha over Ceph, …) — admin infrastructure, not something FOG would stand up.

## Candidate assessment

| Candidate | Role | Server cost | Client cost | Notes |
|---|---|---|---|---|
| Raise `rsize`/`wsize` | **Default — investigate first** | none | ~1 line | Possibly large, possibly nil. Must measure. |
| HTTP(S) deploy | **Default candidate, longer term** | none (Apache exists) | moderate | Bulk path is a one-line seam; metadata is the work. |
| `nconnect` (v3 or v4.1+) | **Tier 1 opt-in — best value** | **none** | high¹ | Helps v3 too, so no v4 migration required. |
| NFSv4.1/4.2 | Tier 1 opt-in | **none**² | high¹ | Single port 2049; `sec=krb5` becomes possible. |
| SMB3 + multichannel | Tier 2 opt-in — strongest security | admin's | **low** | Client already built in and unused. |
| pNFS | Tier 2 opt-in — niche | admin's | high¹ | Kernel layouts already `=y`; needs a pNFS server. |
| iSCSI | Tier 2 opt-in — narrow, caveated | admin's | very high | Wrong shape for a shared tree with writers. |

¹ All four share **one** prerequisite — see below. Pay it once, unlock all of them.
² Beyond confirming the distro `nfsd` is serving v4, which it does by default.

**Recommended default: keep NFSv3 and tune it.** No new infrastructure, no FOS rebuild, no admin
action — which is what "out of the box" has to mean. HTTP(S) deploy is the better long-term
default and should be treated as the intended successor once the metadata layer is designed, not
as a same-cycle change.

## The `rsize` question — investigate before anything else

`fos` `bin/fog.mount:15-22`, verified firsthand:

```sh
case $type in
    up)   mount -o nolock,proto=tcp,rsize=32768,wsize=32768,intr,noatime "$storage" /images ;;
    down) mount -o nolock,proto=tcp,rsize=32768,intr,noatime "$storage" /images ;;
esac
```

This caps transfers at 32 KiB where a modern kernel would negotiate far higher. It may be the
single cheapest improvement available — or it may be nothing, if the real bottleneck is disk,
partclone, or the zstd/pigz stage.

> **ADR-0013 is both the precedent and the warning.** That ADR documents a kernel config default,
> off since 2016, that cost a **measured 5x** deploy throughput on RTL8168h (1.2 → 6.5 GB/min) —
> found only because someone measured. `rsize=32768` has the same shape: an old explicit value
> that may have been right for 2010-era NFS. It carries the same trap in reverse, too — the
> number looks deliberate, so nobody questions it.
>
> **Measure before claiming.** If it lands, it deserves ADR-0014 and a `tests/checks/` guard,
> since the entire point of those ADRs is that a silent config regression is invisible.

Also unexplained: deploy sets `rsize` only while capture sets both. Nothing documents why.

## The one prerequisite gating every NFS improvement

This is harder than "does BusyBox pass the option through."

BusyBox's `util-linux/nfsmount.c` implements its own MOUNT RPC and fills the legacy binary
`struct nfs_mount_data`. It special-cases the filesystem name `"nfs"`, so `strcmp("nfs","nfs4")`
never matches and **BusyBox cannot mount NFSv4 at all**. (Its `NFS_MOUNT_VERSION 4` is the
*struct* version, not the protocol — an easy misread.) `nconnect` is parsed only on the kernel's
text-based mount path, which the binary struct cannot express.

So `nconnect`, NFSv4.1/4.2 and pNFS **all require the same single change**: add `nfs-utils`
(`mount.nfs`) or `util-linux mount` to the Buildroot config, and disable BusyBox's NFS helper so
it does not shadow them. Today `BR2_PACKAGE_NFS_UTILS` and `BR2_PACKAGE_UTIL_LINUX_MOUNT` are
both unset.

Kernel support is already present and unused: `CONFIG_NFS_V4_2=y`, `CONFIG_PNFS_*=y`, on kernel
6.18.38 — well past `nconnect`'s 5.3 floor.

**Price this prerequisite first.** It gates the entire NFS half of this document, and nothing
behind it can be evaluated honestly until its init-size cost is known.

What it unlocks, all against FOG's existing server:

- **`nconnect=N`** — up to 16 connections, `nconnect=8` commonly cited as the sweet spot. Works
  on **v3 as well as v4.1+**, so the throughput feature does not require a protocol migration.
- **NFSv4.1/4.2** — collapses to a **single port 2049**, no rpcbind/statd/mountd.
  `functions.sh:1894-1906` currently opens `2049/tcp`, `111/tcp+udp` and `20048/tcp+udp`, and
  `functions.sh:2450-2470` exists purely to pin mountd's port so it can be firewalled. All of
  that becomes unnecessary. It also makes `sec=krb5` conceivable against today's export, which is
  a `*` wildcard with `all_squash` and no `sec=` at all.
- **pNFS** — Tier 2 (needs a pNFS server), but the client layouts are already compiled in, so it
  costs nothing extra once the prerequisite is paid.

## SMB3 multichannel — the best opt-in

`CONFIG_CIFS=y` **and** `BR2_PACKAGE_CIFS_UTILS=y` are already in FOS and entirely unused, so the
client side costs nothing to enable — no FOS rebuild, unlike every NFS improvement above.
`mount.cifs` documents `multichannel` and `max_channels=N` (up to 16, default 2 when the server
supports it), no longer flagged experimental.

SMB3 also brings encryption (`seal`), signing and per-user authentication — **the strongest
security answer on this list**, against an NFSv3 export that currently has none of those.

Two honest caveats:

- With the admin supplying the server, the remaining objection is **performance**: multichannel
  scales across multiple connections, interfaces or RSS queues. On a single 1 GbE client NIC,
  expect approximately nothing. It pays off on multi-NIC or 10 GbE clients — which is precisely
  the population that would opt in.
- Worth establishing whether the existing CIFS build is deliberate or vestigial. It may indicate
  someone already tried this.

## iSCSI — admissible as a narrow opt-in, never a default

Most expensive to enable: `CONFIG_ISCSI_TCP` is not set and `open-iscsi` is absent, so it needs
both kernel and userspace additions and therefore a FOS rebuild.

With admin-owned infrastructure the provisioning objection disappears, but the **sharing model
objection does not**. Block-level access has no concurrency control, and initiators cache blocks
without invalidation, while FOG's image store has writers — captures and replication. A frozen
read-only LUN mounted read-only by many clients is survivable; FOG's tree is not that.

Admissible only where the admin guarantees the preconditions: a frozen read-only LUN, or SAN-side
thin clones per client. Those belong in documentation as the admin's responsibility. CHAP is a
weak security argument next to what SMB3 `seal` or NFSv4 `sec=krb5` give for far less work.

## HTTP(S) deploy — the one worth designing

`curl` / `libcurl` with OpenSSL are already present in FOS (no wget), used today only for small
control-plane POSTs. No image data moves over HTTP.

It gives encryption for free, works through proxies and across routed/WAN links, supports resume
via range requests, and needs no new daemon — FOG's web tier already reads `/images` for
`getsize.php` and `gethash.php`.

Capture still needs a write path, so keep NFS (or FTP, already used for capture finalize) for
`up` tasks. **Deploy is the overwhelmingly common operation**, so a deploy-only change captures
most of the value at a fraction of the risk.

## Where the optional config lives

Once transport is explicitly admin-selected rather than inferred, **a per-storage-node field is
the right abstraction.** Capability negotiation is the wrong tool here: the question is not "what
can both ends do" but "what infrastructure does this admin have." A node backed by an SMB3 NAS
and a node backed by plain NFS can coexist in one FOG install, so the setting belongs where the
node is described.

`nfsGroupMembers` today has `ip`, `path`, `ftppath`, `snapinpath`, `webroot`, `user`/`pass` —
**no protocol, port or version field at all**. Transport is implied by which field a caller reads.
The addition is a transport column plus somewhere to put mount options, defaulting to current
behaviour so every existing node keeps working untouched. `storagemanagementpage.class.php`
(`:299-315`, `:523-539`) and `maintenance/create_update_node.php:69` are the other two places
that would need it.

The client-side half: `$storage` reaches FOS from `/proc/cmdline` as an opaque `host:/path` with
**no scheme**. Giving it a scheme is the minimal change; ADR-0011's extended-checkin path is the
tidier one and is already sanctioned for exactly this class of server-known task data.

Keep the `getversion.php?caps=1` precedent (`funcs.sh:3488`) for what it is good at — letting an
older init *refuse* a transport it does not understand, rather than choosing one.

**Related wart, worth fixing regardless:** node health is a **TCP connect to the FTP port**, so a
node with FTP down is marked offline and gets no tasks even when NFS and HTTP are healthy. Adding
transports makes that check more wrong, not less.

## Documentation blast radius

15 pages under `fog-docs/docs/` mention NFS. The ones that would actually change:
`kb/reference/network-and-firewall-requirements.md` and `kb/how-tos/firewall.md` (NFSv4 collapsing
to a single port with no rpcbind/statd is a genuine simplification worth calling out), plus
`management/web/storage-node.md` if a per-node transport field is ever added.

---

# Open questions

1. Is `nfs-utils` or `util-linux mount` the smaller addition to the Buildroot config, and what
   does either cost in init size?
2. Is `rsize=32768` actually the deploy bottleneck, or is it disk / partclone / zstd? And why is
   deploy `rsize`-only while capture sets both?
3. What minimum dnsmasq version does `dhcp-pxe-vendor` need, and why do arches `00007`/`00009`
   not require it while `00016` does?
4. Which Let's Encrypt install path is actually in use in production, given the `TRUST=` analysis?
5. Is `CONFIG_CIFS=y` + `cifs-utils` in FOS deliberate or vestigial?

# Testing required

None of this is verifiable without hardware. In order:

**Part A**
1. A dnsmasq proxyDHCP instance serving `HTTPClient` to a real UEFI client — a *confirmation* of
   the published config above, and the place to pin the `dhcp-pxe-vendor` version floor and the
   arch inconsistency.
2. A Windows DHCP server with and without policies, confirming the two-option case.
3. A Dell or Lenovo machine with a static firmware boot URI, confirming zero-DHCP-change
   operation.
4. `curl` against a `signed-pxe-boot-files/*.php` path before and after the `SetHandler None`
   fix.

**Part B**
1. An instrumented deploy measuring throughput at `rsize=32768` versus raised versus unset —
   same hardware, same image, with the disk and the zstd/pigz stage instrumented too, so a
   CPU-bound or disk-bound result is not misread as a network win. **Nothing else in Part B is
   worth costing until this number exists.**
2. A proof-of-concept `curl -sf "$url" >/tmp/pigz1 &` substituted into `writeImage()`'s `case`,
   deploying a real image. Cheap, and it either validates the seam or kills the idea.
3. A Buildroot build with `nfs-utils` added and BusyBox's NFS helper disabled, to price the
   init-size cost. Weigh it against `nconnect` + NFSv4 + pNFS together, not against any one.
4. With that in place, `nconnect=8` on **v3 first** — it isolates the connection-count variable
   from the protocol-version variable. If v3 + `nconnect` gets the win, the v4 migration can be
   judged on security and firewall merits alone.
5. SMB3 with `multichannel,max_channels=8` against an existing SMB3 server on a multi-NIC or
   10 GbE client. Single 1 GbE is expected to show nothing — confirm cheaply so this document
   can cite a number rather than a prediction.

# Explicitly out of scope

- **UEFI HTTPS Boot.** Firmware CA enrolment is impractical at fleet scale, and Let's Encrypt
  does not help.
- **Removing TFTP.** Legacy BIOS PXE ROMs speak TFTP only; `in.tftpd` stays regardless. Every
  proposal here is additive and opt-in.
- **Any code change.** This document is for discussion first.
