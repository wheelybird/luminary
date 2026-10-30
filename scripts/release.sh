#!/bin/bash
# Cut a release: stamp CHANGELOG.md's [Unreleased] section with the next version,
# update version files, then commit, tag and push. Pushing the tag triggers
# .github/workflows/release.yml, which builds and publishes everything else.
#
# Usage: scripts/release.sh [patch|minor|major]   (default: patch)

set -euo pipefail

BRANCH=master

bump_version_files() {
  local version="$1"
  echo "$version" > www/VERSION
  sed -i -E "s|(wheelybird/luminary:)v[0-9]+\.[0-9]+\.[0-9]+|\1v$version|g" README.md
}

die() { echo "release: $*" >&2; exit 1; }

cd "$(git rev-parse --show-toplevel)"

part="${1:-patch}"
case "$part" in
  patch|minor|major) ;;
  *) die "usage: $0 [patch|minor|major]" ;;
esac

[ "$(git branch --show-current)" == "$BRANCH" ] || die "not on $BRANCH"
[ -z "$(git status --porcelain)" ] || die "working tree has uncommitted changes"
git fetch -q --tags origin
[ "$(git rev-parse HEAD)" == "$(git rev-parse "origin/$BRANCH")" ] || die "$BRANCH is not in sync with origin/$BRANCH"

last="$(git describe --tags --abbrev=0 --match 'v[0-9]*.[0-9]*.[0-9]*')"
IFS=. read -r major minor patch <<< "${last#v}"
case "$part" in
  major) version="$((major + 1)).0.0" ;;
  minor) version="$major.$((minor + 1)).0" ;;
  patch) version="$major.$minor.$((patch + 1))" ;;
esac

notes="$(awk '/^## \[Unreleased\]/ {found=1; next} found && /^## \[/ {exit} found' CHANGELOG.md)"
[ -n "${notes//[[:space:]]/}" ] || die "CHANGELOG.md has no entries under '## [Unreleased]'"

echo "Releasing v$version (previous release: $last)"
echo "$notes"
echo
read -r -p "Tag and push v$version? [y/N] " answer
[ "$answer" == "y" ] || die "aborted"

sed -i "s/^## \[Unreleased\]/## [$version] - $(date +%F)/" CHANGELOG.md
bump_version_files "$version"

git commit -q -am "Release v$version"
git tag -a "v$version" -m "v$version"
git push --atomic origin "$BRANCH" "v$version"

echo "Pushed v$version. Watch the build with: gh run watch"
