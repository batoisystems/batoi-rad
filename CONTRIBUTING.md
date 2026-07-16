# Contributing to Batoi RAD

Use a focused branch and keep framework changes separate from local runtime
configuration or application-specific modules. Do not commit secrets, database
dumps, logs, sessions, uploads, caches, or generated Composer dependencies.

Before submitting a change, run:

```sh
cd rad
composer validate --strict
composer test
cd ..
php rad/bin/verify-release.php
```

Database changes must be additive migration files in `rad/upgrades/`. Never
modify a migration that has shipped; its checksum is recorded in `s_migration`.
Update both canonical schema files when a migration changes the fresh-install
schema.

AI integrations must use Batoi AIF. Provider-specific transports and SDK
wrappers do not belong in RAD core or RAD Admin.
