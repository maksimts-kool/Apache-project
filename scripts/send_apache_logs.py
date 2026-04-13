#!/usr/bin/env python3
"""Ship Apache access and error logs into Elasticsearch."""

from __future__ import annotations

import os
import re
from datetime import datetime, timezone

from log_shipper_common import run_shipper


ACCESS_LOG_PATTERN = re.compile(
    r'^(?P<client>\S+) \S+ (?P<user>\S+) \[(?P<timestamp>[^\]]+)\] '
    r'"(?P<request>[^"]*)" (?P<status>\d{3}|-) (?P<size>\d+|-) '
    r'"(?P<referer>[^"]*)" "(?P<agent>[^"]*)"$'
)

ERROR_LOG_PATTERN = re.compile(
    r'^\[(?P<timestamp>[^\]]+)\] \[(?P<module>[^:\]]+):(?P<level>[^\]]+)\]'
    r'(?: \[pid (?P<pid>\d+):tid (?P<tid>\d+)\])?'
    r'(?: \[client (?P<client>[^\]]+)\])? ?(?P<message>.*)$'
)


def _project_name() -> str:
    return os.getenv("PROJECT_NAME", "kawaiiemoji")


def _isoformat(value: datetime) -> str:
    return value.astimezone(timezone.utc).isoformat().replace("+00:00", "Z")


def _parse_access(line: str, source_name: str) -> dict[str, object]:
    match = ACCESS_LOG_PATTERN.match(line)
    if not match:
        return {
            "timestamp": _isoformat(datetime.now(timezone.utc)),
            "level": "INFO",
            "component": "apache",
            "source": source_name,
            "message": line,
            "project": _project_name(),
        }

    payload = match.groupdict()
    timestamp = datetime.strptime(payload["timestamp"], "%d/%b/%Y:%H:%M:%S %z")
    request_line = payload["request"]
    method = None
    endpoint = None
    protocol = None
    if request_line != "-":
        parts = request_line.split()
        if len(parts) == 3:
            method, endpoint, protocol = parts

    status = int(payload["status"]) if payload["status"].isdigit() else None
    if status is None:
        level = "INFO"
    elif status >= 500:
        level = "ERROR"
    elif status >= 400:
        level = "WARNING"
    else:
        level = "INFO"

    return {
        "timestamp": _isoformat(timestamp),
        "level": level,
        "component": "apache",
        "source": source_name,
        "message": request_line if request_line != "-" else "Request line missing",
        "project": _project_name(),
        "remote_addr": payload["client"],
        "method": method,
        "endpoint": endpoint,
        "protocol": protocol,
        "status": status,
        "bytes": int(payload["size"]) if payload["size"].isdigit() else None,
        "referer": payload["referer"] if payload["referer"] != "-" else None,
        "user_agent": payload["agent"] if payload["agent"] != "-" else None,
    }


def _parse_error(line: str, source_name: str) -> dict[str, object]:
    match = ERROR_LOG_PATTERN.match(line)
    if not match:
        return {
            "timestamp": _isoformat(datetime.now(timezone.utc)),
            "level": "ERROR",
            "component": "apache",
            "source": source_name,
            "message": line,
            "project": _project_name(),
        }

    payload = match.groupdict()
    timestamp = datetime.strptime(payload["timestamp"], "%a %b %d %H:%M:%S.%f %Y").replace(tzinfo=timezone.utc)

    return {
        "timestamp": _isoformat(timestamp),
        "level": payload["level"].upper(),
        "component": "apache",
        "source": source_name,
        "message": payload["message"],
        "project": _project_name(),
        "module": payload["module"],
        "pid": int(payload["pid"]) if payload["pid"] else None,
        "tid": int(payload["tid"]) if payload["tid"] else None,
        "client": payload["client"],
    }


def parse_apache_line(line: str, source_name: str) -> dict[str, object]:
    if source_name.endswith("access.log"):
        return _parse_access(line, source_name)
    return _parse_error(line, source_name)


if __name__ == "__main__":
    run_shipper(parse_apache_line)
