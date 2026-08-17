# Chores & Rules

A child-friendly, private PHP web app for managing household chores, rules, rewards, and screen time. It is tailored for a specific autistic child who needs clear, structured instructions and well-defined incentives to understand expectations.

## 💡 Main Features

- 🔐 Password-protected login with admin and kid roles
- 🏠 Household chores, behavior rules, rewards, screen-time guidance, and word definitions
- 💾 Persistent chore checkboxes and editable notes with automatic saving
- 🛡️ Role-based editing permissions for chore text and consequence checkboxes
- 📊 Progress tracking for completed chores
- 🎆 Fireworks celebration when all required chores are complete
- 🔒 CSRF protection, session security, login rate limiting, and authentication logging
- 🐳 Docker-based PHP and MySQL deployment with persistent data storage

## 🚢 Docker setup

### Prerequisites

- Docker Desktop with Docker Compose
- PHP CLI on the host, only for generating password hashes and the session secret

### 1. Create the environment file

Copy `.env.docker` to `.env` and replace every placeholder value. Keep `.env` private; it contains database passwords and account hashes.

```bash
cp .env.docker .env
```

Set at least:

- `HOST_PORT`: the local port for the web app, such as `3001`
- `MYSQL_PASSWORD` and `MYSQL_ROOT_PASSWORD`: strong database passwords
- `COUNCIL_NAME` and `COUNCIL_PHONE`: contact information shown in the rules
- `ADMIN_1` and `KIDUSER`: login usernames
- `PASSWORD_HASH_1` and `KIDPASS_HASH`: generated password hashes
- `ADMIN_2` and `PASSWORD_HASH_2`: optional second admin credentials; configure both or neither
- `SESSION_SECRET`: a generated random secret

### 2. Generate the session secret

Run this from the project directory:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Copy the output into `SESSION_SECRET` in `.env`. This value should be long, random, and different for each deployment.

### 3. Generate password hashes

Generate a bcrypt hash for each account password:

```bash
php hash-passwords.php "your_admin_password"
php hash-passwords.php "your_kid_password"
# Only when configuring ADMIN_2:
php hash-passwords.php "your_parent_password"
```

Copy the results into `PASSWORD_HASH_1` and `KIDPASS_HASH` (and `PASSWORD_HASH_2` only when configuring `ADMIN_2`). Use strong, unique passwords and do not commit the plain-text passwords or `.env` file.

### 4. Start the application

```bash
docker compose up -d --build
```

Open `http://localhost:<HOST_PORT>` in a browser, for example `http://localhost:3001`.

The first database startup creates the schema and seeds the users from `.env`. `ADMIN_2` is optional; if it is used, `PASSWORD_HASH_2` must also be provided. MySQL data is stored in the Docker volume `db-data`; checkbox/text data and logs are stored in the host folders configured by `DATA_PATH` and `LOGS_PATH`.

### Useful commands

```bash
# View application and database logs
docker compose logs -f

# Stop containers without deleting data
docker compose down

# Stop containers and delete the database volume (destructive)
docker compose down -v
```

If you change seeded usernames or password hashes after the database has already been initialized, the seed script will not run again automatically. Update the users in the database or recreate the database volume deliberately.

## 🚀 Production deployment with NGINX Proxy Manager

Do not expose the application directly to the public internet over plain HTTP. Put NGINX Proxy Manager (NPM) in front of the app and configure a Proxy Host:

1. Point your DNS record, such as `rules.example.com`, to the server running NPM.
2. Create a Proxy Host for that domain and forward it to the Docker host and the published app port, for example `http://127.0.0.1:3001`.
3. Request a Let's Encrypt certificate in NPM and enable **Force SSL**. HTTP should redirect to HTTPS.
4. Enable WebSocket support if required by your NPM setup, and preserve the `Host`, `X-Forwarded-For`, and `X-Forwarded-Proto` headers. In NPM's GUI, open the Proxy Host, select **Edit**, and review the **Advanced** tab. Add these directives if your NPM version or custom configuration does not already provide them:

	```nginx
	proxy_set_header Host $host;
	proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
	proxy_set_header X-Forwarded-Proto $scheme;
	```

	Click **Save** and, if necessary, restart or reload NPM. NPM normally sets these proxy headers automatically, so do not add duplicate directives unless they are missing or have been overridden.
5. Restrict the Docker app port with a firewall so it is reachable only from NPM or the local host. Public traffic should use the HTTPS domain, not the raw Docker port.

The application uses `HttpOnly`, `SameSite=Lax`, and persistent 30-day session cookies. The `Secure` cookie flag is enabled when PHP detects HTTPS (or port 443), so verify in the browser's developer tools that the session cookie is marked `Secure` after deploying behind NPM. Never use a plain-HTTP public deployment for authenticated access. Keep `SESSION_SECRET` strong and private, and regenerate it if it is exposed; existing sessions will then be invalidated.

### Cloudflare proxy note

If Cloudflare's DNS record is **Proxied** (orange cloud), NPM sees Cloudflare's IP instead of the visitor's IP. Configure NPM/Nginx to restore the visitor's address from Cloudflare's `CF-Connecting-IP`, then forward it with `proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;`. Trust the Cloudflare header only from Cloudflare IP ranges. This keeps login rate limiting and authentication logs accurate.

Keep the origin and Docker app port inaccessible from the public internet so Cloudflare cannot be bypassed.

## 🔨 Troubleshooting

### Database initialization fails

- Confirm `.env` exists beside `docker-compose.yml` and contains non-placeholder values for `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `MYSQL_DB`, and the required seeded-user variables. `ADMIN_2` and `PASSWORD_HASH_2` are optional, but must be supplied together when used.
- Inspect the startup output with `docker compose logs db`.
- Initialization scripts run only when MySQL creates a new data directory. If the database is disposable and the credentials are wrong, stop the stack and recreate the volume with `docker compose down -v`, then start it again. This deletes all database data.
- If the database already contains data, changing `.env` will not rerun `002-seed.sh`; update existing users in MySQL or use a deliberate migration instead.

### Data or log permission errors

- Ensure the host directories configured by `DATA_PATH` and `LOGS_PATH` exist and are writable by Docker.
- Check the app output with `docker compose logs app`.
- On Linux hosts, inspect ownership and permissions with `ls -ld data logs` and adjust them so the container's PHP process can write the mounted directories. On Windows with Docker Desktop, verify that the drive is shared with Docker and that the folders are not blocked by security software.
- Do not solve permission errors by making sensitive directories world-writable unless there is no safer deployment option.

## ⚠️ Production security warning

Remove `public/test-php.php` before production deployment, or protect it so unauthenticated users cannot access it. It displays database, filesystem, session, and configuration diagnostics that should not be publicly exposed.
