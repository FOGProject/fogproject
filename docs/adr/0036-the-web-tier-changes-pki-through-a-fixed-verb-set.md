# The web tier changes PKI through a fixed verb set, never through a path

## Status

accepted, and implemented on `working-1.6` as `packages/pki/fog-pki-admin` plus
`_installPkiAdminHelper()`.

## Context

`FOGConfigurationPage::certificates()` was read-only. Everything an
administrator might want to *change* — trust a corporate root, say the web
certificate is publicly issued, turn the HTTPS redirect on — was done by
re-running the installer with flags or by hand-editing `.fogsettings`.

Making it writable runs straight into the reason the page exists at all. Its
headline card asks whether this web application can read a CA private key, and
the answer is supposed to be no, because **PHP is the threat model**. The keys
live at `0400 root:root` in `0700` directories precisely so a compromise of the
web tier cannot reach them. So "let the page change the PKI" is, stated
honestly, "let the thing we assume may be compromised change what this server
trusts".

Two of the four things GH-1121 asked for cannot be done by the web user at all:

- `.fogsettings` is `0600 root:root`, and `docs/FOGSETTINGS.md` states the rule
  flatly — the only non-root reader is `Route::whoami()`, reading the published
  `.fogsettings.pub` subset.
- `pki/root/ca`, `pki/web/ca`, `pki/web/leaf` and `pki/secureboot` are `0700
  root:root`, so the web tier cannot read even the **public** Web CA
  certificate, let alone hand it out.

And one of them is worse than it looks. `.fogsettings` is **sourced as shell by
root** on the next installer run. A helper that let the web tier write an
arbitrary `key=value` into it is not a permissions nicety with a sharp edge; it
is a root shell with extra steps. `WEB_https_redirect="no'; curl … | sh; #"`
executes as root at install time, and nothing in between would report it.

FOG already answers this shape twice — `fog-sign-kernel` and
`fog-sign-node-cert`. Both are root-owned helpers the web user reaches through
a narrow `sudoers` rule, and both take **no path arguments**, because that is
what stops a compromised web server naming its own key.

## Decision

A third helper of the same shape, `fog-pki-admin`, and the generalization of
the rule those two encode:

**The web tier names a VERB and an allowlisted token. It never names a path, a
key, or a value that is not drawn from a fixed set.**

Five verbs, and nothing else:

| verb | what the caller may say | what the helper decides |
|---|---|---|
| `status` | nothing | which certificates and key paths exist; emits public metadata only |
| `export <slot> <reqid>` | one of eight slot names, a 32-hex id | which file each slot resolves to |
| `import-root <reqid>` | a 32-hex id | whether the upload is a self-signed, in-date CA; where it lands |
| `clear-root` | nothing | — |
| `set-preference <key> <value>` | one of **three** keys, `yes` or `no` | — |

Three consequences are the whole point:

- **The `^(yes|no)$` pattern is the security boundary, not a validation
  nicety.** It is what makes writing into a root-sourced shell file safe, so it
  lives on the far side of `sudo` where a compromised web tier cannot remove
  it. A duplicate check in PHP would be harmless; the helper's is load-bearing.
- **`set-preference` refuses a key the file does not already carry.** It never
  appends, so it cannot move a managed key past `## End of FOG Settings` into
  the region `writeUpdateFile()`'s merge treats as the administrator's own
  lines.
- **`import-root` keeps only self-signed certificates.** Anchoring an
  intermediate would trust it *as a root*, widening what the host accepts.
  `_resolveTrustAnchor()` filters the same way, so the two halves cannot
  disagree.

**The readability check stays in PHP.** The helper reports the private keys'
*paths*; the page tests `is_readable()` itself. The helper runs as root, so
"root can read it" answers a different question from the one the card asks.

**The gate is `system.pki`, deny by default.** No schema step seeds it, so only
a holder of `*` has it until an administrator grants it — the way
`system.export` and `impersonate.start` arrived. `settings.edit` was the
alternative and is wrong for the same reason it was wrong for the database
dump: six page nodes already map onto it, and "may edit the OUI table" is not
"may decide what this server trusts".

**Rotating FOG's own root is not offered.** The page composes the
`installfog.sh` invocation and states the cost. It changes the certificate
every registered fog-client pins, so every client stops authenticating until it
is re-pinned; that is a migration with a re-pin story, and a web form should
not be able to start one in a click.

## Consequences

- A third `sudoers` drop-in on a master. All three are `visudo -cqf`-validated
  before installation and removed when their precondition stops holding —
  here, a server that is a storage node, which does not serve the management UI
  at all.
- `_resolveTrustAnchor()` now reads `PKI_web_external_root_cert` directly.
  Before, an imported root reached the anchor only through the chain file,
  which `validateExternalCA()` writes — and that runs only when all three of
  `--ca-cert`/`--ca-key`/`--ca-root` were supplied. "Just trust our corporate
  root" is the narrower ask, and without this the next installer run rebuilt
  the anchor without it and silently undid the import.
- The imported root is copied to a canonical path inside the web zone, and
  *that* path is what `.fogsettings` records. `--ca-root` persists the
  administrator's **source** path, which is routinely a temp file that is gone
  by the next run.
- The three preferences take effect on the next installer run and not before.
  The page says so at the point of change, because that is the difference
  between a flag — read back before it runs — and a checkbox.
- The helper duplicates `_splitPemBundle()` and the anchor rebuild from
  `lib/common/functions.sh`. An installed server has `bin/` but no `lib/`, so
  there is nothing to share; `tests/pki-admin-helper.test.sh` and
  `tests/trust-anchor.test.sh` pin both copies against the same behavior.
- `acmeLeaf` is not settable, because GH-1120 retired it. Whether a leaf is
  managed elsewhere is derived from whether `PKI_web_vhost_cert` resolves
  outside the web zone, and the page reports that derived state rather than
  offering a flag that could disagree with the filesystem.

## Alternatives rejected

**A `sudoers` rule on `sed`, or on a generic settings writer.** Reduces to
arbitrary root code execution through `.fogsettings`, as above.

**Passing paths to the helper and validating them there.** The moment the
caller names a file, the helper's job becomes proving a path is safe — symlinks,
`..`, TOCTOU, bind mounts — instead of never being handed one. The two existing
helpers already made this choice and it is the reason they are short enough to
audit.

**Publishing the Web CA and vhost certificates into `management/other/` so the
web tier could read them directly.** Would have avoided a `sudo` call for the
downloads, at the cost of a second copy of every certificate to keep in step
and a new set of files in the document root. `status` and `export` cover it
with no new published state.

**Letting the page write `.fogsettings` through a `.fogsettings.pub`-style
writable subset.** The public file exists so secrets can stay unreadable; a
*writable* counterpart would have to be merged back by root anyway, which is
this helper with a queue in front of it and a window in which the two disagree.

**`BOOT_url_proto_forced` in the allowlist.** Forcing netboot to HTTPS with
neither steering key set is GH-1116's "legal but warned" case: it breaks PXE for
machines that cannot fix themselves. Not a thing a misclick should reach.

## References

- Issue: FOGProject/fogproject#1121, under FOGProject/fogproject#1116
- [ADR 0024](0024-fogsettings-unified-key-model.md) — the key model, and why
  `acmeLeaf` is derived rather than stored
- [ADR 0015](0015-install-settings-are-independent-keys.md) — the three
  preferences are independent keys
- `docs/PKI_ZONES.md` — the zones the slots name
- `docs/FOGSETTINGS.md` — the reader table this helper is now the second entry in
