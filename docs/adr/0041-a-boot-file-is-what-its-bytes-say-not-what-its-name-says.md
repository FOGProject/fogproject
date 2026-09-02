# A boot file is what its bytes say, not what its name says

## Status

accepted

## Context

The Host and Group Kernel and Init fields became dropdowns built from the boot
directory listing, because the installer now leaves the outgoing kernel behind
as `bzImage.<release>` on every update — so that directory is exactly the set
of "current, or any version still on this server, or anything I put here
myself", and picking from it beats typing a filename from memory.

What that listing could not do was say what any of those files were FOR. The
rule it shipped with had no positive test for a kernel at all:

```php
$isInit = (bool)preg_match('/(^|\/)(init|arm_init)|\.(xz|cpio\.gz)/i', $file);
if ($type === 'init' ? $isInit : !$isInit) {
```

An init was a name that looked like one; a kernel was **everything else** that
survived a blacklist of extensions. Two failures came out of that, and they
fail in opposite directions.

The first is a naming collision, not an oversight. `memdisk`, `memtest.bin` and
`grub.exe` were kept in the kernel list deliberately, because the *same* list
feeds `FOG_MEMTEST_KERNEL`, which legitimately points at them. There was one
list and two meanings, so narrowing it for the host field would have emptied
the memtest field. The setting's own name is where the collision is written
down: it says kernel and means payload.

The second is that a blacklist cannot be finished. `.efi` covered `refind.efi`;
a server carrying `refind.efi.new` from an old backup script offered it as a
bootable host kernel. Every future file dropped in that directory defaults to
"kernel" under a rule of that shape, and the directory is one an admin is
explicitly invited to put their own files in.

Tightening the pattern would have traded one of those for the other. A stricter
allowlist — `bzImage*`, `arm_Image*` — throws away the hand-compiled kernel
under a name FOG does not ship, which is half the reason the dropdown exists.

## Decision

### 1. The role is read out of the file

Four roles, decided by `FOGPage::bootFileRole()` from the file's own contents:

| Role | Test |
|---|---|
| FOS Kernel | `HdrS` at 0x202 (x86 setup header magic) or `ARMd` at 0x38 (arm64 Image header magic) |
| FOS Init | an initramfs archive or compression magic at offset 0 |
| Boot Payload | neither of the above, and not one of FOG's own web assets |
| Unclassified | FOG's web assets sharing the directory, and `.unsigned` signing working files |

Each field then asks for the role it means. The host and group fields ask for a
FOS Kernel or a FOS Init; `FOG_MEMTEST_KERNEL` asks for a Boot Payload. One
list serving two meanings is what put memdisk in a kernel menu, so there is no
longer one list.

`grub.exe` and `memdisk` are PE binaries, so a PE/`MZ` check could not have
separated them from an EFI-stub kernel. The two header magics can, and an
arm64 kernel is *also* a PE image when built with the EFI stub, which is why
its magic is checked in its own right rather than inferred.

### 2. Name-based exclusion survives for exactly two things

FOG's own web assets (`boot.php`, `bg.png`, `refind.conf`) and the `.unsigned`
working files `_resignKernels()` leaves behind. Both are files FOG itself put
there under names FOG chose, so a name is the right thing to know them by —
and `bzImage.unsigned` holds genuine kernel bytes, so content alone would
offer a half-signed working file as bootable.

Everything else that is neither kernel nor init is a **payload**, not
unclassified. `memtest.bin` and `memdisk` are raw images with no magic to match
on, and `FOG_MEMTEST_KERNEL` has always pointed at them; classifying by what we
can prove would have quietly dropped them out of the one field that needs them.

### 3. It is read in PHP, not by shelling out

No `file(1)`, no `attr`, no `shell_exec`. The obvious implementation was to
shell out — `utils/reporting/report.sh` already runs `file(1)` over this very
directory — but the web tier cannot rely on that. `SELinux/fog.te` carries no
rule permitting `httpd_t` to execute `bin_t`, which is why the Kernel Versions
panel reports `Unknown` on RHEL-family servers: its `attr` calls return empty
and the reason is discarded on stderr. Building the dropdown on the same
mechanism would inherit that failure and hide it the same way.

Reading bytes needs no binary, no extended attribute support, and no exec
permission.

### 4. One 4KiB read, at fixed offsets

Both magics live at fixed offsets, so the test is exact and costs one 4KiB read
per file rather than a scan of a 50MB image. The x86 setup header also records
where its own version banner lives — a 16-bit offset at 0x20e, relative to
0x200 — so `bootFileKernelVersion()` reads the version without searching
either.

arm64's header has no equivalent field, so that answers `''`. A caller must say
the version is unavailable rather than inventing one: reporting a wrong version
is worse than reporting none, and this directory already has a way of reporting
wrong versions — an in-place overwrite leaves FOG's old `tag_name` xattr on the
admin's kernel, so it reports as original.

### 5. The typed name survives as a failsafe

A classifier can be wrong, and an admin can be about to copy in a file that is
not there yet. So every field keeps a manual entry: the text input carries the
field name and is what posts, and the select has no name at all and only writes
into it. With no JavaScript the field degrades to the free-text box it was
before the dropdowns landed, rather than to nothing.

A stored value that names no recognized file is shown in that box with a note
saying so, and saved as typed. It is not refused — the admin holding a
known-good kernel for awkward hardware knows something the classifier does not.

## Consequences

- An arm64 kernel not named `arm*` now classifies correctly for the dropdown
  but is still judged by its prefix at boot time by
  `IpxeBootMenu::_fileFitsArch()`, which remains name-based. That is the one
  case nothing can police, and it is noted in that method's docblock.
- A file whose first 4KiB cannot be read at all is Unclassified, so an
  unreadable file disappears from the dropdowns rather than being offered and
  failing at boot.
- The listing now reads every file in the directory instead of only its names.
  The cost is bounded (4KiB each, a directory of tens of files) but it is no
  longer free, which is the argument for caching the answer once there is
  somewhere to cache it.
- `FOG_MEMTEST_KERNEL` keeps a name that describes the wrong thing. Renaming it
  is a settings migration and not worth it on its own; the glossary in
  `CONTEXT.md` records that it names a Boot Payload.
