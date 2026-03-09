# VR Proj 1 — Containerized IT Infrastructure

## Team Roles (5 Members)

| # | Role | Responsibility |
|---|------|----------------|
| 1 | CEO / Team Lead (Scrum Master) - Hussein | Communication, coordination, testing |
| 2 | DB Admin - Timur | Database container + logging |
| 3 | Webserver Admin - Maksim | Web server container + logging |
| 4 | App Developer - Nikita L | Web application code |
| 5 | DevOps / Monitoring - Nikita G | Docker Compose setup + log monitoring |

---

## Project Structure

```text
project/
├── docker-compose.yml
├── webserver/
│   └── Dockerfile (if custom config needed)
├── app/
│   └── index.php (or app.js / app.py)
├── db/
│   └── init.sql
└── logs/
    ├── apache_access.log
    ├── apache_error.log
    └── mysql.log
```

---

## What Each Person Does

### 1. CEO / Team Lead
- Notify the teacher of team composition and chosen technologies (Sprint 0)
- Submit project name + description (2–5 sentences) to the teacher (Sprint 1)
- Submit architecture diagram to the teacher (Sprint 1)
- Test the full system end-to-end in Sprint 3
- Coordinate sprint progress and resolve blockers
- Lead the presentation in Sprint 4

### 2. DB Admin
- Set up the MySQL/MariaDB container in `docker-compose.yml`
- Create `db/init.sql` with a test table and sample data
- Configure database logging (query logs / error logs)
- Ensure logs are mapped to the host machine via volumes
- Verify the app user has correct permissions

### 3. Webserver Admin
- Set up the Apache/nginx container in `docker-compose.yml`
- Configure port mapping (host:80 → container:80)
- Ensure `access.log` and `error.log` are generated automatically
- Map log files to `logs/` directory on the host via volumes
- Test that HTTP requests are received and logged

### 4. App Developer
- Write the server-side script (PHP / Node.js / Python)
- Script must connect to the database successfully
- Display at least one piece of data from the database (e.g. a test record or DB status)
- Place the app files in the correct directory served by the web server
- Design does not matter — focus on functionality

### 5. DevOps / Monitoring
- Write and maintain the `docker-compose.yml` file
- Configure the internal Docker network between services
- Set up named volumes for all log files
- Verify that both the webserver and database produce readable logs
- Document how to start, stop, and monitor the system (see commands below)

---

## Sprints Overview

| Sprint | Goal | Who |
|--------|------|-----|
| Sprint 0 | Form team, assign roles, notify teacher | CEO |
| Sprint 1 | Write project description + architecture diagram | CEO + all |
| Sprint 2 | Docker Compose up with webserver + DB, logs working | Webserver Admin, DB Admin, DevOps |
| Sprint 3 | App connects to DB, full system works | Developer, CEO tests |
| Sprint 4 | Present + live demo to class | Minimum 2 people present actively |

---

## Quick Start

```bash
# Start all services
docker compose up -d

# View webserver logs
tail -f logs/apache_access.log
tail -f logs/apache_error.log

# View database logs
tail -f logs/mysql.log

# Stop all services
docker compose down
```

---

## Minimum Requirements Checklist

- [ ] Web server receives HTTP requests and serves the app
- [ ] `access.log` and `error.log` are generated and visible on host
- [ ] Database container is running and accessible
- [ ] Database produces logs visible on host
- [ ] App successfully connects to the database
- [ ] App displays data from the database in the browser
- [ ] All services defined in `docker-compose.yml`
- [ ] Internal Docker network configured between services
- [ ] Log files mapped to host via volumes
- [ ] Live demo ready for Sprint 4