#!/usr/bin/env bash
set -uo pipefail

usage() {
    echo "Usage: scripts/safe-node-test.sh tests/js/archivo.test.js [--pattern \"nombre exacto\"]" >&2
    exit 1
}

die() {
    echo "safe-node-test.sh: $*" >&2
    exit 1
}

if [[ "${1:-}" == "" ]]; then
    usage
fi

if [[ $# -ne 1 && $# -ne 3 ]]; then
    usage
fi

TEST_FILE="$1"
PATTERN=""

if [[ $# -eq 3 ]]; then
    if [[ "$2" != "--pattern" ]]; then
        die "argumento desconocido: $2"
    fi
    PATTERN="$3"
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

if [[ ! -f "$TEST_FILE" ]]; then
    die "el archivo no existe: $TEST_FILE"
fi

if [[ "$TEST_FILE" == *".."* ]]; then
    die "no se permite .. en la ruta del test"
fi

if [[ "$TEST_FILE" != tests/js/* ]]; then
    die "el archivo debe estar bajo tests/js/"
fi

if [[ "$TEST_FILE" != *.test.js ]]; then
    die "el archivo debe terminar en .test.js"
fi

if [[ -L "$TEST_FILE" ]]; then
    RESOLVED_TEST="$(readlink -f "$TEST_FILE")"
    TESTS_JS_DIR="$(readlink -f "$REPO_ROOT/tests/js")"
    if [[ "$RESOLVED_TEST" != "$TESTS_JS_DIR"/* ]]; then
        die "el symlink resuelve fuera de tests/js/"
    fi
else
    RESOLVED_TEST="$(readlink -f "$TEST_FILE")"
    TESTS_JS_DIR="$(readlink -f "$REPO_ROOT/tests/js")"
fi

if [[ "$RESOLVED_TEST" != "$TESTS_JS_DIR"/* ]]; then
    die "la ruta resuelta queda fuera de tests/js/"
fi

NODE_BIN="$(command -v node || true)"
if [[ -z "$NODE_BIN" ]]; then
    die "node no está disponible en PATH"
fi

if ! command -v systemd-run >/dev/null 2>&1; then
    die "systemd-run no está disponible; no hay fallback sin límites"
fi

if ! command -v timeout >/dev/null 2>&1; then
    die "timeout no está disponible; no hay fallback sin límites"
fi

if [[ ! -x /usr/bin/time ]]; then
    die "/usr/bin/time no está disponible; no hay fallback sin límites"
fi

OUTPUT="$(mktemp /tmp/safe-node-test-XXXXXX.out)"
WRAPPER="$(mktemp /tmp/safe-node-test-run-XXXXXX.sh)"

cleanup() {
    rm -f "$OUTPUT" "$WRAPPER"
}
trap cleanup EXIT

{
    echo '#!/usr/bin/env bash'
    echo 'set -uo pipefail'
    printf 'cd %q\n' "$REPO_ROOT"
    echo 'args=()'
    printf 'args+=(%q)\n' "$NODE_BIN"
    echo 'args+=(--max-old-space-size=192 --test --test-concurrency=1 --test-timeout=3000)'
    if [[ -n "$PATTERN" ]]; then
        printf 'args+=(--test-name-pattern=%q)\n' "$PATTERN"
    fi
    printf 'args+=(%q)\n' "$RESOLVED_TEST"
    echo 'exec /usr/bin/time -v timeout -k 1s 12s "${args[@]}"'
} > "$WRAPPER"
chmod +x "$WRAPPER"

set +e
systemd-run --user --collect --wait --pipe \
    -p MemoryMax=256M \
    -p MemorySwapMax=0 \
    -p TasksMax=64 \
    "$WRAPPER" >"$OUTPUT" 2>&1
STATUS=$?
set -e

cat "$OUTPUT"
echo "exit=$STATUS"

if grep -qiE 'heap out of memory|Allocation failed|\bOOM\b' "$OUTPUT"; then
    echo "Detenerse: no reintentar ni ampliar memoria; diagnosticar el test/harness" >&2
fi

case "$STATUS" in
    124)
        echo "Timeout externo (exit 124). Detenerse: no reintentar ni ampliar memoria; diagnosticar el test/harness." >&2
        ;;
    137)
        echo "Kill/OOM probable (exit 137). Detenerse: no reintentar ni ampliar memoria; diagnosticar el test/harness." >&2
        ;;
esac

exit "$STATUS"
