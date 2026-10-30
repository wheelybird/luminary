# Releasing

1. As changes land, add them to CHANGELOG.md under `## [Unreleased]`.
2. On an up-to-date `master`, run `scripts/release.sh` (a patch release), or `scripts/release.sh minor` / `major`. It shows the notes, asks for confirmation, then stamps the changelog, bumps `www/VERSION` and the README image tag, commits, tags and pushes.
3. The tag triggers the Release workflow, which builds the `linux/amd64`, `linux/arm64` and `linux/arm/v7` images, pushes `wheelybird/luminary:vX.Y.Z` and `:latest` to Docker Hub, and creates the GitHub release from that version's changelog section.

The PHP base image is pinned to a patch release. Dependabot opens a PR when a new one is published; merge it and cut a patch release to ship the update.

One-time setup: the repository needs `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` secrets (a Docker Hub access token with read/write scope).
