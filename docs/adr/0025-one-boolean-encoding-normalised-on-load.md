# One boolean encoding in `.fogsettings`, normalised on load

## Status

accepted

Follows [ADR 0024](0024-fogsettings-unified-key-model.md), which named this and
deliberately deferred it.

## Context

`.fogsettings` is sourced as shell, so a key's value is whatever literal the file
contains. Twelve boolean keys carried three encodings between them:

| Encoding | Keys |
|---|---|
| `yes`/`no` | `WEB_https_redirect`, `BOOT_rebuild_ipxe_with_my_ca`, `PKI_web_cert_publicly_trusted`, `BOOT_url_proto_forced` |
| `1`/`0` | `FOG_copy_back_old`, `DHCP_enabled`, `DB_external`, `BOOT_external_tftp_server`, `STORAGE_rebuild_nfs_exports`, `PKI_sb_enabled`, `FOG_install_lang` |
| `Y`/`N` | `FOG_send_reports` |

The flag layer mixed them *within a single variable*: `sDHCP_enabled` was
assigned `"Y"` and then `1` on the very next line, and
`sBOOT_external_tftp_server` was assigned the string `"true"`, which nothing
anywhere tested for.

So which literal a test had to use was a per-key fact, carried by nobody. That
would be a tidiness problem if getting it wrong were loud. It is silent, in two
different ways:

```sh
[[ "N" == 0 ]]     # simply false
[[ "N" -eq 1 ]]    # "N" is evaluated as an ARITHMETIC expression -- an unset
                   # variable named N, hence 0 -- rather than erroring
```

Both directions were live. `DHCP_enabled` was written `"N"` by the interactive
prompt and read with `== 0` in one place and `-eq 1` in others, so it satisfied
**neither** the enabled test nor the disabled one: FOG neither configured DHCP
nor took the "DHCP is disabled" branch.

ADR 0024 deferred this because changing values would have made the key-rename
migration a translation rather than a copy — and a translation that runs once,
against 79 old keys, is a much riskier thing to get wrong than a rename.

## Decision

**Every boolean key holds `yes` or `no`, and the value is normalised on load,
every run.**

`_normalizeBool()` maps `yes|y|1|true|on|enabled` and the corresponding
negatives, in any case, onto `yes`/`no`. `_normalizeBooleanSettings()` applies it
to the twelve keys.

Two things it deliberately does not do:

- **An unrecognised value is left alone**, not coerced. Silently turning a typo
  into `no` is how a deliberate setting disappears with nothing to show why.
- **Empty stays empty.** The interactive prompts are `while [[ -z ${KEY} ]]`
  loops; collapsing unset into `no` would stop every prompt firing and answer for
  the admin.

Normalisation runs **after** the flag shadows in `bin/installfog.sh`. Every
source of a value has fed in by that point — the value `.fogsettings` persisted,
the value the rename seed block copied off a pre-1.6 key, and the value a flag
set this run — and the flag layer was itself the worst offender for mixed
encodings. Normalising earlier would leave whatever the flags assigned
unconverted. `lib/common/input.sh` and `newinput.sh` are sourced later still and
write `yes`/`no` directly.

### Why on load, and not as a one-time rewrite

A version-marked migration would have been the obvious shape, and it is the
wrong one. `.fogsettings` is a file administrators edit by hand, and every
FOG document that says "set `X=1`" is still out there. An old encoding can
therefore arrive at any time — not only on the upgrade that renamed things.

Normalising on load is idempotent by construction, so it is also
self-repairing, and it answers ADR 0024's objection directly: `writeUpdateFile()`
only ever sees `yes`/`no`, so the key migration stays a copy. Nothing has to
know whether it is running for the first time.

### Excluded

| Key | Why |
|---|---|
| `FOG_installed` | `settingLine()` writes it unquoted and numeric to preserve the historical file format, `bin/updatefog.sh` reads it, and it records install **state** rather than a preference |
| `SVC_firewall_control` | tri-state: `configure`/`disable`/`skip`. Not a boolean; folding it to `yes`/`no` destroys an answer |
| `FOG_install_type` | an `N`/`S` enum |

The latter two are not booleans at all, so they are outside this decision rather
than exceptions to it. `tests/boolean-encoding.test.sh` asserts all three stay
off the list, so a later sweep cannot quietly fold them.

### Polarity is not in scope

`BOOT_external_tftp_server` keeps the sense it inherited from `noTftpBuild`. Only
the encoding moves, so values carry across untouched — which is what lets this be
a normalisation rather than a migration.

## Consequences

- A test against a boolean key is `== yes` or `!= yes`. Arithmetic comparisons
  (`-eq`, `-lt`) are gone, and must stay gone: they are the ones that fail
  silently rather than merely returning false.
- `tests/boolean-encoding.test.sh` greps `lib/`, `bin/` and `utils/` and fails if
  any arithmetic comparison, any `0`/`1`/`Y`/`N`/`true`/`false` comparison, or any
  such assignment reappears — including on the `s`-prefixed flag shadows.
- `tests/fogsettings-migration.test.sh` asserts end to end that a pre-rename
  file supplying all three encodings is written back uniformly `yes`/`no`.
- Nothing outside the installer reads these keys — no PHP, no `utils/` — so the
  change did not have to be negotiated with the web tier. That was checked
  rather than assumed; it is why this could be done as one commit.
- A hand-edited `.fogsettings` using any documented-anywhere spelling now works,
  which it did not before: `DHCP_enabled=1` and `DHCP_enabled=yes` are the same
  setting.
