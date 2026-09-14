# Bundled Distributions

Batoi RAD ships Batoi AIF and Batoi UIF by default. Their source revisions,
versions, licenses, file counts, and integrity values are recorded in
`rad/vendor/batoi/distributions.json` and verified by the release builder.

Batoi AIF is a PHP distribution under `rad/vendor/batoi/aif`. Composer owns all
other content below `rad/vendor`. The release builder installs ordinary locked
packages separately and deliberately preserves the pinned AIF tree.

Batoi UIF is a browser library. Its public distribution is
`public_html/assets/uif`; RAD Admin has a synchronized copy under
`rad/admin/assets/uif` for its isolated asset route. `integrity.json` verifies
both copies. A future packaging simplification may generate both destinations
from one release input, but the v1 artifact verifies that they are identical.

MCP Audit is not shipped. It is development/CI security tooling rather than a
RAD web-runtime dependency and should be installed separately where needed.

## Canonical headless foundation (2.0.1)

Batoi Build acquires exactly `batoi-rad-headless-app.zip` from the latest stable
`batoisystems/batoi-rad` GitHub release. The standard `batoi-rad-VERSION.zip`
remains available with RAD Admin and its existing installation behavior.
The headless package is an additional distribution, not a renamed standard ZIP.

The headless builder reads tracked, allowlisted runtime inputs, relocates the
canonical Community installation SQL from `rad/admin/install` to `rad/install`,
removes the Admin autoloader registration and gateway from the entrypoint and
upgrade script, and excludes `RadAdminController`. It retains core, themes,
public CSS/JS/images/UIF, the complete pinned AIF distribution, migration runner,
installer, configuration example, and locked production Composer dependencies.
Composer dependencies are installed in a fresh temporary directory with plugins,
scripts, and development packages disabled. No local vendor tree is trusted.
Application microservicelets, RAD Admin, local configuration, credentials,
platform data, caches, logs, and Git/development metadata are excluded.
The installer creates mutable directories and credentials only at installation.

### Headless distribution contract

RAD owns the distribution contract in `rad/contracts/headless-app-v1.json` and
its standalone verifier, `rad/bin/verify-headless-release.php`. These codify the
existing wire format verified against Build; they do not change its archive,
manifest, signature, or trust format. RAD builds and releases without a platform
checkout or platform credentials. Build owns acquisition, trusted-key
configuration, and application bootstrap. The optional `HeadlessConsumerTest.php`
loads Build's actual classes for cross-repository compatibility testing.

ZIP layout:

```text
headless-app.manifest.json
headless-app.manifest.sig
headless-app.public-key
artifact/VERSION
artifact/public_html/index.php
artifact/rad/autoload.php
artifact/rad/bin/install.php
artifact/rad/install/{schema.sql,seed.community.sql,sys_core.sql,schema-manifest.json}
artifact/rad/core/...
artifact/rad/vendor/...
artifact/rad/theme/...
artifact/SBOM.cdx.json
```

The manifest has `schema_version: 1`, `profile: headless-app`, a stable `version`,
`source.repository`, `source.tag`, the full `source.commit`, `signing.key_id`,
explicit `include`/`exclude` rules including `rad/admin/**`, and an ordered
`files` array of `{path, sha256}` entries relative to `artifact/`.
`compatibility.contract` is `batoi-build-rad-foundation/v1`, PHP bounds are
`8.3.0 <= version < 9.0.0`, and the tested database engine is MySQL >= 8.0.0.
`database_baseline_hashes` records `schema_sha256`, `seed_sha256`, and
`sys_core_sha256`; the first and last map to the paths used by Build's SQL
quality checks. MariaDB is not claimed without a corresponding test matrix.

The detached signature is base64-encoded, 64-byte Ed25519 over the **exact
manifest bytes**, including the trailing newline. The public-key file contains
the base64-encoded 32-byte key. Build pins:

- Key ID: `batoi-rad-779a3580ce289515`
- Public key: `qNpMsihl9q1r3ofk8q/6clh+BMr+/CsmAQUDeuIfGM4=`

The bundled public key is not a trust-on-first-use mechanism. Production signing
must match this pinned key. On 2026-09-15 the maintainer explicitly authorized
a replacement signing key because the previous private key was unavailable.
Build must adopt the new public key above before acquisition; its previous key
`batoi-rad-4b3542b75435ea4f` remains trusted for historical releases. Build can also
merge explicitly administered keys from
`config['build']['rad_foundation_trusted_keys']`. The ZIP is limited
to 32 MiB, its manifest to 8 MiB, each payload file to 16 MiB, and the unpacked
runtime to 256 MiB and 25,000 files. Build rejects symlinks, traversal, duplicate
manifest paths, executable Admin imports/gateways, and mismatched hashes.

### Build and sign

Use a clean commit. All commands below write artifacts outside the checkout.
`--prepare` may run before the proposed tag exists; its unsigned output cannot
be acquired by Build. Final signing requires `v$(cat VERSION)` to resolve to
HEAD. ZIP timestamps derive from the source commit; order, modes, JSON bytes,
and compression settings are fixed. Reproducibility is checked twice using the
same PHP/Composer/libzip toolchain and locked dependencies.

```sh
mkdir -p /tmp/rad-release
php rad/bin/build-release.php --profile=headless-app --prepare \
  --output=/tmp/rad-release/batoi-rad-headless-app.zip
```

Have the authorized key custodian sign
`batoi-rad-headless-app.zip.unsigned.manifest.json` without reformatting it.
After the reviewed, signed annotated stable tag exists on that same commit:

```sh
php rad/bin/build-release.php --profile=headless-app \
  --signature-file=/secure/path/manifest.sig \
  --output=/tmp/rad-release/batoi-rad-headless-app.zip
```

Alternatively, `--signing-key-file` accepts a permission-restricted file holding
the authorized base64 64-byte Sodium Ed25519 secret key. Never put it in Git,
release assets, logs, or an App repository. The builder verifies the signature
against the pinned public key before producing a signed ZIP. It refuses to
replace an existing ZIP. `--test-mode` requires an explicit test trust override
in the integration test and marks output `.test-only`; these are never release
assets.

Companion assets are the ZIP plus `.sha256`, `.manifest.json`, `.manifest.sig`,
`.public-key`, and `.SBOM.cdx.json`. The manifest and SBOM are also embedded, so
Build only downloads the canonical ZIP. The full ZIP checksum includes the
signature and therefore cannot be finalized before production signing.

### Validation and publication gates

`HeadlessPackageTest.php` validates the package without another repository:
source identity, Ed25519 signature, exact inventory, runtime dependencies,
locked production packages, database baselines, AIF provenance, forbidden
content, and 21 adversarial cases (including invalid payloads re-signed with an
ephemeral test key). `HeadlessRuntimeTest.php` installs the extracted runtime,
reruns installation and upgrades, checks doctor, creates a RAD-owned public DYN
route fixture, and requests home and health through the real entrypoint.

```sh
php rad/bin/verify-headless-release.php \
  --archive=/tmp/rad-release/batoi-rad-headless-app.zip \
  --tag=v2.0.1 --commit=FULL_RESOLVED_TAG_COMMIT \
  --extract=/tmp/new-headless-app
RAD_TEST_DB_NAME=rad_headless_ci RAD_TEST_DB_PASSWORD=... \
  php rad/tests/HeadlessRuntimeTest.php --app=/tmp/new-headless-app
```

Create an empty, isolated `rad_headless_*` MySQL database first. Optional test
variables: `RAD_TEST_DB_HOST`, `RAD_TEST_DB_SOCKET`, `RAD_TEST_DB_USER`.
Never point these tests at Build's database or a customer database.
`--test-public-key-file` on the verifier accepts an explicit ephemeral test key
only for packages marked `test_only` with key ID `test-only`. Production mode
always verifies the pinned release key.

CI runs standalone package and install/HTTP checks on PHP 8.3/8.4 and MySQL
8.0/8.4, including pull requests. No `batoi-www` checkout, private-consumer token,
or platform connection is required. Stable publication reruns these checks and
verifies both the production-signed package and its downloaded release copy.

Optional consumer integration remains available where a Build checkout already
exists. This checks actual catalog registration (only catalog persistence is
replaced), manifest revalidation, installation file loading, generated overlays,
and ten consumer tamper cases. Passing `--consumer` to the runtime test also
executes Build's registration service against the isolated App database.

```sh
php rad/tests/HeadlessConsumerTest.php --consumer=/path/to/batoi-www \
  --archive=/tmp/rad-release/batoi-rad-headless-app.zip \
  --extract=/tmp/new-build-integration-app
RAD_TEST_DB_NAME=rad_headless_ci RAD_TEST_DB_PASSWORD=... \
  php rad/tests/HeadlessRuntimeTest.php --consumer=/path/to/batoi-www \
  --app=/tmp/new-build-integration-app
```

Initial integration validation used consumer commit
`b8403df08d6df811002a7d8c9bd6790bc32b057d`. Its temporary GitHub availability
issue was resolved; neither that commit nor a private token is a RAD release
gate. The separately maintained Build trust list adds the authorized replacement
public key; no Build code is packaged into RAD. Build should run its own integration
checks when adopting new RAD releases.

The stable release workflow requires a signed annotated tag, the authorized
maintainer tag verification key in `RAD_RELEASE_TAG_PUBLIC_KEY` (an environment
variable), and the authorized Ed25519 secret in `RAD_HEADLESS_SIGNING_KEY` (an
Actions secret). Configure required reviewers on the `rad-release` environment.
The maintainer-authorized release environment stores the headless signing secret
and public tag key; private tag signing material remains outside GitHub and Git.
The replacement OpenPGP tag-signing fingerprint is
`748EF608930702F2CF5FCCFA9892B5CF7A608FAB` (expires 2028-09-13).
The key custodian must retain a secure backup of both private keys and the
OpenPGP revocation certificate. Never commit or attach private key material.
The workflow reruns release checks, builds both distributions, checks headless
reproducibility and production trust, creates a new draft, downloads and
compares every asset, revalidates the downloaded ZIP independently, then publishes it as
latest stable. Existing releases/assets are never replaced; failed drafts are
retained for inspection. This task does not authorize tag rewrites.

### Resume SupportFlow after publication

1. Deploy Build's updated trusted-key list (or explicitly configure the new key
   above in `rad_foundation_trusted_keys`). Confirm GitHub's latest stable release
   is `v2.0.1` with exactly one
   `batoi-rad-headless-app.zip`, signed by the pinned key and naming the tag's
   full resolved commit. Keep the existing `Batoi-ACME-CO/supportflow` connection.
2. In SupportFlow's **Source settings**, use **Queue Complete Refresh**. Successful
   source refresh invokes `BuildRadAppBootstrapService::ensure()`, which calls
   `ensureQueued()` when the global foundation is unavailable. A previous failed
   acquisition does not block a new job; an existing queued/running job is reused.
3. Run the normal platform queue. `build_rad_foundation_acquisition` is registered
   at a five-minute frequency and calls `processBatch(1)`. An operator may invoke
   that service inside the authenticated platform runtime to process the queued
   job immediately; do not invent a standalone public acquisition endpoint.
4. Verify the global acquisition job completes, the release catalog is `active`,
   and validation succeeds. The worker automatically retries ready RAD Apps
   (up to its existing 250-App query limit). If needed, complete another selected-App
   source refresh or use **Stage RAD Foundation** after `install_ready` is true.
5. Review the generated source proposal and `.build/rad-foundation.json` pin.
   The installation should become `review`. Apply/publish the reviewed App source,
   install baseline SQL and registration records through the configured selected-App
   database connection, deploy to sandbox, and run sandbox verification before
   promotion. Foundation acquisition alone does not deploy SupportFlow.
