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

## Key names: `CATEGORY_lower_snake_case`

GH-1120 renamed all 79 managed keys. The first `_` is the category boundary, and
there are nine categories:

| Prefix | Owns |
|---|---|
| `FOG_` | Catch-all: install shape, OS records, update channel, install location |
| `NET_` | This server's own network identity |
| `DHCP_` | FOG acting *as* a DHCP server |
| `DB_` | The database connection and its dump path |
| `WEB_` | The web server and the web-UI URL surface |
| `PKI_` | Certificate authorities and trust, with a zone token in the name |
| `BOOT_` | The client netboot path: iPXE, TFTP, FOS kernels |
| `STORAGE_` | Image storage and its NFS export |
| `SVC_` | FOG's system account and host services |

**The category is the subsystem that OWNS the value, not every subsystem that
reads it.** That is what settles the multi-category cases: the storage-node
MySQL password is `DB_password`, not a `STORAGE_` key — it is a database
credential, and the fact that a node also uses it is carried by
`FOG_install_type='S'`. Same rule puts `PKI_client_cert_dir` under `PKI_` (it
holds the client-communication leaf) and `DB_backup_path` under `DB_`.

**`WEB_` and `BOOT_` look like one category and are not.** ADR 0015's whole
point is that "the web UI uses HTTPS" says nothing about the netboot transport.
`WEB_url_proto` and `BOOT_url_proto` are deliberately parallel names in separate
namespaces, so that independence is visible in the file itself rather than only
in the ADR.

**Secure Boot is `PKI_sb_*`, and its issued material is `codesign`, not `leaf`.**
The Secure Boot zone carries `extendedKeyUsage = codeSigning` on both the CA and
the certificate it issues; the web zone carries `serverAuth`. Nothing in the
Secure Boot zone authenticates a server, and a shared `leaf` token would say it
did.

**Case matters.** `FOG_git_path` and the retired `fog_git_path` are different
keys to both the shell and the `awk` merge. That is what lets the old spelling
strip while the new one is written.

---

## The four kinds of key

Every managed key is one of these, and confusing them is the source of most bugs
in this area:

| Kind | Meaning | Examples |
|---|---|---|
| **Preference** | The admin's decision. Persisted so it survives an upgrade, and *nothing* may silently reverse it | `PKI_sb_enabled`, `SVC_firewall_control`, `FOG_update_channel`, `FOG_install_mode`, `PKI_internal_subnets`, `BOOT_kernel_backups_kept`, `WEB_url_primary` |
| **Record** | Written so it can be read back for reference. The installer recomputes the real value every run and ignores what is stored | `FOG_program_dir`, `FOG_git_path`, `FOG_packages`, `WEB_php_version`, `BOOT_url_proto`, every canonical `PKI_*` path |
| **Hand-set** | Nothing in the installer writes it; it survives only because the merge preserves unknown lines | `inetConnectTimeout`, `inetMaxTime`, `storageLocationCapture`, `ftppasvmin`/`ftppasvmax`, `mcastportmin`/`mcastportmax` |
| **Inferred preference** | A preference the installer may write *once* from what it observed, and then treats as the admin's | `WEB_https_redirect`, `BOOT_url_proto_forced` |

The preference/record distinction is load-bearing. A preference that gets
recomputed is a setting the admin cannot make stick; a record that gets trusted
is a stale path silently relocating the install. Both have shipped as bugs.

**A record and a preference cannot share one key.** `BOOT_url_proto` was a
preference, on the reasoning that an admin who forced it should not get the
computed default back next upgrade. But the installer also *derives* it when
nobody forced anything, and wrote the result to the same key — so a derived value
became indistinguishable from a forced one and outlived the keys it was derived
from. Reported from a live server: a run resolved `http` and persisted it, the
admin then set `PKI_web_cert_publicly_trusted="yes"`, and the resolver
short-circuited on the stale value and reported HTTP netboot as though it had
just decided that.

The pair now splits the two jobs. `BOOT_url_proto` is a record, re-derived every
run; `BOOT_url_proto_forced` is the preference, and the only thing that makes
"the admin forced this" distinguishable from "a previous run worked this out".
When a key needs protecting from recomputation *and* is itself computed, it needs
two keys, not a comment.

**Hand-set keys work because of ordering**, not because anything supports them:
`lib/common/config.sh` guards its defaults with `[[ -z $x ]]`, and `.fogsettings`
is sourced before that file. Adding a `[[ -z ]]`-guarded default to `config.sh`
therefore creates a hand-settable key whether or not you meant to.

### Ask the filesystem before adding a state key

GH-1120 retired four keys that each stood in for something already on disk, and
that pattern is worth resisting up front rather than retiring later:

- **`acmeLeaf`** said "the web leaf is managed outside FOG". It had to be typed
  in by hand, and forgetting it was expensive: `createSSLCA()` regenerated the
  leaf from the original CSR while the private key on disk was an ACME key,
  leaving a mismatched pair and a web server that would not start. The signal is
  now derived — `PKI_web_vhost_cert` is a *canonical* path, and when it resolves
  outside `_pkiZoneDir web` the leaf is somebody else's. `_externallyManagedLeaf()`
  is that test. A symlink cannot disagree with itself, where a persisted flag and
  two recorded paths could each disagree with the vhost.
- **`caCreated`** said "the CA exists". Both of its uses already paired it with an
  `-e`/`-f` check on the very file it stood in for.
- **`externalca`** was derivable from "is an import path set", and is now a
  run-scoped prompt variable rather than a persisted key.
- **`sslcsr`** could only ever hold one path, and was re-derived to it every run.

`catrust` and `sbNameConstraints` went for a different reason: both were opt-outs
that put the safe answer behind a flag nobody passes until something has already
broken. See ADR 0024.

### A preference with no flag and no prompt

`WEB_url_primary` is one, and it is the shape to copy when a setting changes
presentation rather than behavior.

It decides which of the two management-portal URLs the installer prints **first**
when it finishes — `name` (the default) or `address`. The name leads by default
because on a certificate carrying names only, reaching the server by address is
a name mismatch and the browser says so. That reasoning does not survive contact
with every network: on a server the whole estate already reaches by address,
whose name resolves for a subset of machines, name-first buries the URL that
actually works.

```sh
WEB_url_primary='address'
```

Three properties make the no-flag, no-prompt shape right here, and all three
have to hold before reusing it:

- **It changes output, not behavior.** Nothing is installed differently, no file
  moves, no service is configured differently. Getting it wrong costs two lines
  of closing text in the wrong order.
- **Only one value means anything but the default.** `bin/installfog.sh`
  normalizes anything that is not `address` to `name`, so a typo in a
  hand-edited file resolves to the default instead of doing something a third
  way, and the rewritten file carries the spelling that was actually used.
- **It orders the URLs; it does not bless them.** The explanation printed under
  them is read from the served certificate, not from this key
  (`_certServesAddress`). Setting `address` on a names-only leaf puts the address
  first **and** still says plainly that it will warn. A preference that could
  silence a true warning would not be a presentation setting at all.

A flag was not added because a value nobody would pass twice does not earn one:
an admin who wants this is editing `.fogsettings` already, and `--help` is a
worse place to discover a setting than the file it lives in.

---

## Precedence, and why the `s`-prefix exists

Highest first:

1. A command-line flag on this run
2. An exported environment variable
3. `.fogsettings` from the previous install
4. An interactive prompt (`lib/common/input.sh`, `lib/common/newinput.sh`)
5. `lib/{redhat,ubuntu,alpine,arch}/config.sh`
6. `lib/common/config.sh`

**Every flag must write to an `s`-prefixed shadow** — `--hostname` →
`$sNET_hostname`, `--web-ca-cert` → `$sImportWebCACert` — applied in
`bin/installfog.sh` *after* `.fogsettings` is sourced. The `s` goes in front of
the whole new name. A handler that writes the real variable directly is silently
discarded on every upgrade, because the sourced file overwrites it. That exact
bug has shipped at least three times — `-E`/`blexports`, `-s`/`-e`/`dodhcp`, and
`-S`/`httpproto` (which additionally had no way back until `--no-force-https`
was added, since a persisted `https` means the `[[ -z ]]` default can never fire
again).

Repeatable flags (`--extra-server-name`, `--internal-domain`,
`--internal-subnet`) **replace** the persisted list rather than appending, so a
value can be removed without hand-editing the file.

### Flag spellings did not change

The rename moved variables, not the command line. `--hostname`, `--web-ca-cert`,
`-T` and the rest all still work. Two flags were removed outright with the keys
behind them: `--no-ca-trust` and `--no-sb-name-constraints`.

### `$fogprogramdir` is NOT renamed

`FOG_program_dir` is the key; `$fogprogramdir` is the variable, and they are
deliberately different.

`.fogsettings` lives at `$fogprogramdir/.fogsettings`, so it cannot be what
locates itself. `/etc/fog/fog.conf` is a one-line pointer file that exists purely
to break that circularity (GH-850), and it still emits `fogprogramdir=`:

```
--fogprogramdir  →  exported $fogprogramdir  →  /etc/fog/fog.conf  →  /opt/fog
```

It is resolved *before* `lib/common/config.sh`, because `servicedst`,
`servicelogs` and `snapindir` all derive from it. `installfog.sh` captures
`resolvedfogprogramdir` and re-asserts it after sourcing `.fogsettings`, so a
stale line cannot relocate an install half way through a run. `FOG_git_path` gets
the same treatment via `resolvedfoggitpath`.

`FOG_program_dir` is therefore a **record** of where the install is, written from
the live variable. `settingLine()` resolves values by indirect expansion
(`${!key}`), so that record needs its own explicit assignment in
`writeUpdateFile()` or the emitted line would simply be empty. The same applies
to `PKI_client_encrypt_cert`/`_key`.

Renaming the `fog.conf` variable is a separate change: every existing server
already carries the old spelling in that file.

---

## `writeUpdateFile()`

Three paths, one key list.

**A recognizable file that already carries new-scheme keys** → merged in place by
an `awk` pass that:

- rewrites each managed key **in the position it already occupies**
- drops each deprecated key
- refreshes `## Version:`
- passes **every other line through untouched**
- appends managed keys that were not already present — which is why upgraded
  servers can carry live keys *after* `## End of FOG Settings`. The marker is
  cosmetic; the file is sourced in full.

**A recognizable file with only pre-GH-1120 keys** → rewritten canonically, once.

This branch exists because the merge above *cannot* do that run. On it every old
key is deprecated and every new key is absent, so the merge would strip all 79
lines and append 66 at the end: the category blocks and the `## Derived` marker
would describe nothing, and the file would read as a pile of appended keys after
its own footer. The rewrite carries every **unrecognized** line through into a
trailing section — hand-set keys and an admin's own comments survive only because
something preserves what it does not manage, and a plain fresh write does not.

**Anything else** → written fresh in canonical order with the full header.

`managedKeys` and `deprecatedKeys` are declared once and drive all three paths,
so they cannot drift apart — they used to be separate lists and did.

### What a canonically written file looks like

Category blocks with a comment header each, then the derived records:

```
## Start of FOG Settings
## Version: 1.6.0-...

## FOG -- install shape, OS records, update channel, install location
FOG_install_type='N'
...

## NET -- this server own network identity
NET_interface='eth0'
...

## Derived -- do not edit
## Canonical certificate paths. The installer recomputes these every
## run, so editing a path here moves nothing. To use a certificate FOG
## did not issue, leave the path alone and make it resolve to your file
## (a symlink is enough) -- FOG then reads the target and leaves it be.
PKI_root_ca_cert='...'
...
## End of FOG Settings
```

`settingSection()` keys those headers off the managed key each one precedes, so
`managedKeys` stays the single source of *order* and the two cannot disagree.

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
- **`FOG_installed` is unquoted and numeric** to match the historical format.
  It is also the one boolean-looking key the normalizer below leaves alone —
  see "Boolean values".
- **A typo in `managedKeys` is silent, not fatal.** `settingLine()` resolves
  `${!key}`, so a key naming no live variable emits an empty line.
- Adding a key to `managedKeys` **turns a hand-set key into a managed one**, so
  the admin's value starts being overwritten. That is a behavior change even
  though it looks like documentation.

---

## Boolean values

Every boolean key holds `yes` or `no`. There were three encodings before this,
and the flag layer mixed them inside a single variable — `sDHCP_enabled` was
assigned `"Y"` and then `1` on the very next line, and
`sBOOT_external_tftp_server` was assigned the string `"true"`, which nothing
tested for.

| Old encoding | Keys |
|---|---|
| `yes`/`no` | `WEB_https_redirect`, `BOOT_rebuild_ipxe_with_my_ca`, `PKI_web_cert_publicly_trusted`, `BOOT_url_proto_forced` |
| `1`/`0` | `FOG_copy_back_old`, `DHCP_enabled`, `DB_external`, `BOOT_external_tftp_server`, `STORAGE_rebuild_nfs_exports`, `PKI_sb_enabled`, `FOG_install_lang` |
| `Y`/`N` | `FOG_send_reports` |

Which literal a test had to use was a per-key fact nobody could carry, and
getting it wrong fails silently in both directions:

```sh
[[ "N" == 0 ]]    # simply false
[[ "N" -eq 1 ]]   # "N" is evaluated as an ARITHMETIC expression -- an unset
                  # variable named N, so 0 -- rather than erroring
```

That pair is how `DHCP_enabled="N"` satisfied neither the enabled test nor the
disabled one.

`_normalizeBool()` maps `yes|y|1|true|on|enabled` and the negatives, in any
case, onto `yes`/`no`. Two things it deliberately does not do:

- **An unrecognized value is left alone**, not guessed at. Turning a typo into
  `no` is how a deliberate setting disappears with nothing to show why.
- **Empty stays empty.** The prompt loops are `while [[ -z ${KEY} ]]`, so
  collapsing unset into `no` would stop every prompt firing.

`_normalizeBooleanSettings()` applies it to the twelve keys and runs on **every**
install, not once behind a version marker — `.fogsettings` is a file admins edit,
so an old encoding can arrive at any time. That makes it idempotent and
self-repairing, and it is what keeps the GH-1120 key migration a *copy* rather
than a translation. It runs after the flag shadows, because every source of a
value has fed in by that point and the flag layer was itself the worst offender.
`writeUpdateFile()` therefore only ever sees `yes`/`no`.

**Polarity is untouched.** `BOOT_external_tftp_server` keeps the sense it
inherited from `noTftpBuild`, so values carry across unchanged and only the
encoding moves.

Three keys are outside this:

| Key | Why |
|---|---|
| `FOG_installed` | unquoted numeric via `settingLine()`'s own branch to preserve the historical format, read by `bin/updatefog.sh`, and install *state* rather than a preference |
| `SVC_firewall_control` | tri-state — `configure`/`disable`/`skip`. Folding it to `yes`/`no` destroys an answer |
| `FOG_install_type` | an `N`/`S` enum |
| `FOG_install_mode` | names a *preset over* four other keys rather than holding a value of its own, and is the only key that is deliberately cleared — any discrete transport flag makes the shape custom, so the name would no longer describe it. See below |

---

## `.fogsettings.pub`

`.fogsettings` holds `$SVC_password` (the `fogproject` account, which is also the
replication FTP account) and `$DB_password`. It was left at the umask's `0644`
until PR #1173, so every local account could read both.

It could not simply be chmod'ed, because `Route::whoami()` read it directly with
`parse_ini_file()` — that is *why* it was readable. So the public subset moved
out:

| File | Mode | Contents |
|---|---|---|
| `.fogsettings` | `0600 root:root` | everything |
| `.fogsettings.pub` | `0644 root:root` | `NET_fog_server_ip`, `NET_hostname`, `FOG_os_id`, `FOG_os_name`, `FOG_install_type` |

**Per server, not `globalSettings`.** That was the obvious alternative and it is
wrong: a storage node serves the API too — `configureMinHttpd()` stubs out
`management/index.php`, not `api/index.php` — and its `config.class.php` points at
the **master's** database. A table-backed `whoami` would have every node
reporting the master's hostname, IP and `FOG_install_type='N'`. "What am I"
cannot be answered from shared storage.

The key list is `Route::WHOAMI_KEYS` and the loop in `writeUpdateFile()`. They
must stay in step, and **`tests/whoami-keys-in-step.test.php` now binds them** —
it did not exist when the two last had to move together. The drift is quiet in
the worst way: `whoami()` fills a missing key with an empty string rather than
dying, so the route stays 200 and a client reads a blank hostname as a fact.

`whoami` still falls back to `.fogsettings` for the window where the web tree was
updated without the installer being re-run (`copybacktrunk.sh` and similar). That
net is one notch narrower since GH-1120: such a server still has pre-rename keys,
which the new names do not match, so the route answers empty strings until the
installer runs. Reading both spellings would mean carrying retired names in code
that exists only to cover a deploy ordering the installer fixes anyway.

---

## Who reads the file

| Reader | Runs as | Notes |
|---|---|---|
| `bin/installfog.sh`, `bin/updatefog.sh` | root | Source it, then `config.sh`, in that order |
| `bin/restorekernel.sh` | root | Same order — `.fogsettings` records `WEB_docroot`/`WEB_root` but **not** `webdirdest`, which `config.sh` derives as `"${WEB_docroot}fog/"`. Sourcing only `.fogsettings` leaves `$webdirdest` empty |
| `bin/fog-plugin-uploads.sh` | root | Guards on `[[ -r ]]` |
| `utils/FOGBackup/FOGBackup.sh` | root | Reads `DB_*` and `STORAGE_image_share_path` |
| `utils/reporting/report.sh` | root | Reads `DB_*`, `WEB_docroot`, `WEB_root` |
| `Route::whoami()` | web user | Reads `.fogsettings.pub`. **The only non-root reader** |
| `packages/pki/fog-pki-admin` | root, started by the web user over `sudo` | Reads three preference keys and rewrites them in place. Not a non-root reader: the web tier names a key from a three-entry allowlist and a value matching `^(yes|no)$`, and never sees the file (ADR 0036) |

If you add a non-root reader, it reads `.fogsettings.pub` or it does not read
this file at all.

**And nothing writes it on behalf of a non-root caller except through a fixed
key allowlist.** This file is *sourced as shell by root* on the next installer
run, so an unvalidated value written into it executes as root. `fog-pki-admin`
is the only such writer, it may touch exactly three keys
(`PKI_web_cert_publicly_trusted`, `WEB_https_redirect`,
`BOOT_rebuild_ipxe_with_my_ca`) plus `PKI_web_external_root_cert`, and its value
pattern lives on the far side of `sudo` where the caller cannot remove it.
`tests/pki-admin-helper.test.sh` exercises the injection cases and then sources
the resulting file to prove nothing ran.

The correct order for any new script:

```bash
[[ -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf   # where is FOG
fogprogramdir="${fogprogramdir:-/opt/fog}"
. "$fogprogramdir/.fogsettings"                      # what are its settings
. ../lib/common/config.sh                            # derive the rest
```

Never the reverse, and never trust the `FOG_program_dir` line inside
`.fogsettings` to tell you where you already are.

**Third-party scripts source this file too.** Anything reading `$snmysqlpass` or
`$storageLocation` breaks on the run that strips the old line. That is a release-note
item, not something the installer can paper over.

---

## When you change something here

- **Adding a managed key** → add it to `managedKeys` under the right category,
  decide whether it is a preference or a record, and document it in the fog-docs
  page. A preference also needs a reason it must survive `-y`.
- **Renaming a key** → both halves, in the same change: a seed line in
  `bin/installfog.sh` to carry the value, and the old spelling in
  `deprecatedKeys` to strip the line. `deprecatedKeys` carries no value, so the
  second without the first wipes the setting on every server, silently, and under
  `-y`. `tests/fogsettings-migration.test.sh` exercises the pair end to end.
- **Retiring a key** → add it to `deprecatedKeys`, or every upgraded server keeps
  a line describing a layout the installer no longer implements. If the key was
  standing in for something on disk, derive it instead and say so where the key
  used to be read.
- **Changing the `whoami` set** → update `Route::WHOAMI_KEYS` and the pub-file
  loop together; `tests/whoami-keys-in-step.test.php` will hold you to it.
- **Touching permissions** → check the reader table above first.
- **Anything admin-visible** → the fog-docs page is the one users find. A change
  here that is not reflected there is not documented.

## Related

- Admin reference: <https://docs.fogproject.org/install-fogsettings>
- [ADR 0024](adr/0024-fogsettings-unified-key-model.md) — the key model, the
  categories, and what the rename deliberately left alone
- [ADR 0015](adr/0015-install-settings-are-independent-keys.md) — why the install
  settings are independent keys
- [`PKI_ZONES.md`](PKI_ZONES.md) — what each certificate authority is for
- [`EXTERNAL_CA_AND_LETSENCRYPT.md`](EXTERNAL_CA_AND_LETSENCRYPT.md)
- [`SUPPORTED_CUSTOMIZATIONS.md`](SUPPORTED_CUSTOMIZATIONS.md)
- [ADR 0002](adr/0002-kea-dhcp-engine-selection.md) — `DHCP_engine`
