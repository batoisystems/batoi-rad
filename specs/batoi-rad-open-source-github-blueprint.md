# Batoi RAD v1.0 Release Readiness Review and Implementation Specification

## Task List

### P0 — Required before any v1.0 release candidate

- [x] **REL-001** Freeze and document the supported platform: PHP 8.3 and 8.4, MySQL 8.0/8.4, required PHP extensions, Apache and Nginx examples.
- [x] **REL-002** Repair `rad/composer.json` package metadata and declare PHP plus runtime extension requirements.
- [x] **SEC-001** Update and re-lock Composer dependencies until `composer audit --locked` reports no unaccepted advisories; remove unused direct dependencies.
- [x] **SEC-002** Regenerate the PHP session ID after primary and MFA login, destroy invalid/expired sessions, and implement administrator-forced reauthentication.
- [x] **SEC-003** Replace MFA `rand()` generation with `random_int()`, add attempt limits, expiry, replay prevention, and authentication rate limiting.
- [x] **SEC-004** Put code execution, SQL execution, patching, and file mutation behind separate least-privilege capabilities; disable execution tools by default in the public distribution.
- [x] **SEC-005** Replace string-prefix path checks with one canonical, symlink-safe path policy used by Code Assist, File Manager, uploads, themes, and vendor installation.
- [x] **SEC-006** Review dynamic SQL identifiers and multi-statement execution; reject untrusted table, field, order, group, limit, and SQL-console input by default.
- [x] **UPG-001** Remove dummy/demo migrations from the production upgrade stream and establish a database-backed, locked migration ledger.
- [x] **UPG-002** Define a v1.0 schema baseline so a fresh install never replays historical migrations already represented by `schema.sql`.
- [x] **TST-001** Add automated clean-install, installer-rerun, admin-login, logout, session, CSRF, RBAC, migration, and AIF-boundary tests.
- [x] **TST-002** Add browser smoke coverage for the public site and critical RAD Admin workflows.
- [x] **CI-001** Add GitHub Actions for Composer validation, dependency audit, PHP 8.3/8.4 syntax and tests, clean MySQL install, upgrade tests, and distribution verification.
- [x] **REL-003** Add an authoritative RAD version constant/file and a SemVer compatibility and deprecation policy.
- [x] **REL-004** Produce a deterministic release archive that excludes runtime state and Composer-generated packages while deliberately including the approved UIF and AIF distributions.
- [x] **DOC-001** Publish installation, configuration, web-server, security, backup, upgrade, AIF, UIF, and troubleshooting documentation.
- [x] **DOC-002** Add `CHANGELOG.md`, `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `NOTICE`, issue templates, and a pull-request template.

### P1 — Required for v1.0 general availability

- [x] **INS-001** Make installation transactional: preflight first, write configuration atomically last, and leave a recoverable state on failure.
- [x] **INS-002** Add database port/socket/TLS options, URL and identifier validation, noninteractive secret-file support, and a `doctor` command.
- [x] **INS-003** Correct the README installer example so its sample administrator password satisfies validation.
- [x] **DB-001** Make `schema.sql` the single canonical install schema and generate or remove `sys_core.sql` to prevent drift.
- [x] **DB-002** Add schema checksum/drift verification and clean-install fixtures for every supported database version.
- [x] **PKG-001** Decide and document the first-party distribution policy for bundled AIF/UIF, including version, license, integrity, update, and Composer ownership rules.
- [ ] **PKG-002** Remove duplicated or non-runtime frontend artifacts; generate both UIF destinations from one pinned source during release packaging.
- [x] **QA-001** Resolve all PHP 8.4 deprecations and adopt a static-analysis baseline with no new errors for release, installer, migration, and security boundaries.
- [ ] **QA-002** Add coding standards, a formatter, and targeted complexity limits for newly changed code.
- [x] **OPS-001** Remove unconditional `/tmp` trace files and formalize structured, redacted application/security logging; deployment documentation assigns rotation and retention policy.
- [x] **OPS-002** Add health/readiness diagnostics that check configuration, database connectivity, writable paths, migration state, and AIF availability without exposing secrets.
- [ ] **API-001** Inventory the public PHP, route, API, schema, configuration, and extension surfaces; publish the v1 compatibility contract and add contract tests.
- [ ] **UX-001** Complete keyboard, focus, form-label, contrast, and screen-reader checks for login and critical RAD Admin workflows.
- [ ] **PERF-002** Establish response-time, query-count, memory, and release-archive size baselines with regression budgets.
- [ ] **REL-005** Build and test `v1.0.0-rc.1`, publish checksums and an SBOM, install it from the archive in a blank environment, and complete a release-candidate soak.
- [ ] **REL-006** Tag `v1.0.0` only after every release gate below is evidenced and signed off.

### P2 — Recommended simplifications and post-v1 enhancements

- [ ] **ARC-001** Introduce Composer PSR-4 autoloading for new code and retire the custom autoloader incrementally.
- [ ] **ARC-002** Split oversized admin classes into application services, repositories, validators, and thin HTTP actions.
- [ ] **ARC-003** Centralize admin JSON responses, CSRF enforcement, privilege enforcement, file policy, and request decoding. Mutation CSRF and developer-tool privilege policy are centralized; response/request consolidation remains.
- [ ] **ARC-004** Replace array-shaped global `runData` dependencies with typed context/configuration objects at new boundaries.
- [x] **ARC-005** Merge the duplicate CLI and RAD Admin upgrade implementations behind one migration service.
- [ ] **PERF-001** Remove persistent PDO connections by default, cache schema metadata per request, and profile repeated configuration/navigation queries. Persistent connections are removed; profiling and metadata caching remain.
- [ ] **DX-001** Add a supported local development environment and one-command install/test workflow.
- [ ] **DX-002** Move intentional sample applications and migrations into `examples/` so production installers contain no demo schema.

## Review Outcome

**Current verdict: ready to produce `v1.0.0-rc.1`, but not yet ready to tag `v1.0.0`.** All P0 engineering gates are implemented: dependency, authentication, filesystem/SQL containment, migrations, installation, HTTP/browser tests, canonical schema verification, static analysis, reproducible archive/SBOM generation, documentation, and governance. The remaining release blockers are P1 candidate evidence: an RC-labeled artifact, its soak period, final compatibility/API review, accessibility review, and release sign-off.

This assessment was performed on 2026-07-16 against local `main` at `9afc60d` plus the current uncommitted distribution/installer/AIF changes. The public GitHub repository exists, is Apache-2.0 licensed, uses `main`, has no tags, and has no GitHub Release. The local repository has 680 tracked files, 441 tracked PHP files, only three commits, and a large dirty working tree; release decisions must be made from a reviewed commit, never directly from the current working tree.

### Evidence Snapshot

| Area | Observed state |
| --- | --- |
| Repository | Public GitHub repository; CI workflow implemented locally; no tags or releases yet |
| Runtime source | 82 core and 65 RAD Admin PHP class files |
| Syntax | All 444 first-party/runtime PHP files parse on PHP 8.4 |
| Compatibility | PHP 8.3-compatible lock; all reviewed PHP parses on PHP 8.4 without deprecation output |
| Tests | PHP security/AIF tests, MySQL install/upgrade matrix, HTTP auth smoke, and Chromium RAD Admin smoke |
| Database | Canonical checksummed schema; database-backed migration ledger and fresh-install baseline |
| Dependencies | 22 runtime packages plus PHPStan for development; locked audit has no advisories |
| Composer metadata | Strict-valid `batoi/rad` package with PHP/extension platform requirements |
| Assets | Repository about 67 MB; admin assets about 23 MB; Monaco about 11 MB; Bootstrap tree about 9.7 MB |
| UIF | Identical runtime bundles are committed in public and admin locations |
| Distribution | Pinned AIF/UIF manifest, deterministic ZIP, CycloneDX SBOM, and external SHA-256 checksum |

### What is already strong

- The repository is public under Apache-2.0 with an appropriate description and a clear framework/runtime directory boundary.
- The new installer has been proven against a disposable database, provisions the required administrator, and safely recognizes a matching rerun.
- The public front controller and Apache rewrite rules work in the localhost deployment, and baseline clickjacking, MIME-sniffing, and referrer headers are present.
- All reviewed AI call sites use Batoi AIF; no provider transport remains in RAD core, and a boundary test protects that decision.
- UIF is shipped as a browser distribution in public assets rather than mixed into PHP core.
- Runtime configuration, data, logs, microservices, and ordinary Composer packages are ignored by default, with explicit curated-distribution exceptions.
- All 444 reviewed first-party/runtime PHP files pass syntax parsing on PHP 8.4.

## Prioritized Findings and Recommendations

The findings below preserve the original review rationale. Completed remediations
are recorded in the task list and current evidence snapshot above.

### 1. Dependency security is a release blocker

`composer audit --locked` reported 13 advisories across `guzzlehttp/guzzle`, `guzzlehttp/psr7`, `phpseclib/phpseclib`, and `symfony/http-client`, including high-severity phpseclib advisories. The lock also holds materially old direct dependencies. Update constraints and the lock, identify whether `web3p/web3.php` and Mailgun are required by the base distribution, and move optional integrations into Composer suggestions or optional packages where practical.

Acceptance criteria:

- `composer audit --locked --no-dev` passes under a documented advisory policy.
- Direct dependencies have an owner and an observed runtime call site.
- Dependabot or Renovate opens dependency updates, and CI blocks newly introduced high/critical advisories.

### 2. Authentication and session lifecycle need hardening

The login path verifies password hashes, but no `session_regenerate_id(true)` call is present after login. `SessionManager` detects invalid, expired, and idle sessions but does not destroy them; the destroy call is commented out. Administrator-forced reauthentication is an empty method. MFA codes use `rand()`, and login responses distinguish invalid usernames from invalid passwords.

Implement one authentication lifecycle service that owns session rotation, expiry, revocation, rate limiting, uniform login errors, MFA challenges, trusted-device handling, and security-event audit records. Add tests for fixation, expiry, replay, brute force, logout, and forced logout.

### 3. Developer execution tools require explicit containment

`Codeassistapi` can write files, apply patches, execute SQL, and execute arbitrary PHP. CSRF protection alone is not authorization, and a leading `SELECT` check does not safely enforce read-only SQL when multi-statements are possible. These tools are valuable in a local development edition but are too powerful to expose as ordinary admin actions in a standard public runtime.

Keep AI inference in Batoi AIF, while placing developer tools behind RAD-owned capability boundaries:

- Separate `code_assist_chat`, `source_read`, `source_write`, `sql_read`, and `code_execute` privileges.
- Disable mutation and execution capabilities by default.
- Require an explicit development-mode configuration that cannot be enabled by request parameters.
- Execute code in a separate constrained process/container with time, memory, filesystem, environment, and network limits.
- Use a read-only database connection for SQL inspection and reject comments, delimiters, and multiple statements.
- Audit every tool invocation without recording secrets or full sensitive source payloads.

### 4. Filesystem containment is duplicated and unsafe

Several code paths use `strpos($candidate, $base) === 0`. That accepts sibling prefixes such as `/srv/rad-other` for a `/srv/rad` base and is insufficient for nonexistent paths and symlinks. Create a single `SafePath`/`WorkspaceFilePolicy` service that normalizes separators, rejects traversal and stream wrappers, validates the nearest existing parent with `realpath`, enforces a directory-boundary separator, and applies extension/size policies.

Cover Code Assist, File Manager, UI assets, themes, upgrades, uploads, file storage, and vendor ZIP extraction with adversarial path and symlink tests.

### 5. Migration history cannot support a reliable v1 upgrade promise

Upgrade application state is stored in `rad/data/upgrade/checkpoints.json`, so it can diverge from the database, disappear during deployment, or differ across horizontally scaled nodes. Fresh installs receive the current schema but no baseline checkpoint and therefore see every historical script as pending. The stream includes `dummy` and `a_mytestash` demo migrations, and upgrade behavior is duplicated between `UpgradeController` and the RAD Admin `Upgrade` class.

Use a database migration ledger containing migration ID, checksum, release, start/end timestamps, status, node/actor, and error. Lock migration execution, verify checksums, wrap transactional DDL where supported, and record an explicit v1 baseline during installation. Move demo migrations to test fixtures. Define exactly which pre-v1 database snapshot is supported for upgrade testing.

### 6. The installer is a strong start but not release-atomic

The installer correctly provisions an administrator, refuses partial schemas, and is idempotent for a matching ID-1 administrator. Remaining gaps:

- It writes the live configuration before Composer and database validation complete.
- It has no database port, socket, or TLS options.
- It does not preflight PHP version/extensions, filesystem permissions, URL validity, database collation/version, or existing schema version.
- It enables PDO multi-statements for the install connection.
- It does not atomically write through a temporary file or preserve a prior configuration on failure.
- The README's sample password does not satisfy the installer's uppercase/numeric rule.
- Documentation says “PDO-supported database,” while implementation and SQL are MySQL-specific.

Add `install --check`, `install --dry-run`, and `doctor` modes. Use secret files/STDIN for automation, redact all diagnostics, and emit a machine-readable install result in addition to human output.

### 7. Automated verification is far below v1 scope

Syntax validation is currently the only broad automated check; `rad/tests/AifBoundaryTest.php` is the sole executable first-party test. Establish layers:

- Unit: configuration, requests, permissions, safe paths, SQL policy, sessions, MFA, AIF adapters.
- Integration: clean schema/seed, admin provisioning, migration ledger, API authentication, queues and file storage.
- HTTP: public routing, login/admin redirects, CSRF, headers, RBAC/CE route blocking.
- Browser: install-to-login, dashboard, CRUD, UIF/Monaco loading, code studio in safe mode.
- Distribution: install from the exact release archive with no source checkout assumptions.

Use disposable MySQL databases and deterministic fixtures. Make failures preserve useful logs without secrets.

### 8. Composer and package ownership need one coherent model

The root `rad/composer.json` lacks package identity, license, PHP, extensions, autoloading, scripts, and development tooling. The curated Batoi AIF copy lives inside `rad/vendor/batoi/aif`, while Composer owns the same parent directory. Nested package requirements are not enforced by the root package, and a Composer operation can make ownership ambiguous.

Recommended v1 policy:

- Keep AIF in the default release as requested, but treat it as a pinned first-party distribution artifact, not an accidental Composer install.
- Declare AIF's effective PHP/extension requirements in RAD's root package.
- Record AIF and UIF versions, source commits, licenses, and hashes in a release manifest.
- Make the release builder restore/pin first-party distributions after Composer installation and verify their integrity.
- Plan a later move to normal Composer packages once public package availability and version compatibility are stable.

### 9. Frontend distribution can be substantially smaller and simpler

UIF is committed twice, with identical runtime CSS/JS hashes. Bootstrap includes upstream development/package files and source maps, and Monaco is necessarily large but can be trimmed to required languages and locales. Keep one canonical pinned UIF artifact in the source/release input and generate the public/admin destinations during build. Commit only runtime Bootstrap assets and required licenses. Decide whether TypeScript declarations belong in a PHP runtime archive.

Do not remove Monaco blindly: it is a functional RAD Admin dependency. Create a tested language/worker allowlist and measure the archive before and after trimming.

### 10. Core code should be simplified incrementally, not rewritten for v1

The custom class loader and `.cls.php` convention work but prevent normal Composer tooling. Several admin classes are thousands of lines and combine HTTP handling, validation, persistence, filesystem mutation, code generation, and rendering. CSRF, JSON error responses, path checking, and upgrade state are repeated.

For v1, extract only security- and test-critical seams. After v1, use a strangler approach:

1. Add PSR-4 for new `Batoi\\Rad\\` services while preserving legacy autoloading.
2. Introduce typed request/context and service interfaces at controller boundaries.
3. Extract shared admin action middleware for authentication, privilege, CSRF, JSON, and audit.
4. Move persistence into repositories with validated identifiers.
5. Split the largest admin modules by use case, with characterization tests before movement.

### 11. Database abstraction currently overstates portability

`Database` dynamically concatenates table and identifier fragments, uses persistent connections by default, does not specify `utf8mb4` in the runtime DSN, and converts a connection exception into an invalid error-handler property rather than failing fast. Schema inspection is repeatedly performed by generic CRUD methods. Document MySQL as the v1 database, validate every identifier against schema-derived allowlists, fail fast on connection errors, default persistent connections off, set charset/timezone explicitly, and cache schema metadata per request.

### 12. Operational and documentation surfaces are incomplete

Debug traces are written to shared `/tmp` files in some admin code. Security headers exist in Apache `.htaccess`, but equivalent Nginx guidance and an application-level baseline are absent. There is no structured release documentation set, vulnerability reporting policy, contributor guidance, changelog, notice/third-party inventory, or support matrix.

Replace ad-hoc traces with structured logging and redaction. Provide production hardening guidance covering HTTPS, proxy trust, cookies, CSP rollout, writable paths, backups, cron/queue operation, log retention, database privileges, AIF keys, and disabling developer tools.

### 13. v1 needs an explicit compatibility surface

There is no authoritative inventory of which PHP classes, routes, API payloads, configuration keys, database tables, template variables, or extension points are public. Without that boundary, semantic versioning cannot be applied consistently. Mark public, experimental, and internal surfaces; publish deprecation rules; add API/schema/config contract snapshots; and require migration notes for every intentional break.

### 14. Performance and accessibility need measurable baselines

The architecture repeatedly loads configuration and schema metadata, uses persistent PDO connections by default, and serves a comparatively large admin asset set. Establish representative query-count, wall-time, peak-memory, and asset-size budgets before optimizing. Cache only after measurement, and add invalidation tests.

For the UI, make the login, navigation, tables, dialogs, forms, editor controls, and error states keyboard operable with visible focus and useful accessible names. Automated accessibility scans are useful gates, but critical workflows also need manual keyboard and screen-reader checks before GA.

## Implementation Workstreams

### Workstream A — Security baseline

Deliverables: authentication lifecycle service, migration-safe session changes, MFA/rate-limit service, safe-path policy, developer-tool capability policy, read-only SQL policy, dependency updates, and security regression tests.

Exit criteria: no unaccepted dependency advisories; fixation/path traversal/multi-statement/tool-authorization tests pass; developer execution is off by default.

### Workstream B — Installer, schema, and upgrades

Deliverables: platform preflight, atomic config writer, canonical schema, database migration ledger, v1 baseline marker, cleaned migration stream, legacy upgrade fixture, doctor command, and installation diagnostics.

Exit criteria: clean install and rerun pass on every support-matrix combination; a selected legacy fixture upgrades to the same schema checksum; failed installs preserve the prior config and report recovery steps.

### Workstream C — Architecture and package boundaries

Deliverables: corrected Composer metadata, explicit AIF/UIF distribution manifest, shared admin action/security services, and limited extraction from high-risk oversized classes.

Exit criteria: Composer strict validation passes; first-party distribution hashes are verified; no AI provider transport exists outside AIF; duplicated security code is removed from touched endpoints.

### Workstream D — Quality and CI

Deliverables: PHPUnit or Pest test harness, integration fixtures, browser smoke suite, PHP 8.3/8.4 matrix, static analysis baseline, formatter, dependency audit, secret scan, license scan, and release artifact test.

Exit criteria: required checks run on every pull request and protected `main` cannot merge with a failing release gate.

### Workstream E — Documentation and release engineering

Deliverables: focused docs, project governance files, version policy, release script/workflow, `.gitattributes` export rules, checksums, SBOM, provenance/release manifest, and rollback notes.

Exit criteria: a new user can install from the release archive, reach RAD Admin, run the smoke suite, upgrade the supported legacy fixture, and remove the installation without relying on unpublished knowledge.

## GitHub v1.0 Release Gates

| Gate | Required evidence |
| --- | --- |
| G1 Security | Dependency audit policy passes; secret scan clean; P0 auth, path, SQL, and execution tests pass |
| G2 Reproducibility | Release archive built twice from the same commit has identical contents/checksums, excluding signed metadata where necessary |
| G3 Installation | Fresh archive installs on the support matrix and provisions a working admin login |
| G4 Upgrade | Supported legacy fixture upgrades to the expected schema and data invariants; rerun is a no-op |
| G5 Quality | Unit/integration/HTTP/browser suites pass on PHP 8.3 and 8.4 with no deprecations |
| G6 Package | Composer strict validation passes; license/NOTICE/SBOM and AIF/UIF manifest are complete |
| G7 Documentation | Installation, configuration, security, operations, backup, upgrade, and troubleshooting guides are reviewed |
| G8 GitHub | Protected `main`, required CI, templates, security policy, changelog, signed annotated tag, checksums, and release notes are present |
| G9 Candidate | `v1.0.0-rc.1` is installed from its archive and completes the agreed soak without unresolved P0/P1 defects |

## Recommended Release Sequence

1. Create a focused release-readiness branch from a reviewed commit and partition the current dirty tree into intentional commits.
2. Complete Workstream A and the Composer/package corrections first; do not publish a beta with known high-severity advisories.
3. Complete the migration baseline and installer atomicity work before expanding features.
4. Add CI and tests alongside each fix so release gates become executable.
5. Publish `v1.0.0-beta.1` for installer/API feedback after P0 gates pass.
6. Publish `v1.0.0-rc.1` after P0 and P1 tasks pass from the exact release archive.
7. Run the soak, resolve all P0/P1 defects, update the changelog, then sign and publish `v1.0.0`.

## Explicitly Deferred Beyond v1.0

- Full rewrite to PSR-4 or a new framework architecture.
- Complete removal of Bootstrap classes/icons.
- Replacing Monaco without an equivalent tested editor.
- Supporting databases other than MySQL/MariaDB.
- Bundling MCP Audit into the runtime distribution; use it or equivalent tooling in development/CI instead.
- Adding more AI providers inside RAD; provider adapters remain a Batoi AIF responsibility.

## Historical Blueprint (Preserved)

The remainder of this file is the earlier May 2026 publication blueprint. It is retained for decision history. Where it conflicts with the current review above—especially its earlier recommendation not to bundle AIF—the current v1.0 specification governs.

## Goal

Create a public GitHub repository named `batoi-rad` for publishing the Batoi RAD Framework as open source, with a clean repository boundary, reproducible installation path, clear license posture, and safeguards that prevent internal runtime data, customer/application code, secrets, logs, and generated artifacts from being published.

## Target Repository

- Repository name: `batoi-rad`
- Local target path: `/Users/ashwinirath/Sites/localhost/gitrepo/batoi-rad`
- Source checkout path: `/Users/ashwinirath/Sites/localhost/beanstalkrepo/radsandbox/trunk`
- Suggested description: `Batoi RAD Framework for rapid PHP application development`
- Visibility: public, after release-readiness gates pass
- Primary language: PHP
- Package posture:
  - framework source is committed
  - Composer dependencies are declared in `composer.json`
  - generated/vendor dependency directories are not committed unless a deliberate vendoring decision is made
- Default branch: `main`
- Initial release tag: `v0.1.0` or `v1.0.0-beta.1`, depending on desired public stability signal

## Handoff Status

The target repository already exists at:

```text
/Users/ashwinirath/Sites/localhost/gitrepo/batoi-rad
```

The current handoff objective is to copy a conservative, publishable subset from the source checkout into that Git repository. The operation must be copy-only. Do not move files from the source checkout and do not delete the target repository's `.git` directory.

Clean copy status as of 2026-05-26:

- The conservative framework subset has been copied into the target repository.
- Existing target `.git`, `LICENSE`, and `README.md` were preserved.
- Runtime/config/vendor paths were not copied.
- Generated/test asset folders that were accidentally included during the first asset copy were removed from the target.
- The target repository is a staging copy only; it still needs secret/privacy scanning, documentation cleanup, Composer layout decisions, and CI setup before any public push.

## Publish Principles

1. Publish framework code, not an installation snapshot.
2. Keep runtime state, logs, uploads, sessions, generated caches, sample tenant data, and local config out of Git.
3. Keep app-specific microservice code out of the framework repository unless it is converted into intentionally maintained examples.
4. Make installation reproducible from documented prerequisites, Composer, schema/migration assets, and seed/demo data.
5. Use a license that is explicit and consistent across source headers, dependency inventory, and GitHub metadata.
6. Start public with conservative CI gates rather than broad claims of production readiness.

## Current Source Inventory

The current working tree contains a mix of framework code, application/runtime content, vendored dependencies, generated data, and local artifacts.

### Candidate framework source

- `rad/core/sys/`
- `rad/core/app/`
- `rad/admin/classes/`
- `rad/admin/routes/`
- `rad/admin/ui/`
- `rad/admin/install/`
- `rad/bin/`
- `rad/upgrades/`
- `public_html/index.php`
- public/admin static assets that are authored and required by RAD

### Candidate documentation and metadata

- `RAD Architecture.md`
- `TECH_MASTER.md`
- `BCP RBAC Architecture.md`
- `TODO.md`, after review for internal-only content
- selected files from `specs/`, after sanitization
- a new root `README.md`
- a new `CHANGELOG.md`
- a new `CONTRIBUTING.md`
- a new `SECURITY.md`
- a new `CODE_OF_CONDUCT.md`
- a new `LICENSE`

### Exclude by default

- `rad/config/`
- `rad/data/`
- `rad/log/`
- `rad/ms/`
- `rad/tests/`, until reviewed and scrubbed
- `rad/vendor/`
- `rad/composer.phar`
- `rad/package-lock.json`, unless the admin asset build is made reproducible
- `public_html/php_error.log`
- `public_html/assets/img/`, unless images are confirmed to be owned and reusable
- `public_html/assets/vendor/`, unless asset dependencies are intentionally vendored with license review
- database dumps such as `radsandbox.sql`, unless replaced with sanitized schema/bootstrap SQL
- local environment files such as `php.ini`, `.user.ini`, `.env`, and generated temp files

## Proposed Repository Layout

```text
batoi-rad/
  .github/
    workflows/
      ci.yml
    ISSUE_TEMPLATE/
    pull_request_template.md
  docs/
    architecture.md
    installation.md
    configuration.md
    routing-and-microservices.md
    admin-console.md
    security.md
    upgrade-guide.md
  examples/
    microservices/
      hello-world/
    config/
      config.example.php
  public/
    index.php
    assets/
      css/
      js/
  rad/
    admin/
    bin/
    core/
    upgrades/
  tests/
    unit/
    integration/
  tools/
    release/
  composer.json
  composer.lock
  README.md
  CHANGELOG.md
  CONTRIBUTING.md
  SECURITY.md
  CODE_OF_CONDUCT.md
  LICENSE
  .gitignore
  .gitattributes
```

## Clean Copy Manifest

The first copy into `/Users/ashwinirath/Sites/localhost/gitrepo/batoi-rad` should be intentionally conservative. Copy framework/runtime source, RAD Admin source, minimal public entry assets, core docs, and this handoff spec. Do not copy live application microservices, local config, runtime data, logs, sessions, uploads, database dumps, or installed dependencies.

### Copy these paths

```text
RAD Architecture.md
BCP RBAC Architecture.md
TECH_MASTER.md
TODO.md
readme.txt
rad/autoload.php
rad/composer.json
rad/composer.lock
rad/core/
rad/admin/classes/
rad/admin/routes/
rad/admin/ui/
rad/admin/install/
rad/admin/assets/
rad/bin/
rad/upgrades/
rad/theme/default.tpl.php
rad/theme/web.tpl.php
rad/theme/login.tpl.php
rad/theme/forgotpassword.tpl.php
rad/theme/mfa.tpl.php
rad/theme/app.tpl.php
rad/theme/maintenance.tpl.php
rad/theme/error-page.tpl.php
rad/theme/home.tpl.php
rad/theme/kiosk.tpl.php
public_html/index.php
public_html/assets/css/
public_html/assets/js/
public_html/assets/img/logo.svg
public_html/assets/img/logo-icon.svg
public_html/assets/img/welcome.svg
specs/batoi-rad-open-source-github-blueprint.md
```

### Exclude inside copied parent directories

```text
rad/admin/temp/
rad/admin/assets/monaco/node_modules/
rad/admin/assets/bootstrap-table/cypress/
rad/admin/assets/summernote/test/
```

### Do not copy these paths

```text
radsandbox.sql
changelog.txt
rad/composer.phar
rad/package-lock.json
rad/config/
rad/data/
rad/docs/
rad/log/
rad/ms/
rad/tests/
rad/vendor/
public_html/php_error.log
public_html/assets/.archive/
public_html/assets/img/94330.jpg
public_html/assets/img/MassMutual.png
public_html/assets/img/at-dubai-mall-4.jpeg
public_html/assets/img/logo-1.svg
public_html/assets/img/logo-icon.png
public_html/assets/img/logo.png
public_html/assets/vendor/
```

### Rationale

- `rad/core/`, `rad/admin/classes/`, `rad/admin/routes/`, `rad/admin/ui/`, and `rad/admin/install/` are the main framework and admin source surface.
- `rad/admin/assets/` is copied because the RAD Admin UI likely depends on it; generated dependency trees and test folders are excluded.
- `rad/theme/` is reduced to framework-default templates. Locally named experimental templates such as `aaa_aabb.tpl.php`, `aaa_aabb_copy.tpl.php`, `dh_ddsdjsd.tpl.php`, and `newsample.tpl.php` are not included in the first public staging copy.
- `rad/ms/` is excluded because it contains generated/application microservice code. Public examples should be created under `examples/` later.
- `rad/config/` is excluded because it contains installation and deployment-specific config.
- `rad/vendor/` is excluded because dependencies should be restored through Composer.
- `public_html/assets/img/` is limited to neutral framework SVG assets. Photos, customer-looking logos, and raster logo variants are excluded until ownership and public-use rights are confirmed.

## Mapping From Current Tree

| Current path | Target path | Action |
| --- | --- | --- |
| `rad/core/sys/` | `rad/core/sys/` | Copy after secret/internal reference scan |
| `rad/core/app/` | `rad/core/app/` | Copy after product/customer reference scan |
| `rad/admin/classes/` | `rad/admin/classes/` | Copy after admin credential/config review |
| `rad/admin/routes/` | `rad/admin/routes/` | Copy |
| `rad/admin/ui/` | `rad/admin/ui/` | Copy after asset/path cleanup |
| `rad/admin/install/` | `rad/admin/install/` | Copy after install flow review |
| `rad/admin/assets/` | `rad/admin/assets/` | Copy with generated dependency/test folders excluded |
| `rad/bin/` | `rad/bin/` | Copy selected CLI tools only |
| `rad/upgrades/` | `rad/upgrades/` | Copy reviewed framework migrations |
| `rad/theme/*.tpl.php` | `rad/theme/*.tpl.php` | Copy framework-default templates only |
| `public_html/index.php` | `public_html/index.php` initially, later `public/index.php` | Copy first; rename only after bootstrap review |
| `public_html/assets/css/` | `public_html/assets/css/` initially, later `public/assets/css/` | Copy authored CSS only |
| `public_html/assets/js/` | `public_html/assets/js/` initially, later `public/assets/js/` | Copy authored JS only |
| `RAD Architecture.md` | `docs/architecture.md` | Convert to repository documentation |
| `readme.txt` | `README.md` | Rewrite for RAD, current requirements, and GitHub audience |

## License Strategy

The existing `readme.txt` says the earlier Batoi Open Source Framework is GPL licensed. Before publishing `batoi-rad`, choose and document one of these paths:

- GPL-compatible release:
  - use GPL-3.0-or-later or GPL-2.0-or-later intentionally
  - verify all bundled dependencies and copied assets are license-compatible
  - include complete license text in `LICENSE`
- Permissive release:
  - use MIT, Apache-2.0, or BSD only if Batoi owns the full source and there are no inherited GPL obligations from reused framework code
  - remove or replace any incompatible assets/dependencies

Recommendation: start with GPL-3.0-or-later if continuity with the existing open-source framework matters and commercial dual licensing is not blocked by internal policy. If Batoi expects broad SaaS/company adoption with fewer copyleft concerns, complete legal review before choosing a permissive license.

## Security and Privacy Gates

Run these checks before the first public push:

- Search for secrets:
  - API keys
  - OAuth client secrets
  - database credentials
  - JWT/signing keys
  - SMTP/Mailgun/Stripe/Twilio credentials
  - private IPs and production hostnames
- Search for customer or internal identifiers:
  - real workspace names
  - real user emails
  - sample session data
  - uploaded filenames
  - support/admin notes
- Remove runtime data:
  - PHP sessions
  - logs
  - upload contents
  - generated versions/trash/temp files
  - local database dumps
- Review generated application code under `rad/ms/`; do not publish it as framework source.
- Review docs for internal-only roadmap, server names, or operational details.
- Review screenshots/images for ownership and privacy.

## `.gitignore` Baseline

The public repository should ignore at least:

```gitignore
/rad/config/
/rad/data/
/rad/log/
/rad/ms/
/rad/vendor/
/public/assets/uploads/
/public/php_error.log
/.env
/.env.*
!/examples/config/.env.example
/composer.phar
/node_modules/
/coverage/
/.phpunit.cache/
/.DS_Store
```

If `rad/ms/` examples are needed, place them under `examples/microservices/` rather than committing live generated microservice folders.

## Immediate Verification Commands

After the clean copy, run these from the target repository:

```sh
pwd
find . -path './rad/vendor' -o \
  -path './rad/config' -o \
  -path './rad/data' -o \
  -path './rad/log' -o \
  -path './rad/ms' -o \
  -path './public_html/assets/vendor' -o \
  -path './public_html/php_error.log'
```

The `find` command should print nothing.

Also inspect the Git status before committing:

```sh
git status --short
```

## Documentation Set

### `README.md`

Must include:

- what Batoi RAD is
- current stability status
- supported PHP versions
- supported databases through PDO
- quick start
- local development command
- project layout
- license
- links to docs

### `docs/installation.md`

Must include:

- PHP extensions
- web server rewrite requirements
- Composer install
- database setup
- config file creation from template
- writable directories
- first admin setup

### `docs/configuration.md`

Must include:

- required config keys
- environment override strategy
- directory constants
- session settings
- mail settings
- optional AI/provider integrations, with secrets never committed

### `docs/security.md`

Must include:

- admin access model
- session handling
- CSRF posture
- upload/storage guidance
- IP restriction behavior
- security reporting policy link

### `docs/routing-and-microservices.md`

Must include:

- microservice route files
- pre/page/post part execution
- API route behavior
- branch/live-beta model if included in public code

## CI Blueprint

Start with a conservative GitHub Actions workflow:

- PHP matrix:
  - current supported production PHP version
  - newest supported PHP version after compatibility review
- Composer:
  - `composer validate`
  - `composer install --no-interaction --prefer-dist`
- Static checks:
  - PHP syntax check over committed PHP files
  - optional PHPCS/PSR-12 check once current code style is baseline-clean
- Tests:
  - unit tests when available
  - integration smoke test for bootstrap/config loading
- Security:
  - Composer audit
  - secret scanning enabled in GitHub repository settings
  - Dependabot for Composer dependencies

## Release Workflow

1. Prepare sanitized source tree in a staging directory.
2. Run secret and privacy scans.
3. Run dependency license inventory.
4. Run local syntax and bootstrap checks.
5. Create `batoi-rad` repository on GitHub.
6. Push to a private repository first, or create public repository only after gates pass.
7. Enable branch protection on `main`.
8. Enable GitHub security features:
   - Dependabot alerts
   - Dependabot security updates
   - secret scanning
   - code scanning if available
9. Cut first pre-release tag.
10. Publish public announcement only after install docs are validated on a fresh machine/container.

## Implementation Plan

### Phase 1: Repository Boundary and Audit

- Start from the conservative copy manifest in this document.
- Decide license and public stability label.
- Create a file inventory of:
  - framework source
  - generated/runtime data
  - app-specific microservice code
  - third-party dependencies/assets
  - docs safe to publish
- Define the copy list and exclude list as a scriptable manifest.
- Run initial scans for secrets, customer data, local hostnames, and production domains.
- Decide whether `public_html` remains as-is or is normalized to `public`.

Deliverable: approved publish manifest and exclusion rules. The manifest above is the starting point and should be treated as provisional until install/runtime smoke tests confirm no required files were omitted.

### Phase 2: Staging Repository Assembly

- Use `/Users/ashwinirath/Sites/localhost/gitrepo/batoi-rad` as the staging repository.
- Confirm the source files were copied, not moved.
- Copy only approved framework files using the manifest above.
- Rename and normalize paths:
  - `public_html/index.php` to `public/index.php`, if selected
  - docs into `docs/`
- Add root repository files:
  - `README.md`
  - `LICENSE`
  - `CONTRIBUTING.md`
  - `SECURITY.md`
  - `CHANGELOG.md`
  - `.gitignore`
  - `.gitattributes`
- Replace committed config with examples:
  - `examples/config/config.example.php`
  - optional `.env.example`

Deliverable: clean local `batoi-rad` repository candidate.

## Next Codex Session Checklist

Open the next Codex session in:

```text
/Users/ashwinirath/Sites/localhost/gitrepo/batoi-rad
```

Then proceed in this order:

1. Verify the staged tree contains no excluded runtime/config/vendor paths.
2. Inspect `git status --short` before adding files.
3. Create or revise root `.gitignore`, `.gitattributes`, `README.md`, `SECURITY.md`, `CONTRIBUTING.md`, and `CHANGELOG.md`.
4. Preserve the existing `LICENSE` unless a license decision changes.
5. Move `rad/composer.json` to root only if bootstrap/autoload changes are handled.
6. Run PHP syntax checks across copied PHP files.
7. Run Composer validation after deciding the root Composer layout.
8. Convert architecture docs into `docs/`.
9. Add an example config and a hello-world microservice under `examples/`.
10. Run secret/privacy scans before any GitHub push.

### Phase 3: Dependency and Asset Cleanup

- Move `rad/composer.json` to root or keep it under `rad/` only if bootstrapping requires that layout.
- Prefer root `composer.json` for public developer ergonomics.
- Remove `rad/vendor/` from source control.
- Rebuild `composer.lock` from declared dependencies.
- Review admin/public front-end assets:
  - keep authored CSS/JS
  - replace large vendored bundles with package-managed dependencies where practical
  - keep only third-party assets with clear license attribution
- Add `THIRD_PARTY_NOTICES.md` if required.

Deliverable: dependency-managed repository with clear third-party attribution.

### Phase 4: Install and Bootstrap Hardening

- Create a minimal install path that works from a fresh clone.
- Replace installation-specific assumptions with config examples.
- Ensure missing writable directories are created or documented.
- Add sanitized schema/migration path:
  - framework schema only
  - no tenant/customer/application data
- Add a `hello-world` microservice example under `examples/`.
- Verify first-run admin setup can be completed without private data.

Deliverable: documented fresh install that reaches a working RAD admin/runtime page.

### Phase 5: CI and Quality Gates

- Add GitHub Actions CI.
- Add syntax check command.
- Add Composer validation and audit.
- Add baseline tests or smoke scripts.
- Add Dependabot config.
- Add branch protection recommendations.

Deliverable: pull requests show repeatable pass/fail status.

### Phase 6: Private Dry Run

- Create the GitHub repository as private first if possible.
- Push candidate source.
- Confirm GitHub secret scanning has no findings.
- Clone into a new temp directory and test install from scratch.
- Fix docs, paths, Composer autoloading, and bootstrap assumptions found during dry run.
- Tag `v0.1.0-rc.1` or equivalent pre-release.

Deliverable: private GitHub dry run that can be installed from documentation alone.

### Phase 7: Public Launch

- Switch repository visibility to public or recreate from the validated private repository.
- Create initial release notes.
- Mark known limitations clearly in `README.md`.
- Add issue templates for:
  - bug report
  - feature request
  - security issues redirecting to `SECURITY.md`
- Add repository topics:
  - `php`
  - `rad-framework`
  - `low-code`
  - `web-framework`
  - `batoi`
- Publish announcement and link to docs.

Deliverable: public `batoi-rad` repository with a tagged initial release.

## Suggested Automation

Create release preparation scripts under `tools/release/`:

- `build-open-source-tree.php`
  - copies allowlisted files into a clean staging directory
  - refuses to include excluded directories
- `scan-release-tree.php`
  - checks for known secret patterns, logs, sessions, uploads, and disallowed paths
- `verify-install.php`
  - performs a bootstrap smoke test against example config

The first version can be intentionally simple. The important property is repeatability: the public tree should be rebuilt from the internal source using a controlled manifest rather than hand-copying files.

## Open Decisions

- Final license: GPL continuity vs permissive licensing after legal review.
- Repository layout: keep `public_html/` or normalize to `public/`.
- Composer layout: root `composer.json` vs existing `rad/composer.json`.
- Public stability label: beta/pre-release vs stable release.
- Whether RAD Admin front-end vendor assets are committed or package-managed.
- Whether any `rad/ms/` content becomes curated examples.
- Whether current `specs/` documents are public documentation, internal planning artifacts, or excluded.

## Acceptance Criteria

- `batoi-rad` can be cloned without internal/private runtime data.
- No logs, sessions, uploads, tenant data, local config, or live microservice app code are committed.
- License is explicit and dependency-compatible.
- Fresh install instructions are validated.
- CI runs on every pull request.
- Security reporting instructions exist.
- The initial public release has clear known limitations and a reproducible source tree.
