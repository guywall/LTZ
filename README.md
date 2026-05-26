# LTZ Operational Intelligence

Custom PHP + MySQL web app for the LTZ weekly cadence workflow that was originally mapped for Google AppSheet.

## What is included

- Named login accounts with PHP sessions and hashed passwords.
- Role-based access for barbers, training, social, HR, leadership, strategy, and admin.
- Dated submission forms for each functional area, with dashboards filtered by reporting period.
- RAG calculations based on the supplied workbook targets and formulas.
- Executive, functional, leadership, and 5x5 strategy dashboards.
- Risk register, action tracker, target editing, lookup editing, and user creation.
- MySQL schema and seed data from the workbook.

## Plesk web install

This is the simplest install path for Plesk, where Nginx, MySQL, and PHP-FPM already exist.

1. In Plesk, create a MySQL database and database user.
2. Upload the project files to the domain.
3. Set the domain document root to the `public` folder.
4. Visit:

```
https://yourdomain.com/setup.php
```

5. Enter the Plesk database host, database name, user, and password.
6. Leave "Install seed data" ticked for the starter users and workbook sample data.
7. Submit the form.

The setup page will create the tables, import the seed data, write `config/config.php`, and lock itself with `config/installed.lock`.

If the setup page says the `config` directory is not writable, adjust permissions in Plesk File Manager for the setup step, then restore tighter permissions after install.

## SSH VPS install

If you are not using Plesk and want a command-line installer, upload this project folder to the VPS and run from the project root:

```bash
chmod +x deploy/install-vps.sh
sudo DOMAIN=yourdomain.com CONFIGURE_NGINX=yes INSTALL_PACKAGES=yes deploy/install-vps.sh
```

Useful SSH installer options:

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
