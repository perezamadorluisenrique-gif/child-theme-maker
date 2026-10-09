#!/usr/bin/env bash
# Builds the wordpress.org release copy in build/child-theme-maker and build/child-theme-maker.zip,
# leaving out the top-level entries listed in .distignore.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf build/child-theme-maker build/child-theme-maker.zip
mkdir -p build/child-theme-maker
shopt -s dotglob
for entry in *; do
	if ! grep -qxF "/$entry" .distignore; then
		cp -r "$entry" build/child-theme-maker/
	fi
done
(cd build && zip -qr child-theme-maker.zip child-theme-maker)
echo "Built build/child-theme-maker ($(find build/child-theme-maker -type f | wc -l) files) and build/child-theme-maker.zip"
