# Configuration

Local bootstrap configuration is stored in `rad/config/sys.inc.php`, which is
created by the installer with mode `0600` and ignored by Git. Environment
variables are appropriate for installation automation; application settings
are subsequently loaded from `s_config`.

Database configuration supports `host`, `port`, `socket`, `ssl_ca`, `name`,
`user`, and `password`. Prefer a dedicated MySQL user restricted to the RAD
database. Use TLS with a trusted CA for remote database connections.

The public base URL must use HTTP or HTTPS and must not contain credentials,
query parameters, or a fragment. Production deployments should use HTTPS and
set secure session cookies. Keep `display_errors` and SQL logging disabled in
production.

AI provider credentials are stored in ignored local configuration. Batoi AIF
is present by default, but no provider is contacted until credentials are
configured and an authorized operation requests inference. Developer tools and
AI code assistance remain disabled by default.
