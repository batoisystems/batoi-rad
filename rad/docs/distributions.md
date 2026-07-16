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
