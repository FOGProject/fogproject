# Security Policy

## Security Release Process
Deploy and manage any desktop operating system, anywhere. FOG Project can 
capture, deploy, and manage Windows, Mac OSX, and various Linux distributions. 
The community has adopted this security disclosure and response policy to 
ensure responsible handling of critical issues.

## Supported Versions

Use this section to tell people about which versions of your project are 
currently being supported with security updates.

| Version  | Supported  |
| -------- | ---------- |
| 1.5.10   | yes        |
| <1.5.10  | no         |

## Reporting a Vulnerability - Private Disclosure Process
Security is of high importance and all security vulnerabilities or suspected 
security vulnerabilities should be reported to FOG Project privately, to 
minimize attacks against current users of FOG Project before they are fixed. 
Vulnerabilities will be investigated and patched on the next patch (or minor) 
release as soon as possible.

If you know of a publicly disclosed security vulnerability for FOG Project, 
please open a **private security advisory** to inform the FOG Project Security
Team: https://github.com/FOGProject/fogproject/security/advisories/new
 
**IMPORTANT: Do not file public issues on GitHub for security 
vulnerabilities**

The request will be handled by the FOG Project Security Team. Requests will be 
addressed within 7 business days, including a detailed plan to investigate 
the issue and any potential workarounds to perform in the meantime.

Do not report non-security-impacting bugs through this channel. Use 
[GitHub issues](https://github.com/FOGProject/fogproject/issues/new/choose) 
instead.

### Proposed Content
Provide a descriptive subject line and in the body of the email include the 
following information:
* Basic identity information, such as your name and your affiliation or 
company.
* Detailed steps to reproduce the vulnerability  (POC scripts, screenshots, 
and compressed packet captures are all helpful to us).
* Description of the effects of the vulnerability on FOG Project and the 
related hardware and software configurations, so that the FOG Project 
Security Team can reproduce it.
* How the vulnerability affects FOG Project usage and an estimation of the 
attack surface, if there is one.
* List other projects or dependencies that were used in conjunction with 
FOG Project to produce the vulnerability.
 
## When to report a vulnerability
* When you think FOG Project has a potential security vulnerability.
* When you suspect a potential vulnerability but you are unsure that it 
impacts FOG Project.
* When you know of or suspect a potential vulnerability on another project 
that is used by FOG Project. For example FOG Project has a dependency on 
PHP, MariaDB/MySQL, Apache, Linux kernel, buildroot, etc.
  
## Patch, Release, and Disclosure
The FOG Project Security Team will respond to vulnerability reports as 
follows:
 
1.  The Security Team will investigate the vulnerability and determine 
its effects and criticality.
2.  If the issue is not deemed to be a vulnerability, the Security Team 
will follow up with a detailed reason for rejection.
3.  The Security Team will initiate a conversation with the reporter 
within 7 business days.
4.  If a vulnerability is acknowledged and the timeline for a fix is 
determined, the Security Team will work on a plan to communicate with the 
appropriate community, including identifying mitigating steps that 
affected users can take to protect themselves until the fix is rolled out.
5.  The Security Team will also create a 
[CVSS](https://www.first.org/cvss/specification-document) using the 
[CVSS Calculator](https://www.first.org/cvss/calculator/3.0). The Security 
Team makes the final call on the calculated CVSS; it is better to move 
quickly than making the CVSS perfect. Issues may also be reported to 
[Mitre](https://cve.mitre.org/) using this 
[scoring calculator](https://nvd.nist.gov/vuln-metrics/cvss/v3-calculator). 
The CVE will initially be set to private.
6.  The Security Team will work on fixing the vulnerability and perform 
internal testing before preparing to roll out the fix.
7.  A public disclosure date is negotiated by the FOG Project Security 
Team, the bug submitter, and the distributors list. We prefer to fully 
disclose the bug as soon as possible once a user mitigation or patch is 
available. It is reasonable to delay disclosure when the bug or the fix 
is not yet fully understood, the solution is not well-tested, or for 
distributor coordination. The timeframe for disclosure is from immediate 
(especially if it’s already publicly known) to a few weeks. For a 
critical vulnerability with a straightforward mitigation, we expect 
report date to public disclosure date to be on the order of 14 business 
days. The FOG Project Security Team holds the final say when setting a 
public disclosure date.
8.  Once the fix is confirmed, the Security Team will patch the 
vulnerability in the next patch or minor release, and backport a patch 
release into all earlier supported releases. Upon release of the patched 
version of FOG Project, we will follow the **Public Disclosure Process**.

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
acceptance to the terms and conditions of the Embargo Policy**

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
