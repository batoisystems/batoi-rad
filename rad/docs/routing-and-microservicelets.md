# Routing and Microservicelets

Batoi RAD supports one microservicelet type: **DYN**. New records, imported
packages, browser requests, API requests, route files, and administrator tools
all use named dynamic routes.

## URL Shapes

- Platform or global: `/{microservicelet}/{route}/{arguments...}`
- Workspace with a configured prefix:
  `/{workspace_prefix}/{space_slug}/{microservicelet}/{route}/{arguments...}`
- Workspace without a prefix:
  `/{space_slug}/{microservicelet}/{route}/{arguments...}`

Route files use the route name: `route.{route_name}.php` and the corresponding
page/pre/post part files. Numeric route IDs and route UIDs are not URL or file
keys.

## Breaking Upgrade Rule

The DYN-only database migration does not convert legacy records or rename their
files. It stops if `s_ms` contains a type other than `DYN`. Remove or rebuild
those microservicelets as DYN before retrying the upgrade. There is no legacy
routing fallback.
