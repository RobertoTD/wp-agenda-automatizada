#!/usr/bin/env python3
"""Cursor beforeShellExecution hook: block direct JS test runs.

Allowed controlled runners (exact invocation shapes only):
  - wp-agenda-automatizada/scripts/safe-node-test.sh
      → tests/js/<file>.test.js [--pattern "..."]
  - deoia-oauth-backend/scripts/safe-node-test.sh
      → tests/<file>.test.js [--pattern "..."]

Optional single prefix: cd <approved_repo_root> && <runner> ...
Shell chaining / alternate executables / path escapes are denied.
node --check remains allowed. npm test / node --test remain denied.
"""

from __future__ import annotations

import json
import re
import shlex
import sys
from pathlib import Path

DENY_RESPONSE = {
    "permission": "deny",
    "user_message": (
        "Prueba JS directa bloqueada por seguridad. "
        "Usa el runner seguro del repo: scripts/safe-node-test.sh "
        "(plugin: tests/js/*.test.js; oauth-backend: tests/*.test.js)."
    ),
    "agent_message": (
        "No ejecutes node --test ni npm test directamente. "
        "Usa un solo archivo mediante el scripts/safe-node-test.sh del repo "
        "correspondiente; ante OOM o timeout detente y diagnostica."
    ),
}

ALLOW_RESPONSE = {"permission": "allow"}

NODE_CHECK_RE = re.compile(r"\bnode\b(?:\s+[^\s|;&]+)*\s+--check\b")
NODE_TEST_RE = re.compile(r"\bnode\b")
TEST_FILE_RE = re.compile(r"tests/js/[^\s'\"|;&]+\.test\.js")
NPM_TEST_RE = re.compile(r"\bnpm\b(?:\s+run)?\s+test(?:\s|$|[:\"'])")
NPX_TEST_RE = re.compile(
    r"\b(?:npx|pnpm|yarn)\b[^\n|;&]*\b(?:test|node\s+--test)\b"
)
SAFE_NAME_RE = re.compile(r"safe-node-test\.sh")
# Disallow chaining / substitution beyond a single optional `cd ROOT &&`.
DISALLOWED_META_RE = re.compile(r"[|$;`\n\r]|\|\|")


def _roots() -> tuple[Path, Path]:
    hook_file = Path(__file__).resolve()
    wp_root = hook_file.parents[2]
    backend_root = wp_root.parents[1] / "deoia-oauth-backend"
    return wp_root, backend_root


def _approved_runners() -> dict[Path, str]:
    wp_root, backend_root = _roots()
    return {
        (wp_root / "scripts" / "safe-node-test.sh").resolve(): "wp",
        (backend_root / "scripts" / "safe-node-test.sh").resolve(): "backend",
    }


def _is_node_syntax_check(command: str) -> bool:
    if "--test" in command:
        return False
    return NODE_CHECK_RE.search(command) is not None


def _is_direct_js_test(command: str) -> bool:
    if "--test" in command and NODE_TEST_RE.search(command):
        return True
    if NODE_TEST_RE.search(command) and TEST_FILE_RE.search(command):
        return True
    if NPM_TEST_RE.search(command):
        return True
    if NPX_TEST_RE.search(command):
        return True
    return False


def _split_optional_cd(tokens: list[str]) -> tuple[Path | None, list[str]] | None:
    """Return (cd_root|None, remaining_tokens) or None if malformed cd prefix."""
    if len(tokens) >= 3 and tokens[0] == "cd" and tokens[2] == "&&":
        try:
            cd_root = Path(tokens[1]).expanduser().resolve()
        except OSError:
            return None
        return cd_root, tokens[3:]
    if "&&" in tokens or tokens[:1] == ["cd"]:
        # Partial/malformed cd or extra chaining.
        return None
    return None, tokens


def _resolve_runner(token: str, cwd: Path | None, cd_root: Path | None) -> Path | None:
    raw = Path(token).expanduser()
    try:
        if raw.is_absolute():
            return raw.resolve()
        base = cd_root or cwd
        if base is None:
            return None
        return (base / raw).resolve()
    except OSError:
        return None


def _valid_wp_test_arg(arg: str, wp_root: Path) -> bool:
    if ".." in arg or arg.startswith("/"):
        return False
    if not arg.startswith("tests/js/") or not arg.endswith(".test.js"):
        return False
    if arg.count("/") != 2:
        return False
    try:
        resolved = (wp_root / arg).resolve()
    except OSError:
        return False
    tests_js = (wp_root / "tests" / "js").resolve()
    return resolved.parent == tests_js and resolved.name.endswith(".test.js")


def _valid_backend_test_arg(arg: str, backend_root: Path) -> bool:
    if ".." in arg or arg.startswith("/"):
        return False
    if not arg.startswith("tests/") or not arg.endswith(".test.js"):
        return False
    # Flat tests/*.test.js only (no nested segments).
    rest = arg[len("tests/") :]
    if "/" in rest or rest.startswith("."):
        return False
    try:
        resolved = (backend_root / arg).resolve()
    except OSError:
        return False
    tests_dir = (backend_root / "tests").resolve()
    return resolved.parent == tests_dir and resolved.name.endswith(".test.js")


def _valid_runner_args(kind: str, args: list[str], wp_root: Path, backend_root: Path) -> bool:
    if len(args) == 1:
        test_arg = args[0]
        pattern_ok = True
    elif len(args) == 3 and args[1] == "--pattern":
        test_arg = args[0]
        # Pattern must be a single argv token (already from shlex); reject empties.
        pattern_ok = isinstance(args[2], str) and args[2] != ""
    else:
        return False

    if not pattern_ok:
        return False
    if kind == "wp":
        return _valid_wp_test_arg(test_arg, wp_root)
    if kind == "backend":
        return _valid_backend_test_arg(test_arg, backend_root)
    return False


def _command_has_disallowed_meta(command: str) -> bool:
    """Reject shell metacharacters except a single `cd ROOT &&` prefix."""
    stripped = command.strip()
    # Allow one `cd <path> &&` then require the remainder free of meta.
    cd_match = re.match(
        r"^cd\s+(\S+)\s+&&\s+(.*)$",
        stripped,
        flags=re.DOTALL,
    )
    if cd_match:
        remainder = cd_match.group(2)
        if DISALLOWED_META_RE.search(cd_match.group(1)):
            return True
        # Extra chaining after the runner invocation.
        if "&&" in remainder or ";" in remainder:
            return True
        return DISALLOWED_META_RE.search(remainder) is not None
    if "&&" in stripped or ";" in stripped:
        return True
    return DISALLOWED_META_RE.search(stripped) is not None


def _is_approved_safe_runner_invocation(command: str, cwd: str | None) -> bool:
    if not SAFE_NAME_RE.search(command):
        return False
    if _command_has_disallowed_meta(command):
        return False

    try:
        tokens = shlex.split(command)
    except ValueError:
        return False

    if not tokens:
        return False

    split = _split_optional_cd(tokens)
    if split is None:
        return False
    cd_root, rest = split
    if not rest:
        return False

    wp_root, backend_root = _roots()
    approved = _approved_runners()

    if cd_root is not None and cd_root not in {wp_root.resolve(), backend_root.resolve()}:
        return False

    cwd_path: Path | None = None
    if cwd:
        try:
            cwd_path = Path(cwd).expanduser().resolve()
        except OSError:
            cwd_path = None

    runner = _resolve_runner(rest[0], cwd_path, cd_root)
    if runner is None or runner not in approved:
        return False

    # Relative runner must be invoked from matching repo (via cd or cwd).
    kind = approved[runner]
    expected_root = wp_root.resolve() if kind == "wp" else backend_root.resolve()
    if not rest[0].startswith("/"):
        effective = cd_root or cwd_path
        if effective is None or effective != expected_root:
            return False

    if not runner.is_file():
        return False

    return _valid_runner_args(kind, rest[1:], wp_root.resolve(), backend_root.resolve())


def _mentions_safe_runner(command: str) -> bool:
    return SAFE_NAME_RE.search(command) is not None


def evaluate(command: str, cwd: str | None = None) -> dict[str, str]:
    if _is_node_syntax_check(command):
        return ALLOW_RESPONSE

    if _is_approved_safe_runner_invocation(command, cwd):
        return ALLOW_RESPONSE

    # Mention of the runner name without a valid approved shape → deny.
    if _mentions_safe_runner(command):
        return DENY_RESPONSE

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

    cwd = payload.get("cwd")
    if cwd is not None and not isinstance(cwd, str):
        cwd = None

    sys.stdout.write(json.dumps(evaluate(command, cwd)))
    sys.stdout.write("\n")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
