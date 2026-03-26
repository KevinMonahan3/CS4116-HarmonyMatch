# Deploying HarmonyMatch on the Oracle VM

This app is a plain PHP site with static assets and MySQL. For this VM, the simplest production setup is:

- Nginx
- PHP-FPM
- MySQL on the same host
- Let's Encrypt for HTTPS

## Current VM notes

- Host: `193.123.178.162`
- OS: Ubuntu 22.04
- MySQL is already running
- MySQL is currently listening on `0.0.0.0:3306`
- No web server or PHP is installed yet

Because the app and database will live on the same VM, MySQL should be moved to `127.0.0.1` so it is not publicly exposed.

## 1. Install the web stack

```bash
sudo apt update
sudo apt install -y nginx php-fpm php-mysql php-cli php-curl php-mbstring php-xml php-zip unzip
```

Optional but recommended:

```bash
sudo apt install -y certbot python3-certbot-nginx
```

## 2. Add a little swap

This VM has about 1 GB RAM and no swap. Adding swap helps avoid crashes during package installs and traffic spikes.

```bash
sudo fallocate -l 1G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
free -h
```

## 3. Upload the application

Pick one approach:

### Option A: copy from your machine

```bash
rsync -avz --delete \
  -e "ssh -i ~/Downloads/ssh-key-2026-03-13.key" \
  /home/kassym/soft_dev/CS4116-HarmonyMatch/ \
  ubuntu@193.123.178.162:/tmp/CS4116-HarmonyMatch/
```

Then on the VM:

```bash
sudo mkdir -p /var/www/harmonymatch
sudo rsync -av --delete /tmp/CS4116-HarmonyMatch/ /var/www/harmonymatch/
sudo chown -R www-data:www-data /var/www/harmonymatch
```

### Option B: git clone on the VM

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone <your-repo-url> harmonymatch
sudo chown -R www-data:www-data /var/www/harmonymatch
```

## 4. Add production DB config

Create `/var/www/harmonymatch/config/db.local.php`:

```php
<?php

return [
    'DB_ENABLED' => true,
    'DB_DRIVER' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'projectdb',
    'DB_USER' => 'your_mysql_user',
    'DB_PASS' => 'your_mysql_password',
    'DB_CHARSET' => 'utf8mb4',
];
```

Set ownership:

```bash
sudo chown www-data:www-data /var/www/harmonymatch/config/db.local.php
sudo chmod 640 /var/www/harmonymatch/config/db.local.php
```

## 5. Configure Nginx

Create `/etc/nginx/sites-available/harmonymatch`:

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com 193.123.178.162;

    root /var/www/harmonymatch;
    index index.php index.html;

    access_log /var/log/nginx/harmonymatch_access.log;
    error_log /var/log/nginx/harmonymatch_error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Enable it:

```bash
sudo ln -s /etc/nginx/sites-available/harmonymatch /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl enable nginx
sudo systemctl enable php8.1-fpm
```

If the VM installs a newer PHP version, update the socket path accordingly:

```bash
ls /var/run/php/
```

## 6. Secure MySQL for same-host access

Edit MySQL config:

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Change:

```ini
bind-address = 127.0.0.1
```

Then restart MySQL:

```bash
sudo systemctl restart mysql
sudo ss -tulpn | grep 3306
```

## 7. Open only the needed ports

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

Also update Oracle Cloud networking so the instance allows:

- `22/tcp` from your admin IP if possible
- `80/tcp` from the internet
- `443/tcp` from the internet

You should remove any public `3306` ingress rule once the app uses local MySQL access.

## 8. Point your domain to the VM

Create DNS `A` records for:

- `your-domain.com -> 193.123.178.162`
- `www.your-domain.com -> 193.123.178.162`

Wait for DNS to resolve before enabling HTTPS.

## 9. Enable HTTPS

```bash
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

Check renewal:

```bash
sudo systemctl status certbot.timer
```

## 10. Verify the app

Run these checks:

```bash
curl -I http://127.0.0.1
curl -I http://your-domain.com
php -m | grep -i pdo
php -m | grep -i mysql
```

Open:

- `/login.php`
- `/dashboard.php`
- `/admin.php`
- `/api/auth.php`

## Suggested rollout order

1. Back up the current MySQL database.
2. Install Nginx, PHP-FPM, and swap.
3. Copy the app to `/var/www/harmonymatch`.
4. Create `config/db.local.php` on the VM.
5. Configure Nginx and confirm the app loads over HTTP.
6. Change MySQL to `127.0.0.1`.
7. Lock down firewall and Oracle ingress rules.
8. Attach the domain and enable HTTPS.

## Notes for this project

- The frontend is not a separate server. It is static HTML/CSS/JS served by Nginx from the same PHP app root.
- API calls like `/api/auth.php` assume the site is deployed at the domain root, not under a subfolder.
- If you later add user photo uploads, make sure the upload directory is writable by `www-data`.
