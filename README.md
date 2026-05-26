# LTZ Operational Intelligence

Custom PHP + MySQL web app for the LTZ weekly cadence workflow that was originally mapped for Google AppSheet.

## What is included

- Named login accounts with PHP sessions and hashed passwords.
- Role-based access for barbers, training, social, HR, leadership, strategy, and admin.
- Weekly submission forms for each functional area.
- RAG calculations based on the supplied workbook targets and formulas.
- Executive, functional, leadership, and 5x5 strategy dashboards.
- Risk register, action tracker, target editing, lookup editing, and user creation.
- MySQL schema and seed data from the workbook.

## Local setup

1. Create a MySQL database.
2. Import the schema and seed data:

```bash
mysql -u root -p ltz_operational_intelligence < database/schema.sql
mysql -u root -p ltz_operational_intelligence < database/seed.sql
```

3. Copy `config/config.php.example` to `config/config.php` and update the database credentials.
4. Run the app locally:

```bash
php -S 127.0.0.1:8080 -t public
```

5. Open `http://127.0.0.1:8080`.

Seed accounts use `ChangeMe123!` as the password. Example logins:

- `cosmin@ltz.local`
- `mario@ltz.local`
- `ravi@ltz.local`
- `luke@ltz.local`
- `martin@ltz.local`
- `barbers@ltz.local`
- `training@ltz.local`
- `social@ltz.local`
- `hr@ltz.local`

Change these passwords before any real deployment.

## VPS deployment notes

- PHP 8.2+ with PDO MySQL enabled.
- MySQL 8+.
- Apache or Nginx should point the web root at `public/`.
- `config/config.php` should not be committed.
- Use HTTPS and secure session cookie settings at the web server/PHP configuration level.

## Verification

```bash
php -l public/index.php
php -l app/app.php
php -l app/kpi.php
php tests/kpi_test.php
```

