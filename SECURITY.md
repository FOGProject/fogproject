# Security Policy

## Security Release Process

Deploy and manage any desktop operating system, anywhere. FOG Project can
capture, deploy, and manage Windows, Mac OSX, and various Linux distributions.
The community has adopted this security disclosure and response policy to
ensure responsible handling of critical issues.

## Supported Versions

| Version | Supported | Notes |
|---|---|---|
| 1.6.x | yes | Current release. Security fixes land here. |
| 1.5.x | no | **End of life.** No further fixes of any kind, security included. |
| Earlier | no | Unsupported and long superseded. |

**1.5.x reached end of life with the 1.6.0 release.** A report that affects
1.5 only will be acknowledged, and recorded so that anyone reading later can
find it — but it will not be fixed, and there will be no further 1.5 release.
If the same issue affects 1.6, it is fixed in 1.6. Nothing is being deleted:
`dev-branch`, `stable` and every 1.5 tag remain in this repository. See the
[1.5 support statement](docs/release/1.5-support-statement.DRAFT.md) for the
full wording.

## Reporting a Vulnerability - Private Disclosure Process

Security is of high importance and all security vulnerabilities or suspected
security vulnerabilities should be reported to FOG Project privately, to
minimize attacks against current users of FOG Project before they are fixed.
Vulnerabilities will be investigated and patched on the next patch (or minor)
release as soon as possible.

If you know of a publicly disclosed security vulnerability for FOG Project,
please open a **private security advisory** to inform the FOG Project Security
Team: <https://github.com/FOGProject/fogproject/security/advisories/new>

That is the "Report a vulnerability" button under the repository's **Security**
tab. It opens a private thread visible only to you and the maintainers, and it
is the channel every FOG advisory to date has gone through.

**IMPORTANT: Do not file public issues on GitHub for security vulnerabilities.**

If you cannot use GitHub advisories for any reason, open a normal issue saying
only that you have a security report and asking for a private contact —
**no details in the public issue**.

The request will be handled by the FOG Project Security Team. Requests will be
addressed within 7 business days, including a detailed plan to investigate
the issue and any potential workarounds to perform in the meantime.

Do not report non-security-impacting bugs through this channel. Use
[GitHub issues](https://github.com/FOGProject/fogproject/issues/new/choose)
instead.

### What to include

Provide a descriptive subject line, and in the body of the advisory include as
much of the following as you can. The more of it you can give, the faster the
report can be confirmed:

- basic identity information, such as your name and your affiliation or
  company;
- the FOG version (`FOG_VERSION`, shown at the bottom of the web UI) and how
  it was installed — release tarball, `dev-branch`, or a git checkout;
- what an attacker gains, and what access they need to start: unauthenticated,
  an enrolled host, a low-privileged API user, a logged-in admin;
- detailed steps to reproduce the vulnerability, or a request/response pair,
  or the file and line. Proof-of-concept scripts, screenshots and compressed
  packet captures are all helpful;
- a description of the effects of the vulnerability on FOG Project and the
  related hardware and software configurations, so that the FOG Project
  Security Team can reproduce it;
- how the vulnerability affects FOG Project usage and an estimation of the
  attack surface, if there is one;
- anything that limits it — a non-default setting, a particular plugin, a
  particular database or web server;
- other projects or dependencies that were used in conjunction with FOG
  Project to produce the vulnerability.

A report with no reproduction is still worth sending. A precise file and line
is worth more than a proof-of-concept exploit, and we would rather not receive
a weaponized one.

Say when you report whether you want to be credited in the advisory, and under
what name and link. If you have a disclosure deadline of your own, tell us at
the start rather than at the end — we would rather plan around it than be
surprised by it.

## When to report a vulnerability

- When you think FOG Project has a potential security vulnerability.
- When you suspect a potential vulnerability but you are unsure that it
  impacts FOG Project.
- When you know of or suspect a potential vulnerability on another project
  that is used by FOG Project. For example FOG Project has a dependency on
  PHP, MariaDB/MySQL, Apache, Linux kernel, buildroot, etc.

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

## Patch, Release, and Disclosure

The FOG Project Security Team will respond to vulnerability reports as
follows:

1. The Security Team will investigate the vulnerability and determine its
   effects and criticality.
2. If the issue is not deemed to be a vulnerability, the Security Team will
   follow up with a detailed reason for rejection.
3. The Security Team will initiate a conversation with the reporter within 7
   business days.
4. If a vulnerability is acknowledged and the timeline for a fix is
   determined, the Security Team will work on a plan to communicate with the
   appropriate community, including identifying mitigating steps that
   affected users can take to protect themselves until the fix is rolled out.
5. The Security Team will also create a
   [CVSS](https://www.first.org/cvss/specification-document) using the
   [CVSS Calculator](https://www.first.org/cvss/calculator/3.0). The Security
   Team makes the final call on the calculated CVSS; it is better to move
   quickly than making the CVSS perfect. Issues may also be reported to
   [Mitre](https://cve.mitre.org/) using this
   [scoring calculator](https://nvd.nist.gov/vuln-metrics/cvss/v3-calculator).
   The CVE will initially be set to private.
6. The Security Team will work on fixing the vulnerability and perform
   internal testing before preparing to roll out the fix.
7. A public disclosure date is negotiated by the FOG Project Security Team,
   the bug submitter, and the distributors list. We prefer to fully disclose
   the bug as soon as possible once a user mitigation or patch is available.
   It is reasonable to delay disclosure when the bug or the fix is not yet
   fully understood, the solution is not well-tested, or for distributor
   coordination. The timeframe for disclosure is from immediate (especially
   if it is already publicly known) to a few weeks. For a critical
   vulnerability with a straightforward mitigation, we expect report date to
   public disclosure date to be on the order of 14 business days. The FOG
   Project Security Team holds the final say when setting a public
   disclosure date.
8. Once the fix is confirmed, the Security Team will patch the vulnerability
   in the next patch or minor release, and backport a patch release into all
   earlier supported releases. Upon release of the patched version of FOG
   Project, we will follow the **Public Disclosure Process**.

### Public Disclosure Process

The Security Team publishes a public
[advisory](https://github.com/FOGProject/fogproject/security/advisories)
to the FOG Project community via GitHub. In most cases, additional
communication via forums, website and other channels will assist in
educating FOG Project users and rolling out the patched release to
affected users.

The Security Team will also publish any mitigating steps users can take
until the fix can be applied to their FOG Project instances. FOG Project
distributors will handle creating and publishing their own security
advisories.

**The terms and conditions of the Embargo Policy apply to all members
of this mailing list. A request for membership represents your
acceptance to the terms and conditions of the Embargo Policy.**

### Embargo Policy

The information that members receive on noreply@fogproject.org must not
be made public, shared, or even hinted at anywhere beyond those who need
to know within your specific team, unless you receive explicit approval
to do so from the FOG Project Security Team. This remains true until the
public disclosure date/time agreed upon by the list. Members of the list
and others cannot use the information for any reason other than to get
the issue fixed for your respective distribution's users.
Before you share any information from the list with members of your team
who are required to fix the issue, these team members must agree to the
same terms, and only be provided with information on a need-to-know basis.

In the unfortunate event that you share information beyond what is
permitted by this policy, you must urgently inform the
noreply@fogproject.org mailing list of exactly what information was leaked
and to whom. If you continue to leak information and break the policy
outlined here, you will be permanently removed from the list.

### Requesting to Join

Send new membership requests to security@fogproject.org.
In the body of your request please specify how you qualify for membership
and fulfill each criterion listed in the Membership Criteria section above.

## Confidentiality, integrity and availability

We consider vulnerabilities leading to the compromise of data
confidentiality, elevation of privilege, or integrity to be our highest
priority concerns. Availability, in particular in areas relating to DoS
and resource exhaustion, is also a serious security concern. The FOG
Project Security Team takes all vulnerabilities, potential
vulnerabilities, and suspected vulnerabilities seriously and will
investigate them in an urgent and expeditious manner.

Note that we do not currently consider the default settings for FOG
Project to be secure-by-default. It is necessary for operators to
explicitly configure settings, role based access control, and other
resource related features in FOG Project to provide a hardened FOG
Project environment. We will not act on any security disclosure that
relates to a lack of safe defaults. Over time, we will work towards
improved safe-by-default configuration, taking into account backwards
compatibility.

## Hardening

If you are looking for how to *deploy* FOG safely rather than reporting a
flaw in it, the imaging network's isolation is the single biggest lever: FOG
hands out boot images and credentials to anything that asks on it, by design.
See the project documentation at <https://docs.fogproject.org>.
