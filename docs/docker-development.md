# Docker Development Environment

This guide explains how to use MariaDB and phpMyAdmin for local development testing.

## Overview

The `docker-compose.dev.yml` provides a database-only development environment. Your Laravel application runs locally on your machine and connects to the dockerized MariaDB database.

## Why Use This?

SQLite is great for quick development, but some database features and migration behaviors differ from production databases. This setup lets you test with a real MariaDB instance to catch issues early.

## Quick Start

### 1. Start the Database Services

```bash
docker-compose -f docker-compose.dev.yml up -d
```

This starts:
- MariaDB 11.4 on port `3306`
- phpMyAdmin on port `8085`

### 2. Configure Your Local App

Update your `.env` file to use MariaDB:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marketing_mail
DB_USERNAME=marketing
DB_PASSWORD=secret
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Access phpMyAdmin

Open your browser and navigate to:

```
http://localhost:8085
```

**Login credentials:**
- **Username:** `marketing`
- **Password:** `secret`

## Available Commands

### Start Services
```bash
docker-compose -f docker-compose.dev.yml up -d
```

### Stop Services
```bash
docker-compose -f docker-compose.dev.yml down
```

### Stop and Remove Data
```bash
docker-compose -f docker-compose.dev.yml down -v
```

⚠️ **Warning:** The `-v` flag removes the database volume, deleting all data.

### View Logs
```bash
docker-compose -f docker-compose.dev.yml logs -f mariadb
```

### Check Service Status
```bash
docker-compose -f docker-compose.dev.yml ps
```

## Database Configuration

| Setting | Value |
|---------|-------|
| Database Engine | MariaDB 11.4 |
| Database Name | `marketing_mail` |
| Username | `marketing` |
| Password | `secret` |
| Root Password | `root_secret` |
| Host | `127.0.0.1` (localhost) |
| Port | `3306` |

## phpMyAdmin Configuration

| Setting | Value |
|---------|-------|
| URL | http://localhost:8085 |
| Username | `marketing` |
| Password | `secret` |
| Upload Limit | 64MB |

## Testing Migrations

This setup is ideal for testing migration behaviors that differ between SQLite and MariaDB:

```bash
# Fresh migration
php artisan migrate:fresh

# Run with seeding
php artisan migrate:fresh --seed

# Rollback and re-run
php artisan migrate:rollback
php artisan migrate
```

## Troubleshooting

### Port 3306 Already in Use

If you have MySQL/MariaDB installed locally:

1. Stop your local database service
2. Or change the port in `docker-compose.dev.yml`:
   ```yaml
   ports:
     - "3307:3306"  # Use port 3307 instead
   ```
   Then update `DB_PORT=3307` in your `.env`

### Cannot Connect to Database

Check if the service is running:
```bash
docker-compose -f docker-compose.dev.yml ps
```

Wait for health check to pass:
```bash
docker-compose -f docker-compose.dev.yml logs mariadb
```

### Reset Everything

Stop and remove all containers and volumes:
```bash
docker-compose -f docker-compose.dev.yml down -v
docker-compose -f docker-compose.dev.yml up -d
php artisan migrate:fresh
```

## Switching Back to SQLite

Update your `.env`:

```env
DB_CONNECTION=sqlite
```

Remove the database file reference if needed, then run migrations:

```bash
php artisan migrate:fresh
```

## Notes

- The MariaDB data persists in a Docker volume named `mariadb_dev_data`
- Both services use the `dev-network` bridge network
- Services automatically restart unless stopped manually
- Health checks ensure MariaDB is ready before phpMyAdmin starts
