# One trust anchor across several FOG servers

You have more than one FOG server — separate installs, each with its own
database, not storage nodes of one another. Each generated its own
`FOG Server CA` at install time, so every browser, every `curl`, and every
system trust store needs one certificate **per server**. Five servers, five
imports, five things to redo when one is rebuilt.

This document covers the ways to collapse that to one, what each costs, and how
to tell which one you are actually in.

> **If your other machines are storage nodes, stop here — this is already
> solved.** A node asks its master for a certificate automatically. See
> [Storage nodes](PKI_ZONES.md#storage-nodes). This document is for servers
> that have no master/node relationship.

---

## Table of contents

- [First: which problem do you have?](#first-which-problem-do-you-have)
- [The options](#the-options)
- [Option A: import each server's root](#option-a-import-each-servers-root)
- [Option B: a hub FOG server issues to the others](#option-b-a-hub-fog-server-issues-to-the-others)
- [Option C: your own enterprise PKI or internal ACME CA](#option-c-your-own-enterprise-pki-or-internal-acme-ca)
- [What is and is not unified](#what-is-and-is-not-unified)
- [Verifying](#verifying)
- [Troubleshooting](#troubleshooting)

---

## First: which problem do you have?

Two different things both show up as "the certificate is invalid", and they have
different fixes. Sort this out before changing anything.

**1. The client does not trust the CA.** The certificate is fine; nothing told
this machine to trust the issuer. Browsers are the usual case, and note that
importing into the *system* store does not fix them — Firefox carries its own
NSS store, Chrome reads a per-user one.

**2. The certificate does not chain to the CA you trusted.** You trusted server
A's root and are browsing to server B, which has its own unrelated root. Adding
more trust does not fix this; the servers have to be re-issued.

Tell them apart in one command, run against each server:

```bash
echo | openssl s_client -connect <ip>:443 2>/dev/null | openssl x509 -out /tmp/l.pem
openssl verify -CAfile /path/to/the/root/you/trusted.pem /tmp/l.pem
```

`OK` means you are in case 1 — a trust distribution problem. A verify error
means case 2, and the rest of this document applies.

> **Do not compare issuer names.** Every FOG install names its root
> `CN=FOG Server CA`, so two unrelated servers look identical by name. Compare
> the root's `subjectKeyIdentifier` to the leaf's `authorityKeyIdentifier`, or
> just use `openssl verify` as above.

## The options

| | Effort | Servers must trust each other | Best when |
|---|---|---|---|
| **A. Import each root** | One import per server, forever | no | 2–3 stable servers |
| **B. Hub FOG server issues** | One-time per server | yes — a hub key is copied out | several FOG servers, no existing PKI |
| **C. Your own PKI / ACME** | Depends on your PKI | no | you already run a CA |

There is no option where the servers keep their independence *and* share an
anchor. Something has to sign for everyone.

## Option A: import each server's root

The baseline, and genuinely fine for a small number of servers. Nothing changes
on the FOG side; you distribute N certificates instead of one.

Each server publishes its own anchor at:

```
https://<server>/fog/management/other/ca.cert.der
```

Since the trust-store change, each server also anchors *itself* in its own
system store at install time, so `curl` and `wget` **on** a FOG server work
against that server without `-k`. That is per-server and does not federate;
`--no-ca-trust` opts out.

Browsers still need a manual import — see [What is and is not
unified](#what-is-and-is-not-unified).

## Option B: a hub FOG server issues to the others

Pick one server as the hub. Its root stays where it is. Every other server gets
its **own** Web CA, signed by the hub's root and constrained to that server's
names, and uses it to sign its own web certificate. All leaves then chain to one
root, so one anchor covers the fleet.

```
        hub: FOG Server CA  (root, key never leaves the hub)
         ├── FOG Web CA - fog1.lan   → fog1's web certificate
         ├── FOG Web CA - fog2.lan   → fog2's web certificate
         └── FOG Web CA - fog3.lan   → fog3's web certificate
```

**Requires** a FOG version with `--web-ca-cert/--web-ca-key/--web-ca-root`. Both
the 1.6 line and the 1.5 line have them; update the installer on every server
first (`git pull`) or the flags will not parse.

### 1. Issue a CA per server, on the hub

```bash
sudo packages/pki/fog-mint-web-ca <hostname> <ip> [extra-dns-name ...]
```

`<hostname>` must be what the far server's own `hostname` reports, and any
`--extra-server-name`/`--internal-domain` values that server installs with must
be passed as extra arguments. Both go into the CA's name constraints, and a CA
constrained to the wrong names cannot sign the certificate it exists for. The
script signs a probe certificate carrying the names that server will actually
request and refuses to emit a CA that would reject it, so a mismatch fails here
rather than on the far server.

Each run writes `/root/fog-web-cas/<short>-webca.tar.gz` containing
`webca.pem`, `webca.key`, `fog-root.pem`.

### 2. Install on each server

```bash
scp root@<hub>:/root/fog-web-cas/<short>-webca.tar.gz /root/
cd /root && tar -xzf <short>-webca.tar.gz

cd ~/fogproject/bin
sudo ./installfog.sh --web-ca-cert /root/webca.pem \
                     --web-ca-key  /root/webca.key \
                     --web-ca-root /root/fog-root.pem
```

`--external-ca` is not needed; any one of the three implies it. You pass these
**once** — the files are imported into the web zone and later upgrades reuse the
import without the flags.

### 3. Anchor the hub root wherever you need it

One certificate now covers every server. On a Linux client:

```bash
curl -k -o /tmp/fogca.der https://<hub>/fog/management/other/ca.cert.der
openssl x509 -inform DER -in /tmp/fogca.der -out /tmp/fogca.crt
sudo cp /tmp/fogca.crt /etc/pki/ca-trust/source/anchors/fog-server-ca.crt   # RHEL family
sudo update-ca-trust extract
```

Debian/Ubuntu/Alpine use `/usr/local/share/ca-certificates` +
`update-ca-certificates`; Arch uses `/etc/ca-certificates/trust-source/anchors`
+ `trust extract-compat`.

### The cost, stated plainly

**Each satellite holds a CA private key.** That is the trade. The mitigation is
name constraints: `fog2`'s key can only mint certificates for `fog2`'s own
names, so a stolen key does not become a fleet-wide signing capability. This is
why each server gets its own CA rather than a copy of one shared key — never
distribute the same intermediate to several servers, and never distribute the
hub's root key at all.

If that trade is unacceptable, use Option C, or accept Option A.

## Option C: your own enterprise PKI or internal ACME CA

If you already run a CA, issue each FOG server an intermediate from it and use
the same `--web-ca-*` flags. FOG does not care that the CA came from a hub FOG
server or from step-ca; it validates the same three things either way — the key
matches the certificate, the certificate is `CA:TRUE`, and it chains to the root
you supply.

This is better than Option B when it is available: no FOG server holds signing
authority for another, and your existing rotation and revocation processes
apply.

An internal ACME CA (step-ca and similar) is the best fit overall, because the
anchor is stable while the leaves rotate automatically. See
[EXTERNAL_CA_AND_LETSENCRYPT.md](EXTERNAL_CA_AND_LETSENCRYPT.md), which covers
ACME, public Let's Encrypt's caveats, and the renewal model in full.

## What is and is not unified

Unifying the **Web** zone is what all of this does. The other zones are separate
questions.

| Zone | Unified by this? | Notes |
|---|---|---|
| Web (HTTPS vhost) | **yes** | what `--web-ca-*` targets |
| Client communication | **no** | each server keeps its own root; fog-client pins per server |
| Secure Boot | **no** | see `--secureboot-ca-cert` in [PKI_ZONES.md](PKI_ZONES.md#bringing-your-own-ca) |

**fog-client is deliberately untouched.** It pins the root of the server it
registered against, and that root is not replaced by `--web-ca-*` — which is
precisely what makes this safe to do on a running fleet without re-registering a
single machine. Clients registered to fog2 keep trusting fog2.

**Browsers are not covered by the system trust store.** Firefox uses its own
NSS store; Chrome reads a per-user one. Import the hub root by hand, once:

- **Firefox** — Settings → Privacy & Security → Certificates → View
  Certificates → Authorities → Import, tick *Trust this CA to identify
  websites*.
- **Chrome/Chromium on Linux** —
  `certutil -d sql:$HOME/.pki/nssdb -A -t "C,," -n "FOG Server CA" -i fogca.crt`

## Verifying

From any machine, per server:

```bash
echo | openssl s_client -connect <ip>:443 2>/dev/null | openssl x509 -out /tmp/l.pem
openssl verify -CAfile /path/to/hub-root.pem /tmp/l.pem
```

You want `OK`, and the issuer should read `CN=FOG Web CA - <hostname>`:

```bash
echo | openssl s_client -connect <ip>:443 2>/dev/null | grep -E '^ [0-9] s:| *i:'
```

## Troubleshooting

**The certificate did not change after installing with `--web-ca-*`.**
Fixed, but check your version. The installer used to decide whether to re-sign
the web leaf by hashing the SAN set alone, so switching CAs imported the new one
and then skipped reissue because the *names* had not changed — a clean install
that changed nothing. The signing CA is now part of that check. On a version
with the fix, the next run reissues once by itself.

**The leaf on disk is correct but the server still sends the old one.**
Check for two FOG vhosts in one file:

```bash
grep -c '^<VirtualHost \*:443>' /etc/apache2/sites-available/001-fog.conf
```

A `2` means the install upgraded across the introduction of the FOG-managed
vhost block while that migration still appended rather than replaced, so FOG's
previous vhost is sitting above the managed one — and the first matching vhost
is the one the web server uses. Update the installer and run it again; it
detects and removes the stale copy by itself now. `openssl x509 -in
"$(grep -oP "(?<=^sslpubcert=').*(?=')" /opt/fog/.fogsettings)" -noout -issuer`
is what tells you the leaf itself was fine all along.

**The far server's web tier will not start, or its certificate does not
verify.** Its leaf carries a name the CA does not permit. Every FOG leaf
includes `fogserver` and `fog-server` regardless of hostname, plus the host's
long and short names and any `--extra-server-name`. Re-mint with the missing
names passed as extra arguments. `fog-mint-web-ca` probes for this before
emitting, so this mostly appears when a CA was built by hand.

**The installer prompts for CA paths even though you passed the flags.**
Fixed. Passing `--web-ca-*` set `externalca=yes`, which triggered the
interactive prompt for the *flat* `extcacert`/`extcakey`/`extcaroot` paths —
and those were then ignored, because the command-line values take precedence.
Pressing Enter through it was harmless. On a version with the fix the run prints
the paths it is using instead.

**`Refusing to continue: the root ... carries pathlen:0`.** That root cannot
anchor an intermediate, so nothing beneath it would verify. Use a root that can,
or Option A.

**The root key is offline.** `fog-mint-web-ca` needs it to sign. Restore it, mint
every CA you need in one sitting, then take it away again — see
[Taking a key offline](PKI_ZONES.md#taking-a-key-offline).

## See also

- [PKI_ZONES.md](PKI_ZONES.md) — the three zones, layout, name constraints, key protection
- [EXTERNAL_CA_AND_LETSENCRYPT.md](EXTERNAL_CA_AND_LETSENCRYPT.md) — ACME, Let's Encrypt, renewal
- `packages/pki/fog-mint-web-ca` — issue a Web CA for another FOG server
- `packages/pki/fog-offline-ca-key` — move a CA private key off the server
- `packages/pki/fog-sign-node-cert` — how storage nodes are issued (different mechanism)
