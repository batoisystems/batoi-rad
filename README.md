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
| `rad/config/` | Local configuration template and installation configuration. |
| `rad/data/` | Writable runtime data, caches, uploads, queues, and version history. |
| `rad/docs/` | Framework documentation. |
| `rad/log/` | Writable application logs and sessions. |
| `rad/ms/` | Application microservice and route code. |
| `rad/tests/` | Framework tests. |
| `rad/vendor/` | Bundled PHP distributions and Composer-generated dependencies. |
| `rad/upgrades/` | Upgrade scripts and migration helpers. |
| `specs/` | Project notes and reference material. |

## Requirements

- PHP 8.3 or 8.4 with `curl`, `fileinfo`, `json`, `mbstring`, `openssl`,
  `pdo_mysql`, and `zip`.
- MySQL 8.0 or 8.4.
- Composer for PHP dependencies.
- A web server configured to serve `public_html/` as the document root.

Create an empty database, then run the installer from the project root:

```sh
php rad/bin/install.php
```

The interactive installer creates runtime directories, writes local
configuration, installs Composer dependencies, initializes the database, sets
the base URL, and provisions the ID-1 system administrator required by RAD
Admin.

For automation, provide secrets through environment variables rather than
command-line arguments:

```sh
RAD_DB_HOST=127.0.0.1 \
RAD_DB_NAME=batoi_rad \
RAD_DB_USER=batoi_rad \
RAD_DB_PASSWORD='database-secret' \
RAD_BASE_URL='https://rad.example.test' \
RAD_ADMIN_NAME='RAD Administrator' \
RAD_ADMIN_USERNAME='admin' \
RAD_ADMIN_EMAIL='admin@example.test' \
RAD_ADMIN_PASSWORD='StrongAdministratorPassword2026' \
php rad/bin/install.php --non-interactive
```

Administrator passwords must be at least 12 characters and include upper-case,
lower-case, and numeric characters. Re-running the installer preserves an
existing complete schema and matching system administrator. It refuses partial
or ambiguous database states instead of modifying them.

Configure the web server to use `public_html/` as its document root. The web
server user must be able to write to `rad/data/` and `rad/log/`.

Run `php rad/bin/doctor.php` after installation. See
[`rad/docs/installation.md`](rad/docs/installation.md) for Apache/Nginx setup,
upgrades, backups, and safety defaults.

## Default Distributions

RAD includes two first-party distributions by default:

- Batoi UIF is a client-side library, so its complete distribution is served
  from `public_html/assets/uif/`. RAD Admin keeps a synchronized runtime copy in
  `rad/admin/assets/uif/` for its separate admin asset route.
- Batoi AIF is a PHP library and is shipped under `rad/vendor/batoi/aif/`. Its
  autoloader is registered automatically, while provider credentials and AIF
  execution remain opt-in.

All AI provider calls, inference, embeddings, RAD code assistance, and AI API
endpoints pass through Batoi AIF. RAD core contains no provider clients or AI
transport implementations. Additional providers must be supplied as Batoi AIF
provider adapters. See `rad/docs/aif-integration.md`.

Composer continues to create all other `rad/vendor/` content. MCP Audit is not
bundled because it is a platform-specific development and CI security tool,
not a web-runtime dependency.

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

## Routing And API Model

RAD applications are organized around microservice-style modules and route definitions.

- A microservice definition identifies the application module being requested.
- A route definition determines how the request is handled.
- Route fragments can support pre-processing, page rendering, and post-processing.
- Static pages, slug routes, UID-based lookups, and dynamic PHP route handlers are supported.
- API requests follow the same broad routing model while using API-specific request validation and response handling.

The API gateway accepts structured requests and routes them to application or system-level handlers based on payload type and configured allowlists. Public API documentation should describe the contract without exposing private keys, private endpoint inventories, or environment-specific service details.

Expected API concepts include:

- API keys associated with framework identities.
- Application API calls routed to microservice routes.
- System API calls restricted to configured tables, services, or named endpoints.
- Server-side allowlists for sensitive operations.
- Optional endpoint-level restrictions for API identities.

## Access Control

RAD separates administrative privileges from application runtime permissions.

- RAD Admin access is controlled by admin-specific privilege checks.
- Public/runtime access is controlled by roles, permission bindings, route scope, and application context.
- Navigation rendering should be filtered by the roles available to the active principal.
- Developer-facing helper classes should not implicitly expose RAD Admin privilege logic into runtime application code.

The framework RBAC model is based on principals, memberships, roles, and permission bindings. A typical runtime permission check resolves the active principal, workspace or application membership, direct and inherited roles, matching permission bindings, and whether the requested object and action are allowed.

Principals can represent users, teams, API identities, or organization-like entities. A membership links a principal to a workspace or tenant context. Roles can be assigned to memberships and may be scoped to a workspace, application module, or other supported context.

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
- Composer-generated vendor dependencies, except the curated first-party
  distributions under `rad/vendor/batoi/`.

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

## License

Batoi RAD is licensed under the Apache License 2.0. See `LICENSE` for details.
