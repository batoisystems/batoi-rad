# Batoi RAD 1.x Compatibility Contract

The machine-readable contract is [`rad/contracts/v1.json`](../contracts/v1.json).
CI verifies its public routes, installer options, required extensions, and
configuration environment variables against the implementation.

## Stable in 1.x

- Supported PHP and MySQL versions listed in the contract.
- The six framework entry routes and their controller responsibilities.
- Documented installer options and environment variables.
- The database migration ledger and immutable applied-migration checksums.
- Batoi AIF contracts used by RAD and the bundled Batoi UIF browser interface.

Compatible minor releases may add optional fields, routes, configuration, and
interfaces. They will not remove or reinterpret a stable surface. Deprecations
are announced in `CHANGELOG.md` and normally remain for at least one minor
release. A security correction may shorten that period and will be called out.

## Internal surfaces

RAD Admin templates, undocumented classes, implementation-specific database
details, and developer execution tools are internal. Applications must not use
them as compatibility boundaries. Provider-specific AI transports are Batoi
AIF responsibilities and are not RAD extension points.
