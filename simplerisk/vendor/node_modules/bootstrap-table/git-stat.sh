#!/bin/bash

# Git statistics script - Count commits, additions, deletions for a specific author and date range

if [ $# -lt 3 ]; then
    echo "Usage: $0 <author> <start_date> <end_date>"
    echo "Example: $0 \"John Doe\" 2024-01-01 2024-12-31"
    exit 1
fi

author="$1"
start_date="$2"
end_date="$3"

echo "Author: $author"
echo "Date range: $start_date to $end_date"
echo "---"

# Get commit count
commits=$(git log --author="$author" --after="$start_date" --before="$end_date" --oneline --no-merges | wc -l | tr -d ' ')

echo "Commits: $commits"

# Get code statistics (additions, deletions)
git log --author="$author" --after="$start_date" --before="$end_date" --no-merges --pretty=tformat: --numstat | \
    awk '{
        add += $1;
        subs += $2
    } END {
        printf "Additions: %s\nDeletions: %s\n", add, subs
    }'
