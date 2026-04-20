#!/usr/bin/env python3
"""Import the shared KawaiiEmoji Kibana data view and dashboard."""

from __future__ import annotations

import json
import os
import time
import urllib.error
import urllib.request
from uuid import uuid4


def wait_for_kibana(kibana_url: str, timeout_seconds: int = 180) -> None:
    deadline = time.time() + timeout_seconds
    status_url = kibana_url.rstrip("/") + "/api/status"

    while time.time() < deadline:
        try:
            request = urllib.request.Request(
                status_url,
                headers={
                    "Accept": "application/json",
                    "kbn-xsrf": "true",
                },
            )
            with urllib.request.urlopen(request, timeout=5) as response:
                if response.status == 200:
                    return
        except (urllib.error.URLError, TimeoutError):
            time.sleep(3)

    raise RuntimeError(f"Kibana did not become ready in {timeout_seconds} seconds")


def build_multipart_form(field_name: str, filename: str, content: bytes) -> tuple[bytes, str]:
    boundary = "----CodexBoundary" + uuid4().hex
    parts = [
        f"--{boundary}".encode("utf-8"),
        f'Content-Disposition: form-data; name="{field_name}"; filename="{filename}"'.encode("utf-8"),
        b"Content-Type: application/ndjson",
        b"",
        content,
        f"--{boundary}--".encode("utf-8"),
        b"",
    ]
    return b"\r\n".join(parts), boundary


def import_dashboard(kibana_url: str, dashboard_file: str) -> None:
    with open(dashboard_file, "rb") as handle:
        file_bytes = handle.read()

    payload, boundary = build_multipart_form("file", os.path.basename(dashboard_file), file_bytes)
    request = urllib.request.Request(
        kibana_url.rstrip("/") + "/api/saved_objects/_import?overwrite=true",
        data=payload,
        headers={
            "Content-Type": f"multipart/form-data; boundary={boundary}",
            "Accept": "application/json",
            "kbn-xsrf": "true",
        },
        method="POST",
    )

    with urllib.request.urlopen(request, timeout=30) as response:
        result = json.loads(response.read().decode("utf-8"))

    if result.get("success") is not True:
        raise RuntimeError("Kibana dashboard import did not report success")

    if result.get("successCount", 0) == 0:
        raise RuntimeError("Kibana import finished without importing saved objects")


if __name__ == "__main__":
    kibana_url = os.getenv("KIBANA_URL", "http://localhost:5601")
    dashboard_file = os.getenv("DASHBOARD_FILE", "/workspace/kibana/kawaiiemoji-dashboard.ndjson")

    print(f"Waiting for Kibana at {kibana_url}...", flush=True)
    wait_for_kibana(kibana_url)
    import_dashboard(kibana_url, dashboard_file)
    print("Imported Kibana data view and dashboard.", flush=True)
