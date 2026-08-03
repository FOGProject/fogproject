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

## What the server ships for the automated path

The automated path is writing `db` while the platform is in **Setup Mode**, where
nothing is enforcing, FOS loads normally, and `db` is writable without a
signature. The baseline keyset is Microsoft's published certificates — KEK
CA 2011, Windows Production PCA 2011, UEFI CA 2011, plus the 2023 generation —
vendored at `packages/secureboot/mscerts/` with a MANIFEST recording each source
URL and sha256.

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

## The server holds a PK and a KEK of its own

`_ensureSecureBootPlatformKeys()` generates a second and third keypair alongside
the MOK signing key. They sign nothing that ever executes; they exist only to
authorise updates to a client's Secure Boot databases.

The reason to generate them properly rather than reuse the MOK key or throw one
away after enrolment: once a client leaves Setup Mode it is in **User Mode with
our PK**, and the UEFI spec then requires a KEK-signed update to touch `db` and a
PK-signed update to touch `KEK`. Holding those keys is what lets this same server
push a `db` change to an already-enrolled fleet later without another firmware
trip. A server that enrolled a throwaway PK would strand every client it
enrolled. So `db.auth` is signed by KEK, `KEK.auth` by PK, and `PK.auth` by
itself, and `tests/secureboot-authvars.test.sh` asserts that chain.

Like the MOK key, they never regenerate once they exist — a new PK is not
accepted by any client carrying the old one, and the failure surfaces as an
unbootable machine long after the install that caused it.

## The builder gets no sudoers rule

`fog-sign-kernel` needs one because the *web user* invokes it from the Kernel
Update page. `fog-build-sb-authvars` is only ever run by root at install time, so
it is installed 0700 with no `sudoers` drop-in at all — the web user cannot even
execute it. That is a real reduction in what a web compromise reaches, and worth
being explicit about rather than copying the sudo pattern by reflex.

It still takes no arguments and reads every path from the root-owned config, for
the same reason `fog-sign-kernel` does: if some future change ever does expose it
to the web user, that property is already in place rather than needing to be
added under pressure.

The private keys stay root-owned at `${fogprogramdir}/secureboot/`, mode 0600,
and are never copied near the web root. Only certificates and the signed `.auth`
blobs are published, and neither contains key material.

## An exit status is not evidence

`cert-to-efi-sig-list` reads PEM only. Hand it the DER that Microsoft publishes
and it **exits 0** after writing a 44-byte signature list containing no
certificate. That is what happened on the first run of the builder: it reported
success, produced three well-formed `.auth` blobs, and every Microsoft CA was
silently absent from `db`.

A client that enrolled that `db` would have lost Windows *and* FOG's own shim,
and would need a per-machine firmware trip to recover — with nothing in any log
to say why.

Two guards, because the first one alone is what already failed:

1. Every certificate is normalised to PEM before the tool sees it, whatever
   format it arrived in.
2. The result is size-checked. An ESL is exactly a 28-byte header plus a 16-byte
   owner GUID plus the DER certificate, so its size must be `DER + 44`; anything
   else aborts the build. `tests/secureboot-authvars.test.sh` parses the finished
   `db.auth` back apart and compares each embedded certificate by sha256, so the
   check cannot be satisfied by a plausible-looking empty list.

This is the same class of silent success that fos ADR-0003 removed from the
partition path — here it just wears a zero exit status instead of a warning.

## Consequences

- New `taskTypes` row 25, `mode=enrollsb`, appended as schema step 322. Its
  description states both paths and the one hard limit, because that text is the
  last thing an admin reads before scheduling against a fleet.
- `efitools` becomes an install dependency, on the same reasoning as
  `sbsigntool`: the feature is on by default, so the tooling it needs is not
  something an admin should have to know to install first. Where it is genuinely
  absent the installer skips the build with a warning and the MOK paths keep
  working — it does not fail the install.
- The Secure Boot configuration page gains a card that says whether automatic
  enrolment is available, and spells out that Setup Mode is *not* the same as
  "Secure Boot turned off". An admin who has never heard the term will not find
  "clear the Secure Boot keys" in their firmware menu on their own.
- Verified end to end on 2026-08-03: `boot.php` emits `mode=enrollsb` on the
  kernel line, FOS dispatches it, `MokNew`/`MokAuth` land in NVRAM carrying FOG's
  exact certificate, the task reports Complete, and shim shows MokManager on the
  next boot — on a machine whose Secure Boot was off, per the scope above.
- The existing USB kit and PXE menu item 14 stay, and become *more* important,
  not less: they are the only routes that work on a machine already enforcing
  Secure Boot that cannot be put into Setup Mode.
