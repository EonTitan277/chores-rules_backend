# Running the Chores & Rules App with Laragon

This guide explains how to run the PHP Chores & Rules application using **Laragon** instead of Docker for faster debugging and testing.

## Prerequisites

1. **Laragon** installed (Full version recommended — includes PHP, MySQL, Apache, Redis, etc.)
   - Download from: https://laragon.org/download/
   - Choose "Full" edition for all services
2. **Git** (for cloning if needed)

---

## Step 1: Start Laragon Services

1. Open Laragon
2. Click **"Start All"** — Apache and MySQL should turn green
3. Verify PHP version: open a terminal (Ctrl+Alt+T or Laragon Terminal) and run:
   ```bash
   php -v
   ```
   Should show PHP 8.2+ (project uses PHP 8.3 features; PHP 8.1+ is the minimum).

### Enable Required PHP Extensions
1. Laragon Menu → **PHP** → **Extensions**
2. Enable:
   - `pdo_mysql` (required for database)
   - `mysqli` (optional, but recommended)
   - `mbstring` (usually enabled by default)
   - `openssl` (usually enabled by default)
   - `curl` (usually enabled by default)
3. Click **"Reload Apache"**

---

## Step 2: Place the Project

**Option A: Use existing project location (recommended)**
Keep the project where it already is and point a virtual host at it:
```
C:\gits\chores-rules_backend\public
```

**Option B: Copy to the Laragon www folder**
```bash
cd C:\laragon\www
git clone <your-repo> chores-app
```
With this option, Laragon can auto-generate the virtual host (see Step 4, Option A).

---

## Step 3: Set Up the Database

> **Automated alternative**: after configuring `.env` and generating the password hashes, run `php setup-database.php` from the project root. It creates the database, `users` table, and `login_attempts` table, then seeds the configured users. The optional credential pairs must be configured together when used.

### 3.1 Create Database and App User
Open Laragon → Menu → MySQL → **HeidiSQL** (or any MySQL client), connect with `root` / empty password, and run:

```sql
-- Create database
CREATE DATABASE IF NOT EXISTS `chores`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Create application user (optional but recommended)
CREATE USER IF NOT EXISTS 'chores_app'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON `chores`.* TO 'chores_app'@'localhost';
FLUSH PRIVILEGES;
```

### 3.2 Run Database Schema
Select the `chores` database and run the schema from `docker/init/001-schema.sql`:

```sql
CREATE TABLE IF NOT EXISTS `users` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`     VARCHAR(64)  NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`         ENUM('kid', 'admin', 'readonly') NOT NULL DEFAULT 'kid',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Create the Login Attempts Table
Select the `chores` database in HeidiSQL, open `docker/init/003-login-attempts.sql`, and execute it. This only needs to be done once; `public/login.php` records failed login attempts automatically afterward.

```sql
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip`           VARCHAR(45)  NOT NULL,
    `attempted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_attempts_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 Generate Password Hashes
In the project root:
```bash
php hash-passwords.php "your_admin_password"
php hash-passwords.php "your_kid_password"
# Only if configuring ADMIN_2:
php hash-passwords.php "your_parent_password"
# Only if configuring READONLYUSER:
php hash-passwords.php "your_viewer_password"
```

### 3.5 Seed the Database (Create Users)
Adapt from `docker/init/002-seed.sh`, using the hashes generated above:

```sql
USE `chores`;

-- Replace these hashes with your own generated bcrypt hashes!
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`) VALUES
  ('admin',  'your_bcrypt_hash', 'admin'),
  ('kid',    'your_bcrypt_hash',   'kid');
```

If `ADMIN_2` and `PASSWORD_HASH_2` are both configured, add the second admin row to the seed SQL. If `READONLYUSER` and `READONLYPASS_HASH` are both configured, add a row with role `readonly`. If using `php setup-database.php`, no manual seed SQL is required.

---

## Step 4: Configure the `.env` File

Copy the Laragon-specific template and update it with your own values:
```bash
copy .env.laragon .env
```
(On non-Windows shells, use `cp .env.laragon .env`.)

Paste the password hashes from Step 3.4 into the appropriate `.env` keys. The optional admin and read-only credential pairs may be left commented out, but each pair must be configured together when used.

---

## Step 5: Create the Apache Virtual Host

### Option A: Auto Virtual Host (Easiest — Laragon Magic)
1. Rename/place your project folder as `C:\laragon\www\chores-app` (see Step 2, Option B)
2. Ensure **auto-vhosts** is enabled: Laragon Menu → **Apache** → **sites-enabled** → **auto-vhosts**
3. Click **"Reload Apache"**
4. Laragon auto-creates `http://chores-app.test` in the Windows hosts file, with an SSL cert

### Option B: Manual Virtual Host (for an existing project location)
Create `C:\laragon\etc\apache2\sites-enabled\chores-app.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/path/to/the/public/folder"
    ServerName chores-app.test
    ServerAlias *.chores-app.test

    <Directory "C:/path/to/the/public/folder">
        AllowOverride All
        Require all granted

        # Enable rewrite engine for clean URLs
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>

    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:fcgi://127.0.0.1:9000"
    </FilesMatch>

    ErrorLog "C:/path/to/the/logs-folder/apache_error.log"
    CustomLog "C:/path/to/the/logs-folder/apache_access.log" common
</VirtualHost>
```

Add to the Windows hosts file (`C:\Windows\System32\drivers\etc\hosts`):
```
127.0.0.1    chores-app.test
```

Click **"Reload Apache"** in Laragon.

---

## Step 6: Test the Application

### 6.1 Verify PHP/Environment Health
Visit: `http://chores-app.test/test-php.php` — you should see all green checkmarks.

### 6.2 Access and Log In
Open `http://chores-app.test` in your browser. You should see the login page. Log in with a seeded user:

If a second admin was configured, also test that account using the username and password assigned to it.

### 6.3 Verify Dashboard
After login, confirm the dashboard shows links to:
- Introduction
- Chore & Screen Time Rules
- School Break Screen Time Rules
- Daily Chores List
- Consequences & Punishments

---

## Troubleshooting (Windows 11)

**"Server configuration error: required environment variables are missing"**
- Check the `.env` file exists in the project root
- Verify all required keys are set (see `includes/config.php` `REQUIRED_KEYS`)
- Ensure no extra spaces around `=` in `.env`

**"Database connection failed"**
- Verify MySQL is running in Laragon (green indicator)
- Check credentials in `.env` match your database user
- Test the connection in HeidiSQL first

**"Directory not writable" errors**
- Ensure `logs/` and `data/` directories exist
- As Administrator, run: `icacls logs /grant "Everyone:(OI)(CI)F" /T`

**"404 Not Found" for pages**
- Ensure Apache `mod_rewrite` is enabled (on by default in Laragon)
- Check `.htaccess` or virtual host rewrite rules
- Verify `AllowOverride All` in the virtual host config

**Port 80 already in use**
- Change the Laragon Apache port: Menu → Apache → Port → 8080

---

## Quick Reference Commands

```bash
# Generate password hash
php hash-passwords.php "your_password"

# Generate session secret
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"

# Check PHP version and extensions
php -v
php -m | findstr pdo_mysql

# Test database connection
php -r "
\$pdo = new PDO('mysql:host=localhost;dbname=chores', 'chores_app', 'your_password');
echo 'Connected successfully!';
"
```