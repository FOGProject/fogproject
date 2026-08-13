# DHCP engine: detect-and-prefer Kea, fall back to ISC, never auto-switch an existing install

## Status

accepted

## Context

ISC-DHCP reached end-of-life in 2022 and Debian 13 (Trixie) dropped the
`isc-dhcp-server` package entirely (issue #730). FOG can optionally run the
DHCP service itself (the installer's `bldhcp=1` path), and that service must
hand each PXE client the architecture-appropriate iPXE binary — BIOS gets
`undionly.kkpxe`, the various UEFI arches and ARM64 get the right `snponly.efi`.
Today `configureDHCP()` does this by writing an ISC `dhcpd.conf` whose `class`
blocks match `vendor-class-identifier` substrings.

Kea is ISC's replacement, but it is not a drop-in: its config is JSON, and
per-architecture bootfile selection is expressed as `client-classes` with `test`
expressions rather than `class … filename`. We need Kea support without breaking
the large installed base still on ISC, and without orphaning distros that do not
yet ship Kea in their default repositories.

## Decision

The installer **detects available DHCP engines and prefers Kea, falling back to
ISC**, resolved with the existing candidate-list pattern (as used for the SQL
server/client packages). The resolved engine drives the package, service name,
and config path per distro.

Engine selection is **stable across re-runs and never silently switches a
working install**:

1. An explicit `dhcpengine=` in `.fogsettings` is honored (and is the admin's
   supported opt-in path — set `dhcpengine='kea'` by hand to migrate an existing
   ISC box).
2. Otherwise, if a prior `.fogsettings` already configured DHCP (a pre-Kea
   install), the engine is treated as `isc` — existing boxes are not switched.
3. Otherwise (fresh install), the engine resolves to the preferred available
   one (Kea, else ISC). The result is persisted as `dhcpengine`.

A fresh install on a distro lacking Kea (or where it is not in a configured
repo) correctly lands on ISC; if neither is available the install hard-fails
rather than guessing. We do **not** auto-enable EPEL/COPR/remi for Kea — but the
installer already enables EPEL/remi on RHEL-family for other reasons, so Kea is
reachable there without any new repo work.

The per-architecture arch→bootfile mapping is **deliberately duplicated** between
the untouched ISC generator and the new Kea generator rather than refactored into
a shared table; the working ISC path is left as-is, with a cross-reference
comment in both spots.

The Kea config is generated and validated in **two tiers** via `kea-dhcp4 -t`:
the base subnet plus standard arch classes (BIOS / UEFI / ARM64 / Surface Pro 4)
must validate or the install **hard-fails**; the legacy Apple-Intel BSDP block is
then appended and re-validated, and **dropped with a visible warning** if it does
not validate. (Apple BSDP relies on stateful `vendor-encapsulated-options` reply
logic that does not translate cleanly to Kea client-classes.) The ISC path gains
an optional, **non-fatal** `dhcpd -t` check that warns but still starts the
daemon, preserving its long-standing behavior.

When the installer owns DHCP (`bldhcp=1`), bringing up one engine **stops and
disables the other FOG-relevant DHCP daemon** if present, symmetrically, so the
two never contend for port 67. When `bldhcp=0`, the installer touches no DHCP
service at all.

## Why

ISC-DHCP is dead upstream and gone from current Debian, so FOG must move; but the
DHCP server on a FOG box is load-bearing infrastructure, and silently swapping a
running engine on upgrade is the kind of surprise that breaks production networks.
Preferring Kea for new installs while pinning existing ones (and offering an
explicit opt-in) gets the migration without the ambush. Hard-failing the base Kea
config but degrading the Apple block keeps the 99.9% PXE case provably valid
before the daemon starts, while not letting obsolete Apple-Intel netboot block an
otherwise-good install. The duplication of the arch table is the cheaper, lower-
risk choice than rewriting a field-tested generator for a mapping that changes
once every few years.

## Consequences

- `.fogsettings` gains a `dhcpengine` variable; its absence on an older install
  is meaningful (implies ISC) and must be preserved by the inference rule above.
- The arch→bootfile mapping now lives in two places; a new firmware arch code
  must be added to both the ISC and Kea generators (comments flag this).
- Apple-Intel BSDP netboot is best-effort under Kea and may be silently absent;
  this is logged, not hidden.
- On RHEL-family, Kea availability depends on EPEL, which the installer already
  enables — the engine-availability probe must therefore run after repo setup.
