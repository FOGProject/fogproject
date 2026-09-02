# The filesystem is the inventory; the database records judgments about it

## Status

accepted

## Context

FOG deliberately has no manifest of boot files. Two places say so in their own
words. `bin/restorekernel.sh`:

> Reports the FOS release a file came from using the xattr `downloadfiles()`
> stamps at download time, so a generation is self-describing and there is no
> manifest to drift out of sync.

And `lib/common/functions.sh`, on using `cp -a` for the generations:

> preserves the version/tag_name xattrs ... so every generation says which FOS
> release it came from without a separate manifest to keep in sync.

That reasoning is sound and this decision does not dispute it. A record of
*which files exist* would be wrong for exactly the stated reason: an admin
copies a kernel into that directory by hand, and any table claiming to list
the directory is now lying with no event to correct it.

Three things pushed against it anyway.

**Role classification is no longer free.** ADR 0041 replaced a filename test
with reading each file's header magic. That is one 4KiB read per file — cheap
in isolation, but it happens on every render of a host form, a group form, the
mass edit modal and the settings page, for every file in the directory.

**Two facts have nowhere to live.** An admin's pin ("no pruner may delete
this") is an intention; nothing on disk carries it. And the FOS release tag has
the opposite problem: it exists *only* as an extended attribute, and it is
frequently unreadable.

**The unreadable half is worse than it looks.** PHP has no xattr reader — the
PECL extension is absent on every server this runs on and this codebase has
never used it — so reaching the tag means running `attr`. On an
SELinux-enforcing RHEL-family server that exec is denied outright:
`SELinux/fog.te` carries no rule letting `httpd_t` execute `bin_t`. It also
fails on a mount without `user_xattr`, and wherever the `attr` package was
skipped. So on a large class of servers the tag is not slow to read, it is
**permanently unreadable from the web tier** — and the old panel rendered that,
along with six other distinct causes, as the single word `Unknown`.

## Decision

### 1. Existence is read, never recorded

Whether a file is there, how big it is, and when it changed are read live on
every listing. There is no reconciliation step, no orphan sweep, and no way for
the records to disagree with the directory about what is in it. A kernel copied
in by hand appears on the next listing; one deleted by hand stops appearing.
The no-manifest principle is intact for the thing it was about.

### 2. The `bootFile` row holds only what the directory cannot say

| Column | Why it cannot be read from the directory |
|---|---|
| `bfRole` | derivable, but only by reading the file. A cache. |
| `bfKernelVersion` | same — from the image's own header. A cache. |
| `bfChecksum` | same — but expensive enough to be worth keeping. |
| `bfReleaseTag` | **not a cache.** May be unreadable forever on this server. |
| `bfPinned` | an admin's intention. Nothing on disk carries it. |

`bfSize` and `bfMtime` are the cache *key*, not the inventory. A file whose
stat has moved is re-read; one whose stat matches is trusted.

### 3. A tag once read is never discarded

The three cache columns can be thrown away and recomputed. The release tag
cannot, so it is stored the first time it can be read at all and served from
the row after that. A later read that fails does **not** clear it.

This matters most where the read works exactly once: `kernelfetch()` already
runs `attr -s` over SSH at download time, so a server that can never read the
tag from a page render can still have recorded it when the file arrived.

### 4. A failed read reports which failure it was

`bootFileXattr()` runs `attr` through `proc_open`, captures stderr and the exit
status, and answers `['value' => ..., 'reason' => ...]` where exactly one of
the two is ever empty. "attr is not installed on this server", "this filesystem
does not carry extended attributes" and "not recorded on this file" are three
different problems with three different fixes, and collapsing them into
`Unknown` is what made the panel useless rather than merely incomplete.

`-q` is passed, which the old call site omitted — without it `attr -g` prints a
header line before the value, and the old code took `tail -n1` and hoped.

### 5. The records are an accelerator, never a dependency

Every read and write against them is wrapped and failure is silent. A listing
renders with the table missing, unreachable or empty; a cache write that does
not land costs one re-read on the next listing and nothing else. Rendering a
list of kernels is not the moment to fail a page because a cache write failed.

## Consequences

- **The kernel version stops depending on the shell entirely.** That is the
  column most often blank, and it is now read from bytes on every platform.
  Only the release tag still needs `attr`.
- The first listing after this lands inspects every file in the boot directory
  and hashes it. Kernels are tens of megabytes, so that is a visible one-time
  cost per file, paid again only when a file's stat changes.
- `bfChecksum` is written before anything reads it. It is what lets two names
  be known to be the same kernel, which is what a count-based retention policy
  needs to avoid keeping three copies of one kernel and calling it three
  versions.
- A stale row can outlive its file. Nothing breaks — the row is simply never
  consulted again, because lookups start from a directory listing — but a tidy
  sweep is a reasonable thing to add later.
- This table is **not** REST-exposed: no `Route::$validClasses` entry and no
  OpenAPI change. The shape should prove itself in the UI before anything
  outside FOG depends on it.
