# Security and Operations

## Deployment

- Serve only `public_html/`; deny direct web access to `rad/`.
- Use HTTPS, secure/HTTP-only cookies, trusted proxy configuration, and a
  restrictive firewall.
- Give the web process write access only to required runtime directories.
- Keep `developer_tools_enabled` and `ai_code_assist_enabled` off unless a
  trusted administrator explicitly needs them.
- Run `composer audit`, `composer test`, `composer analyse`, and
  `php rad/bin/doctor.php` before promotion.

## Backup and Restore

Back up the MySQL database and `rad/config`, `rad/data`, `rad/ms`, and any
application-owned public uploads together. Encrypt backups and test restores.
Before an upgrade, take a consistent database snapshot and retain the prior
release archive. To roll back application code, restore that archive; if an
applied migration is not safely reversible, restore the matching database
snapshot as well.

## Logging and Incident Response

Application and security logs belong under `rad/log`, outside public routing.
Rotate and retain them according to local policy, restrict access, and avoid
logging credentials, session values, full prompts, or sensitive source. Revoke
active sessions and rotate affected database/provider credentials after a
suspected compromise.

## Troubleshooting

Run `php rad/bin/doctor.php` first. A failed database check commonly indicates
an incorrect host/socket/TLS CA, unavailable MySQL service, or insufficient
privileges. A migration checksum failure means an applied migration was edited;
restore the original file and add a new migration. UIF failures should be
checked with `php rad/bin/verify-release.php`. Do not solve permission failures
with world-writable directories.
