# `.fogsettings` — the installer's memory

`.fogsettings` is the file that makes an *upgrade* different from a *reinstall*.
Every answer you gave the installer, every flag you passed, and everything it
worked out for itself is written there at the end of a run, and read back at the
start of the next one — so `installfog.sh` on an existing server can go straight
to work instead of asking eighty questions again.

This document covers where it lives, how each value gets there, what every key
means, and which of them you can safely edit by hand.

- **Path:** `$fogprogramdir/.fogsettings` — `/opt/fog/.fogsettings` by default.
- **Format:** shell. It is *sourced* by the installer, so it is `key='value'`,
  one per line, and a syntax error there breaks the installer rather than being
  ignored.
- **Permissions:** `0600 root:root`. It holds two cleartext passwords — see
  [Security](#security).
- **Written by:** `writeUpdateFile()` in `lib/common/functions.sh`, at the end of
  every successful install or update.

---

## How to read this document

| Section | Read it when |
|---|---|
| [Where the settings come from](#where-the-settings-come-from) | You want to know why a flag "didn't take" |
| [How the file is rewritten](#how-the-file-is-rewritten) | You hand-edited it and want to know what survives |
| [Key reference](#key-reference) | You are looking up one variable |
| [Secure Boot keys](#secure-boot-keys) | You are signing FOS kernels, or your clients say "Security Policy Violation" |
| [Things that are *not* in `.fogsettings`](#things-that-are-not-in-fogsettings) | You went looking for a setting and it isn't there |
| [Security](#security) | Before you copy this file anywhere |

---

## Where the settings come from

Each key is filled in from the first source below that supplies a value.
**Highest precedence first:**

1. **A command-line flag on this run.** Every flag writes to an `s`-prefixed
   shadow variable (`--hostname` → `$shostname`), and the shadows are applied in
   `bin/installfog.sh` *after* `.fogsettings` has been sourced. That ordering is
   the whole point: without it, a persisted key would silently overwrite the flag
   you just passed.
2. **An exported environment variable.** The installer is a shell script and
   almost every default is written `[[ -z $x ]] && x=…`, so anything you export
   before running it wins over the default.
3. **`.fogsettings` from the previous install.** Sourced early, unless you pass
   `-U`/`--no-upgrade`.
4. **An interactive prompt.** `lib/common/input.sh` and `lib/common/newinput.sh`
   ask only for values still empty at that point. `-y`/`--autoaccept` skips the
   prompts and takes the suggested default for each.
5. **The distribution config.** `lib/{redhat,ubuntu,alpine,arch}/config.sh`
   supplies package lists, web-server names, paths.
6. **`lib/common/config.sh`.** The cross-distro defaults.

### Two special cases that break the chain

**`fogprogramdir` cannot come from `.fogsettings`, because `.fogsettings` lives
inside it.** It is resolved before anything else, from
`/etc/fog/fog.conf` — a one-line pointer file the installer writes for exactly
this purpose (GH-850):

```
--fogprogramdir  →  exported $fogprogramdir  →  /etc/fog/fog.conf  →  /opt/fog
```

`fogprogramdir` *is* also written into `.fogsettings`, but only as a **record**,
so that `grep fogprogramdir /opt/fog/.fogsettings` answers "where does this
install live". The installer re-asserts the value it actually resolved after
sourcing the file, so a stale line there cannot relocate an install half way
through a run. `fog_git_path` is a record in the same sense.

**Repeatable flags replace, they do not append.** `--extra-server-name`,
`--internal-domain` and `--internal-subnet` each *replace* the persisted list
when given. Appending would make a value impossible to remove without editing
the file by hand.

---

## How the file is rewritten

`writeUpdateFile()` has two paths.

**A recognizable existing file** — one containing `## Start of FOG Settings` or a
`## Version:` line — is **merged in place** by an `awk` pass:

- Every **managed key** is rewritten with this run's value, *in the position it
  already occupies*.
- Every **deprecated key** is deleted.
- The `## Version:` line is refreshed.
- **Every other line is passed through untouched** — including comments, blank
  lines, and any key you added yourself. Nothing you put in this file is thrown
  away.
- **Managed keys that were not already present are appended at the end** — which
  is why an upgraded server has keys *after* the `## End of FOG Settings` marker.
  That marker is cosmetic; the file is sourced in full and the trailing keys are
  live.

**Anything else** — no file, or a file with no recognizable header — is written
**from scratch** in the canonical key order, with the full header and both
markers.

The managed-key list is defined once, in `writeUpdateFile()`, and drives both
paths, so the two can no longer drift apart.

Either path then finishes with two steps that are easy to miss:

1. `.fogsettings` is set to **`0600 root:root`**, because it holds passwords.
2. **`.fogsettings.pub`** (`0644 root:root`) is written alongside it, holding only
   the five facts the `/api/whoami` route publishes. Both are covered in
   [Security](#security).

### Keys the installer strips

These were written by older installers and are removed on every upgrade:

| Removed key | Why |
|---|---|
| `storageftpuser`, `storageftppass` | Storage-node FTP credentials moved into the database |
| `bootfilename`, `notpxedefaultfile` | Replaced by the per-architecture boot file logic |
| `php_verAdds` | Folded into the distro package lists |
| `pkiMode`, `fogClientCACN` | Belong to the four-tier PKI layout that `--split-pki` selected. There is one hierarchy now, so a stale `pkiMode='flat'` would describe a layout the installer has no code for. See [`PKI_ZONES.md`](PKI_ZONES.md) |

---

## Key reference

**"Set by"** uses these labels:

- **flag** — a command-line option
- **prompt** — asked interactively, skipped under `-y`
- **detected** — worked out from the system
- **distro** — from `lib/<distro>/config.sh`
- **record** — written for reference; the installer recomputes it and ignores
  what is stored
- **hand** — nothing sets it; you add it yourself

### Network and host identity

| Key | Meaning | Set by |
|---|---|---|
| `interface` | NIC FOG binds services to and derives its address from | prompt (default: first interface that is up) |
| `ipaddress` | The server's **primary** IP. Everything that needs "the FOG server's address" uses this | detected from `$interface` |
| `ipaddresses` | **All** IPv4 addresses on that interface, space separated. Used where every address legitimately matters: certificate SANs, nginx `server_name`, Apache `ServerAlias`, the maintenance allow list | detected (GH-954) |
| `submask` | Netmask, dotted quad. Written into the DHCP config | detected |
| `hostname` | The name put in the web server certificate and vhost. **Not** set as the system hostname | `--hostname`, else prompt (default `hostname -f`) |
| `extraServerNames` | Extra vhost/certificate names this server also answers to, space separated. Also mirrored into the `FOG_EXTRA_SERVER_NAMES` global setting | `--extra-server-name` (repeatable, replaces) |

### DHCP

| Key | Meaning | Set by |
|---|---|---|
| `dodhcp` | `Y`/`N` — should FOG run DHCP for you | prompt; forced `Y` by `-s`/`-e` |
| `bldhcp` | `1`/`0` — the same answer as an integer. Both are kept because both are read in different places | prompt; forced `1` by `-s`/`-e` |
| `dhcpengine` | `isc` or `kea`. Empty means "detect": prefer Kea where ISC is unavailable, never auto-switch an existing install. See [ADR 0002](adr/0002-kea-dhcp-engine-selection.md) | detected, or hand |
| `dhcpd` | Service/unit name for the DHCP daemon | distro / detected |
| `routeraddress` | Router handed to DHCP clients. When DHCP is off this holds the literal comment `# No router added` — it is written straight into a config file | prompt |
| `plainrouter` | The same address without the comment fallback, for code that needs a real value or nothing | prompt |
| `dnsaddress` | DNS handed to DHCP clients; same comment-string convention as `routeraddress` | prompt |
| `startrange`, `endrange` | DHCP pool bounds | `-s`/`--startrange`, `-e`/`--endrange` |

### Install shape

| Key | Meaning | Set by |
|---|---|---|
| `installtype` | `N` (Normal server) or `S` (Storage node) | prompt |
| `osid` | `1` Redhat, `2` Debian, `3` Alpine (experimental), `4` Arch | prompt (default detected) |
| `osname` | `Redhat`/`Debian`/`Alpine`/`Arch` — the family name matching `osid` | detected |
| `packages` | The exact package list installed on this box. A record of what was asked for | distro |
| `php_ver` | PHP major.minor actually found, e.g. `8.3`. Drives the php-fpm pool and ini paths | detected |
| `webserver` | `apache`, `httpd` or `nginx` | distro |
| `installlang` | `1`/`0` — install the extra gettext language packs | prompt |
| `sendreports` | `Y`/`N` — send OS name, OS version and FOG version to the project | prompt |
| `fogupdateloaded` | `1` once the first install has completed. Its real job is to let later runs skip the full question set | installer |
| `copybackold` | `1` to copy the previous web tree aside before replacing it | `-o`/`--oldcopy` |
| `fogprogramdir` | FOG base directory. **Record** — see [above](#two-special-cases-that-break-the-chain) | `--fogprogramdir` (record) |
| `fog_git_path` | Where the checkout that ran the installer lives. **Record**, recomputed every run. Mirrored into the `FOG_GIT_PATH` global setting so the web UI can show it | detected (record) |
| `fog_update_channel` | Which channel this server tracks: `stable`, `staging` or `dev`, mapped from the checked-out branch (`stable`, `dev-branch`, `working-1.6` respectively). A genuine persisted preference, so your choice carries forward on every upgrade. Left empty on a feature branch or a tarball install rather than guessed. Mirrored into the `FOG_UPDATE_CHANNEL` global setting | detected on first install, then persisted |

### Paths

| Key | Meaning | Set by |
|---|---|---|
| `docroot` | Web server document root, e.g. `/var/www/html/` | `-D`/`--docroot`, else distro |
| `webroot` | URL path FOG is served under. `/fog/` by default; `/` serves it at the site root | `-W`/`--webroot` |
| `storageLocation` | Image store, `/images` | distro |
| `backupPath` | Where the database backup is written before a schema change | `-B`/`--backuppath` (default `/home/`) |
| `sslpath` | Holds admin-uploaded snapin SSL material and the client-communication leaf. **Not** where FOG's own PKI lives any more | `-c`/`--ssl-path` (default `$fogprogramdir/snapins/ssl`) |

Note that `storageLocationCapture` (`/images/dev`) and `snapindir` are **not**
persisted — they are derived from `storageLocation` and `fogprogramdir` by the
distro config on every run.

### Database

| Key | Meaning | Set by |
|---|---|---|
| `mysqldbname` | Database name, `fog` | `-N`/`--mysqldbname` |
| `snmysqlhost` | Database host. `localhost` on a normal server; on a storage node, the main server | prompt |
| `snmysqluser` | `fogmaster` on a normal server, `fogstorage` on a storage node | installer |
| `snmysqlpass` | That user's password. **Generated (20 chars) on first install** if empty | generated / prompt |
| `snmysqlexternal` | `1` selects **External Unprivileged Database** mode: the database is on a host FOG does not administer and `snmysqluser` has no `GRANT` rights. The installer then verifies the connection instead of installing a server, and skips the database backup, the `fogstorage`-user management and the `GRANT`s. Defaults to `0` on a fresh file | hand |

### Web / TLS

| Key | Meaning | Set by |
|---|---|---|
| `httpproto` | `http` or `https` — how the web UI is served | `-S`/`--force-https`, `--no-force-https`, else prompt |
| `netbootproto` | Protocol iPXE uses to reach `boot.php`. On a fresh HTTPS install using FOG's own CA this defaults to `http`, because HTTPS netboot needs an iPXE rebuilt with that CA baked in. An existing server keeps whatever it has | `--netboot-proto`, else computed |
| `caCreated` | `yes` once FOG's CA exists. Gates the name-constraint prompt and the netboot-protocol default | installer |
| `catrust` | `1`/`0` — add this server's CA to *this server's own* system trust store. On by default; without it every HTTPS call FOG makes to itself fails to verify | `--no-ca-trust` |
| `externalca` | `yes` when FOG signs its server certificate from a CA you supplied instead of generating one | `--external-ca` (implied by any `--ca-*`/`--web-ca-*` flag), else prompt |
| `extcacert`, `extcakey`, `extcaroot` | Paths to your intermediate certificate, its key, and your root certificate | `--ca-cert`, `--ca-key`, `--ca-root`, else prompt |
| `webExtCACert`, `webExtCAKey`, `webExtCARoot` | The same three, scoped to the **Web zone only**. Persisted so a re-run still knows which root the served chain belongs to | `--web-ca-cert`, `--web-ca-key`, `--web-ca-root` |
| `rootCAPem`, `rootCAKey` | The trust anchor: what `ca.cert.der` publishes and what fog-client pins. Recorded explicitly rather than inferred from `sslcapem`, because that names the CA signing the *vhost leaf* — the Web intermediate — and deriving the root from it would mistake the intermediate for the root | installer |
| `sslcapem`, `sslcakey`, `sslcachain` | The **Web** CA certificate, its key, and the chain file | installer |
| `sslprivkey`, `sslpubcert`, `sslcsr` | The web server leaf's key, certificate and CSR | installer |
| `internalDomains` | Extra domains the Web and Secure Boot CAs may issue for, space separated. The server's own domain is always permitted | `--internal-domain` (repeatable, replaces), else prompt |
| `internalSubnets` | Subnets those CAs may issue for, e.g. `10.20.30.0/24`. Anything listed here **replaces** the default of all RFC1918 ranges rather than adding to it | `--internal-subnet` (repeatable, replaces), else prompt |
| `acmeLeaf` | Set **by hand** to `yes` when the web leaf is managed outside FOG — certbot, acme.sh, a corporate issuance process. Without it the installer regenerates the leaf from the *original* CSR while the key on disk is the ACME key, producing a cert/key mismatch that stops the web server | hand |

Name constraints are baked into a CA when it is **first issued**, and a CA is
never re-minted. Changing `internalDomains`/`internalSubnets` on an existing
server therefore does nothing until you also remove the intermediate. This is
why the prompt is skipped once `caCreated=yes`.

The full certificate architecture — which CA does what job, and what it costs to
change each one — is in [`PKI_ZONES.md`](PKI_ZONES.md). Bringing your own CA or
using Let's Encrypt is in
[`EXTERNAL_CA_AND_LETSENCRYPT.md`](EXTERNAL_CA_AND_LETSENCRYPT.md).

### Services

| Key | Meaning | Set by |
|---|---|---|
| `username` | The FOG system account, `fogproject`. Also the FTP account used for image replication. A stored value of `fog` is silently upgraded to `fogproject` | installer |
| `password` | That account's password. **Generated (20 chars) on first install**, or recovered from an existing `config.class.php` on an upgrade | generated |
| `blexports` | `1`/`0` — build `/etc/exports` for NFS | `-E`/`--no-exportbuild` |
| `noTftpBuild` | Non-empty/`1` to leave the TFTP config alone | `-T`/`--no-tftpbuild` |
| `tftpAdvOpts` | Extra options spliced into the `in.tftpd` command line, ahead of `-s` | hand |
| `fwconfigure` | `configure`, `disable` or `skip` for the local firewall. Persisted so an upgrade — particularly an unattended `-y` one — cannot quietly reverse an admin who chose "leave it alone" | prompt (default `configure` under `-y`) |
| `kernelBackupGenerations` | How many prior kernel/init generations to keep under `customizations/kernel-backups`. Default 3; restore one with `bin/restorekernel.sh`. See [`SUPPORTED_CUSTOMIZATIONS.md`](SUPPORTED_CUSTOMIZATIONS.md) | `--kernel-backup-count` |

### Secure Boot

| Key | Meaning | Set by |
|---|---|---|
| `secureboot` | `1`/`0` — master switch. On by default | `--no-secure-boot` |
| `secureBootKey` | Private key that **signs** the FOS kernels | `--secure-boot-key`, else generated |
| `secureBootCert` | Certificate matching `secureBootKey` | `--secure-boot-cert`, else generated |
| `secureBootMokCert` | The certificate that gets **enrolled in firmware** — what `MOK.der` publishes and what goes in `db`. Not always the same file as `secureBootCert` | `--secureboot-ca-cert`, else generated |
| `sbNameConstraints` | `no` to issue the Secure Boot CA **without** name constraints. Use only if your firmware rejects the constrained chain | `--no-sb-name-constraints` |

These five are covered properly in the next section.

---

## Secure Boot keys

Secure Boot is the part of `.fogsettings` most worth understanding, because the
cost of getting it wrong is a fleet of machines that will not boot, and the
failure appears at the *client* ("Security Policy Violation") with nothing on the
server to explain it.

### The one thing to know: signing and enrollment are different keys

| Variable | Role | Rotating it costs |
|---|---|---|
| `secureBootKey` + `secureBootCert` | The **leaf**. What `sbsign` actually signs kernels with | Nothing. Re-sign and carry on |
| `secureBootMokCert` | The **intermediate**. What firmware enrolls, what `MOK.der` publishes | A physical MokManager trip to **every machine** |

FOG issues a Secure Boot **intermediate CA** (`codeSigning` EKU, name
constrained) from its root, and that intermediate issues a short-lived signing
leaf. Firmware trusts the *issuer*, so the leaf can be rotated, revoked, or
issued per storage node and the fleet keeps booting. `sbsign --addcert` ships the
intermediate inside the signature so shim can build the chain.

The older "flat" model enrolled the signing certificate itself — a self-signed
leaf that can issue nothing — which made the thing you must never change and the
thing you want to rotate the same object. Servers on the flat layout are migrated
onto the intermediate automatically, and **every machine that enrolled the old
MOK must enroll once more**. The installer prints a boxed notice when this
happens. After that, no future signing-key change needs a firmware trip. The old
`MOK.key`/`MOK.pem` are left on disk so you can still re-sign with the
previously enrolled key if you need to.

### Why these keys are persisted at all

An upgrade that silently replaced signed kernels with unsigned ones is the main
way this setup breaks. Persisting the pair means every later upgrade re-signs
without you passing the flags again. `secureboot` is persisted for the mirror
reason: an opt-out that reverted on the next upgrade would hand you back a
root-only key and a sudoers rule you had deliberately declined.

### The generated layout

Everything lives under `$fogprogramdir/pki/secureboot/`, `0700 root:root`:

```
pki/secureboot/
├── ca/
│   ├── .fogSBCA.key      the intermediate's key       0600
│   ├── .fogSBCA.pem      the intermediate             0644
│   └── .fogSBCA.der      DER sibling, byte-identical to the published MOK.der
├── leaf/
│   ├── sign.key          what signs kernels           0600
│   └── sign.pem                                       0644
├── MOK.key / MOK.pem     the legacy flat pair, kept, never regenerated
└── admin-MOK.key/.pem    a copy of a pair you supplied
```

The key is `0600 root:root` and the web user never reads it. Signing goes through
`$fogprogramdir/bin/fog-sign-kernel`, a root helper invoked via a
`/etc/sudoers.d/fog-secureboot` rule. **The helper takes no arguments on
purpose** — its config path is baked in at install time, so a compromised web
server cannot name its own key. Its config is
`$fogprogramdir/.fog-secureboot`, `0600 root:root`, and is regenerated on every
run; do not edit it.

**Keys are never regenerated once they exist.** A fresh key silently invalidates
enrollment on every machine that already trusted the old one, and nothing
surfaces that until a client fails to boot. `--recreate-keys` deliberately does
not reach Secure Boot material.

### Bringing your own

Two shapes are supported.

**Leaf only** (the historic form) — your certificate is *also* what gets
enrolled:

```
installfog.sh --secure-boot-key /path/sign.key --secure-boot-cert /path/sign.pem
```

**Intermediate plus leaf** — you keep an enrolled CA and rotate leaves under it:

```
installfog.sh --secureboot-ca-cert /path/your-sb-intermediate.pem \
              --secure-boot-key    /path/leaf.key \
              --secure-boot-cert   /path/leaf.pem
```

Both halves of the leaf pair are required together. Supplying only one is
refused outright rather than leaving kernels unsigned on a server whose admin
believes they are signed.

**Your files are copied, and your originals are never modified.** The pair is
copied to `pki/secureboot/admin-MOK.{key,pem}` and `.fogsettings` is rewritten to
point at the copy. This is not tidiness: if you park the pair under the web root
— not unreasonable, it is where the enrollment kit is published — it would be
destroyed by the web tree rebuild in the *same run* that first accepted the
flags, before the kernels were ever signed. The copy lands to
`admin-MOK.*` rather than `MOK.*` so it can never overwrite FOG's own generated
pair, which some machine may already have enrolled.

### If a recorded key goes missing

A path read back from `.fogsettings` is indistinguishable from one just passed on
the command line, so the installer checks the files still exist before trusting
either:

- **You passed the flag this run and the file is unreadable** → hard failure,
  `exit 9`. That is a typo, and continuing would sign nothing.
- **Only `.fogsettings` names it, and it is gone** → the installer says so,
  treats the setting as unset, and generates a fresh key. This is the escape
  hatch: deleting the Secure Boot directory really does force a new key, rather
  than leaving a stale path that fails somewhere unrelated later.

### Turning it off

`--no-secure-boot` sets `secureboot='0'`, which leaves `secureBootKey`,
`secureBootCert` and `secureBootMokCert` **unset rather than half-set**, so every
downstream "no key configured" branch does the right thing: no signing helper, no
sudoers rule, no published enrollment kit, and unsigned kernels.

### Name constraints

The Secure Boot intermediate is issued with `nameConstraints` limiting it to your
own domain and private ranges (or to `internalDomains`/`internalSubnets` if you
narrowed them). Some firmware rejects a constrained chain. `--no-sb-name-constraints`
sets `sbNameConstraints='no'` and issues without them — but like every CA
property, it only takes effect when the intermediate is **first** issued, so
changing it on an existing server also means removing the intermediate.

Related: [`SUPPORTED_CUSTOMIZATIONS.md § Secure Boot certificates`](SUPPORTED_CUSTOMIZATIONS.md),
[`PKI_ZONES.md`](PKI_ZONES.md), and
[ADR 0008](adr/0008-secure-boot-enrolment-task-type.md) for the enrollment task type.

---

## Things that are *not* in `.fogsettings`

| Setting lives in | What it holds | Why not here |
|---|---|---|
| `/etc/fog/fog.conf` | `fogprogramdir`, and nothing else | `.fogsettings` lives *inside* `fogprogramdir`, so it cannot be what locates it |
| `$fogprogramdir/.fogsettings.pub` | The five facts `/api/whoami` returns | `.fogsettings` is `0600` and the web user cannot read it. See [Security](#security) |
| `$fogprogramdir/.fog-secureboot` | Key/cert/staging paths for `fog-sign-kernel` | Root-only, regenerated every run. `.fogsettings` is world-readable |
| `packages/web/lib/fog/config.class.php` | Database credentials as the web tier sees them | Generated at deploy time, excluded from git |
| The `globalSettings` table | Everything configurable from **FOG Configuration → FOG Settings** | Runtime application config, not install-time |
| Nothing (one-shot) | `--restore-kernel-backup`, `--recreate-CA`, `--recreate-keys`, `--dry-run`, `--force`, `--uninstall`, `--purge-*` | Instructions for a single run. Persisting `--restore-kernel-backup` would roll the kernels back on every future update |

A few keys are mirrored *from* `.fogsettings` *into* `globalSettings` so the web
UI can display them — `FOG_GIT_PATH`, `FOG_UPDATE_CHANNEL`,
`FOG_EXTRA_SERVER_NAMES`, `SERVICE_LOG_PATH`. Editing those in the web UI has no
effect on the next update; the installer overwrites them from this file.

---

## Security

**`.fogsettings` is `0600 root:root`, because it holds two cleartext
passwords** — `password` (the `fogproject` system account, which is also the FTP
account image replication logs in with) and `snmysqlpass` (the database user).
Only root can read it.

### It used to be world-readable

Until this change the file was left at whatever the umask gave it — `0644` — so
**every local account on the server could read both passwords**, and the FTP one
is fleet-wide. That was not simple carelessness: `Route::whoami()` read the file
directly with `parse_ini_file()`, so the web user genuinely needed it readable,
and tightening the mode alone would have broken the route.

The fix separates the two jobs. The installer now also writes:

```
/opt/fog/.fogsettings.pub      0644 root:root
```

which holds **only** the five facts `whoami` answers with — `ipaddress`,
`hostname`, `osid`, `osname`, `installtype` — and no credentials. `whoami` reads
that instead, so the secrets can be shut away without taking a working API route
with them.

`.fogsettings.pub` is written **per server**, rather than mirrored into
`globalSettings`, which is the obvious alternative and is wrong here: a storage
node serves the API too (`configureMinHttpd` stubs out the management UI, not
`api/index.php`) and its `config.class.php` points at the **master's** database.
A table-backed `whoami` would have every node reporting the master's hostname, IP
and `installtype='N'`. Answering "what am I" out of a shared table cannot work.

### Migration

The mode is corrected and the public file written by `writeUpdateFile()`, which
runs at the end of **every** install and update, on both normal servers and
storage nodes. So:

- **Re-run `installfog.sh`** (or `updatefog.sh`) and you are done.
- **Until you do**, `whoami` falls back to reading `.fogsettings` as before, so a
  web-only deploy — `copybacktrunk.sh`, or any other method that updates the web
  tree without running the installer — does not leave the route blank. The
  fallback costs nothing once the installer has run, because the file is
  unreadable to the web user by then.
- If neither file can be read, `whoami` answers with empty strings rather than
  raising a 500. The previous code `extract()`ed the parse result unchecked and
  fataled with a `TypeError` on a missing file.

### Still true

- **Do not paste `.fogsettings` into a forum post, an issue, or a support
  ticket** without redacting `password` and `snmysqlpass`. `.fogsettings.pub` is
  safe to share.
- **Do not copy it between servers** to "clone" a config. The credentials in it
  belong to the machine that generated them.
- Reading or editing it now requires root.

---

## Editing it by hand

Safe, and sometimes necessary:

| Key | Why you would |
|---|---|
| `acmeLeaf='yes'` | Your web certificate is managed by certbot/acme.sh. Nothing else sets this, and without it the installer will break your certificate |
| `snmysqlexternal='1'` | Your database is on a host FOG does not manage |
| `dhcpengine='kea'` / `'isc'` | Force an engine instead of letting detection choose |
| `tftpAdvOpts` | Extra `in.tftpd` options |
| `fwconfigure` | Change your mind about the firewall without being re-prompted |
| `inetConnectTimeout` / `inetMaxTime` | Raise the bounds on the installer's network fetches (default 5s connect, 15s total) for a link genuinely slower than that. They exist so an unreachable host costs seconds rather than libcurl's 300s default, so lower them only with that in mind |

The last two rows are worth a note on **why** hand-editing works for keys the
installer never writes. `lib/common/config.sh` sets its defaults with
`[[ -z $x ]] && x=…`, and `.fogsettings` is sourced *before* that file — so any
value you put here wins, and the in-place merge preserves the line untouched
forever because it is not a managed key. That is the same mechanism `acmeLeaf`
relies on. `internet_ok` is *not* in this class despite living beside them: it is
a runtime result computed by `checkInternetConnection()`, not a setting.

Before you edit:

1. **It is sourced as shell.** Keep the `key='value'` form. An unbalanced quote
   breaks the next install rather than being ignored.
2. **Managed keys are overwritten on the next run.** Editing `ipaddress` or
   `hostname` here does not change anything — pass `--hostname`, or fix the
   interface. The table above marks which keys are genuinely yours to set.
3. **Lines the installer does not know about are preserved forever**, in place.
   That is useful for your own notes, and it is also why old installs carry
   long-dead keys.
4. **`fogprogramdir` and `fog_git_path` are records.** Editing them moves
   nothing; the installer recomputes both.
5. Take a copy first. `writeUpdateFile()` rewrites in place with no backup.
6. You need root, and you should keep the mode at `0600`. Do not edit
   `.fogsettings.pub` — it is regenerated from `.fogsettings` on every run.

---

## Reading it from a script

Because it is plain shell, sourcing it is the intended way in — that is what
`bin/updatefog.sh`, `bin/restorekernel.sh` and `bin/fog-plugin-uploads.sh` do.
**All three must run as root**, since the file is `0600`; each already guards on
readability rather than assuming it. If your script only needs the server's
identity and not its settings, read `.fogsettings.pub` instead and stay
unprivileged.

```bash
[[ -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf   # find the install
fogprogramdir="${fogprogramdir:-/opt/fog}"
. "$fogprogramdir/.fogsettings"                      # read its settings
```

Note the order. Ask `/etc/fog/fog.conf` where FOG is, *then* read the settings —
never the reverse, and never trust the `fogprogramdir` line inside
`.fogsettings` to tell you where you already are.

Not every derived path is in the file. `webdirdest`, for instance, is built by
the distro config as `"${docroot}fog/"`; scripts that need it source
`lib/common/config.sh` after `.fogsettings`, in that order.
