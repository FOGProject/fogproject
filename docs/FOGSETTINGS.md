# `.fogsettings` internals

**Looking for what a setting means, or whether you can edit it?** That is the
admin-facing reference, and it lives in fog-docs:
<https://docs.fogproject.org/install-fogsettings> (source:
`docs/management/server/install-fogsettings.md` in `FOGProject/fog-docs`).

This document is the other half: how `writeUpdateFile()` actually works, and the
decisions behind it. It is for whoever is about to change the installer, not for
someone configuring a server. Keep the per-key reference in fog-docs and the
mechanics here — that is the same split `PKI_ZONES.md` and
`EXTERNAL_CA_AND_LETSENCRYPT.md` already use.

---

## The four kinds of key

Every managed key is one of these, and confusing them is the source of most bugs
in this area:

| Kind | Meaning | Examples |
|---|---|---|
| **Preference** | The admin's decision. Persisted so it survives an upgrade, and *nothing* may silently reverse it | `secureBoot`, `caTrust`, `fwconfigure`, `fog_update_channel`, `internalSubnets`, `kernelBackupGenerations` |
| **Record** | Written so it can be read back for reference. The installer recomputes the real value every run and ignores what is stored | `fogprogramdir`, `fog_git_path`, `packages`, `php_ver`, `netbootProto`, `httpProto` |
| **Hand-set** | Nothing in the installer writes it; it survives only because the merge preserves unknown lines | `snapinLocation`, `storageLocationCapture`, `inetConnectTimeout`, `ftppasvmin` |
| **Inferred preference** | A preference the installer may write *once* from what it observed, and then treats as the admin's | `acmeLeaf`, `webCertFile`, `webKeyFile`, `httpsRedirect`, `netbootProtoForced` |

The preference/record distinction is load-bearing. A preference that gets
recomputed is a setting the admin cannot make stick; a record that gets trusted
is a stale path silently relocating the install. Both have shipped as bugs.

**An inferred preference is written once and never re-derived.** `acmeLeaf` used
to be hand-set only, and forgetting it was expensive: `createSSLCA()` regenerated
the web leaf from the original CSR while the private key on disk was an ACME key,
leaving a mismatched pair and a web server that would not start. So the installer
now infers it from evidence about the certificate itself and records it, together
with `webCertFile`/`webKeyFile` naming the files it found.

The guard that makes this a preference rather than a measurement is the same one
`httpsRedirect` uses: the inference is skipped entirely once the key has a value.
Re-deriving every run would let a heuristic overrule an admin who cleared it,
which is the failure mode a *record* has and a preference must not. Clearing all
three by hand is the documented way back to FOG managing the leaf.

**A record and a preference cannot share one key.** `netbootProto` was a
preference, on the reasoning that an admin who forced it should not get the
computed default back next upgrade. But the installer also *derives* it when
nobody forced anything, and writes the result to the same key — so a derived
value became indistinguishable from a forced one and outlived the keys it was
derived from. Reported from a live server: a run resolved `http` and persisted
it, the admin then set `publicWebCert="yes"`, and the resolver short-circuited on
the stale value and reported HTTP netboot as though it had just decided that.

The pair now splits the two jobs. `netbootProto` is a record, re-derived every
run; `netbootProtoForced` is the preference, and the only thing that makes "the
admin forced this" distinguishable from "a previous run worked this out". When a
key needs protecting from recomputation *and* is itself computed, it needs two
keys, not a comment.

**Hand-set keys work because of ordering**, not because anything supports them:
`lib/common/config.sh` guards its defaults with `[[ -z $x ]]`, and `.fogsettings`
is sourced before that file. Adding a `[[ -z ]]`-guarded default to `config.sh`
therefore creates a hand-settable key whether or not you meant to.

---

## Precedence, and why the `s`-prefix exists

Highest first:

1. A command-line flag on this run
2. An exported environment variable
3. `.fogsettings` from the previous install
4. An interactive prompt (`lib/common/input.sh`, `lib/common/newinput.sh`)
5. `lib/{redhat,ubuntu,alpine,arch}/config.sh`
6. `lib/common/config.sh`

**Every flag must write to an `s`-prefixed shadow** (`--hostname` → `$shostname`),
applied in `bin/installfog.sh` *after* `.fogsettings` is sourced. A handler that
writes the real variable directly is silently discarded on every upgrade, because
the sourced file overwrites it. That exact bug has shipped at least three times —
`-E`/`blexports`, `-s`/`-e`/`dodhcp`, and `-S`/`httpProto` (which additionally had
no way back until `--no-force-https` was added, since a persisted `https` means
the `[[ -z $httpProto ]]` default can never fire again).

Repeatable flags (`--extra-server-name`, `--internal-domain`, `--internal-subnet`)
**replace** the persisted list rather than appending, so a value can be removed
without hand-editing the file.

### `fogprogramdir` cannot come from `.fogsettings`

`.fogsettings` lives at `$fogprogramdir/.fogsettings`, so it cannot be what
locates itself. `/etc/fog/fog.conf` is a one-line pointer file that exists purely
to break that circularity (GH-850):

```
--fogprogramdir  →  exported $fogprogramdir  →  /etc/fog/fog.conf  →  /opt/fog
```

It is resolved *before* `lib/common/config.sh`, because `servicedst`,
`servicelogs` and `snapindir` all derive from it. `installfog.sh` captures
`resolvedfogprogramdir` and re-asserts it after sourcing `.fogsettings`, so a
stale line cannot relocate an install half way through a run. `fog_git_path` gets
the same treatment via `resolvedfoggitpath`.

---

## `writeUpdateFile()`

Two paths, one key list.

**Existing recognizable file** (contains `## Start of FOG Settings` or
`## Version:`) → merged in place by an `awk` pass that:

- rewrites each managed key **in the position it already occupies**
- drops each deprecated key
- refreshes `## Version:`
- passes **every other line through untouched**
- appends managed keys that were not already present — which is why upgraded
  servers carry live keys *after* `## End of FOG Settings`. The marker is
  cosmetic; the file is sourced in full.

**Anything else** → written fresh in canonical order with the full header.

`managedKeys` and `deprecatedKeys` are declared once and drive both paths, so the
fresh-write and merge cannot drift apart — they used to be separate lists and did.

Then, unconditionally:

1. `.fogsettings` → `0600 root:root`
2. `.fogsettings.pub` → `0644 root:root`, holding only the `whoami` keys

### Traps

- **`mkdir -p "$fogprogramdir"` first.** On a pristine system nothing creates it
  until much later, and the redirection failing produces *nothing* while the
  function still returns 0 (GH-632). Only harmless because the sole call site is
  at the end of the install.
- **`settingLine()` must stay single-quote-safe.** Values reach it from admin
  input and package lists.
- **`fogupdateloaded` is unquoted and numeric** to match the historical format.
- Adding a key to `managedKeys` **turns a hand-set key into a managed one**, so
  the admin's value starts being overwritten. That is a behavior change even
  though it looks like documentation.

---

## Renaming a key

The transport and PKI keys were lower-case run-together names (`httpproto`,
`sslpath`, `catrust`) sitting beside camelCase ones added later
(`httpsRedirect`, `publicWebCert`, `secureBootMokCert`). They are all camelCase
now. No aliases were kept — an alias means the file carries two spellings of one
setting for the rest of its life, and nothing ever tells you which one is live.

A rename is four edits, and missing any one of them is silent:

1. **`managedKeys`** — the new name, or it is never written.
2. **`deprecatedKeys`** — the old name, or the stale line stays in the file
   forever alongside its replacement.
3. **`_migrateLegacySettingNames()`** — the pair, or the admin's persisted value
   is discarded by the write that removes the old line.
4. **The `s`-prefixed shadow and every reader.** `.fogsettings` is sourced by
   `bin/updatefog.sh`, `bin/restorekernel.sh`, `bin/fog-plugin-uploads.sh`,
   `lib/common/uninstall.sh`, `utils/FOGBackup/FOGBackup.sh`,
   `utils/reporting/report.sh` and `packages/pki/fog-mint-web-ca`, not only by
   the installer.

`tests/settings-name-migration.test.sh` pins 1–3 against each other in both
directions, so a name added to one list and not the other fails rather than
silently losing a value.

**Where the migration runs is load-bearing.** It is called immediately after
`.fogsettings` is sourced and *before* `doOSSpecificIncludes`, because
`config.sh` applies `[[ -z $secureBoot ]] && secureBoot=1` and the same for
`caTrust`. Called any later, those defaults find the new name unset and
overwrite an admin's `--no-secure-boot` / `--no-ca-trust` with the value they
deliberately opted out of. For the same reason `installfog.sh` no longer
pre-seeds `httpProto`/`externalCA` before the source: a compiled default sitting
in the new name is indistinguishable from a persisted one.

**Two names that look like keys and are not.** PHP's `self::$httpproto` is a
static property derived from the request, and `sslpath` is a storage-node
`$databaseFields` name (`'sslpath' => 'ngmSSLPath'`) that also appears as an API
POST field and a CSV import column. Neither is this file's key; both keep their
spelling. `registerStorageNode()` sends `-d "sslpath=$(... $sslPath ...)"` — the
field name on the left is the ORM's, the variable on the right is ours.

---

## `.fogsettings.pub`

`.fogsettings` holds `$password` (the `fogproject` account, which is also the
replication FTP account) and `$snmysqlpass`. It was left at the umask's `0644`
until PR #1173, so every local account could read both.

It could not simply be chmod'ed, because `Route::whoami()` read it directly with
`parse_ini_file()` — that is *why* it was readable. So the public subset moved
out:

| File | Mode | Contents |
|---|---|---|
| `.fogsettings` | `0600 root:root` | everything |
| `.fogsettings.pub` | `0644 root:root` | `ipaddress`, `hostname`, `osid`, `osname`, `installtype` |

**Per server, not `globalSettings`.** That was the obvious alternative and it is
wrong: a storage node serves the API too — `configureMinHttpd()` stubs out
`management/index.php`, not `api/index.php` — and its `config.class.php` points at
the **master's** database. A table-backed `whoami` would have every node reporting
the master's hostname, IP and `installtype='N'`. "What am I" cannot be answered
from shared storage.

The key list is `Route::WHOAMI_KEYS` and the loop in `writeUpdateFile()`. **They
must stay in step**; there is no test binding them.

`whoami` still falls back to `.fogsettings` for the window where the web tree was
updated without the installer being re-run (`copybacktrunk.sh` and similar). The
fallback is inert afterwards, since the file is unreadable to the web user by
then.

---

## Who reads the file

| Reader | Runs as | Notes |
|---|---|---|
| `bin/installfog.sh`, `bin/updatefog.sh` | root | Source it, then `config.sh`, in that order |
| `bin/restorekernel.sh` | root | Same order — `.fogsettings` records `docroot`/`webroot` but **not** `webdirdest`, which `config.sh` derives as `"${docroot}fog/"`. Sourcing only `.fogsettings` leaves `$webdirdest` empty |
| `bin/fog-plugin-uploads.sh` | root | Guards on `[[ -r ]]` |
| `Route::whoami()` | web user | Reads `.fogsettings.pub`. **The only non-root reader** |

If you add a non-root reader, it reads `.fogsettings.pub` or it does not read
this file at all.

The correct order for any new script:

```bash
[[ -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf   # where is FOG
fogprogramdir="${fogprogramdir:-/opt/fog}"
. "$fogprogramdir/.fogsettings"                      # what are its settings
. ../lib/common/config.sh                            # derive the rest
```

Never the reverse, and never trust the `fogprogramdir` line inside
`.fogsettings` to tell you where you already are.

---

## When you change something here

- **Adding a managed key** → add it to `managedKeys`, decide whether it is a
  preference or a record, and document it in the fog-docs page. A preference also
  needs a reason it must survive `-y`.
- **Retiring a key** → add it to `deprecatedKeys`, or every upgraded server keeps
  a line describing a layout the installer no longer implements.
- **Changing the `whoami` set** → update `Route::WHOAMI_KEYS` and the pub-file
  loop together.
- **Touching permissions** → check the reader table above first.
- **Anything admin-visible** → the fog-docs page is the one users find. A change
  here that is not reflected there is not documented.

## Related

- Admin reference: <https://docs.fogproject.org/install-fogsettings>
- [`PKI_ZONES.md`](PKI_ZONES.md) — what each certificate authority is for
- [`EXTERNAL_CA_AND_LETSENCRYPT.md`](EXTERNAL_CA_AND_LETSENCRYPT.md)
- [`SUPPORTED_CUSTOMIZATIONS.md`](SUPPORTED_CUSTOMIZATIONS.md)
- [ADR 0002](adr/0002-kea-dhcp-engine-selection.md) — `dhcpengine`
