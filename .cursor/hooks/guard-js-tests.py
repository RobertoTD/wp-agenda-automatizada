#!/usr/bin/env python3
"""Cursor beforeShellExecution hook: block direct JS test runs."""

from __future__ import annotations

import json
import re
import sys

DENY_RESPONSE = {
    "permission": "deny",
    "user_message": (
        "Prueba JS directa bloqueada por seguridad. Usa scripts/safe-node-test.sh."
    ),
    "agent_message": (
        "No ejecutes node --test directamente. Usa un solo archivo mediante "
        "scripts/safe-node-test.sh; ante OOM o timeout detente y diagnostica."
    ),
}

ALLOW_RESPONSE = {"permission": "allow"}

SAFE_RUNNER_RE = re.compile(r"(?:^|[\s'\"=])(?:\./)?scripts/safe-node-test\.sh(?:\s|$)")
NODE_CHECK_RE = re.compile(r"\bnode\b(?:\s+[^\s|;&]+)*\s+--check\b")
NODE_TEST_RE = re.compile(r"\bnode\b")
TEST_FILE_RE = re.compile(r"tests/js/[^\s'\"|;&]+\.test\.js")
NPM_TEST_RE = re.compile(r"\bnpm\b(?:\s+run)?\s+test(?:\s|$|[:\"'])")
NPX_TEST_RE = re.compile(
    r"\b(?:npx|pnpm|yarn)\b[^\n|;&]*\b(?:test|node\s+--test)\b"
)


def _uses_safe_runner(command: str) -> bool:
    return SAFE_RUNNER_RE.search(command) is not None


def _is_node_syntax_check(command: str) -> bool:
    if "--test" in command:
        return False
    return NODE_CHECK_RE.search(command) is not None


def _is_direct_js_test(command: str) -> bool:
    if _uses_safe_runner(command):
        return False

    if "--test" in command and NODE_TEST_RE.search(command):
        return True

    if NODE_TEST_RE.search(command) and TEST_FILE_RE.search(command):
        return True

    if NPM_TEST_RE.search(command):
        return True

    if NPX_TEST_RE.search(command):
        return True

    return False


def evaluate(command: str) -> dict[str, str]:
    if _uses_safe_runner(command) or _is_node_syntax_check(command):
        return ALLOW_RESPONSE
    if _is_direct_js_test(command):
        return DENY_RESPONSE
    return ALLOW_RESPONSE


def main() -> int:
    try:
        payload = json.load(sys.stdin)
    except json.JSONDecodeError:
        return 1

    command = payload.get("command")
    if not isinstance(command, str):
        return 1

    sys.stdout.write(json.dumps(evaluate(command)))
    sys.stdout.write("\n")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
