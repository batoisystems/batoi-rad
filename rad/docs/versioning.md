# Versioning and Compatibility

The repository-root `VERSION` file is the authoritative Batoi RAD version.
Stable releases use Semantic Versioning (`MAJOR.MINOR.PATCH`); prereleases use
standard suffixes such as `1.0.0-rc.1`.

For the 1.x series, documented configuration keys, installer options, migration
ledger format, public routes/API payloads, and supported extension points will
remain backward compatible within the same major version. New optional fields
and capabilities may be added in minor releases. Security and correctness fixes
that preserve compatibility are patch releases.

An intentional breaking change requires a major release. Deprecated public
behavior is documented in `CHANGELOG.md` and retained for at least one minor
release where security permits. Internal classes, undocumented database
details, RAD Admin templates, and development-only tooling are not stable API
unless explicitly documented otherwise.

Database upgrades are forward migrations. Applied migration files are
immutable and checksum-verified. Back up before upgrading; downgrade support is
limited to the latest migration that explicitly supplies a rollback handler.
