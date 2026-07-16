# Changelog

All notable changes to Batoi RAD will be documented here.

## [Unreleased]

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
