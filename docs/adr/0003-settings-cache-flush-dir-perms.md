# The settings-cache flush dir is sticky world-writable, not owned by one web user

## Status

accepted

## Context

The TTL settings cache (#849) coordinates across processes with a single zero-byte
beacon file, `FOG_CACHE_DIR/.settings_cache_flush` (i.e. `/opt/fog/cache/...`).
Whatever process triggers a flush/refresh `touch`es it; every `getSetting()` caller
reads its mtime to decide whether to drop cached values. This is the **first time the
FOG web tier writes into `/opt/fog`** — every existing path under `/opt/fog` is written
by FTP or the CLI daemons as `fogproject`, never by the web request.

The web request runs as the **php-fpm pool user**, which is **not guaranteed to equal
`$apacheuser`**. FOG never sets the pool `user`/`group` (it relies on each distro's
package default), so on RedHat/nginx php-fpm keeps `user = apache` while FOG sets
`$apacheuser = nginx`. An `$apacheuser`-owned directory at mode `775` is therefore not
reliably writable by the process that must touch the beacon: the `@touch` is denied,
the flush action still returns `200`, and no file ever appears.

## Decision

The installer creates `/opt/fog/cache` **sticky world-writable (`1777`)**, `/tmp`-style,
and the beacon lives there. We do **not** force the php-fpm pool to run as `$apacheuser`.

## Why

- The beacon carries **no secrets** — it is an mtime signal meaning "re-read settings on
  next access." Read access (the common path, used by every web request and daemon) only
  needs `r-x` on the dir, which `1777` keeps.
- The set of *writers* (the php-fpm worker today; any future CLI/daemon flush) is not a
  single user that is knowable at install time across web servers (nginx, apache,
  lighttpd, …) and distros. Sticky world-writable sidesteps that without hardcoding a user.
- The "correct-looking" alternative — set the php-fpm pool `user`/`group` to `$apacheuser`
  — has **high blast radius**. FOG sets neither today, and the PHP `session.save_path`
  (e.g. `/var/lib/php/session`, owned `root:apache` mode `770` on RedHat; a different path
  and owner on Debian/Arch/Alpine) is owned by the *package-default* user. Repointing
  php-fpm at `$apacheuser` makes that directory unwritable and **breaks login** unless the
  session dir is also migrated per distro. That is out of proportion to a non-secret
  coordination beacon, and the kind of OS-state change that needs cross-distro install
  validation before it can be trusted.
- The sticky bit (`1777`) stops one user deleting another user's files, matching `/tmp`.

## Note

Only the web tier writes the beacon today; daemons just read its mtime. If a
CLI/daemon-initiated flush is ever added, the beacon (created ~`644` by its first writer)
may not be mtime-updatable by a *different* user — at that point
`clearSettingsCache()` / `refreshSettingsCache()` should `chmod 0666` the file after
`touch`. Left out now (no such caller), to avoid speculative code.
