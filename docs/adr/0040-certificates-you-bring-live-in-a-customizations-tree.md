# Certificates you bring live in /etc/fog/customizations/pki

## Status

accepted, and implemented on `working-1.6` as `_customPkiDir()` /
`_customPkiPair()` / `_ensureCustomizationsTree()` in `lib/common/functions.sh`,
with `PKI_custom_dir` recorded in `.fogsettings` and signal 0 of
`_detectExternalCertManagement()`.

## Amended 2026-09-02 — two trees, by direction, is the design

FOGProject/fogproject#1684 asked the question "One unified tree" below
deferred: can one directory be both an input and an output? **No**, and the
reason is the conflict rule, not the root it sits under. The `/opt` side
restores by *absence* — `restorePreservedCustomizations()` puts a file back
only when the rebuilt tree no longer has it, and leaves a fresh shipped file
alone. The `/etc` side adopts by *presence* — what is there overrides what FOG
would otherwise generate. One directory cannot honor both: either an
administrator's drop-in is silently outranked by whatever FOG shipped, or a
stale backup FOG made for itself silently outranks the fresh file. Two trees
with one rule each is what keeps "FOG's copy" and "yours" distinguishable in a
support thread, and the FHS split in ADR 0037 lands on the same answer for the
independent reason that binaries cannot live under `/etc`.

So the split is by **direction**, and it is the design rather than an accident
to be tidied: `/etc/fog/customizations` is written by the administrator and
only read by FOG; `/opt/fog/customizations` is written by FOG and never needs
the administrator's hand. The two readmes already say exactly that and point
at each other. The name stays `customizations` on both sides — `custom` beside
`customizations`, with opposite data directions, would be worse than either
word alone.

What the rest of `docs/SUPPORTED_CUSTOMIZATIONS.md` gets from a blessed
*input* path, item by item, is nothing today:

| item | what it needs | already has it? |
|---|---|---|
| custom-named kernels and inits | to be **found** and to be **kept** | found: GH-1688 classifies by header magic, any name, no config. kept: the backup set is (live dir) minus (source tree), a naming guarantee that holds without a location |
| the iPXE menu background | to survive the rebuild | round-tripped through `ipxe-bg/`; it is set through the UI, so a pre-run drop-in has no caller |
| replaced iPXE binaries | to survive the rebuild | round-tripped through `ipxe-legacy/`; a blessed input would have to hold binaries, which rules out `/etc` |
| reports you have written | to survive the rebuild | `restoreReports()` since GH-1580, its own mechanism |
| vhost outside the managed block | to survive the rewrite | `spliceManagedBlock()` preserves it in place |

Certificates were different on all four counts ADR 0040 lists — secret,
irreplaceable, needing SELinux labels, and previously documented by invented
example. None of the five above is any of those. A second input tree is
therefore a mechanism waiting for a customer, and it is not built. The
constraints #1684 records stand unchanged: `bin/restorekernel.sh` and
`bin/revertfog.sh` keep resolving `$fogprogramdir/customizations/kernel-backups`,
`_resolveCustomizationsDir()` stays resolved on call, and the frozen vhost
marker keeps naming `docs/SUPPORTED_CUSTOMIZATIONS.md`.

If something later does need a pre-run input that is not a certificate, it
goes under `/etc/fog/customizations/<thing>/` when it is small configuration,
and the `/opt` side stays output-only. That is the whole of the rule.

## Amended 2026-09-02 — a third name, for the chain

`web-leaf-chain.pem` joins `web-leaf.pem` and `web-leaf.key` as a documented
name in this directory, optional where the other two are required. It settles a
gap FOGProject/fogproject#1685 found: a leaf issued by an external CA is not
servable on its own. The chain has to reach the vhost, and the pair test here
deliberately excludes `PKI_web_trust_chain`, so a leaf whose intermediates are
nowhere could be adopted and then fail to build a path.

Three names rather than two does not weaken "two names, not a glob" — the
reasoning was never about the count. It was that FOG must not choose between
candidates on its own, and an explicitly documented third name chooses nothing.
A fullchain `web-leaf.pem` is also accepted, and is split rather than stored
whole: the leaf slot takes exactly one certificate, for the GH-863 reason
recorded in [ADR 0036](0036-the-web-tier-changes-pki-through-a-fixed-verb-set.md)'s
2026-09-02 amendment.

Adoption otherwise behaves as decided below. A chain that does not let the leaf
verify is a refusal, not a warning, and a self-signed root found in the supplied
material is reported for an explicit import rather than anchored — the amendment
to ADR 0036 carries both rules and the reason the second one is not optional.

## Context

[ADR 0024](0024-fogsettings-unified-key-model.md) settled how FOG uses a
certificate it did not issue: the `PKI_*` key names a **canonical** path, and the
administrator either makes that path resolve to their file or records their own
path in it. `_externallyManagedLeaf()` then answers "is this leaf mine?" by
asking whether the canonical path resolves inside the web zone. That mechanism
works and is not in question here.

What was missing is a **place**. "Outside the web zone" is anywhere at all, so
every document that explains this had to invent its own example directory —
`/etc/pki/fog/`, `/etc/letsencrypt/live/…`, `~/.acme.sh/` — and a reader
following one of them has no way to tell whether the location mattered. The
observable cost: fog-docs#178 shipped a Cloudflare recipe installing into
`/etc/fog/pki/web/leaf/`, which is *inside* the web zone, so FOG read the leaf as
its own and would have regenerated over it. Nothing was wrong with the
mechanism; there was nowhere obvious to point it at.

Two smaller problems came with that. A hand-made symlink into an arbitrary
directory inherits **that directory's** SELinux label
(`docs/PKI_ZONES.md`, "Certificate paths"), which can leave the web tier unable
to read the key it is pointed at. And there was no answer to "where do I put this
so an upgrade does not eat it" that did not require reading the installer.

## Decision

**`/etc/fog/customizations/pki/`**, recorded as `PKI_custom_dir` in
`.fogsettings`.

Three properties are load-bearing.

**It is a SIBLING of `$(_pkiRootDir)`, never a directory inside it.** This is the
whole mechanism rather than tidiness: `_externallyManagedLeaf()` compares against
the web zone directory, so a sibling answers "the administrator's" with no new
state to record, no flag to forget, and no change to the detection at all. A
subdirectory of `/etc/fog/pki` would answer the opposite, which is exactly the
mistake #178 made.

**The default is derived from `_pkiRootDir()`, not hardcoded.** It is
`$(dirname "$(_pkiRootDir)")/customizations/pki`, which on a default install is
the `/etc/fog/customizations/pki` above. Hardcoding `/etc/fog` would mean every
shell test that reached `_ensureCustomizationsTree()` created that directory on
whatever machine the suite ran on; the tests already relocate `PKI_root_dir`, and
this follows it for free.

**A dropped-in pair is adopted, with no `.fogsettings` edit.** `web-leaf.pem` and
`web-leaf.key` in that directory are signal 0 of
`_detectExternalCertManagement()`, and `createSSLCA()`'s existing
detect-then-`_linkCanonical()` step points the canonical paths at them. So the
whole procedure is "put the files there, re-run the installer" — the thing an
administrator would guess, made correct.

Adoption requires **both** files, and requires them to actually be a pair
(`_certKeyPairMatches()`, comparing the subject public key rather than an RSA
modulus, per GH-1393). Declining is the safe direction: FOG's own leaf still
serves, whereas adopting a leaf whose key is missing or mismatched points the
vhost at a certificate the web server cannot start with.

**Two names, not a glob.** A glob would have to choose between candidates on its
own, and the run where it chooses wrong repoints the vhost without saying so. An
administrator whose files are named something else records the path explicitly,
which is the route that already existed.

## The /etc versus /opt split

`$fogprogramdir/customizations` already exists and is **not** this. The two run in
opposite directions, and the `readme.txt` in each says so:

| | `/opt/fog/customizations` | `/etc/fog/customizations` |
|---|---|---|
| Written by | FOG | the administrator |
| Purpose | copies FOG makes of the admin's files before a run rebuilds the tree they lived in, and restores after | an input FOG only reads |
| Holds | `ipxe-bg/`, `ipxe-legacy/`, `kernel-backups/` | `pki/` |

The split by root is the one ADR 0037 already made for FOG's own PKI: `/etc` is
for small, secret, irreplaceable configuration a backup policy and a
config-management run are meant to capture; `/opt/<pkg>` is for a package's own
static files, and the FHS does not permit binaries under `/etc` — which is what
keeps kernels and iPXE backgrounds on the `/opt` side. Unifying the two into one
directory would have to break one of those two rationales.

## Consequences

- `.fogsettings`' own "Derived -- do not edit" header was **wrong** and is
  corrected. It said the installer "recomputes these every run, so editing a path
  here moves nothing", which is true of most canonical keys and false of the
  three that matter: `_resolveWebLeafPaths()` resets `PKI_web_vhost_cert` only
  while it holds one of four known-FOG defaults, and `createWebIntermediateCA()`
  treats `PKI_web_trust_chain` the same way. An administrator who believed the
  header had no way to discover the recorded-path route worked.
- `/etc/fog/customizations` is created on every install and gets `restorecon -RF`,
  for the reason `_migratePkiTree()` does it: a tree whose labels change the first
  time somebody runs a relabel is worse than one that is already right. This is
  what removes the label footgun above.
- Neither `readme.txt` is overwritten once present. A run that rewrote it would
  discard the note somebody left for the next person, in the one directory that
  exists for the administrator's own use.
- `PKI_custom_dir` is resolved through `_customPkiDir()` before being recorded, so
  a relocated value is preserved rather than overwritten. (Contrast
  `PKI_client_encrypt_key`/`_cert` immediately above it in `writeUpdateFile()`,
  which are hard-reassigned and therefore lose an admin path from
  `--client-cert`/`--client-key` on the next run. That is a defect, filed
  separately, not a pattern to copy.)

## Alternatives rejected

**`/etc/fog/pki/custom/`, inside the tree.** The obvious reading of "put custom
certs with the certs", and it inverts the detection: everything under the PKI root
is FOG's by definition, so a leaf there would be regenerated over. Making it an
exception would mean `_externallyManagedLeaf()` growing a carve-out, which is the
one function that must stay a single unambiguous question.

**`/etc/fog/certs/`.** Shorter, and it was the first proposal. Rejected because
`$fogprogramdir/customizations` already exists for admin-related material: a
second word for the same idea means an administrator has to learn which of
`certs`, `custom` and `customizations` holds what. Matching the existing name
costs nothing and the readmes can then explain one concept.

**One unified tree for all customizations, in either root.** Attractive, and the
right long-term shape for the rest of `docs/SUPPORTED_CUSTOMIZATIONS.md`, but it
cannot be one *location*: see the split above. Filed as its own issue, since it
also has to decide whether one directory can be both an input and an output.
Decided in the 2026-09-02 amendment above: it cannot, and the split by direction
is the design.

**Leave it to the documentation.** Zero code, and it is what was tried. The recipe
in #178 is the evidence: with no blessed location, each document picks one, and
picking wrong is silent.

## References

- [ADR 0024](0024-fogsettings-unified-key-model.md) — canonical paths, and why
  the filesystem carries the indirection
- [ADR 0036](0036-the-web-tier-changes-pki-through-a-fixed-verb-set.md) — why the
  web UI cannot simply be handed a path
- [ADR 0037](0037-the-pki-tree-lives-in-etc.md) — the /etc versus /opt reasoning
  this reuses
- `docs/PKI_ZONES.md` — "Certificate paths" and the acme.sh recipe
- fog-docs#178 — the recipe that motivated this
