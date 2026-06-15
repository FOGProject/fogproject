# Contributing to the FOG Project

Thanks for taking the time to check out this document and consider contributing! You are very welcome to go ahead.

Below you'll find information on how the FOG Project is structured and how to contribute to it. These are mostly guidelines, not hard rules. Use your best judgment, and feel free to propose changes to this document and to the project as a whole.

#### Table Of Contents

[Code of Conduct](#code-of-conduct)

[Just allow me one question](#just-allow-me-one-question)

[Before you get started](#before-you-get-started)
  * [Repos, languages and foo](#repos-languages-and-foo)
  * [Versions and branches](#versions-and-branches)

[How to contribute](#how-to-contribute)
  * [Reporting Bugs](#reporting-bugs)
  * [Suggesting Enhancements](#suggesting-enhancements)
  * [Your First Code Contribution](#your-first-code-contribution)
  * [Pull Requests](#pull-requests)

[Styleguides](#styleguides)
  * [Git Commit Messages](#git-commit-messages)
  * [PHP Styleguide](#php-styleguide)
  * [JavaScript Styleguide](#javascript-styleguide)
  * [Documentation Styleguide](#documentation-styleguide)

[Additional Notes](#additional-notes)
  * [Issue and Pull Request Labels](#issue-and-pull-request-labels)
  * [Attribution](#attribution)


## Code of Conduct

### Our Pledge

We as members, contributors, and leaders pledge to make participation in our
community a harassment-free experience for everyone, regardless of age, body
size, visible or invisible disability, ethnicity, sex characteristics, gender
identity and expression, level of experience, education, socio-economic status,
nationality, personal appearance, race, caste, color, religion, or sexual
identity and orientation.

We pledge to act and interact in ways that contribute to an open, welcoming,
diverse, inclusive, and healthy community.

<details><summary>Click here for further details</summary>

### Our Standards

Examples of behavior that contributes to a positive environment for our
community include:

* Demonstrating empathy and kindness toward other people
* Being respectful of differing opinions, viewpoints, and experiences
* Giving and gracefully accepting constructive feedback
* Accepting responsibility and apologizing to those affected by our mistakes,
  and learning from the experience
* Focusing on what is best not just for us as individuals, but for the overall
  community

Examples of unacceptable behavior include:

* The use of sexualized language or imagery, and sexual attention or advances of
  any kind
* Trolling, insulting or derogatory comments, and personal or political attacks
* Public or private harassment
* Publishing others' private information, such as a physical or email address,
  without their explicit permission
* Other conduct which could reasonably be considered inappropriate in a
  professional setting

### Enforcement Responsibilities

Community leaders are responsible for clarifying and enforcing our standards of
acceptable behavior and will take appropriate and fair corrective action in
response to any behavior that they deem inappropriate, threatening, offensive,
or harmful.

Community leaders have the right and responsibility to remove, edit, or reject
comments, commits, code, wiki edits, issues, and other contributions that are
not aligned to this Code of Conduct, and will communicate reasons for moderation
decisions when appropriate.

### Scope

This Code of Conduct applies within all community spaces, and also applies when
an individual is officially representing the community in public spaces.
Examples of representing our community include using an official e-mail address,
posting via an official social media account, or acting as an appointed
representative at an online or offline event.

### Enforcement

Instances of abusive, harassing, or otherwise unacceptable behavior may be
reported to the community leaders responsible for enforcement through the
project's regular community channels — the
[FOG forums](https://forums.fogproject.org) or the
[GitHub issue tracker](https://github.com/FOGProject/fogproject/issues).
All complaints will be reviewed and investigated promptly and fairly.

All community leaders are obligated to respect the privacy and security of the
reporter of any incident.

### Enforcement Guidelines

Community leaders will follow these Community Impact Guidelines in determining
the consequences for any action they deem in violation of this Code of Conduct:

#### 1. Correction

**Community Impact**: Use of inappropriate language or other behavior deemed
unprofessional or unwelcome in the community.

**Consequence**: A private, written warning from community leaders, providing
clarity around the nature of the violation and an explanation of why the
behavior was inappropriate. A public apology may be requested.

#### 2. Warning

**Community Impact**: A violation through a single incident or series of
actions.

**Consequence**: A warning with consequences for continued behavior. No
interaction with the people involved, including unsolicited interaction with
those enforcing the Code of Conduct, for a specified period of time. This
includes avoiding interactions in community spaces as well as external channels
like social media. Violating these terms may lead to a temporary or permanent
ban.

#### 3. Temporary Ban

**Community Impact**: A serious violation of community standards, including
sustained inappropriate behavior.

**Consequence**: A temporary ban from any sort of interaction or public
communication with the community for a specified period of time. No public or
private interaction with the people involved, including unsolicited interaction
with those enforcing the Code of Conduct, is allowed during this period.
Violating these terms may lead to a permanent ban.

#### 4. Permanent Ban

**Community Impact**: Demonstrating a pattern of violation of community
standards, including sustained inappropriate behavior, harassment of an
individual, or aggression toward or disparagement of classes of individuals.

**Consequence**: A permanent ban from any sort of public interaction within the
community.

### CoC Attribution

This Code of Conduct is adapted from the [Contributor Covenant][homepage],
version 2.1, available at
[https://www.contributor-covenant.org/version/2/1/code_of_conduct.html][v2.1].

For answers to common questions about this code of conduct, see the FAQ at
[https://www.contributor-covenant.org/faq][FAQ]. Translations are available at
[https://www.contributor-covenant.org/translations][translations].

[homepage]: https://www.contributor-covenant.org
[v2.1]: https://www.contributor-covenant.org/version/2/1/code_of_conduct.html
[FAQ]: https://www.contributor-covenant.org/faq
[translations]: https://www.contributor-covenant.org/translations
</details>


## Just allow me one question

Please **don't** open a GitHub issue just to ask a question. The issue tracker is
for confirmed bugs and concrete enhancement proposals. If you simply need help,
have a usage question, or aren't yet sure whether something is a bug, start in
one of these places instead:

 - **Forums:** https://forums.fogproject.org — the best place for general help,
   "how do I…?" questions, and discussion.
 - **Wiki / documentation:** https://docs.fogproject.org — installation guides,
   configuration, and troubleshooting.

Questions answered in the right place get better, faster responses and keep the
issue tracker focused on actual work.


## Before you get started

### Repos, languages and foo

The FOG Project is split across a few repositories under the
[FOGProject organization](https://github.com/FOGProject):

 - [**fogproject**](https://github.com/FOGProject/fogproject) — the main
   repository: the web management interface, the installer, and the background
   services. This is where most contributions land.
 - [**fos**](https://github.com/FOGProject/fos) — the FOG Operating System: the
   Linux/Buildroot environment that boots on clients to capture and deploy
   images.
 - [**fog-client**](https://github.com/FOGProject/fog-client) — the cross-platform
   client agent that runs on managed hosts.

Languages and tooling you'll encounter in the main repo:

 - **PHP** (7.4+) — the bulk of the application: the web UI, the REST API, and
   the CLI background services. Some newer files use `declare(strict_types=1)`.
 - **JavaScript** — front-end behavior, built on **jQuery**, **Bootstrap**, and
   **AdminLTE**. There is **no build step**; JS and CSS are served as-is and
   third-party libraries are vendored.
 - **Shell (bash)** — the installer (`bin/installfog.sh`) and its per-distro
   library scripts in `lib/`.
 - **SQL** — the schema lives in PHP as `CREATE TABLE` definitions in
   `packages/web/commons/schema.php`.

A quick map of the main repo:

```
fogproject/
├── bin/                  # installfog.sh installer
├── lib/                  # per-distro shell library scripts
└── packages/
    ├── service/          # PHP CLI background daemons (scheduler, replicators, etc.)
    └── web/              # the web application
        ├── api/          # REST API entry point
        ├── commons/      # boot/init files loaded by every entry point
        ├── lib/          # core classes, pages, hooks, events, reports, plugins
        └── management/   # Apache/Nginx document root (UI, JS, CSS)
```

### Versions and branches

We use [Semantic Versioning](https://semver.org/) (`MAJOR.MINOR.PATCH`).

 - **`dev-branch`** is the latest development branch. **All pull requests should
   target `dev-branch`** unless a maintainer explicitly directs you elsewhere.
 - **`working-1.6`** is the active 1.6 working line.
 - **`stable`** tracks the current released line.

If you're unsure which branch to base your work on, ask on the forums or in your
issue before you start — it saves rework.


## How to contribute

### Reporting Bugs

Bugs are tracked as [GitHub issues](https://github.com/FOGProject/fogproject/issues).
Before opening one:

1. **Search existing issues** (open and closed) to avoid duplicates.
2. **Confirm it's a bug**, not a configuration or usage question — if in doubt,
   ask on the [forums](https://forums.fogproject.org) first.
3. **Use the latest version** if you can, to verify the problem still exists.

A good bug report includes:

 - A clear, descriptive **title**.
 - **Exact steps to reproduce**, in order.
 - **What you expected** to happen vs. **what actually happened**.
 - Your **environment**: FOG version, OS and version of the FOG server, client
   OS where relevant, and how FOG was installed.
 - Relevant **logs, screenshots, or error messages**. The FOG logs (web UI under
   *FOG Configuration → Log Viewer*, and the service logs under `/opt/fog/log/`)
   are often the most useful thing you can attach.

You can also report bugs in the
[bug-reports forum category](https://forums.fogproject.org/category/17/bug-reports).

### Suggesting Enhancements

Enhancement suggestions are also tracked as
[GitHub issues](https://github.com/FOGProject/fogproject/issues). When proposing
one:

 - **Search first** to see if it's already been suggested.
 - Use a **clear, descriptive title**.
 - Describe the **current behavior** and the **behavior you'd like**, and explain
   **why** it would be useful — ideally to more than just your own setup.
 - Where helpful, include mockups, example workflows, or links to how other tools
   solve the same problem.

### Your First Code Contribution

Unsure where to begin? Good entry points:

 - Issues labeled **`good first issue`** or **`help wanted`**, if present.
 - Documentation fixes, typos, and small UI corrections — low risk, genuinely
   helpful.
 - Reproducing and confirming existing bug reports.

To get a local development environment running:

1. Stand up a test FOG server (a throwaway VM is ideal — never develop against
   production).
2. **Fork** the repository and clone your fork.
3. Create a topic branch off `dev-branch`:
   `git checkout dev-branch && git checkout -b my-fix dev-branch`.
4. Make your changes and test them against your running FOG server.
5. Push to your fork and open a pull request (see below).

### Pull Requests

1. **Target `dev-branch`.** Always open your pull request against the latest
   development branch (currently `dev-branch`) unless a maintainer tells you
   otherwise.

2. **One logical change per PR.** Keep pull requests focused — it makes review
   far easier and faster. Open separate PRs for unrelated changes.

3. **Bump the version.** Increase the version number in
   [`system.class.php`](https://github.com/FOGProject/fogproject/blob/dev-branch/packages/web/lib/fog/system.class.php)
   (the `FOG_VERSION` define) to the version this change would represent,
   following [SemVer](https://semver.org/).

4. **Follow the styleguides** below and match the conventions of the
   surrounding code.

5. **Describe your change.** In the PR description, explain *what* changed and
   *why*, and link any related issue (e.g. `Fixes #123`).

6. **Test before you submit.** Verify your change works against a running FOG
   server and that you haven't broken adjacent functionality.


## Styleguides

### Git Commit Messages

 - Use the present tense ("Add feature", not "Added feature").
 - Use the imperative mood ("Fix bug", not "Fixes bug").
 - Keep the first line short (~50 characters) and add detail in the body when
   needed.
 - Reference issues and pull requests where relevant (e.g. `Fixes #123`).

### PHP Styleguide

PHP is the primary language; please match the existing conventions:

 - Target **PHP 7.4+**.
 - **Class name must match the filename** (case-sensitive) — the autoloader
   relies on it (e.g. `HostManagement` lives in `HostManagement.page.php`).
 - **Private methods** use a single underscore prefix (`_init()`, `_verCheck()`).
 - Prefer the established factory helpers over `new ClassName()` directly — use
   `FOGBase::getClass()` / `FOGBase::getManager()`.
 - Read input via `filter_input()` or the already-sanitized values — **never**
   raw `$_GET` / `$_POST`.
 - **Always escape user-controlled output** with `Initiator::e()` when echoing
   into HTML.
 - Use gettext for user-facing strings: `_('string')` inline, or
   `$foglang['Key']` for predefined strings in `text.php`.
 - Add or maintain **PHPDoc** blocks on classes and methods.
 - Only add `declare(strict_types=1)` to files that already use it — don't add it
   retroactively to older files.
 - Don't remove hooks, events, or plugin integration points without good reason.

### JavaScript Styleguide

 - There is **no build step** — write plain, browser-ready JavaScript; don't
   introduce a bundler or transpiler.
 - FOG-specific scripts live under `packages/web/management/js/fog/`, with
   per-entity files named `fog.{entity}.{sub}.js`.
 - Reuse the shared helpers (`$.apiCall()`, `$.notifyFromAPI()`, the `Common`
   object) rather than reinventing them.
 - Keep third-party libraries **vendored**; don't pull dependencies from a CDN.
 - Match the existing indentation and style of the file you're editing.

### Documentation Styleguide

 - Write in clear, plain English.
 - Use [Markdown](https://daringfireball.net/projects/markdown/) for docs in the
   repo.
 - When you add a section with a heading, make sure any table of contents and
   internal anchor links are updated to match (GitHub generates anchors by
   lowercasing the heading, replacing spaces with hyphens, and dropping
   punctuation).
 - User-facing end-user documentation lives in the
   [wiki / docs](https://docs.fogproject.org).


## Additional Notes

### Issue and Pull Request Labels

Maintainers apply labels to help organize and prioritize work — for example
`enhancement`, `bug`, `needs-triage`, `needs-info`, and `wontfix`. You don't
need to apply labels yourself; just write a clear issue or PR and a maintainer
will triage it. Watch for the `needs-info` label, which means a maintainer is
waiting on more detail from you.

### Attribution

The initial draft is based on the [Atom project's contribution document](https://github.com/atom/atom/blob/master/CONTRIBUTING.md) as well as [Mozilla ScienceLab's information](https://mozillascience.github.io/working-open-workshop/contributing/) on this topic.
</content>
</invoke>
