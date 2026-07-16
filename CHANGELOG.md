# Changelog

All notable changes to Batoi RAD will be documented here.

## [Unreleased]

- Added Composer PSR-4 autoloading for new `Batoi\\Rad\\` classes while
  retaining the legacy RAD autoloader.
- Added aggregate total, unique, and duplicate query profiling without logging
  SQL text or parameters.
- Added `composer ci` as the one-command contributor verification workflow.
- Added shared JSON request/response and CSRF-token boundary utilities, with the
  code-assist and file-manager APIs migrated off duplicated HTTP handling.

## [1.0.0] - 2026-07-16

- Promoted the validated `1.0.0-rc.1` architecture to general availability.
- Added a machine-checked 1.x compatibility contract.
- Added source formatting and release-boundary complexity checks.
- Made release packaging generate RAD Admin UIF assets from the canonical
  public UIF distribution.
- Added accessibility gates for keyboard navigation, landmarks, accessible
  names, form labels, focus visibility, and alert announcements.
- Added release archive, asset, response-time, query-count, and memory budgets.
- Added request-level performance metrics with one-way session fingerprints;
  raw session identifiers are no longer written to access logs.

## [1.0.0-rc.1] - 2026-07-16

- Added an idempotent CLI installer with administrator provisioning.
- Added database-backed, checksummed migrations and a shared execution lock.
- Added login throttling, CSRF enforcement, MFA hardening, and session rotation.
- Standardized all AI execution on the bundled Batoi AIF distribution.
- Bundled Batoi UIF as the default public client-side distribution.
- Added developer-tool capability flags, safe path handling, and read-only SQL
  enforcement.
- Added readiness diagnostics, release verification, tests, and CI.
- Added deterministic ZIP packaging, a CycloneDX SBOM, checksums, and pinned
  Batoi AIF/UIF integrity verification.
- Added PHP 8.3/8.4, MySQL 8.0/8.4, HTTP authentication, and Chromium RAD Admin
  release gates.
