# `.fogsettings` audit — 2026-08-18

A point-in-time audit of every managed key and of the resolution paths around
them, done while documenting the settings model for #1120. Line numbers drift;
re-verify before acting on one.

The naming unification and the false Secure Boot claim in the UI were fixed on
the same branch as this file. **Everything below was left alone deliberately** —
each is a behaviour change with its own blast radius, and bundling them into a
rename commit would have made both unreviewable. They are recorded here so the
next person starts from a list rather than from a rediscovery.

## Summary

| # | Finding | Severity |
|---|---|---|
| 1 | `public-cert`/`embed-ca` are not self-consistent — `FOG_WEB_HOST` still holds an IP | High |
| 2 | The two `-10sec` ESP archives ship with no FOG binaries; a third is not built | High |
| 3 | `service/secureboot/mmx64.efi` is missing on a first install | Medium |
| 4 | `_booturl` hardcodes `/fog/service`, ignoring `FOG_WEB_ROOT` | Medium |
| 5 | `--install-mode http-only` does not survive an upgrade | Medium |
| 6 | `--public-web-cert` alone silently stops leaf issuance | Medium |
| 7 | `usage()` and the mode prompt claim `standard` means "no redirect" | Low |
| 8 | The redirect-seeding discriminator is destroyed after the first 1.6 run | Low |
| 9 | `httpProto=http` with `httpsRedirect=yes` is reachable and unguarded | Low |
| 10 | Three reporting defects around the install-mode prompt | Low |
| 11 | Two keys are read but never written | Low |
| 12 | `localboot-publish.test.sh` cannot see finding 2 | Medium (test) |

---

## 1. `public-cert` and `embed-ca` are not self-consistent

`configureDefaultiPXEfile()` writes `default.ipxe` chaining to `$hostname` when
`netbootProto == https`, and hard-fails rather than write an IP
(`functions.sh:1877–1900`). Correct.

But once that first request reaches `boot.php`, `BootMenu` rebuilds every
`${boot-url}` from the **`FOG_WEB_HOST`** setting
(`bootmenu.class.php:271–301`, `:471`), which is seeded from
`confighostip="$ipaddress"` (`functions.sh:8399`, `:8512`) inside
`_initSetting()` — and that only runs on a **first install**. Upgrades never
touch it.

So every step-2 fetch — `MOK.der`, `mmx64.efi`, `refind*`, `advanced.php`, the
background image — is attempted against `https://<IP>/` and fails iPXE's name
check. Kernel and init survive only because iPXE resolves them relative to
`boot.php`'s own URI.

ADR 0015 promises a public-certificate site gets HTTPS netboot with no rebuild.
In practice it also needs a manual `FOG_WEB_HOST` edit, and nothing in the
installer says so. Either set it when `netbootProto` resolves to `https`, or
warn. Documented as a manual step meanwhile.

## 2. The `-10sec` ESP archives contain no FOG binaries

`_espKitFiles()` sources the delay variant from `10secdelay/${fogdir}${name}.efi`
(`functions.sh:10102`). `_retireStaleEfiPaths()` deletes exactly those —
`10secdelay/*.efi` at depth 1 and the whole `10secdelay/{i386,arm64}-efi/`
subtrees (`functions.sh:2246–2258`) — and it runs from `configureTFTPandPXE` at
`installfog.sh:1255`, **47 lines before** `_publishLocalBootFiles` at `:1302`.
`v2.0.0-fog.8` stopped shipping them as well, so they are absent on a fresh
install too.

Consequences, per archive:

| Archive | FOG binaries | Upstream set | Result |
|---|---|---|---|
| `fog-esp-x86_64` / `-i386` / `-arm64` | 5 | 5 / 0 / 5 | fine |
| `fog-esp-x86_64-10sec` | **0** | 5 | built, ships an `autoexec.ipxe` chaining five absent files |
| `fog-esp-arm64-10sec` | **0** | 5 | same |
| `fog-esp-i386-10sec` | **0** | 0 | `copied=0`, **not built at all** |

`manifest.json` therefore lists **five** archives and the install prints
`Done (5)`, while `SUPPORTED_CUSTOMIZATIONS.md:161–167` documents six.

Either drop the delay variants and point at `--boot-delay`, or rebuild the
`10secdelay` EFI set. Note `--boot-delay` is *not* an equivalent for ESP boot:
`_applyBootDelay()` rewrites the TFTP root's `autoexec.ipxe`, which an ESP never
reads.

## 3. `mmx64.efi` is missing from the enrolment kit on a first install

`_publishSecureBootKit()` copies MokManager out of `$tftpdirdst/secureboot/`
(`functions.sh:9306–9313`), but it is reached from `downloadfiles()`
(`:8729`) inside `configureHttpd()` (`:8569`), which runs at
`installfog.sh:1249` — six lines **before** `configureTFTPandPXE` populates that
tree at `:1255`.

So on a fresh master `service/secureboot/mmx64.efi` does not exist, and the
`Enroll Secure Boot Key` menu entry chains a 404 into its own error branch
(`bootmenu.class.php:2135–2137`). It self-heals on the second installer run,
because the TFTP tree persists. Upgrades are unaffected.

## 4. `_booturl` hardcodes `/fog/service`

`bootmenu.class.php:471–472` builds `_booturl` as
`"{$httpproto}://{$webserver}/fog/service"`, ignoring `FOG_WEB_ROOT` — unlike
`$this->_web` twelve lines earlier, which uses `$curroot`. Every `_booturl`
consumer breaks on a custom webroot: the `boot.php` chains, `advanced.php`, the
background image, and both Secure Boot enrolment targets. GH-529 was this class
of bug; this looks like a survivor rather than a decision.

## 5. `--install-mode http-only` does not survive an upgrade

`httpProto` is a managed key, so `http` is persisted — and then discarded, because
`installfog.sh:847` force-assigns `https` on every run. The only route back is
`_applyInstallMode` with `sinstallMode=http-only`, which needs the flag passed
again or an attended answer of `2`. Under `-y`, `promptInstallMode` returns
early (`functions.sh:6050`) and the server **silently flips to HTTPS**.

There is no preference key that can hold "HTTP only" and no `--http-only` flag.
Since `httpProto` is documented as a key with a default, this is a key an admin
cannot make stick — the exact failure mode `FOGSETTINGS.md` warns about.

## 6. `--public-web-cert` alone silently stops leaf issuance

`_createWebLeaf()` returns early when `publicWebCert == yes`
(`functions.sh:6626`): no key, no CSR, no certificate, no SAN stamp. That is
right for a server whose leaf really is externally issued, but
`--install-mode public-cert` on a **fresh** install produces a server with no
FOG-issued web leaf at all, signalled only by one "leaving it in place" line.

Neither #1116's key table ("the web certificate chains to a public root") nor
ADR 0015 says the key also means "FOG will not issue or renew a leaf".

## 7. `standard` is documented as "no redirect" and does not set it

`_applyInstallMode()` touches exactly four variables and `httpsRedirect` is not
one of them. But `usage()` (`installfog.sh:107`) and the prompt
(`functions.sh:6056`) both describe `standard` as "No redirect, no rebuild". On
a migrated `-S` server, choosing `standard` keeps the redirect **and HSTS**.

## 8. The redirect-seeding discriminator is single-use

The seeding is guarded on `httpsRedirect` being unset, which makes it fire once
— as designed. But step two of the same migration persists `httpProto='https'`
for **everyone**, so `[[ $httpProto == https ]]` is now universally true. If
`httpsRedirect` is ever cleared — hand-edited out, or a `.fogsettings` restored
from a partial backup — the seed yields `yes`, turning the redirect and HSTS on
for a server that never asked. Nothing detects or warns.

## 9. `httpProto=http` with `httpsRedirect=yes` is unguarded

Reachable: a pre-1.6 `-S` server seeds `httpsRedirect=yes`, then a later
`--install-mode http-only` sets `httpProto=http`. Port 80 then redirects to
HTTPS (`functions.sh:7394`, `:7761`) while FOG's own self-calls are built from
`http` and `_resolveSelfCacert()` returns empty on `[[ $httpProto == https ]] ||
return 0` (`:4666`). `grep -n 'httpProto == http\b\|httpProto != https'` finds
no guard anywhere.

## 10. Three reporting defects around the mode prompt

- `promptInstallMode`'s guard checks `$sinstallMode`, `$shttpsRedirect`,
  `$spublicWebCert`, `$srebuildIpxeWithMyCA`, `$autoaccept` and `! -t 0` — but
  **not `$snetbootproto`** (`functions.sh:6048–6050`). An attended
  `--netboot-proto https` run is still asked, and the prompt's own
  `_applyInstallMode` then prints `netboot=http` while the summary printed
  `https`. The final value is right; both displayed values are not.
- The resolved-settings summary prints at `installfog.sh:1078–1082`, and the
  prompt that can change it runs at `:1084`.
- `_reportNetbootProto` is only called in the Normal-server arm (`:1337`), so a
  storage node whose netboot resolves to HTTPS with neither trigger is never
  warned.

## 11. Keys read but never written

- **`snapinLocation`** — read at `utils/FOGBackup/FOGBackup.sh:157–160`, whose
  own error text tells the admin to add it to `.fogsettings` by hand. Not a
  managed key, so it survives only through the awk pass-through.
- **`storageLocationCapture`** — read at `functions.sh:5098–5135` and written
  into `STORAGE_DATADIR_CAPTURE`; `:5131` says outright that ".fogsettings can
  relocate it out from under `$storageLocation`". Also not a managed key.

Both are now named in `FOGSETTINGS.md` as the genuine hand-set examples.

Separately, `writeKeaSample()` declares `local startrange` / `local endrange`
(`functions.sh:10926`, `:10931`), so the Kea sample ignores a persisted or
`-s`/`-e`-supplied range while the ISC path honours it.

## 12. The localboot test cannot see finding 2

`tests/localboot-publish.test.sh:122–146` fabricates `10secdelay/`,
`10secdelay/i386-efi/` and `10secdelay/arm64-efi/` full of `.efi` files — a tree
`_retireStaleEfiPaths()` would have emptied. Its provenance assertions then pass
against a state a real server never reaches, and the two functions are never run
in installer order. Any fix for finding 2 needs this fixture corrected first, or
it will look already-fixed.

## Related

- `docs/FOGSETTINGS.md` — the internals, and how to rename a key
- `docs/HTTPPROTO_COVERAGE_AUDIT.md` — the phase 1 audit this follows
- `docs/adr/0015-install-settings-are-independent-keys.md`
- `docs/adr/0016-ipxe-enforces-x509-name-constraints.md`
