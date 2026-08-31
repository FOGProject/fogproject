# Security Policy

## Reporting a vulnerability

**Report privately through GitHub Security Advisories**, not as a public
issue and not on the forums:

> <https://github.com/FOGProject/fogproject/security/advisories/new>

That is the "Report a vulnerability" button under the repository's **Security**
tab. It opens a private thread visible only to you and the maintainers, and it
is the channel every FOG advisory to date has gone through.

If you cannot use GitHub advisories for any reason, open a normal issue saying
only that you have a security report and asking for a private contact —
**no details in the public issue**.

### What to include

The more of this you can give, the faster it can be confirmed:

- the FOG version (`FOG_VERSION`, shown at the bottom of the web UI) and how
  it was installed — release tarball, `dev-branch`, or a git checkout;
- what an attacker gains, and what access they need to start: unauthenticated,
  an enrolled host, a low-privileged API user, a logged-in admin;
- reproduction steps, or a request/response pair, or the file and line;
- anything that limits it — a non-default setting, a particular plugin, a
  particular database or web server.

A report with no reproduction is still worth sending. A precise file and line
is worth more than a proof-of-concept exploit, and we would rather not receive
a weaponized one.

### What happens next

- **Acknowledgement:** we will confirm we have the report and say whether we
  can reproduce it.
- **Fix:** developed in the private advisory, so the patch and the disclosure
  land together.
- **Credit:** you are credited in the advisory by whatever name and link you
  ask for, unless you would rather not be. Say which when you report.
- **Disclosure:** the advisory is published when the fix ships. If you have a
  disclosure deadline of your own, tell us at the start rather than at the end
  — we would rather plan around it than be surprised by it.

FOG is a volunteer project. Nobody here is on call, and response time depends
on who is available; a report that has gone quiet is being worked on or has
been missed, and a nudge on the advisory thread is welcome rather than rude.

## Supported versions

| Version | Supported | Notes |
|---|---|---|
| 1.6.x | ✅ | Current release. Security fixes land here. |
| 1.5.x | ❌ | **End of life.** No further fixes of any kind, security included. |
| Earlier | ❌ | Unsupported and long superseded. |

**1.5.x reached end of life with the 1.6.0 release.** A report that affects
1.5 only will be acknowledged, and recorded so that anyone reading later can
find it — but it will not be fixed, and there will be no further 1.5 release.
If the same issue affects 1.6, it is fixed in 1.6. Nothing is being deleted:
`dev-branch`, `stable` and every 1.5 tag remain in this repository. See the
[1.5 support statement](docs/release/1.5-support-statement.DRAFT.md) for the full
wording.

## Scope

**In scope** — anything in this repository and the project's own components:
the web application, the REST API, the installer and its shell library, the
background daemons, the FOG client protocol endpoints, the iPXE boot chain and
the storage node interfaces.

**Out of scope**, because they are not ours to fix — please report them
upstream instead:

- vulnerabilities in third-party dependencies, unless FOG's use of one is
  itself what creates the exposure;
- third-party plugins that do not ship in `FOGProject/*`;
- the operating system, web server, PHP or database FOG is installed on;
- findings that only restate FOG's documented design. A FOG server is
  deliberately powerful: it images machines over the network, so anyone who
  can administer it can already run code on everything it manages. "An admin
  can do X" is only a vulnerability if X escapes the admin boundary, and
  "a machine on the imaging network can PXE boot" is how FOG works.

Scanner output pasted without a working path to impact is not a report, and
we do not run a bug bounty — there is no money in this project to pay one
with.

## Hardening

If you are looking for how to *deploy* FOG safely rather than reporting a
flaw in it, the imaging network's isolation is the single biggest lever: FOG
hands out boot images and credentials to anything that asks on it, by design.
See the project documentation at <https://docs.fogproject.org>.
