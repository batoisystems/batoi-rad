# Batoi RAD Compatibility Contract

The current machine-readable contract is
[`rad/contracts/v2.json`](../contracts/v2.json). The historical 1.x contract is
retained as [`rad/contracts/v1.json`](../contracts/v1.json). CI selects the
contract matching the major version in the repository-root `VERSION` file and
verifies its public routes, installer options, required extensions, and
configuration environment variables against the implementation.

## Stable in 2.x

- Supported PHP and MySQL versions listed in the contract.
- The six framework entry routes and their controller responsibilities.
- Documented installer options and environment variables.
- The database migration ledger and immutable applied-migration checksums.
- Batoi AIF contracts used by RAD and the bundled Batoi UIF browser interface.

Compatible minor releases may add optional fields, routes, configuration, and
interfaces. They will not remove or reinterpret a stable surface. Deprecations
are announced in `CHANGELOG.md` and normally remain for at least one minor
release. A security correction may shorten that period and will be called out.

## Breaking change from 1.x

Batoi RAD 2.x supports only named DYN microservicelets. The STA, ID, and UID
routing models are not supported. The 2.0 migration refuses to alter the schema
until all non-DYN microservicelets have been removed or rebuilt.

## Internal surfaces

RAD Admin templates, undocumented classes, implementation-specific database
details, and developer execution tools are internal. Applications must not use
them as compatibility boundaries. Provider-specific AI transports are Batoi
AIF responsibilities and are not RAD extension points.
