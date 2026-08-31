# The PKI tree lives in /etc/fog/pki, reached at its old name by a symlink

## Status

accepted, and implemented on `working-1.6` as `_pkiRootDir()` /
`_migratePkiTree()` in `lib/common/functions.sh`, with `PKI_root_dir` recorded
in `.fogsettings`.

## Context

FOG's four-zone PKI (ADR-adjacent, documented in `docs/PKI_ZONES.md`) sat at
`$fogprogramdir/pki` — `/opt/fog/pki` on a default install. It holds the
fleet's root CA key, the Web CA, the Secure Boot CA, the vhost keypair and the
client-communication keypair every registered fog-client pins.

`/opt/<pkg>` is for a package's own static files: the things that arrive with
an install and are replaced wholesale by the next one. Keys and certificates
are the opposite of that. They are irreplaceable per-server state, they are
what a backup policy and a config-management run are supposed to capture, and
every other TLS-consuming daemon on the box keeps its own under `/etc`. A site
backing up `/etc` and not `/opt` — an entirely ordinary policy — was silently
not backing up the one thing on this server that cannot be regenerated.

## Decision

The tree lives at **`/etc/fog/pki`**. `$fogprogramdir/pki` remains as a
**symlink** to it.

`/etc/fog` is not a new directory to create: GH-850 already makes it a real
directory on every install, to hold `fog.conf` — the pointer that tells the
next installer run where this install lives. So there is no per-distro branch
and no "this distro has no such directory" case to handle.

The location is recorded as `PKI_root_dir` in `.fogsettings`, alongside every
other PKI location. That is what makes the tree *expressible* rather than
hardcoded in two places: the migration has to be able to name its own source,
the three utils scripts (`renewal-helper`, `fog-offline-ca-key`,
`fog-mint-web-ca`) read it instead of each carrying a copy of the default, and
the shell tests point it at a scratch directory the same way they already point
`$fogprogramdir`.

Three properties are load-bearing.

**The old name keeps resolving.** `$fogprogramdir/pki` is a *published* path.
`PKI_ZONES.md`, `MULTI_SERVER_CA.md` and `EXTERNAL_CA_AND_LETSENCRYPT.md` all
name `/opt/fog/pki/...`; an admin's renewal cron entry names
`/opt/fog/pki/renewal-helper`; and a `.fogsettings` written before this change
records every canonical certificate path underneath it. A symlink means none of
that has to be rewritten, and an admin who has memorized a path is not wrong.

**The move is copy, then remove only if the copy succeeded — never `mv`.** `/opt` and `/etc` are
frequently separate mounts, so `mv` degrades to copy-then-unlink anyway. Doing
it in explicit steps buys two things: a failed copy leaves the *source*
authoritative rather than half a tree on each side with no record of which
half, and it creates the moment at which the source blocks can be overwritten
before the unlink. That overwrite is best effort — `shred` promises nothing on
a journaling or copy-on-write filesystem — but leaving the fleet's root CA key
recoverable from freed blocks because the move was "just a rename" is the worse
default.

**The migration is driven from the path accessor, not from a call site.**
`_pkiRootDir()` performs it, so no caller can reach a zone path before the tree
is in place. Ordering this by hand in `installfog.sh` would be one line and
would not survive: getting it wrong is not a visible failure. An accessor
answering `/etc/fog/pki` while the material still sits under `/opt/fog/pki`
reads as "no CA yet", mints a fresh root, and every fog-client in the estate
stops trusting this server. The cost of the safe version is one `-L` test per
call after the first run.

## Consequences

- An upgraded server relocates on its next `installfog.sh` run, once. Nothing
  an admin has written down stops working.
- `/etc/fog/pki` inherits `etc_t` under SELinux where the old tree was `usr_t`.
  Both are readable by the web tier, which only needs to traverse to
  `client/leaf`; `_migratePkiTree()` runs `restorecon -RF` on the target because
  `cp -a` carries the *source's* labels, and a tree whose labels change the
  first time somebody runs a relabel is worse than one that is already right.
- `renewal-helper` is installed into the tree, so an executable now sits under
  `/etc`. It stays there rather than moving to `$fogprogramdir/bin` beside
  `fog-offline-ca-key`, because `/opt/fog/pki/renewal-helper` is the path in the
  docs and in people's crontabs; the symlink keeps it working either way.

## Alternatives rejected

**`/etc/pki/fog`.** The first proposal, and the more obvious FHS answer. On the
RHEL family `/etc/pki` is owned by `ca-certificates` and `openssl` and already
holds `/etc/pki/ca-trust/`, so a distro package reorganizing under it would be
reorganizing around FOG's private keys. It also does not exist on Debian and
Ubuntu, which would put a per-distro branch in the one code path that must not
have one. `/etc/fog` is FOG's own namespace and is already there on every
install.

**Leave it in `/opt/fog/pki`.** Zero work and zero risk, and it is what
everything already points at. It also leaves the irreplaceable state in the
directory a site is least likely to be backing up, which is the problem.

**A bind mount instead of a symlink.** Survives a `readlink -f`, which a symlink
does not — and that matters, because `_externallyManagedLeaf()` decides "is this
certificate FOG's or the admin's" by resolving both sides and comparing. But a
bind mount has to be re-established on every boot, so it needs an `fstab` entry
or a systemd unit, and a server that comes up without it silently has no PKI.
The symlink resolves identically on both sides of that comparison, so nothing
needed the extra machinery.

**Move the tree and rewrite the docs to the new path.** Cheaper to reason about
than a compat symlink, and wrong for the one case that matters: an existing
server whose `.fogsettings` records `/opt/fog/pki/web/leaf/.webLeaf.pem` as its
canonical vhost certificate, and whose admin's renewal cron names the old
helper. Both keep working through the symlink; neither survives a clean break.
