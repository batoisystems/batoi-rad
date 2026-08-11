# Security Policy

## Supported Versions

Security fixes are provided for the latest stable Batoi RAD release. Fixes are
developed on the default branch and published in a supported release.

## Reporting a Vulnerability

Do not open a public issue for a suspected vulnerability. Use GitHub's private
security-advisory reporting for this repository and include affected versions,
reproduction steps, impact, and any proposed mitigation.

Do not include live credentials, customer data, or production database content.
Maintainers will acknowledge a complete report, assess severity, coordinate a
fix, and publish an advisory when appropriate.

## Deployment Expectations

- Serve only `public_html/` as the web document root.
- Keep `rad/config/`, `rad/data/`, and `rad/log/` outside public routing.
- Leave AI code assistance and developer tools disabled unless explicitly
  needed and restricted to trusted administrators.
- Run `php rad/bin/doctor.php` after installation or upgrade.
