# VPS Installer

Use `deploy/install-vps.sh` on the VPS after uploading the project.

## One-command install

From the project root on the VPS:

```bash
chmod +x deploy/install-vps.sh
sudo DOMAIN=yourdomain.com CONFIGURE_NGINX=yes INSTALL_PACKAGES=yes deploy/install-vps.sh
```

That will:

- install Nginx, MySQL, PHP-FPM, and PHP MySQL support
- create the MySQL database and app user
- import `database/schema.sql`
- import `database/seed.sql`
- write `config/config.php`
- set web-server permissions
- create and enable an Nginx site pointing at `public/`

## Useful options

```bash
sudo DB_NAME=ltz DB_USER=ltz_app DB_PASS='strong-password' deploy/install-vps.sh
```

```bash
sudo RESET_DB=yes CONFIGURE_NGINX=yes DOMAIN=yourdomain.com deploy/install-vps.sh
```

```bash
sudo INSTALL_SEED=no deploy/install-vps.sh
```

## HTTPS

After the domain points at the VPS:

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

