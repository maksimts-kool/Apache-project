# NoSQL Integration Guide

## Project

KawaiiEmoji is currently running with:

- Apache web server in `webserver`
- PHP application in `app/`
- MySQL database in `db`
- local log files:
  - `logs/apache_access.log`
  - `logs/apache_error.log`
  - `logs/mysql.log`

The goal of this integration is to add a centralized logging layer with:

- Elasticsearch
- Kibana

This lets the team collect logs from Apache, MySQL, and the PHP API into one place and analyze them from a single dashboard.

---

## Main Goal

Add a NoSQL-based logging workflow where:

- Apache logs go to `server-logs`
- MySQL logs go to `db-logs`
- PHP API logs go to `api-logs`
- Kibana shows all logs in one dashboard

---

## Current Project Context

The project already contains the base services:

- `docker-compose.yml`
- `webserver/apache.conf`
- `webserver/Dockerfile`
- `app/db.php`
- API endpoints in `app/api/`

That means the main work is not rebuilding the app, but integrating Elasticsearch and Kibana into the current stack.

---

## Integration Plan

### 1. Add Elasticsearch

Extend `docker-compose.yml` with an `elasticsearch` service.

Recommended settings:

- port `9200`
- `discovery.type=single-node`
- `ES_JAVA_OPTS=-Xms512m -Xmx512m`

Example requirements:

- it must be on the same Docker network as `webserver` and `db`
- it must be reachable from the PHP app and from Kibana

---

### 2. Add Kibana

Extend `docker-compose.yml` with a `kibana` service.

Recommended settings:

- port `5601`
- connection to `elasticsearch`

After startup, Kibana should be available at:

`http://localhost:5601`

---

### 3. Send Apache logs to Elasticsearch

Use the existing log files:

- `logs/apache_access.log`
- `logs/apache_error.log`

Create a script such as:

- `scripts/send_apache_logs.py`

The script should:

- read log lines from the files
- transform them into JSON
- send them to Elasticsearch index `server-logs`

Example document:

```json
{
  "timestamp": "2026-04-13T12:00:00",
  "level": "INFO",
  "component": "apache",
  "source": "access.log",
  "message": "GET /api/search.php 200",
  "project": "kawaiiemoji"
}
```

---

### 4. Send MySQL logs to Elasticsearch

Use the existing DB log file:

- `logs/mysql.log`

Create a script such as:

- `scripts/send_mysql_logs.py`

The script should:

- read MySQL log lines
- convert them into JSON documents
- send them to index `db-logs`

Example document:

```json
{
  "timestamp": "2026-04-13T12:00:00",
  "level": "INFO",
  "component": "mysql",
  "source": "mysql.log",
  "message": "SELECT * FROM emojis",
  "project": "kawaiiemoji"
}
```

---

### 5. Add API logging

The application API is in:

- `app/api/auth.php`
- `app/api/search.php`
- `app/api/profile.php`
- `app/api/categories.php`
- `app/api/tags.php`

Create a shared logger helper, for example:

- `app/logger.php`

This helper should send structured JSON logs to Elasticsearch index `api-logs`.

Important events to log:

- login attempts
- successful login
- failed login
- registration
- search requests
- profile fetch
- validation errors
- database connection errors

Example API log:

```json
{
  "timestamp": "2026-04-13T12:00:00",
  "level": "INFO",
  "component": "api",
  "endpoint": "/api/auth.php",
  "action": "login",
  "message": "User login succeeded",
  "project": "kawaiiemoji"
}
```

---

## Suggested Indexes

Use three separate indexes:

- `server-logs`
- `db-logs`
- `api-logs`

This makes it easier to filter data in Kibana and build clear visualizations.

---

## Kibana Setup

After all services are running:

1. open Kibana at `http://localhost:5601`
2. create a Data View with pattern `*-logs`
3. choose `timestamp` as the time field
4. create a dashboard

Suggested dashboard widgets:

- table of latest DB errors
- API request volume over time
- Apache event overview

---

## Workflow For 2 People

Because the assignment describes more roles than people, the work should be merged into 2 practical tracks.

### Person 1

Responsibilities:

- Docker Compose changes
- Elasticsearch setup
- Kibana setup
- Apache log ingestion
- MySQL log ingestion
- final dashboard

Main files:

- `docker-compose.yml`
- `webserver/apache.conf`
- `scripts/send_apache_logs.py`
- `scripts/send_mysql_logs.py`

### Person 2

Responsibilities:

- API structured logging
- shared logger helper
- testing API events
- validating that logs appear in Kibana

Main files:

- `app/logger.php`
- `app/api/auth.php`
- `app/api/search.php`
- `app/api/profile.php`
- `app/api/categories.php`
- `app/api/tags.php`

---

## Git Workflow For 2 People

Recommended branches:

- `feature/logging-stack`
- `feature/api-logs`

Recommended sequence:

1. both create a branch from `main`
2. Person 1 prepares Elasticsearch and Kibana
3. Person 2 adds API logging
4. both test locally
5. Person 1 merges first
6. Person 2 rebases or updates from `main`
7. Person 2 merges next
8. both run final integration testing

---

## End-to-End Test Scenario

The integration is complete when this flow works:

1. run `docker compose up -d`
2. open the app
3. perform a search request
4. perform login or failed login
5. trigger at least one DB-backed API request
6. open Kibana
7. confirm that:
   - Apache logs are in `server-logs`
   - MySQL logs are in `db-logs`
   - API logs are in `api-logs`

---

## Definition Of Done

The task is done when:

- Elasticsearch is running
- Kibana is running
- Apache logs are indexed
- MySQL logs are indexed
- API logs are indexed
- Kibana Data View `*-logs` exists
- Kibana dashboard is saved
- both team members can explain their part of the implementation

---

## Result

After this integration, the KawaiiEmoji project will have a centralized NoSQL logging solution where all important system logs are collected into Elasticsearch and visualized in Kibana from one dashboard.
