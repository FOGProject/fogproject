# Storage-node certificate issuance — live test results

> **Headline:** a real `installtype=S` install cannot obtain a certificate today.
> Two independent defects stop it before the PKI is even reached, and a third
> stops it at the CA. All four findings below were reproduced on a purpose-built
> Rocky 9.8 node (`fognode1`, 10.255.30.11) installed by `installfog.sh` against
> the 1.6 master, not simulated.

## Five-node matrix

One purpose-built Rocky 9.8 storage node per master, each a genuine
`installtype=S` install driven by `installfog.sh -y`.

| node | master | master ver | `Creating SSL Private Key` | registered | `web` | `signing` |
|---|---|---|---|---|---|---|
| fognode1 | 10.255.20.1 | 1.6 (patched `nodecert.php`) | fixed mid-run | after manual SELinux boolean | 500 name constraints | 500 no SB CA |
| fognode2 | 10.255.25.2 | 1.6 | OK | automatic | 409 no hostname | 409 no hostname |
| fognode3 | 10.255.22.2 | 1.6 | OK | automatic | 409 no hostname | 409 no hostname |
| fognode4 | 10.254.25.2 | 1.5.10.2258 | OK | **correctly reported failed** (HTTP 401) | Skipped | Skipped |
| fognode5 | 10.253.25.2 | 1.5.10.2258 | — | — | — | — |

fognode5 did not run: `10.253.25.2` has firewalld active with 3306 closed to
every host, so `Checking connection to master database` fails before FOG reaches
anything under test. That box is itself a storage node of `10.254.25.2` and has
never needed to accept inbound MySQL; using it as a master needs the port opened.

What the matrix establishes:

- **The `prompt = no` CSR fix holds.** `Creating SSL Private Key....OK` on three
  independent fresh installs. It hard-failed on fognode1 before the fix, which is
  what exposed it.
- **The `fog.te` `mysqld_port_t` rule holds.** fognode1 needed
  `httpd_can_network_connect_db` flipped by hand to register at all; fognode2,
  3 and 4 shipped with the rule and registered — or failed honestly — unaided.
- **The `registerStorageNode` status check earns its keep.** fognode4's master
  answered HTTP 401 and the installer said `Failed` with the code and the URL.
  Before the change that line read `Done`, and the missing node would only have
  surfaced later and elsewhere, as a refused certificate.
- **No node obtained a certificate.** Every 1.6 master refused, for the same
  root cause in two different disguises: with the patch a node's IP-derived name
  fails the CA's name constraints (500), without it the endpoint refuses before
  building a name at all (409).

## Blockers found on a real node install

| # | Defect | Effect |
|---|---|---|
| 1 | `fog.te` has no `mysqld_port_t` rule | Node web tier can't reach the master DB under SELinux; every page 308s to `?node=schema` |
| 2 | `registerStorageNode`'s second curl lacks `-L`, and prints `Done` unconditionally | Registration silently no-ops behind any redirect |
| 3 | Node record is named after its IP — `create_update_node.php` discards the posted `name=` | `DNS:<ip>` can't satisfy the Web CA's DNS name constraints → 500 |
| 4 | `_installNodeCertSigner` writes SB CA paths unguarded | `type=signing` 500s on any server without a Secure Boot CA |

### 1. SELinux: no rule for the master's database port

`packages/selinux/fog.te` grants `httpd_t` `name_connect` on `http_port_t`,
`ftp_port_t` and `ssh_port_t`, and reasons explicitly about each. There is **no
rule for `mysqld_port_t`**, and the module's "WHAT DOES *NOT* NEED A RULE HERE"
section never mentions the database.

That is correct for a master, whose database is local. A **storage node's** web
tier must reach the *master's* database over TCP. Under enforcing, with both
`httpd_can_network_connect` and `httpd_can_network_connect_db` off (the default,
and the installer sets neither), php-fpm cannot open that connection:

```
thrown in /var/www/html/fog/lib/db/pdodb.class.php on line 664
```

`DatabaseManager::_getVersion()` then leaves `$mySchema` at 0, which
`schemaNeedsDeploy()` reads as "behind" — deliberately, per the comment at
`fogbase.class.php:3803` — so **every** request on the node, including
`/maintenance/*`, 308s to `../management/index.php?node=schema`.

Confirmed by flipping the boolean at runtime on the node:

```
create_update_node.php   before: HTTP 308      after: HTTP 200
```

CLI PHP is unaffected, which is why `Checking connection to master database....OK`
passes during install — that check runs from the shell, not from php-fpm.

**Fixed** in `packages/selinux/fog.te` (module 1.1.0 → 1.2.0): a narrow
`allow httpd_t mysqld_port_t:tcp_socket name_connect;` rather than the blanket
boolean, consistent with the module's own stated philosophy —
`httpd_can_network_connect_db` would additionally grant every other database port
type in the policy (postgresql, redis, mongod…) on every FOG server, including
masters that never reach a database over TCP at all.

Verified on the node with the boolean explicitly turned back **off**, so the
module alone accounts for the change:

| check | before | after |
|---|---|---|
| `create_update_node.php` | 308 | 200 |
| `check_node_exists.php` | 500 | 200, body `exists` |
| `management/index.php` | 308 → `?node=schema` | 200 |
| AVC denials (`ausearch -m avc -ts recent`) | — | 0 |

Note this is why the module is built from source at install time rather than
shipped precompiled — see the BUILDING note in `fog.te`. Existing nodes pick the
rule up on their next installer run.

### 2. Registration fails silently behind a redirect

`functions.sh:443-449`:

```bash
storageNodeExists=$(curl -X POST ... -kL  .../check_node_exists.php -o -)
curl -s -k -X POST -d "newNode" ...       .../create_update_node.php
echo "Done"
```

The existence check follows redirects; the registration POST does not, and the
`Done` is printed unconditionally regardless of HTTP status. With defect #1 in
play the install printed:

```
 * Node being registered.......................................Done
```

while the master's node list was unchanged. The node then asked for a
certificate and was correctly refused with `no storage node is registered at
10.255.30.11`. Adding `-L` and checking the status would have surfaced the real
problem at the point it happened.

### 3. A node's record is named after its IP

`functions.sh:448` registers with `name=$(echo -n $ipaddress|base64)`. Once
registered, node id 6 was `name=10.255.30.11`, `ip=10.255.30.11`.

With no PTR for the node (confirmed: `gethostbyaddr("10.255.30.11")` returns the
address unchanged), `nodecert.php` falls back to that name and emits
`DNS:10.255.30.11`, which is not beneath any permitted subtree of the Web CA:

```
HTTP 500
{"error":"the issued certificate does not verify -- a requested name is probably
          outside the CA's name constraints"}
```

So even with #1 and #2 fixed, a stock node still gets no certificate unless
reverse DNS exists, or the record is renamed by hand to something under the CA's
permitted domains. Renaming node 3 to `debian.lan` is what made the successful
issuance in section 1 below possible.

**Fixed** in `bce22c403`. The posted `name=` was never the whole story:
`create_update_node.php` opened with `$name = $ip = $stripped['ip']` and
discarded it, so the field had no effect and no value the installer sent could
have helped. Three changes:

- the installer derives a name from `hostname -f` (not `$hostname`, which a
  `.fogsettings`-seeded node install never has — that path skips `input.sh`);
- `create_update_node.php` honours it, but only when it is a real hostname and
  is not already taken. `ngmMemberName` is UNIQUE and `FOGController::save()`
  inserts with `ON DUPLICATE KEY UPDATE`, so an unchecked name collision would
  silently rewrite the other node's row rather than fail — and hostnames are not
  unique across a fleet (two default RHEL installs are both
  `localhost.localdomain`). Anything unusable falls back to the address, which
  is the old behavior exactly;
- `nodecert.php` refuses to put an IP literal in a DNS SAN. `FILTER_VALIDATE_DOMAIN`
  accepts `10.0.0.5` as a hostname, so without an explicit `FILTER_VALIDATE_IP`
  test the request reached the signer and died at verify. Existing IP-named nodes
  now get a 409 naming the two remedies instead.

Verified against a CA carrying this master's exact permitted subtrees:

```
IP:10.255.30.13, DNS:10.255.30.13         signed=yes  verify=FAIL
IP:10.255.30.13, DNS:fognode1.lan         signed=yes  verify=PASS
IP:10.255.30.13, DNS:fognode1.example.org signed=yes  verify=FAIL
```

All three sign — OpenSSL applies name constraints when verifying, never when
signing, which is why `fog-sign-node-cert`'s own `openssl verify` step is what
catches this. The third line is the CA's declared boundary working as intended:
a node in a domain the admin never listed still needs `--internal-domain`.

#### Live confirmation

A sixth node — Rocky 9.6, `fognode1.lan` at `10.255.30.41`, no PTR record — was
built and installed against the 1.6 master at `10.255.20.1` with all five fixes
in place. It is the first stock storage-node install in this campaign to finish
holding a certificate.

```
 * Checking if this node is registered.........................Done
 * Node being registered as fognode1.lan.......................Done
 * Requesting a web certificate from the master................Done
```

The record on the master, and the certificate on the node:

```
id=7  name=fognode1.lan  ip=10.255.30.41  enabled=1

subject=CN=fognode1.lan, O=FOG Project, OU=FOG Web UI
issuer =CN=FOG Web CA,   O=FOG Project, OU=FOG Web UI
X509v3 Subject Alternative Name: IP Address:10.255.30.41, DNS:fognode1.lan
X509v3 Extended Key Usage: TLS Web Server Authentication
X509v3 Basic Constraints: critical CA:FALSE

openssl verify -CAfile .nodeChain.pem .webLeaf.pem   ->  OK
```

httpd is serving that leaf (`SSLCertificateFile /opt/fog/pki/web/leaf/.webLeaf.pem`),
and the master logged the issuance:

```
FOG nodecert: issued a web certificate to storage node 10.255.30.41
              for IP:10.255.30.41, DNS:fognode1.lan
```

Note the name came from `hostname -f`: `.fogsettings` was seeded without a
`hostname` entry, so this is the upgrade path where `$hostname` is unset — the
case that would have silently fallen back to the address before.

Three further checks against the live master:

- **Legacy nodes.** Renaming the record to `10.255.30.41` and re-requesting
  returns `HTTP 409` with `its Storage Node name ("10.255.30.41") is not usable
  as one`, instead of the old `500` about name constraints. Renaming it back and
  replaying the identical request returns `200` with
  `names: ['IP:10.255.30.41', 'DNS:fognode1.lan']` and a leaf that verifies — so
  the remedy the message names is the remedy that works.
- **Collision guard**, run against the master's real rows: a posted
  `fognode1.lan` or `debian` (both taken) falls back to the address; `fognode2.lan`
  (free) is accepted. No node can take a name that would rewrite another's row.
- **Idempotency.** A second installer run reports `Node is registered`, emits no
  certificate request at all, and leaves the leaf's serial and `notBefore`
  unchanged. Neither guard re-fires on upgrade.

---


Tested against the lab 1.6 master at `10.255.20.1` (`1.6.0-beta.3286`), exercising
`packages/web/service/nodecert.php` + `packages/pki/fog-sign-node-cert` exactly the way
`_requestNodeCert()` does: fresh keypair, CSR, HMAC-SHA256 over
`"<type>\n<base64 csr>"` keyed with `FOG_STORAGENODE_MYSQLPASS`.

Lab inventory (all five are `installtype=N` servers; some are cross-linked as
storage-node records of each other):

| Host | Version | Can issue node certs? |
|---|---|---|
| 10.255.20.1 | 1.6.0-beta.3286 | yes |
| 10.255.25.2 | 1.6.0-beta.3288 | yes |
| 10.255.22.2 | 1.6.0-beta.3288 | yes |
| 10.254.25.2 | 1.5.10.2258 | no — no `nodecert.php` |
| 10.253.25.2 | 1.5.10.2258 | no — no `nodecert.php` |

## Summary

| Test | Result |
|---|---|
| `type=web`, loopback from the master's own node record | **PASS** |
| `type=web`, from a remote node with no PTR (pre-patch) | **409** — blocked before signing |
| `type=web`, from a remote node, patched, name outside CA constraints | **500** — name constraints |
| `type=web`, from a remote node, patched, name inside CA constraints | **PASS** |
| `type=signing` | **500** — the Secure Boot CA is not present on this server |
| `type=signing`, ever requested by an installing node | **never happens** — no caller exists |

---

## 1. `type=web`, remote node — PASS

Requested from `10.255.25.2` (`fogdebian`), a genuinely separate machine holding storage
node record id 3 on the master. This is the real cross-machine path, not loopback.

```
HTTP 200

LEAF   subject = CN=debian.lan, O=FOG Project, OU=FOG Web UI
       issuer  = CN=FOG Web CA, O=FOG Project, OU=FOG Web UI
       notBefore = Aug 11 14:33:05 2026 GMT
       notAfter  = Nov 13 14:33:05 2028 GMT      (825 days)
       Basic Constraints:     critical  CA:FALSE
       Extended Key Usage:    TLS Web Server Authentication
       Subject Alternative Name: IP Address:10.255.25.2, DNS:debian.lan

CHAIN  [1] CN=FOG Web CA     issuer CN=FOG Server CA
       [2] CN=FOG Server CA  self-signed

openssl verify -CAfile <root> -untrusted <webca> → nleaf.pem: OK
```

The earlier loopback run (from the master's own `DefaultMember` record) produced the same
shape and showed the Web CA's constraints in full:

```
[1] CN=FOG Web CA   CA:TRUE, pathlen:0
    Name Constraints: critical
      Permitted: DNS:7550precision.lan, DNS:lan, DNS:fogserver, DNS:fog-server,
                 DNS:fog.7550precision.com, DNS:7550precision.com, DNS:7550precision,
                 IP:10.0.0.0/255.0.0.0, IP:172.16.0.0/255.240.0.0,
                 IP:192.168.0.0/255.255.0.0, IP:127.0.0.0/255.0.0.0,
                 IP:10.255.20.1/255.255.255.255
```

Confirms the hierarchy: **the node leaf is signed by the Web CA intermediate, never by
the root.** The root appears only as the anchor in the returned chain. `pathlen:0` means
a node's certificate can never be used to mint another CA.

### The node's name must fall inside the CA's permitted DNS subtrees

With the record named `debian`, the same request returned:

```
HTTP 500
{"error":"the issued certificate does not verify -- a requested name is probably
          outside the CA's name constraints"}
```

`debian` is not in the permitted DNS set. Renaming the record to `debian.lan` succeeded,
because a DNS name constraint covers the name and everything beneath it, and `DNS:lan`
is permitted (it comes from the master's own domain via `_defaultServerNames()`).

This is a real operational constraint on the feature: **node names have to sit under a
domain the master's Web CA was minted with.** The constraints are fixed at mint time, so
widening them later means removing and re-issuing the intermediate.

---

## 2. `type=signing` — FAIL (500)

Same node record, same authentication path, only `type` changed:

```
HTTP 500
{"error":"the signing CA is not present on this server"}
```

Verbatim from `fog-sign-node-cert`:

```bash
if [[ -z $cacert || ! -f $cacert ]]; then
    die "the ${type} CA is not present on this server"
fi
```

The endpoint authenticated the node, authorized it, staged the CSR and invoked the helper
through sudo correctly. The helper then found no CA to sign with.

### Root cause

`_installNodeCertSigner()` (`lib/common/functions.sh:3247-3252`) writes the config the
helper reads:

```bash
echo "PKI_WEB_CA_CERT=${sslcapem}"
echo "PKI_WEB_CA_KEY=${sslcakey}"
echo "PKI_ROOT_CERT=${rootCAPem}"
echo "PKI_SB_CA_CERT=$(_pkiZoneDir secureboot)/ca/.fogSBCA.pem"
echo "PKI_SB_CA_KEY=$(_pkiZoneDir secureboot)/ca/.fogSBCA.key"
```

The **web** paths are gated — a few lines above, the function deletes the helper, the
config and the sudoers rule outright when there is no usable Web intermediate:

```bash
if [[ $installtype == [Ss] ]] || \
   [[ -z $sslcapem || ! -f $sslcapem || $sslcapem == "$rootCAPem" ]]; then
    rm -f "$helper" "$conf" "$sudoersfile" >>$error_log 2>&1
    return 0
fi
```

The **Secure Boot** paths get no equivalent check. They are written unconditionally,
whether or not a FOG Secure Boot CA was ever minted.

On this server it was not. `/opt/fog/.fogsettings`:

```
secureboot='1'
secureBootKey='/opt/fog/pki/secureboot/admin-MOK.key'
secureBootCert='/opt/fog/pki/secureboot/admin-MOK.pem'
secureBootMokCert='/opt/fog/secureboot/MOK.pem'
```

`admin-MOK.*` is the name `_ensureSecureBootKeys()` copies an **admin-supplied** Secure
Boot key to (`functions.sh:6666-6667`), and `secureBootMokCert` still points at the flat
pre-restructure `/opt/fog/secureboot/MOK.pem` rather than `${cadir}/.fogSBCA.pem`, which
is only assigned at the very end of `createSecureBootIntermediateCA()`
(`functions.sh:6885-6887`). So that function returned early on this install and
`.fogSBCA.pem` was never created — while `.fog-pki` still advertises it.

Net: `type=signing` is advertised by an endpoint and a sudo rule that cannot ever satisfy
it, and the failure only appears at request time.

### Suggested fix

Mirror the web guard — only write `PKI_SB_CA_CERT`/`PKI_SB_CA_KEY` when
`$(_pkiZoneDir secureboot)/ca/.fogSBCA.pem` exists, so a server with no Secure Boot CA
answers "not configured" instead of 500ing out of the signing helper.

**Not applied** — it changes what a sudo rule advertises, so it is yours to call.

---

## 3. Nothing ever requests `type=signing`

```
$ grep -rn "_requestNodeCert" bin/ lib/ packages/
lib/common/functions.sh:3297:  _requestNodeCert() {
lib/common/functions.sh:3383:      if _requestNodeCert web "$sslprivkey" "$sslpubcert" "$chain"; then
packages/pki/fog-sign-node-cert:152:  # ... (comment only)
```

The only caller is `_installNodeWebCert()`, and it passes `web`. There is no
`_installNodeSigningCert()`. The `signing` branch is complete in both the endpoint and
the helper — `codeSigning` EKU, 730 days, the Secure Boot CA's own name constraints — but
no installed node has ever asked for one. It is reachable only by hand, as in this test.

Presumably the intended next step (a node signing its own kernels rather than being handed
the fleet's one trusted key — the rationale is written out at `functions.sh:6947-6954`),
but it is not wired up.

---

## 4. The DNS fallback the 409 promised was never implemented

Before the patch, a request from `10.255.25.2` failed for both types with:

```
HTTP 409
{"error":"this node has no resolvable hostname; give it one in DNS, or set its
Storage Node name to a hostname, then retry"}
```

The master has no PTR for that address, so `gethostbyaddr()` returned it unchanged. The
check itself is correct and deliberate: with DNS name constraints on the issuer and no
`dNSName` SAN on the leaf, OpenSSL falls back to matching the subject CN, and the CN
would be an IP literal.

But the remedy the message names — set the node's **Name** to a hostname — did nothing,
because the endpoint only ever read `ip`:

```php
$names[] = (filter_var($remoteIP, FILTER_VALIDATE_IP) ? 'IP:' : 'DNS:') . $remoteIP;
$recorded = trim((string) $node->get('ip'));
if ($recorded && $recorded !== $remoteIP) {
    $names[] = (filter_var($recorded, FILTER_VALIDATE_IP) ? 'IP:' : 'DNS:') . $recorded;
}
```

And putting a hostname in the `ip` field instead makes things worse: the authorization
lookup is `Route::getIds('storagenode', ['ip' => $remoteIP], 'id')`, and `$remoteIP` is
always an address literal, so the record stops matching and the request fails one step
earlier with `403 no storage node is registered at <ip>`.

**Patched** (`packages/web/service/nodecert.php`, applied): reverse DNS is still tried
first; the node's `name` field is consulted when that yields nothing, making the 409's own
advice true. The name is validated with `FILTER_VALIDATE_DOMAIN` here and bounded again by
the issuing CA's `nameConstraints` in `fog-sign-node-cert`. Verified above — the same
request that 409'd now returns a valid certificate.

Deployed to `10.255.20.1` only. `10.255.25.2` and `10.255.22.2` still run the unpatched
endpoint.

---

## Environment notes

- The master's `.fogsettings` `snmysqlpass` is **not** the HMAC secret on a master
  install — it holds the master's own DB password there, while the endpoint reads
  `FOG_STORAGENODE_MYSQLPASS` from `globalSettings` (set from `$snmysqlstoragepass`,
  `functions.sh:699`). On a real `installtype=S` node the two coincide, which is what
  makes `_requestNodeCert()` work. Not a bug, but a trap when reproducing this by hand
  from a master.
- Storage node record id 3 was renamed `debian` → `debian.lan` during this testing and
  left that way.
