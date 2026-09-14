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

## Headless distribution correction

The missing Build foundation asset requires runtime and packaging changes beyond
`v2.0.0` (commit `dfc7ad7b41f8b8de08d01965017868c36ae46e21`). These compatible
correctness fixes are prepared as `2.0.1`. Do not attach a newly modified runtime
to `v2.0.0`, rewrite that tag, or replace published standard assets. Adding a
missing artifact to an old release is legitimate only when built from that
exact reviewed tag and satisfying its source/provenance contract; that is not
this correction. Complete release gates on the reviewed commit before creating
the signed annotated `v2.0.1` tag and publishing the new stable release.
