#!/usr/bin/env python3
"""Ship MySQL log entries into Elasticsearch."""

from __future__ import annotations

import os
import re
from datetime import datetime, timezone
from typing import Optional

from log_shipper_common import run_shipper


TIMESTAMP_PATTERN = r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z"

MYSQL_LOG_PATTERN = re.compile(
    rf"^(?P<timestamp>{TIMESTAMP_PATTERN})\s+(?P<thread_id>\d+)\s+\[(?P<level>[^\]]+)\]"
    r"(?:\s+\[(?P<code>[^\]]+)\])?(?:\s+\[(?P<subcomponent>[^\]]+)\])?\s+(?P<message>.*)$"
)

MYSQL_GENERAL_PATTERN = re.compile(
    rf"^(?P<timestamp>{TIMESTAMP_PATTERN})\s+(?P<thread_id>\d+)\s+(?P<command>[A-Za-z_]+)\s+(?P<message>.*)$"
)


def _project_name() -> str:
    return os.getenv("PROJECT_NAME", "kawaiiemoji")


def _isoformat(value: datetime) -> str:
    return value.astimezone(timezone.utc).isoformat().replace("+00:00", "Z")


def _parse_timestamp(raw_timestamp: str) -> str:
    value = datetime.fromisoformat(raw_timestamp.replace("Z", "+00:00"))
    return _isoformat(value)


def _normalize_level(raw_level: str) -> str:
    level = raw_level.strip().upper()
    if level in {"SYSTEM", "NOTE"}:
        return "INFO"
    if level == "WARNING":
        return "WARNING"
    if level == "ERROR":
        return "ERROR"
    return level


def _should_skip_line(line: str) -> bool:
    stripped = line.strip()
    if not stripped:
        return True

    return stripped.startswith("--") or stripped.startswith("mysql>") or stripped.startswith("->")


def parse_mysql_line(line: str, source_name: str) -> Optional[dict[str, object]]:
    if _should_skip_line(line):
        return None

    match = MYSQL_LOG_PATTERN.match(line)
    if match:
        payload = match.groupdict()
        return {
            "timestamp": _parse_timestamp(payload["timestamp"]),
            "level": _normalize_level(payload["level"]),
            "component": "mysql",
            "source": source_name,
            "message": payload["message"],
            "project": _project_name(),
            "thread_id": int(payload["thread_id"]),
            "event_code": payload["code"],
            "subcomponent": payload["subcomponent"],
        }

    match = MYSQL_GENERAL_PATTERN.match(line)
    if match:
        payload = match.groupdict()
        return {
            "timestamp": _parse_timestamp(payload["timestamp"]),
            "level": "INFO",
            "component": "mysql",
            "source": source_name,
            "message": payload["message"],
            "project": _project_name(),
            "thread_id": int(payload["thread_id"]),
            "command": payload["command"],
        }

    if not re.match(r"^\d{4}-\d{2}-\d{2}T", line):
        return None

    return {
        "timestamp": _isoformat(datetime.now(timezone.utc)),
        "level": "INFO",
        "component": "mysql",
        "source": source_name,
        "message": line,
        "project": _project_name(),
    }


if __name__ == "__main__":
    run_shipper(parse_mysql_line)
