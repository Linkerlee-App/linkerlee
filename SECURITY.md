# Security policy

## Supported versions

LinkerLee has no tagged releases yet. **`main` is the supported branch** — fixes land there,
and self-hosters should track it. When releases begin, this section will list them.

## Reporting a vulnerability

**Please do not open a public issue.**

Email **linkerlee@neti.ro** with:

- what the issue is and roughly how severe you think it is,
- steps to reproduce, or a proof of concept,
- the commit SHA or instance you tested against,
- whether it affects the hosted instance at linkerlee.com, self-hosted instances, or both.

You can expect an acknowledgement within **5 working days** and an assessment within **14
days**. If a fix is needed, we will agree a disclosure timeline with you and credit you in
the release notes unless you would rather stay anonymous.

There is no bug bounty — this is a small project. Reports are still very much appreciated.

## In scope

The application in this repository and the hosted instance at linkerlee.com, including the
web UI, the `/api` surface, the Mailgun inbound webhook, and public share pages.

The [browser extension](https://github.com/linkerlee-app/linkerlee-browser-extension) has its
own repository; report extension issues to the same address and say which component is affected.

## Out of scope

- Findings from automated scanners with no demonstrated impact.
- Missing hardening headers or best-practice warnings without an exploit path.
- Social engineering, physical attacks, or denial of service.
- Vulnerabilities in third-party dependencies that already have a public advisory — those are
  handled through dependency updates, though do tell us if we are behind on one.
- Anything requiring an already-compromised account or device.

## Deliberate design choices

These are intentional. Reporting them is fine, but they are not bugs:

- **`PUT` and `DELETE` on another user's link return `404`, not `403`.** Returning 403 would
  confirm that a link with that id exists. The 404 is deliberate.
- **API tokens carry exactly one ability, `create`.** Every token minted at Settings → API
  tokens gets it, and the delete endpoint accepts it. This means **read-only tokens are not
  currently possible, and any valid token can delete links.** It is a known limitation of the
  present design rather than an oversight; a scoped-token model is on the list.
- **The Mailgun inbound webhook is signature-verified** and rejects unsigned requests. If
  `MAILGUN_WEBHOOK_SIGNING_KEY` is unset the endpoint refuses everything rather than falling
  open.

## Notes for self-hosters

Two settings are worth getting right on your own instance:

- **`LOG_VIEWER_ALLOWED_EMAILS`** gates `/log-viewer`. Leave it empty and nobody gets in;
  set it carelessly and you have exposed your application logs.
- **A user's inbox address (`inbox-<token>@…`) is a credential.** Anyone who knows it can
  write links into that account. Treat it like a token: do not log it, do not share it.
