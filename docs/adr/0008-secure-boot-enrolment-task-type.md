# The Secure Boot enrolment task type, and the narrow case it is actually for

The client-side mechanics and the full reasoning live in
[fos ADR-0009](https://github.com/FOGProject/fos/blob/master/docs/adr/0009-secure-boot-enrolment-paths.md).
This ADR records the server-side decisions: what the task type is for, what it is
**not** for, and what the server will ship for the automated path.

## The correction that shapes this

An earlier revision of this ADR presented the task type as the answer to
enrolling FOG's Secure Boot certificate across a fleet. **It is not, and cannot
be.**

Measured on real firmware (2026-08-03): iPXE verifies both the kernel and the
initrd through shim. On a machine with Secure Boot enforcing and FOG's key not
yet trusted, `bzImage` and `init.xz` are both refused with
`Verification failed: Security Policy Violation`. FOS never starts, so a FOG task
running inside FOS cannot possibly be what establishes trust in FOG's key.

The task that would enrol the key cannot run on the machine that needs it.

## What the task type IS for

**Machines that currently have Secure Boot off and are going to have it turned
on.** That is a real and common case — many sites disable Secure Boot precisely
so they can use FOG, and want it back on afterwards.

For those, the task stages the certificate with no USB stick, no Ubuntu live
image and no fingerprint transcription; the technician confirms once at
MokManager. That is a genuine improvement on the existing USB kit, for that case,
and nothing more.

`ttIsAccess` is `'both'` so a group can still be scheduled at once — useful when
a whole batch is in the same "Secure Boot currently off" state. `ttIsAdvanced` is
`'0'`: it changes nothing on disk and the request must still be confirmed by a
human, so hiding it would cost discoverability and buy no safety. `ttID` is 25,
not 24, because 24 was deleted by name in an earlier schema step and its id is
not reused, matching how `pxeMenu` ids are handled.

## The description must state both limits

Two things will mislead an admin if the task description omits them, and both
cost a wasted trip to a machine:

1. The MokManager confirmation **cannot** be automated. shim's `MokList` is
   boot-services-only; nothing FOG does can enrol a key unattended.
2. The task **cannot run at all** if Secure Boot is already enforcing.

An admin who schedules this against 200 enforcing machines and watches every one
fail to boot has been misled by us. The place to prevent that is the text they
read before scheduling, so `ttDescription` says both outright.

## What the server will ship for the automated path

*(Decided here, implemented in Phase 2.)*

The automated path is writing `db` while the platform is in **Setup Mode**, where
nothing is enforcing, FOS loads normally, and `db` is writable without a
signature. The baseline keyset will be Microsoft's published certificates — KEK
CA 2011, Windows Production PCA 2011, UEFI CA 2011, plus the 2023 generation.

The decisive reason is FOG's own boot chain, not Windows.
`downloadipxesecureboot()` already records that the Secure Boot iPXE binaries are
*"signed by keys FOG does not hold: Microsoft's, for the shim, and iPXE's."* The
chain observed in testing was `secureboot/snponly-shimx64.efi` →
`secureboot/snponly.efi` → FOG-signed kernel, and that shim is signed by
**Microsoft Corporation UEFI CA 2011**. Ship a `db` without it and FOG's own
Secure Boot PXE boot stops working.

These are public certificates Microsoft publishes so people can enrol them;
Debian, Fedora, `sbctl` and `efitools` all redistribute them. An earlier concern
about a licensing barrier was mistaken.

Capturing each machine's factory keyset was rejected as the baseline and kept as
optional enrichment — see fos ADR-0009 for why (no answer for the first machine
of a model; an unrecoverable ordering trap; `dbx` rollback re-trusting revoked
bootloaders). `dbx` will not be baked into the shipped bundle.

## Out-of-band is the real zero-touch tier

Redfish (`/redfish/v1/Systems/{id}/SecureBoot` and its `SecureBootDatabases`
collection) writes `db` with the client powered off — no boot, no OS, no physical
presence. Dell `cctk` is the desktop equivalent where a trusted OS is already
running.

This should be documented as a first-class tier rather than a "follow-on",
because it is the only path that is automated end to end with no per-machine
visit at all.

## Key handling is unchanged

The private key stays root-owned at `${fogprogramdir}/secureboot/MOK.key`, mode
0600, and is never copied near the web root. Only the certificate is published.
The Phase 2 `.auth` builder will follow the existing `fog-sign-kernel` pattern
exactly: a root-only helper taking no arguments, invoked through a validated
`sudoers` drop-in, with its configuration baked in at install time so a
compromised web server cannot name its own key. Phase 2 adds no new way to reach
the key.

## Consequences

- New `taskTypes` row 25, `mode=enrollsb`, appended as schema step 322.
- Verified end to end on 2026-08-03: `boot.php` emits `mode=enrollsb` on the
  kernel line, FOS dispatches it, `MokNew`/`MokAuth` land in NVRAM carrying FOG's
  exact certificate, the task reports Complete, and shim shows MokManager on the
  next boot — on a machine whose Secure Boot was off, per the scope above.
- The existing USB kit and PXE menu item 14 stay, and become *more* important,
  not less: they are the only routes that work on a machine already enforcing
  Secure Boot that cannot be put into Setup Mode.
