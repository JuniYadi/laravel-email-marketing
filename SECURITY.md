# Security Policy

## Supported Versions

Only the latest release receives security fixes. The project is pre-1.0, so fixes ship in a new release rather than being backported.

| Version | Supported |
| ------- | --------- |
| Latest release (`ghcr.io/juniyadi/laravel-marketing-mail:latest`) | ✅ |
| Older releases | ❌ |

## Reporting a Vulnerability

**Please do not open a public issue, discussion, or pull request for security problems.**

Report privately through GitHub:
[Report a vulnerability](https://github.com/JuniYadi/laravel-email-marketing/security/advisories/new)
(or the **Security** tab → **Report a vulnerability**).

Please include:

- The affected version or image tag
- Steps to reproduce, or a proof of concept
- The impact you expect (for example, data exposure, account takeover, or sending email as another user)

## What to Expect

- **Acknowledgement:** within 3 business days
- **Initial assessment:** within 7 days
- **Fix:** depends on severity, with critical and high issues prioritised for the next release

We will keep you updated in the advisory thread, credit you in the published advisory unless you prefer otherwise, and request a CVE when appropriate.

## Scope

In scope:

- This application's code (Laravel, Livewire, and Flux UI)
- The published Docker image and its configuration (`Dockerfile`, `docker/`)

Out of scope:

- Vulnerabilities in upstream dependencies that are already public. These are tracked through Dependabot, so a report is only useful if this app is exploitable in a way that isn't obvious.
- Issues that require a compromised server, a leaked `APP_KEY`, or admin credentials
- Missing hardening headers or best-practice suggestions without a demonstrated impact
- Spam or deliverability problems caused by how an operator configures their own mail provider
