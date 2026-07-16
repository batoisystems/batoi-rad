# Contributing to Batoi RAD

Use a focused branch and keep framework changes separate from local runtime
configuration or application-specific modules. Do not commit secrets, database
dumps, logs, sessions, uploads, caches, or generated Composer dependencies.

Before submitting a change, run:

```sh
cd rad
composer ci
```

This runs strict Composer validation, the dependency advisory audit, unit and
boundary tests, PHPStan, the release-boundary style/complexity gate, and
distribution verification. Browser and MySQL matrix checks run in GitHub CI.

Database changes must be additive migration files in `rad/upgrades/`. Never
modify a migration that has shipped; its checksum is recorded in `s_migration`.
Update both canonical schema files when a migration changes the fresh-install
schema.

AI integrations must use Batoi AIF. Provider-specific transports and SDK
wrappers do not belong in RAD core or RAD Admin.
