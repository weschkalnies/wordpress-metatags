#!/bin/sh
# Builds an installable plugin zip (folder "metatags/") from the current commit.
set -e
cd "$(dirname "$0")/.."
VERSION=$(sed -n 's/^ \* Version: *//p' metatags.php)
git archive --format=zip --prefix=metatags/ -o "metatags-$VERSION.zip" HEAD
echo "metatags-$VERSION.zip"
