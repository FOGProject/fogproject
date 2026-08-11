# PXE menu: expose unattended Secure Boot enrollment (Setup Mode)

## Problem

Task type 25 ("Enroll Secure Boot", `ttKernelArgs='mode=enrollsb'`, schema step
323) boots FOS and auto-enrolls FOG's Secure Boot certificate when the client
is in UEFI Setup Mode -- no password, nobody at the keyboard. It is only
reachable today by scheduling a task against a host or group. PXE menu item 14
("Enroll Secure Boot Key") is the only enroll option on the boot menu itself,
and it always chains straight to MokManager for an attended enroll.

A technician standing at a machine that's already in Setup Mode has no PXE
menu path to the unattended flow -- they have to leave the console, go
schedule a task, and come back.

## Change

1. **Rename pxeID 14's description** to `"Enroll Secure Boot Key (MOK
   attended setup)"` so the two options are told apart on the menu. Via a new
   schema step (`UPDATE`), not by editing the step-321 `INSERT` in place --
   that step has already run on existing installs.

2. **Add pxeID 15** (`fog.enrollsecurebootunattended`), description
   `"Enroll Secure Boot Key (Unattended - secure boot in setup mode
   required)"`, `pxeArgs='mode=enrollsb'`, `pxeRegOnly=2` (same "always
   shown" grouping as item 14). No `pxeParams`, so it falls through
   `BootMenu::_menuOpt()`'s existing default kernel-chain branch unchanged --
   the same mechanism `mode=autoreg`/`mode=onlydebug`/`mode=sysinfo` already
   use. No new PHP branch needed there.

3. **Gate item 15's visibility in `BootMenu::printDefault()`**:
   - Extend the existing "hide item 14 when platform != efi" filter to also
     hide item 15 -- Setup Mode enrollment needs a UEFI variable store just
     as much as the MokManager path does.
   - Add a new filter: hide item 15 unless `PK.auth`, `KEK.auth`, and
     `db.auth` all exist in `service/secureboot/` (the output of
     `fog-build-sb-authvars`, same directory `MOK.der` already lives in).
     Without all three, `mode=enrollsb`'s auto-enroll path has nothing valid
     to write, so the task type itself would refuse -- hiding the menu entry
     point avoids advertising a choice that can only fail.
   - Item 14 keeps its current, unchanged gating (`MOK.der` existence only,
     checked inside `_enrollSecureBootChoice()`) -- confirmed with the user
     that the two enroll options intentionally have independent prerequisites
     (`MOK.der` vs. the full `.auth` set), not a shared gate.

## Explicitly out of scope

- No changes to task type 25 or to FOS. `mode=enrollsb`'s behavior is
  unchanged; this only adds a second, direct way to reach it.
- No changes to `_enrollSecureBootChoice()` or item 14's boot behavior beyond
  its label.

## Verification

- With no `.auth` files present: PXE menu on a UEFI client shows only item 14
  ("...MOK attended setup)"). Item 15 absent.
- With all three `.auth` files present: PXE menu on a UEFI client shows both
  items, correctly labelled. Item 15 boots FOS with `mode=enrollsb` in the
  kernel line.
- On a BIOS/CSM client (`platform != efi`): neither item 14 nor 15 appears,
  regardless of `.auth`/`MOK.der` state.
