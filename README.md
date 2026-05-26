# Batoi RAD

Batoi RAD is a PHP rapid application development framework for building database-backed web applications. It provides a structured runtime for routing, microservice-style application modules, administration tools, API entry points, configuration loading, session handling, and role-based access control.

This repository contains the reusable framework source. It is not intended to be a dump of a running installation, and it should not contain runtime configuration, logs, uploads, generated caches, tenant data, secrets, or application-specific private code.

## Features

- PHP application runtime with a public entry point under `public_html/`.
- Microservice-style module and route definitions.
- RAD Admin console for framework management workflows.
- API gateway for application and system-level requests.
- PDO-based database access helpers.
- Configuration loading from filesystem and database-backed sources.
- Session and request handling utilities.
- Role-based access control using memberships, roles, and permission bindings.
- Clear separation between framework system tables and application/domain tables.

## Repository Layout

| Path | Purpose |
| --- | --- |
| `public_html/` | Public web entry point and public assets. |
| `rad/autoload.php` | Framework autoload bootstrap. |
| `rad/core/sys/` | Core runtime services such as request, configuration, database, controller, and view support. |
| `rad/core/app/` | Application-facing framework helpers. |
| `rad/admin/classes/` | RAD Admin module classes. |
| `rad/admin/routes/` | RAD Admin route handlers. |
| `rad/admin/ui/` | RAD Admin templates. |
| `rad/admin/assets/` | Admin console static assets. |
| `rad/admin/install/` | Core schema and installation SQL assets. |
| `rad/bin/` | Framework command-line utilities. |
| `rad/upgrades/` | Upgrade scripts and migration helpers. |
| `specs/` | Project notes and reference material. |

## Requirements

- PHP with PDO support.
- A PDO-supported database.
- Composer for PHP dependencies.
- A web server configured to serve `public_html/` as the document root.

Install Composer dependencies from the framework directory:

```sh
cd rad
composer install
```

## Database Conventions

RAD uses table prefixes to distinguish framework-owned data from application/domain data.

| Prefix | Purpose |
| --- | --- |
| `s_` | System/framework tables, such as identity, configuration, routing, roles, permissions, sessions, navigation, and microservice metadata. |
| `a_` | Application/domain tables owned by a specific app, tenant, or business module. |

Common system table concepts include:

- `s_entity` for identities, users, and API principals.
- `s_config` for database-backed configuration overrides.
- `s_ms` for microservice/module definitions.
- `s_msroute` for route definitions.
- `s_role` for role definitions.
- `s_permission_binding` for role-to-object permissions.
- `s_space_membership` for principal membership in a workspace or tenant context.
- `s_space_membership_role` for assigning roles to memberships.
- `s_team` and `s_team_member` for team-based grouping and inherited access.

Installation SQL assets are available under `rad/admin/install/`.

## Runtime Model

1. The public entry script initializes autoloading, configuration, logging, database access, request context, session state, and shared runtime data.
2. Requests are dispatched to the appropriate controller path, such as public site routing, API handling, authentication, or RAD Admin.
3. Microservice and route definitions determine how application requests are resolved.
4. Rendering is handled through template and view helpers for public or administrative output.
5. Access is evaluated through administrative privileges or runtime roles and permission bindings, depending on the request context.

## Access Control

RAD separates administrative privileges from application runtime permissions.

- RAD Admin access is controlled by admin-specific privilege checks.
- Public/runtime access is controlled by roles, permission bindings, route scope, and application context.
- Navigation rendering should be filtered by the roles available to the active principal.
- Developer-facing helper classes should not implicitly expose RAD Admin privilege logic into runtime application code.

## Repository Hygiene

Keep public repository contents limited to reusable framework source, sanitized documentation, examples, installation assets, and reproducible setup files.

Do not commit:

- Runtime configuration.
- Logs.
- Uploads.
- Sessions.
- Generated caches.
- Tenant or customer data.
- Secrets or credentials.
- Private database dumps.
- Local environment files.
- Application-specific microservices unless they are sanitized examples.

## Documentation Status

Detailed documentation is being organized. The following topics should be split into focused docs as the repository matures:

- Architecture.
- Installation.
- Configuration.
- Routing and microservices.
- RAD Admin console.
- API gateway.
- Security and RBAC.
- Upgrade process.

For now, see `specs/old-reference.md` for a compact sanitized reference.

## License

Batoi RAD is licensed under the Apache License 2.0. See `LICENSE` for details.
