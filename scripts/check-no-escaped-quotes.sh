#!/bin/bash
# Fail when a text file contains backslash-escaped quotes (\"), which render
# literally on WordPress.org readme pages. Filenames come from pre-commit.
set -u

offenders=$(grep -l '\\"' "$@" 2>/dev/null)
if [ -n "$offenders" ]; then
    printf '%s\n' "$offenders"
    echo "ERROR: Found escaped quotes (backslash-quote) in text files. Use regular quotes instead."
    exit 1
fi
exit 0
