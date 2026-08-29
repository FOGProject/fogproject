# API tokens are a separate, hashed credential

## Status

proposed

## Context

FOG has authenticated its REST API with two headers since the API was written:
`fog-api-token`, a server-wide shared secret from FOG Configuration, and
`fog-user-token`, a per-user secret from the API tab. Both are required
together. HTTP basic auth substitutes for the user half, never for the server
half.

The reasoning behind the pair is sound and worth stating, because it is easy to
read the design as belt-and-braces and miss the actual argument: **to obtain
either token you already had to be authenticated to the web UI.** Neither is a
standalone way in, and rotating the global one requires the same UI access. An
attacker holding one token holds half a credential.

GH-1324 then added `Authorization: Bearer`, carrying the *same* per-user token,
standalone. That is a better transport — one header, RFC 6750, what every
client and generated SDK expects — and it is correct that a 512-bit CSPRNG
secret does not need a chaperone. But it changed the value of a leaked
`uAPIToken` from half a credential to a whole one, and two disclosure paths
were found immediately afterwards (GH-1325, GH-1326): the management export
emitted `uAPIToken` and the password hash in the clear, and a single-entity
`GET /fog/user/{id}` returned both.

Those are fixed. What is not fixed is the underlying property: **`uAPIToken` is
stored in plaintext and re-displayed in the UI forever.** Every future emitter
is one oversight away from being the next disclosure, and a database backup is
a set of working credentials. FOG has shipped this defect shape three times now
(GH-1323, GH-1325, GH-1326), which is evidence about the shape rather than
about any one emitter.

There are two further problems the pair cannot solve, independent of secrecy:

- **One token per user.** Rotating it breaks every integration that user owns
  simultaneously, which is why nobody rotates.
- **No service accounts.** A token belongs to a person who can also sign in.

## Decision

A Bearer token is a **new and separate credential**, not a new spelling of
`uAPIToken`.

- **`fog-api-token` and `fog-user-token` are untouched.** Same storage, same UI
  display, same `User API Enable` gate, same requirement to send both. Existing
  integrations are unaffected and nothing is deprecated.
- **`Authorization: Bearer` accepts only the new tokens.** When the new store
  lands it stops accepting `uAPIToken`, so each credential has exactly one
  spelling and there is no ambiguity about what a Bearer token *is*. This
  un-ships part of GH-1324 while it is days old on a beta branch, which is the
  cheapest moment it will ever be un-shipped.
- **New tokens are hashed at rest and shown once**, at creation.
- **N tokens per user**, each with a label, so one integration can be rotated
  without touching the others.
- **Each token carries its own enabled/disabled flag**, independent of the
  user-level `User API Enable`, which continues to govern only
  `fog-user-token`.
- **Every token is owned by a user** and acts with that user's roles. A service
  account is a user row that cannot sign in to the UI, not a token without an
  owner.

### Consequences

The pair keeps its original security property — a leaked `uAPIToken` is once
again half a credential, because Bearer no longer accepts it. The new path is
protected by a different property: the server does not hold the secret at all.
Both are sound, for different reasons, and neither depends on the other.

**There is no migration.** No existing token changes, no install has to do
anything, and show-once applies only to tokens created after the change. This
is the decisive practical advantage over hashing `uAPIToken` in place, which
would have forced a choice between "nobody ever gets a show-once" and
"everybody re-issues on upgrade".

The cost is two token systems to maintain and document.

## Hashing: unsalted SHA-256

Store `SHA-256(token)`, indexed, and look up by hash. **No salt, no pepper.**

A salt defeats *precomputation* — one rainbow table or one dictionary pass
cracking many hashes at once. That requires a guessable input. These tokens are
`bin2hex(random_bytes(64))`: 512 bits from a CSPRNG, no dictionary, and no
constructible table. Salt therefore buys nothing here, while costing the
ability to look a token up: with a per-row salt you cannot compute the hash
until you know which row you are checking, so you must either scan every token
and compare (O(n) per request, growing without bound) or embed a lookup id in
the token itself.

A pepper — HMAC with a key held outside the database — is the same story with
an added key-rotation problem: rotating it invalidates every token at once.

bcrypt is wrong for a different reason: it is deliberately slow, and that cost
would be paid on every API request rather than once per login.

**The invariant this rests on, which must be stated because it is what a future
change would silently break:** the token must remain CSPRNG-generated and at
least 256 bits. If it is ever shortened, made user-choosable, or derived from
anything predictable, this decision is void and salting becomes necessary.

## Deleting a user must destroy its tokens

Tokens live in a child table, so this has to be explicit. `Route::delete()`
funnels to `deletemass()` and skips `destroy()` cascades — the defect class
already recorded for API deletes — so a token row not deliberately destroyed
with its owner becomes a live credential belonging to nobody.

This is also the answer to the one thing the per-token flag gives up: with no
user-level gate over Bearer tokens, there is no single switch that revokes
everything an account holds. Offboarding disables or deletes the *account*, and
that must reach the tokens.

## Alternatives considered

**Hash `uAPIToken` in place.** Rejected. It stops the UI re-displaying the
token, which is a behavior change to a credential people rely on being able to
re-read, and it forces the migration choice described above. It also conflates
"make the API transport modern" with "make token storage safe" — two changes
with different risk profiles.

**Keep Bearer accepting `uAPIToken` as well.** Rejected. As long as a plaintext,
UI-visible, DB-readable value is a complete standalone credential, the exposure
that motivated this ADR is still present; the fix would only be defending it
emitter by emitter, which is the game already lost three times.

**Tokens with no owner.** Rejected. FOG's authorization is entirely per-user and
role-based, so an ownerless token would need a parallel permission model, and
every `auditLog` row keys off the acting user — attribution would be lost
precisely for the automated callers where it matters most.

**Full OAuth 2.0, FOG as authorization server.** Rejected as disproportionate:
token issuance, refresh, client registration and consent is a large
security-critical subsystem, and a half-built one is worse than a random string
in a header. The problems OAuth solves — delegated authorization, third-party
apps acting for a user — are not FOG's. FOG as a *resource server*, validating
a JWT minted by an IdP it already trusts through the ADR 0014 seam, remains a
reasonable future addition and is still Bearer transport.

## Open questions

- Token format. A plain hex string keeps the current shape; a prefixed format
  (`fog_…`) is greppable in leaked-credential scanning and leaves room for a
  lookup id should the no-salt decision ever be revisited.
- Whether `last used` is recorded per token. It is what makes it safe to delete
  a token nobody can account for, at the cost of a write on the hot path.
- Where the management UI lives: the per-user API tab, a central pane listing
  every token on the server, or both. This is a convenience question and not a
  security property; it does not block the rest.
