# A host proves itself with a key, not a MAC and a shared token

## Status

proposed

Companion to [fog-client ADR-0003](https://github.com/FOGProject/fog-client/blob/master/docs/adr/0003-client-identity-in-the-application-layer.md),
which carries the client-side reasoning and the rejected alternatives. This
record exists because most of the *implementation* lands here, and because the
decision touches four ADRs already in this directory.

Nothing is implemented. The client half is unstarted and the client itself has
had no functional commit since March 2023.

## Context

`FOGPage::authorize()` resolves a host from the `mac=` parameter through
`FOGBase::getHostItem()`, then proves it with a bearer token — `hostSecToken`,
rotated each handshake with one generation of grace in `hostSecTokenPrev`. The
session key the client generates is stored verbatim in `hostPubKey`, a column
whose name says public and whose contents are a live AES-256 key.

The MAC half is already documented as unsound, in this repository, in the
function itself: *"authorize() is reachable before login and resolves the host
from a request 'mac' alone, which is spoofable by design on an imaging LAN."*

**[ADR 0039](0039-a-booting-machine-is-identified-by-firmware-second.md) is why
this is worth writing now, and it is worth being precise about what it did and
did not settle.** It gave FOG a real firmware identity — `SmbiosIdentity::pick()`
scoring four fields, with a placeholder table and a repeated-character rule that
handles the MSI UUID which sank the 2018 attempt — and it deliberately scoped
that to the **boot** path, keeping the MAC as the identity and shipping the
firmware check in `log` mode. Its own words: *"FOS and the FOG client need no
change."*

That was the right call for the problem it took on, which was **resolution**:
*which host is this?* It leaves the adjacent problem untouched, and the two are
easy to confuse. `SmbiosIdentity` reads four strings the caller supplies. It
narrows who can impersonate a host from "anyone who can spoof a MAC" to "anyone
who can also read four SMBIOS values off the machine, or claim them". Against an
unprivileged participant on the imaging LAN — the adversary FOG actually has —
that is not a bar. **Identification is not authentication, and FOG currently has
no host authentication at all.**

## Decision

**A registered host holds an asymmetric keypair and signs its requests. The
server stores only the public half.**

- `hosts` gains `hostSignKey`, `hostSignAlgo`, `hostSignEnrolled`,
  `hostSignBinding`, via the existing `ALTER TABLE` pattern in `schema.php` and
  the field maps on `Host` and `HostManager`.
- A new `service/enroll.php` accepts a public key, authenticated *either* by the
  host's existing `hostSecToken` (migrating a registered fleet, no admin action,
  the weak credential spent and cleared in the same transaction) *or* by a
  single-use nonce planted in the deployed filesystem by FOS (a machine imaged
  and never logged into).
- `authorize()` gains a signature path. `FOG_CLIENT_REQUIRE_SIGNED_AUTH` ships
  **off**, and the host list gains a column showing which hosts have converged —
  a switch nobody can see the safety of is a switch nobody throws.
- `FOGPage::clearAES()`, already reachable as **Reset Encryption Data** on the
  host and group pages, becomes the revocation verb. Revoking is
  `hostSignKey = ''`.
- `hostSignBinding` stores what `SmbiosIdentity::usable()` returns at enrollment.
  A signature that verifies against a machine whose firmware no longer matches is
  refused and surfaced as a possible cloned credential.

**This last point is a use ADR 0039 did not rule on and does not conflict with.**
0039 declined to let firmware *decide* which host is which. This does not ask it
to. The signature decides; the firmware fingerprint is a consistency check on a
credential that has *already* proved itself, and its only outcome is a refusal
plus a flag for an administrator. `SmbiosIdentity` is reused rather than
duplicated precisely because it already encodes which values are worthless.

## Why this shape, in this repository's terms

**[ADR 0027](0027-api-tokens-are-a-separate-hashed-credential.md) already made
this argument for the other credential.** Its objection to `uAPIToken` is that it
is stored in plaintext and re-displayed forever, that every future emitter is one
oversight away from being the next disclosure, and that a database backup is a
set of working credentials — a defect shape FOG has shipped three times.

`hostPubKey` and `hostSecToken` are the same shape and worse: they are not
merely disclosable, they are *live session keys and bearer tokens for every host
in the fleet*, and no UI ever needed to display them. One read-only compromise —
SQLi, a stolen backup, an exposed phpMyAdmin — is the whole fleet. A public key
in that column is worth nothing to whoever steals it.

The reasoning is 0027's, applied one layer down. If 0027 is accepted, this
follows from it.

**It also keeps the direction 0036/0037/0040 established.** Those moved PKI to a
fixed verb set, out of the web tier's reach, into `/etc/fog/pki`, with
bring-your-own material quarantined in a customizations tree. The obvious
alternative here — issuing an X.509 certificate per client and doing mTLS — runs
against all of that: it needs an **online issuing CA with a live key**, months
after [GHSA-94p8-jg9j-99v4](https://github.com/FOGProject/fogproject/security/advisories/GHSA-94p8-jg9j-99v4)
caused one to be taken offline, plus CSR handling in PHP, serial management, a
renewal story for machines switched off all summer, and revocation that in
practice becomes a database allowlist keyed on serial — at which point the
certificate is decorative. `SSLVerifyClient` is also Apache configuration, and
this vhost carries authenticated client traffic, unauthenticated FOS traffic and
browser traffic together.

A keypair needs none of it: no CA, no chain, no expiry, no CRL, no vhost change.
Verification is `sodium_crypto_sign_verify_detached()`, core PHP since 7.2.

## Consequences

- **A net deletion.** `certEncrypt`/`certDecrypt`/`aesencrypt` in `FOGBase.php`,
  the `#!en=`/`#!enkey=` envelope, and eventually
  `hostPubKey`/`hostSecToken`/`hostSecTokenPrev`/`hostSecTime` all exist to build
  a secure channel over an insecure one. TLS does that, and has been reviewed by
  more people.
- **HTTPS becomes mandatory** for the new client protocol. Deliberate: this is a
  new client against a new server, and the installer already builds and anchors a
  CA.
- **A prerequisite this repository owns.** The deploy-time enrollment path is
  only as trustworthy as the FOS-to-server channel, and that channel is not
  authenticated today: `status/hostgetkey.php` is MAC-resolved with no auth — its
  own "Aisle 016" comment says so — and `FOG_HOSTKEY_ALLOWED_SOURCES` defaults to
  empty, while FOS fetches with `curl -Lks`. Worth fixing whether or not this ADR
  is adopted; required before the enrollment blob is worth much.
- **The client cannot do Ed25519 as it stands.** .NET Framework 4.5.2 has none
  and Mono's `ECDsaCng` is unusable, so this depends on either a BouncyCastle
  dependency or the runtime move in fog-client ADR-0002. Server-side there is no
  such constraint.
- **`authorize()` stays byte-for-byte during migration.** Both paths are accepted
  until the setting is thrown.

## A documentation correction that falls out of this

`docs/EXTERNAL_CA_AND_LETSENCRYPT.md` and the corresponding `fog-docs` page state
that a Let's Encrypt certificate on the vhost breaks fog-client. **On this branch
that is no longer true.** The client pins `srvpublic.crt` — the client zone's own
leaf — while its TLS callback short-circuits on `SslPolicyErrors.None`, so an LE
certificate is accepted like any other publicly-rooted one. The claim describes
the layout from before the client-communication leaf got its own key, which is
the coupling the zone split removed. The recommendation to run an internal ACME
CA to work around it is advising work this repository already made unnecessary.
