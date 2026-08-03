# Secure Boot enrolment is a task type, and the server never claims it enrolled anything

The client-side mechanics and the reasoning behind them live in
[fos ADR-0009](https://github.com/FOGProject/fos/blob/master/docs/adr/0009-secure-boot-enrolment-paths.md).
This ADR records the decisions that belong to the **server**: why enrolment
became a task type at all, what the task is allowed to say it did, and what the
server will and will not ship.

## Why a task type

The server already generates a signing key by default, signs the FOS kernels on
every install and upgrade (`_resignKernels`), and publishes an enrolment kit
(`_publishSecureBootKit`). Two delivery routes existed and neither scales:

- the USB kit — make a stick, boot a stock Ubuntu/Debian live image, run
  `fog-enroll-mok.sh`, compare a fingerprint by eye, reboot, answer MokManager
- PXE menu item 14 (`BootMenu::_enrollSecureBootChoice`) — chains MokManager
  directly, and still needs `MOK.der` on local FAT media because MokManager has
  no network stack

Both are per-machine, manual, and require materials the technician has to
prepare in advance. Neither is usable on a fleet, which is the only scale at
which Secure Boot support is worth having.

A task type is the right shape because **group tasking is the entire point**.
`ttIsAccess` is `'both'` so a whole group can be scheduled at once; that is the
capability the existing routes lack, not the enrolment itself.

`ttIsAdvanced` is `'0'`. This is an ordinary fleet operation, not a debug tool.
It changes nothing on disk, and the request it stages must still be confirmed by
a human at the MokManager screen, so hiding it behind Advanced would cost
discoverability and buy no safety.

`ttID` is 25, not 24: 24 was deleted by name in an earlier schema step and its
id is not reused, matching how `pxeMenu` ids are handled.

## The task description must not overclaim

shim's `MokList` is a boot-services-only variable, so the running OS cannot write
it — only MokManager can, and it demands a one-time password as proof of physical
presence. FOG stages a request; it does not enrol a key.

The `ttDescription` therefore says so explicitly, including that the confirmation
step *cannot* be automated. An admin who schedules this against 200 machines and
then discovers each one needs a keyboard visit has been misled by us, and the
place to prevent that is the description they read before scheduling.

The same constraint governs status reporting: the task reports **pending**, not
**enrolled**. Server-side surfacing of that state, and of the one-time password,
lands with Phase 2 alongside the automatic `db` path.

## What the server will ship: Microsoft's certificates

*(Decided here, implemented in Phase 2.)*

The automatic path enrols into `db`, which means replacing the platform PK,
which means the server has to decide what else goes into `db` alongside FOG's
certificate. It will be Microsoft's published certificates — KEK CA 2011,
Windows Production PCA 2011, UEFI CA 2011, plus the 2023 generation.

The decisive reason is FOG's own boot chain, not Windows.
`downloadipxesecureboot()` already records that the Secure Boot iPXE binaries
are *"signed by keys FOG does not hold: Microsoft's, for the shim, and iPXE's."*
The chain is `secureboot/snponly-shimx64.efi` → signed iPXE → FOG-signed kernel,
and that shim is signed by **Microsoft Corporation UEFI CA 2011**. Ship a `db`
without it and FOG's own Secure Boot PXE boot stops working — we would enrol the
key and break the thing we enrolled it for.

These are public certificates that Microsoft publishes so that people can enrol
them; Debian, Fedora, `sbctl` and `efitools` all redistribute them. There is no
licensing barrier, and an earlier concern that there might be was mistaken.

The alternative — capturing each machine's factory keyset and restoring it — was
rejected as the baseline and kept as optional enrichment. See fos ADR-0009 for
the full reasoning; briefly, it has no answer for the first machine of a model,
it depends on an ordering the admin cannot recover from getting wrong, and
restoring a captured `dbx` from an older BIOS re-trusts bootloaders revoked
since.

`dbx` will not be baked into the shipped bundle. A stale revocation list shipped
by FOG is worse than none and would make FOG responsible for keeping it current.

## Key handling is unchanged

The private key stays root-owned at `${fogprogramdir}/secureboot/MOK.key`, mode
0600, and is never copied near the web root. Only the certificate is published.
The Phase 2 `.auth` builder will follow the existing `fog-sign-kernel` pattern
exactly: a root-only helper taking no arguments, invoked through a validated
`sudoers` drop-in, with its configuration baked in at install time so a
compromised web server cannot name its own key.

That separation is the one thing in this feature that must not be got wrong, and
Phase 2 adds no new way to reach the key.

## Consequences

- New `taskTypes` row 25, `mode=enrollsb`, appended as schema step 322.
- The FOS side needs `mokutil` in the init and a fixed efivarfs mount; see fos
  ADR-0009.
- Phase 2 adds `packages/secureboot/fog-build-sb-authvars`, the MS baseline
  keyset, and Secure Boot page UI for enrolment state.
- The existing USB kit and PXE menu item 14 stay. They are the answer for a
  machine that cannot PXE boot FOS at all, which is exactly the machine that
  cannot run this task.
