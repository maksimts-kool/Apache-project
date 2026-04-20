#!/usr/bin/env python3
"""Shared helpers for shipping log files into Elasticsearch."""

from __future__ import annotations

import hashlib
import json
import os
import sys
import time
import urllib.error
import urllib.request
from typing import Callable, Optional


Document = dict[str, object]
Parser = Callable[[str, str], Optional[Document]]


def env_bool(name: str, default: bool = False) -> bool:
    value = os.getenv(name)
    if value is None:
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


def load_state(path: str) -> dict[str, dict[str, int]]:
    if not path or not os.path.exists(path):
        return {}

    try:
        with open(path, "r", encoding="utf-8") as handle:
            payload = json.load(handle)
    except (OSError, json.JSONDecodeError):
        return {}

    if isinstance(payload, dict):
        return payload
    return {}


def save_state(path: str, state: dict[str, dict[str, int]]) -> None:
    if not path:
        return

    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8") as handle:
        json.dump(state, handle, indent=2, sort_keys=True)


def wait_for_elasticsearch(es_url: str, timeout_seconds: int = 120) -> None:
    deadline = time.time() + timeout_seconds
    url = es_url.rstrip("/") + "/"

    while time.time() < deadline:
        try:
            request = urllib.request.Request(url, headers={"Accept": "application/json"})
            with urllib.request.urlopen(request, timeout=5) as response:
                if response.status == 200:
                    return
        except (urllib.error.URLError, TimeoutError):
            time.sleep(2)

    raise RuntimeError(f"Elasticsearch did not become ready in {timeout_seconds} seconds")


def bulk_index(es_url: str, index_name: str, docs: list[tuple[str, Document]], timeout: int = 20) -> None:
    if not docs:
        return

    lines: list[str] = []
    for doc_id, document in docs:
        action = {"index": {"_index": index_name, "_id": doc_id}}
        lines.append(json.dumps(action, separators=(",", ":")))
        lines.append(json.dumps(document, separators=(",", ":"), ensure_ascii=False))

    payload = ("\n".join(lines) + "\n").encode("utf-8")
    request = urllib.request.Request(
        es_url.rstrip("/") + "/_bulk",
        data=payload,
        headers={
            "Content-Type": "application/x-ndjson",
            "Accept": "application/json",
        },
        method="POST",
    )

    with urllib.request.urlopen(request, timeout=timeout) as response:
        result = json.loads(response.read().decode("utf-8"))

    if result.get("errors"):
        failures = []
        for item in result.get("items", []):
            error = item.get("index", {}).get("error")
            if error:
                failures.append(error.get("reason") or json.dumps(error))
            if len(failures) == 3:
                break
        raise RuntimeError("Elasticsearch bulk index failed: " + "; ".join(failures))


def build_doc_id(source_name: str, offset: int, line: str) -> str:
    digest = hashlib.sha1(f"{source_name}:{offset}:{line}".encode("utf-8")).hexdigest()
    return digest


def _initial_offset(path: str, start_position: str, state: dict[str, dict[str, int]]) -> tuple[int, int]:
    stat_result = os.stat(path)
    entry = state.get(path)

    if entry:
        saved_inode = entry.get("inode")
        saved_offset = entry.get("offset", 0)
        if saved_inode == stat_result.st_ino and saved_offset <= stat_result.st_size:
            return saved_offset, stat_result.st_ino

    if start_position == "end":
        return stat_result.st_size, stat_result.st_ino
    return 0, stat_result.st_ino


def collect_batch(
    file_paths: list[str],
    state: dict[str, dict[str, int]],
    parser: Parser,
    start_position: str,
    max_docs: int,
) -> tuple[list[tuple[str, Document]], dict[str, dict[str, int]]]:
    docs: list[tuple[str, Document]] = []
    pending_state: dict[str, dict[str, int]] = {}

    for path in file_paths:
        if len(docs) >= max_docs:
            break
        if not path or not os.path.exists(path):
            continue

        offset, inode = _initial_offset(path, start_position, state)
        stat_result = os.stat(path)
        if offset == stat_result.st_size:
            pending_state[path] = {"inode": inode, "offset": offset}
            continue

        with open(path, "rb") as handle:
            handle.seek(offset)
            while len(docs) < max_docs:
                current_offset = handle.tell()
                raw_line = handle.readline()
                if not raw_line:
                    break

                next_offset = handle.tell()
                pending_state[path] = {"inode": inode, "offset": next_offset}

                line = raw_line.decode("utf-8", errors="replace").rstrip("\r\n")
                if not line:
                    continue

                document = parser(line, os.path.basename(path))
                if document is None:
                    continue

                docs.append((build_doc_id(os.path.basename(path), current_offset, line), document))

    return docs, pending_state


def run_shipper(parser: Parser) -> None:
    es_url = os.getenv("ELASTICSEARCH_URL", "http://localhost:9200")
    index_name = os.getenv("INDEX_NAME", "logs")
    state_file = os.getenv("STATE_FILE", "/tmp/log-shipper-state.json")
    log_files = [item.strip() for item in os.getenv("LOG_FILES", "").split(",") if item.strip()]
    poll_interval = float(os.getenv("POLL_INTERVAL", "3"))
    max_docs = int(os.getenv("MAX_BATCH_SIZE", "200"))
    start_position = os.getenv("START_POSITION", "beginning").strip().lower()
    once = env_bool("ONCE")
    dry_run = env_bool("DRY_RUN")

    if start_position not in {"beginning", "end"}:
        start_position = "beginning"

    if not log_files:
        raise RuntimeError("LOG_FILES must contain at least one path")

    print(f"Waiting for Elasticsearch at {es_url}...", flush=True)
    wait_for_elasticsearch(es_url)

    state = load_state(state_file)

    while True:
        try:
            docs, pending_state = collect_batch(
                file_paths=log_files,
                state=state,
                parser=parser,
                start_position=start_position,
                max_docs=max_docs,
            )

            if docs:
                if dry_run:
                    preview = json.dumps(docs[0][1], ensure_ascii=False)
                    print(f"[dry-run] Prepared {len(docs)} documents for {index_name}: {preview}", flush=True)
                else:
                    bulk_index(es_url=es_url, index_name=index_name, docs=docs)
                    print(f"Indexed {len(docs)} documents into {index_name}", flush=True)

            if pending_state:
                state.update(pending_state)
                save_state(state_file, state)

            if once:
                return

            time.sleep(poll_interval)
        except KeyboardInterrupt:
            return
        except Exception as exc:  # pragma: no cover - operational safety
            print(f"Log shipper error: {exc}", file=sys.stderr, flush=True)
            if once:
                raise
            time.sleep(poll_interval)
