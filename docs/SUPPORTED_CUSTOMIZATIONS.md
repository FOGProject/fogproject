# Supported customizations

**This document has moved to <https://docs.fogproject.org/supported-customizations>.**

What FOG preserves across an install or update, what it deliberately does not,
and where to put things so they survive, is now maintained in fog-docs with the
rest of the administrator documentation.

## Why this file still exists

It is a pointer, not something left behind by accident. Three things name this
path and cannot simply be repointed:

**The vhost marker.** Every generated Apache and nginx config on every FOG
server carries

```
# === FOG MANAGED BLOCK -- DO NOT EDIT BETWEEN THESE LINES (see docs/SUPPORTED_CUSTOMIZATIONS.md) ===
```

`spliceManagedBlock()` finds that block with `grep -qF` against the exact
string. Changing the text would mean no existing server's block is recognised
any more; every one of them would take the "file has no markers" path, and an
administrator's directives outside the block — the very thing the block exists
to protect — would be overwritten on the next run. So the marker is frozen, and
the path it names has to keep resolving.

**Offline servers.** `installfog.sh --help`, `updatefog.sh --help` and
`bin/restorekernel.sh` all point here. A FOG server is frequently on an
isolated imaging network with no route to the documentation site, and the
checkout on disk is the only copy of anything.

**Version skew.** The website documents the current release. This file sits in
the commit you actually have checked out, which is the honest place to say so
if the two ever differ.

## What is here, and what is not

The content is deliberately not duplicated. A second copy in the code
repository is exactly how the two would drift, which is the reason it moved.

If you need the full text without network access, it is in this repository's
history — `git log --follow -p docs/SUPPORTED_CUSTOMIZATIONS.md` — and in
fog-docs at `docs/1.6/management/server/supported-customizations.md`.

## The short version

- **Preserved automatically**, on a bare `./installfog.sh` upgrade as much as
  through `bin/updatefog.sh`: the iPXE boot menu background (under whatever
  name `FOG_IPXE_BG_FILE` holds), custom-named kernels and inits, previous
  kernel generations, Secure Boot keys, reports you have written, replaced
  iPXE binaries in the TFTP tree, and anything in the vhost **outside** the
  managed block.
- **Yours to place**: files under names FOG does not ship. Nothing removes
  them.
- **Not preserved**: edits *inside* the managed block, direct edits to
  `default.ipxe`, and anything under the web root that FOG does not ship — the
  tree is rebuilt wholesale on every run.

Read the page above before relying on any of that; every entry has conditions
this summary leaves out.
