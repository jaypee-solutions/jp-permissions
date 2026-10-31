#!/bin/sh
set -eu

cd "$(dirname "$0")/.."

answers_file=.copier-answers.yml
if [ ! -f "$answers_file" ]; then
    echo "Missing $answers_file; cannot determine the configured PHP namespace." >&2
    exit 1
fi

php_namespace=$(awk -F: '
    $1 == "php_namespace" {
        value = substr($0, index($0, ":") + 1)
        sub(/^[[:space:]]+/, "", value)
        sub(/[[:space:]]+$/, "", value)
        if (value ~ /^".*"$/ || value ~ /^\047.*\047$/) {
            value = substr(value, 2, length(value) - 2)
        }
        print value
        exit
    }
' "$answers_file")
if [ -z "$php_namespace" ]; then
    echo "No php_namespace value found in $answers_file." >&2
    exit 1
fi

if [ ! -d tests/integration ]; then
    echo "Missing tests/integration; cannot check integration-test seams." >&2
    exit 1
fi

# Integration tests exercise the plugin through WordPress, not direct calls to
# namespaced implementation methods. Constants such as ::OPTION and ::class stay valid.
# Imported names (`use Ns\Class;` then `Class::method(`) are not detected.
PHP_NAMESPACE=$php_namespace find tests/integration -type f -name '*.php' -exec awk '
    {
        expected = ENVIRON["PHP_NAMESPACE"] "\\"
        line = $0
        while ((position = index(line, expected)) > 0) {
            before = position > 1 ? substr(line, position - 1, 1) : ""
            remainder = substr(line, position + length(expected))
            if (before !~ /[A-Za-z0-9_]/ && remainder ~ /^[A-Za-z_][A-Za-z0-9_]*::[A-Za-z_][A-Za-z0-9_]*[[:space:]]*[(]/) {
                printf "%s:%d: call the plugin through WordPress (hooks, REST, core functions), not a static method.\n", FILENAME, FNR > "/dev/stderr"
                failed = 1
                break
            }
            line = remainder
        }
    }
    END { exit failed }
' {} +
