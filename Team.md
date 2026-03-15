# VR Proj 1 — KawaiiEmoji: Containerized IT Infrastructure

## Project Description

KawaiiEmoji is a web application where users can create and share custom
text-based emojis using Unicode symbols. The platform specializes in
Japanese-style kawaii emojis (e.g. `(づ｡◕‿‿◕｡)づ`) and supports
user-submitted creations. Users can register an account or publish emojis
anonymously. The site is styled in a fun anime aesthetic and features a
browsable gallery with search, categories, and download counts.

---

## Team Roles (5 Members)

| # | Role | Member | Responsibility |
|---|------|--------|----------------|
| 1 | CEO / Team Lead (Scrum Master) | Hussein | Communication, coordination, end-to-end testing |
| 2 | DB Admin | Timur | Database container, schema, logging |
| 3 | Webserver Admin | Maksim | Web server container, port mapping, logging |
| 4 | App Developer | Nikita L | Web application code (PHP/Node/Python) |
| 5 | DevOps / Monitoring | Nikita G | Docker Compose, networking, volumes, monitoring |

---

## Project Structure

```text
project/
├── docker-compose.yml
├── webserver/
│   └── Dockerfile
├── app/
│   ├── index.php          # Home page — emoji gallery
│   ├── login.php          # Login page
│   ├── register.php       # Registration page
│   ├── upload.php         # Upload / edit emoji page
│   ├── emoji.php          # Single emoji detail page
│   └── api/
│       ├── auth.php       # Login / register logic
│       ├── emojis.php     # CRUD for emojis
│       └── search.php     # Search + filter logic
├── db/
│   └── init.sql           # Schema + seed data
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
└── logs/
    ├── apache_access.log
    ├── apache_error.log
    └── mysql.log
```

---

## Database Schema (`db/init.sql`)

```sql
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE emojis (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    symbol       TEXT         NOT NULL,
    name         VARCHAR(100) NOT NULL,
    category     VARCHAR(50)  NOT NULL,
    tags         VARCHAR(255),
    description  TEXT,
    is_anonymous TINYINT(1)   DEFAULT 0,
    user_id      INT,
    downloads    INT          DEFAULT 0,
    likes        INT          DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Seed data
INSERT INTO users (username, email, password) VALUES
    ('kawaii_admin', 'admin@kawaiiemoji.dev', 'hashed_pw');

INSERT INTO emojis (symbol, name, category, tags, user_id) VALUES
    ('(づ｡◕‿‿◕｡)づ', 'Big Hug',     'kawaii', 'hug,cute,love',  1),
    ('(◕‿◕✿)',       'Flower Smile', 'kawaii', 'smile,flower',   1),
    ('(ノಠ益ಠ)ノ彡',  'Rage Flip',   'funny',  'rage,angry,flip', 1);
```

---

## What Each Person Does

### 1. CEO / Team Lead — Hussein
- Notify teacher of team composition and chosen technologies (Sprint 0)
- Submit project name + description to teacher (Sprint 1)
- Submit architecture diagram to teacher (Sprint 1)
- Test all pages end-to-end in Sprint 3:
  - Home page gallery loads emojis from DB
  - Login/registration works correctly
  - Upload form saves to DB and displays in gallery
  - Anonymous toggle hides username
- Coordinate sprint progress and resolve blockers
- Lead the live demo in Sprint 4

### 2. DB Admin — Timur
- Set up the MySQL/MariaDB container in `docker-compose.yml`
- Write `db/init.sql`:
  - `users` table
  - `emojis` table with all metadata fields
  - Seed data (≥3 kawaii emojis, 1 test user)
- Configure query and error logging
- Map `mysql.log` to host via volume
- Create app DB user with correct permissions (`SELECT`, `INSERT`, `UPDATE`, `DELETE`)
- Verify DB is reachable from the app container by hostname

### 3. Webserver Admin — Maksim
- Set up Apache/nginx container in `docker-compose.yml`
- Configure port mapping `host:80 → container:80`
- Ensure `access.log` and `error.log` are generated automatically
- Map both log files to `logs/` on the host via volumes
- Serve the `app/` directory as document root
- Enable PHP processing (mod_php or php-fpm)
- Test that all page routes return HTTP 200

### 4. App Developer — Nikita L
- Build the following pages:
  - **`index.php`** — gallery grid of all emojis from DB, search bar,
    category filter tabs, copy-to-clipboard button per card
  - **`login.php`** — email/password form, inline validation,
    session start on success
  - **`register.php`** — username/email/password form,
    duplicate-check, redirect to login
  - **`upload.php`** — emoji symbol textarea with live preview,
    name/tags/category/description fields, anonymous checkbox,
    INSERT into `emojis` table on submit
- All pages must connect to DB using the container hostname
- Anime/kawaii CSS styling (pink/lavender theme, rounded cards)
- Show at least one live DB value on the home page
  (e.g. total emoji count: `"1,240 emojis and counting ✨"`)

### 5. DevOps / Monitoring — Nikita G
- Write and maintain `docker-compose.yml`
- Define three services: `webserver`, `app` (if separate), `db`
- Configure internal Docker network (`kawaii-net`) between services
- Set up named volumes for all log files
- Set DB environment variables (`MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`,
  `MYSQL_USER`, `MYSQL_PASSWORD`)
- Write `README.md` with start/stop/monitor commands
- Verify all log files appear on the host after `docker compose up`

---

## Architecture

```text
  Browser
     │  HTTP :80
     ▼
┌─────────────┐        ┌─────────────┐
│  Apache /   │──PHP──▶│  PHP App    │
│  nginx      │        │  (app/)     │
│  :80        │        └──────┬──────┘
└─────────────┘               │ MySQL :3306
                               ▼
                      ┌─────────────────┐
                      │  MySQL/MariaDB  │
                      │  kawaiiemoji_db │
                      └─────────────────┘

All services on internal Docker network: kawaii-net
Log files mounted to host: ./logs/
```

---

## Sprints Overview

| Sprint | Goal | Owner |
|--------|------|-------|
| Sprint 0 | Form team, assign roles, notify teacher | Hussein |
| Sprint 1 | Project description + architecture diagram submitted | Hussein + all |
| Sprint 2 | `docker compose up` works: webserver serves a page, DB running, logs on host | Maksim, Timur, Nikita G |
| Sprint 3 | App connects to DB; gallery shows emojis; login works; upload saves to DB | Nikita L, Hussein tests |
| Sprint 4 | Live demo to class: browse, login, upload an emoji, show logs | Min. 2 members present actively |

---

## Quick Start

```bash
# Clone and enter project
git clone <repo-url>
cd project

# Start all services
docker compose up -d

# Check all containers are running
docker compose ps

# View webserver logs
tail -f logs/apache_access.log
tail -f logs/apache_error.log

# View database logs
tail -f logs/mysql.log

# Run a quick DB check
docker compose exec db mysql -u kawaii_user -p kawaiiemoji_db \
  -e "SELECT name, category, downloads FROM emojis;"

# Stop all services
docker compose down

# Stop and remove volumes (full reset)
docker compose down -v
```

---

## Minimum Requirements Checklist

### Infrastructure
- [ ] All services defined in `docker-compose.yml`
- [ ] Internal Docker network configured between services
- [ ] Log files mapped to `logs/` on host via volumes
- [ ] Web server receives HTTP requests and serves the app on port 80
- [ ] `access.log` and `error.log` generated and visible on host
- [ ] Database container running and accessible from app container
- [ ] Database produces logs visible on host

### Application
- [ ] Home page loads and displays emojis from the database
- [ ] Search or category filter works
- [ ] User can register and log in
- [ ] Logged-in user can upload a new emoji (saved to DB)
- [ ] Anonymous toggle hides the author's username
- [ ] Copy-to-clipboard works on emoji cards
- [ ] App displays at least one live data point from DB in browser

### Demo (Sprint 4)
- [ ] Live demo runs without errors on the presentation machine
- [ ] Team can explain the role of each container
- [ ] Logs are shown live during the demo