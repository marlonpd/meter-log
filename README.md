# Meter Log

Track utility meter readings over time. Laravel 13 REST API (SQLite) + React 19 / Vite frontend, both runnable with Docker Compose.

```
meter-log/
├── docker-compose.yml   # backend :8000, frontend :5173
├── laravel/             # API (PHP 8.3, SQLite)
└── react/               # UI (React 19, Vite)
```

---

## Why it's built this way

- **Consumption is computed, not stored.** It's derived from the previous reading every time the API responds, so editing or deleting a reading never leaves a stale number behind.
- **Validation enforces the domain rule, not just data shape.** A reading can't be lower than the one before it or higher than the one after — meters only count up.
- **One reading per date.** Consumption only makes sense between two different dates, so `reading_date` is unique.
- **Docker Compose over local installs.** Anyone can run the whole stack with one command, without installing PHP, Composer, or Node.

---

## Quick start (Docker — recommended)

**Prerequisites:** Docker Desktop (or Docker Engine + Compose v2).

```bash
git clone <repo-url> meter-log
cd meter-log

# 1. Create the SQLite database file (see "Why this step" below)
touch laravel/database/database.sqlite

# 2. Build and start both services
docker compose up -d --build

# 3. Create the tables
docker compose exec backend php artisan migrate --force
```

Then open:

| Service  | URL                     |
| -------- | ----------------------- |
| Frontend | http://localhost:5173   |
| API      | http://localhost:8000   |

Verify the API is alive:

```bash
curl http://localhost:8000/api/meter-readings
# => []
```

### Why the `touch` step is required

The backend `Dockerfile` creates `database/database.sqlite` at **build** time, but
`docker-compose.yml` bind-mounts `./laravel` over `/var/www/html` at **run** time. The bind
mount hides the image's `database/` directory, so only the host's copy is visible — and the
`.sqlite` file is gitignored, so a fresh clone doesn't have one.

Skipping it produces:

```
Database file at path [/var/www/html/database/database.sqlite] does not exist.
```

The fix is always the same: `touch laravel/database/database.sqlite`, then re-run migrations.

---

## Everyday commands

```bash
docker compose up -d              # start
docker compose down               # stop (data in laravel/database/database.sqlite survives)
docker compose logs -f backend    # tail API logs
docker compose logs -f frontend   # tail Vite logs
docker compose restart backend    # restart after config/env changes
docker compose up -d --build      # rebuild after changing a Dockerfile or dependencies
```

Both services bind-mount their source, so **edits to `laravel/` and `react/` are picked up
live** — no rebuild needed for ordinary code changes.

### Laravel (artisan)

```bash
docker compose exec backend php artisan migrate --force     # apply new migrations
docker compose exec backend php artisan migrate:fresh --force   # drop everything and rebuild
docker compose exec backend php artisan db:seed             # run seeders
docker compose exec backend php artisan route:list          # inspect routes
docker compose exec backend php artisan tinker              # REPL
docker compose exec backend php artisan test                # run the test suite
```

### React

```bash
docker compose exec frontend npm run lint     # oxlint
docker compose exec frontend npm run build    # production build
docker compose exec frontend npm install <pkg>   # then: docker compose restart frontend
```

### Inspecting the database

```bash
sqlite3 laravel/database/database.sqlite "select * from meter_readings;"
```

---

## Configuration

Config is supplied through `docker-compose.yml` environment variables — **no `laravel/.env`
is needed for the Docker workflow**.

| Variable       | Service  | Value                                     |
| -------------- | -------- | ----------------------------------------- |
| `DB_CONNECTION`| backend  | `sqlite`                                  |
| `DB_DATABASE`  | backend  | `/var/www/html/database/database.sqlite`  |
| `APP_ENV`      | backend  | `local`                                   |
| `APP_DEBUG`    | backend  | `true`                                    |
| `VITE_API_URL` | frontend | `http://localhost:8000`                   |

`react/.env` also sets `VITE_API_URL` for local (non-Docker) runs. Vite only exposes
variables prefixed with `VITE_`, and it reads them at **startup** — restart the frontend
after changing one.

---

## API

Base URL: `http://localhost:8000/api`

| Method   | Endpoint               | Description                          |
| -------- | ---------------------- | ------------------------------------ |
| `GET`    | `/meter-readings`      | List all readings, oldest first      |
| `POST`   | `/meter-readings`      | Create a reading                     |
| `GET`    | `/meter-readings/{id}` | Fetch one reading                    |
| `PUT`    | `/meter-readings/{id}` | Update a reading                     |
| `DELETE` | `/meter-readings/{id}` | Delete a reading (`204 No Content`)  |

### Fields

| Field           | Type              | Rules                                    |
| --------------- | ----------------- | ---------------------------------------- |
| `reading_date`  | date (`Y-m-d`)    | required, **unique** — one per date       |
| `reading_value` | decimal(10,2)     | required, numeric, `>= 0`                 |

Responses also include two computed fields: **`previous_reading_value`** (the prior date's
reading, or `null` for the first) and **`consumption`** (the difference between the two).

### Validation rules worth knowing

Readings must stay monotonic: a value cannot be **lower than the previous** date's reading
or **higher than the next** date's reading. Violations return `422` with a message naming
the conflicting date.

### Example

```bash
curl -X POST http://localhost:8000/api/meter-readings \
  -H 'Content-Type: application/json' \
  -d '{"reading_date":"2026-08-01","reading_value":1250.50}'
```

---

## Running without Docker

**Prerequisites:** PHP 8.3+ with `pdo_sqlite`, Composer 2, Node 22+.

### Backend

```bash
cd laravel
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve --port=8000
```

`.env.example` sets `DB_CONNECTION=sqlite` with no `DB_DATABASE`, so Laravel defaults to
`database/database.sqlite` — the file still has to exist first.

Unlike the Docker path, a standalone run **does** need `.env` and a generated `APP_KEY`.

### Frontend

```bash
cd react
npm install
npm run dev      # http://localhost:5173
```

---

## Troubleshooting

**`Database file ... does not exist`**
`touch laravel/database/database.sqlite && docker compose exec backend php artisan migrate --force`

**`no such table: meter_readings`**
The file exists but is empty — run the migrations.

**Frontend loads but every request fails**
Confirm the backend is up (`curl http://localhost:8000/api/meter-readings`) and that
`VITE_API_URL` points at `http://localhost:8000`. Because the browser (not the container)
makes these calls, it must be `localhost`, not the `backend` service name.

**Port 8000 or 5173 already in use**
Change the host side of the mapping in `docker-compose.yml` (e.g. `"8001:8000"`), then
`docker compose up -d`. If you move the API port, update `VITE_API_URL` to match.

**Dependency changes aren't taking effect**
`vendor/` and `node_modules/` live in named volumes, so they survive a rebuild. Force a
refresh with `docker compose down -v && docker compose up -d --build` — note that `-v`
removes those volumes, but your SQLite data is a plain file on the host and is unaffected.

**Starting over completely**

```bash
docker compose down -v
rm laravel/database/database.sqlite
touch laravel/database/database.sqlite
docker compose up -d --build
docker compose exec backend php artisan migrate --force
```
