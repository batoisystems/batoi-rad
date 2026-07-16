# Installation and Upgrade

## Supported Platform

Batoi RAD 1.0 targets PHP 8.3 and 8.4 with MySQL 8.0 or 8.4. MariaDB
compatibility is not guaranteed until it is added to CI. Required PHP
extensions are `curl`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`,
`pdo_mysql`, and `zip`.

Serve only `public_html/` as the document root. Apache can use the supplied
`public_html/.htaccess`. An equivalent Nginx location is:

```nginx
root /srv/batoi-rad/public_html;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
}
```

Keep `rad/config`, `rad/data`, `rad/log`, and `rad/ms` outside public routing.

## Install

Create an empty UTF-8 MySQL database and run:

```sh
php rad/bin/install.php
```

The installer performs platform preflight, creates runtime directories,
installs locked Composer dependencies, imports the canonical schema, baselines
bundled migrations, provisions the ID-1 administrator, and publishes local
configuration atomically. A failed fresh schema import is cleaned up so it can
be retried.

For noninteractive use, pass credentials through the `RAD_DB_*`,
`RAD_BASE_URL`, and `RAD_ADMIN_*` environment variables and add
`--non-interactive`. Avoid putting passwords in shell history.

Use `--db-port`, `--db-socket`, or `--db-ssl-ca` when required. Database and
administrator secrets may be supplied with `--db-password-file` and
`--admin-password-file`, or their `_FILE` environment equivalents. Run
`php rad/bin/install.php --help` for the complete option list.

`--check` performs platform and filesystem preflight without credentials.
`--dry-run` validates all inputs, credentials, and existing database state but
does not install dependencies, write configuration, or mutate the database.

After installation:

```sh
php rad/bin/doctor.php
```

## Upgrade

Back up the database plus `rad/config`, `rad/data`, `rad/log`, and application
code before deployment. Install the new locked Composer dependencies, then run:

```sh
php rad/bin/upgrade.php
php rad/bin/doctor.php
```

Migration state and checksums are stored in `s_migration`. Never edit an
applied migration; add a new migration. Only the latest applied migration can
be rolled back, and only when it declares a rollback handler.

The canonical install inputs are `schema.sql` and `seed.community.sql`.
`sys_core.sql` is a generated compatibility artifact. CI verifies its content
and the hashes recorded in `schema-manifest.json`.

## Default AI and Developer Safety

Batoi AIF and Batoi UIF ship with the distribution, but AI code assistance and
source/SQL developer tools are disabled by default. A system administrator may
enable `ai_code_assist_enabled` or `developer_tools_enabled` deliberately after
configuring privileges and provider credentials. Provider code must remain in
Batoi AIF adapters.
