# iPXE enforces X.509 name constraints, rather than FOG weakening them

## Status

accepted

## Context

FOG issues its web certificate through an intermediate. `createSSLCA()` builds a
`FOG Web CA` beneath the `FOG Server CA` root, and `_nameConstraints()` gives
that intermediate a `nameConstraints` extension listing the DNS names and IP
subnets it is allowed to issue for. RFC 5280 section 4.2.1.10 requires the
extension to be marked critical, and it is.

FOG also builds its own iPXE. When the netboot protocol is HTTPS --
`_resolveNetbootProto()` selects that automatically whenever
`rebuildIpxeWithMyCA` is set -- `buildipxe.sh` bakes `.fogCA.pem` into the
binary as `CERT=`/`TRUST=` so iPXE can validate the FOG server over TLS.

**These two features are mutually incompatible.** iPXE's `x509_extensions[]`
table knows five extensions: `basicConstraints`, `keyUsage`, `extKeyUsage`,
`authorityInfoAccess` and `subjectAltName`. `nameConstraints` is not among them,
and `x509_parse_extension()` refuses any critical extension it does not
recognise:

```c
if ( ! extension ) {
        if ( is_critical ) {
                /* Fail if we cannot handle a critical extension */
                return -ENOTSUP_EXTENSION;
```

So iPXE cannot parse FOG's own Web CA. The netboot chain dies with

```
https://<server>/fog/service/ipxe/boot.php... Operation not supported
(https://ipxe.org/3c16e283)
```

`3c16e283` is that `-ENOTSUP_EXTENSION`, "Unsupported extension", from
`crypto/x509.c`. The failure is not TLS negotiation, DNS, or the Secure Boot
chain -- the binary boots, iPXE starts and reports `HTTPS` among its features,
and only then cannot read the certificate it was given.

Both branches were affected: `working-1.6` since 2026-08-10 and `dev-branch`
since 2026-08-09, which bakes the same CA in whenever `httpproto` is `https`.

## Decision

**Teach iPXE to enforce name constraints.** Do not remove the constraints, and
do not mark them non-critical.

The criticality flag exists so that a verifier which cannot enforce a constraint
refuses the certificate rather than ignoring it. Dropping it would convert every
such verifier from fail-closed to fail-open in order to accommodate one that
happens to be ours. Removing the constraints entirely gives up the property on
every verifier, including the ones that do implement it.

The patch lives in `FOGProject/fog-ipxe` under `patches/`, applied by
`buildipxe.sh` after its `git reset --hard`, against the pinned upstream tag.
fog-ipxe overlays configuration onto a pristine upstream checkout rather than
forking it; `patches/` is the one place a C change may live, and it earns that
only when upstream cannot yet supply the behaviour and FOG cannot ship without
it. Because the pin is a fixed tag the patch cannot rot between builds.

### Enforcement is per path, not per issuer

A constraining CA binds every certificate below it, not only the one it signed.
That is why this is enforced in `x509_validate_chain()` rather than folded into
`x509_validate()` alongside the path length check. `path_remaining` can live
there because an inherited integer collapses into a single value
(`x509_set_valid()`); a set of permitted subtrees does not, and merging subtree
sets would need allocation.

Checking only the immediate issuer would be correct for FOG's own depth-3 chain
and silently wrong for anything deeper -- the failure mode being a chain that is
accepted, which is the worst direction to be wrong in. A regression test covers
exactly this: a leaf two levels below the constrained CA, violating it.

### Unenforceable constraints are refused at parse time

Only `dNSName` and `iPAddress` subtrees are implemented, because those are what
`_nameConstraints()` emits. Any other subtree type, and any `minimum` or
`maximum`, causes the *certificate* to fail to parse.

This is checked when the extension is parsed rather than when names are
compared. A constraint on a name type that no certificate in the chain happens
to use would otherwise never be examined, and the chain would be accepted
without the constraint having been enforced -- support in appearance only, which
is worse than the honest refusal we started with.

### The commonName is constrained for end entities with no SAN

Read strictly, RFC 5280 constrains the subject distinguished name through
`directoryName` subtrees, which are refused above, so nothing would examine the
commonName. That leaves a way around the constraint, because iPXE's
`x509_check_name()` accepts a commonName as a host name: a compromised
constrained CA could issue a certificate bearing only `CN=somewhere.else` and it
would be trusted for that name.

So `dNSName` constraints are applied to the commonName, but only when the
certificate carries no `subjectAltName` at all -- if it has one, the SAN is
authoritative and has already been checked -- and only to end entities, since a
CA's commonName is a label rather than a host name and constraining it would
break every chain. OpenSSL applies a broader version of the same check; this is
the subset that cannot reject a certificate whose commonName was never going to
be used as a name.

## Consequences

- FOG keeps a genuine security property instead of trading it for a boot path.
- HTTPS netboot works against FOG's own CA, which is what
  `_resolveNetbootProto()` already assumes when it selects HTTPS.
- fog-ipxe now carries C. `patches/` is a deliberate, bounded exception and the
  build fails loudly if a patch stops applying, rather than quietly producing a
  binary without it.
- Delivery is a fog-ipxe release plus a `FOG_IPXE_VERSION` bump on both
  branches. `prepareiPXEsource()` fetches and checks out the pin, so existing
  installs pick the fix up on their next installer run.
- The patch is written to upstream's standards so it can be offered to
  `ipxe/ipxe`. Doing so is a separate decision; carrying it here does not
  depend on it.

## Alternatives rejected

**Mark the constraints non-critical.** One line, fixes every deployed binary,
and OpenSSL, NSS and Go all still enforce non-critical name constraints. But it
violates RFC 5280's "conforming CAs MUST mark this extension as critical" and
converts fail-closed to fail-open for any verifier that does not implement the
extension. Acceptable only because the sole such verifier in FOG's estate is
one we control -- which is an argument for fixing that verifier.

**Add `--no-web-name-constraints`, defaulting on.** Mirrors the existing
`--no-sb-name-constraints`. Rejected because it leaves HTTPS netboot broken out
of the box, with the fix behind a flag nobody discovers.

**Drop the constraints from the Web CA.** Strictly worse than making them
non-critical: it surrenders the property on every verifier rather than only on
those that ignore the extension.
