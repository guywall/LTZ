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

## VPS install

Upload this project folder to the VPS, then run the installer from the project root:

```bash
chmod +x deploy/install-vps.sh
sudo DOMAIN=yourdomain.com CONFIGURE_NGINX=yes INSTALL_PACKAGES=yes deploy/install-vps.sh
```

The installer creates the MySQL database/user, imports `database/schema.sql`, imports `database/seed.sql`, writes `config/config.php`, sets file permissions, and can create the Nginx site.

Useful installer options:

```bash
sudo DB_NAME=ltz DB_USER=ltz_app DB_PASS='strong-password' deploy/install-vps.sh
sudo RESET_DB=yes CONFIGURE_NGINX=yes DOMAIN=yourdomain.com deploy/install-vps.sh
sudo INSTALL_SEED=no deploy/install-vps.sh
```

After the domain points at the VPS, add HTTPS:

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

See `deploy/README.md` for the full installer reference.

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

## Verification

```bash
php -l public/index.php
php -l app/app.php
php -l app/kpi.php
php tests/kpi_test.php
```
