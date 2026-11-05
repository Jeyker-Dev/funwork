#!/usr/bin/env bash
# Deploys FunWork to the VPS without GitHub Actions or GHCR.
#
# Runs Pest and Pint locally, then over SSH: updates a git checkout on the VPS,
# builds the production image there, restarts the stack, migrates, and mirrors
# docs/ into the FunWork "Docs" notebook.
#
# Usage: scripts/deploy.sh [--skip-checks]
#
# Settings (environment variables, or a gitignored .deploy.env at the repo root):
#   FUNWORK_DEPLOY_HOST      SSH destination, e.g. "deploy@1.2.3.4" or a ~/.ssh/config alias (required)
#   FUNWORK_DOCS_PROJECT_ID  FunWork project that receives docs/ (optional; skips the sync if empty)
#   FUNWORK_DEPLOY_BRANCH    Branch to deploy (default: master)
#   FUNWORK_REPO_URL         Repository to clone on the VPS (default: public GitHub HTTPS URL)
#
# One-time VPS setup: git and Docker installed, ~/funwork/.env present, and
# ~/funwork/auth.json with the Flux Pro credentials for composer.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

if [[ -f .deploy.env ]]; then
    # shellcheck disable=SC1091
    source .deploy.env
fi

host="${FUNWORK_DEPLOY_HOST:-}"
project_id="${FUNWORK_DOCS_PROJECT_ID:-}"
branch="${FUNWORK_DEPLOY_BRANCH:-master}"
repo_url="${FUNWORK_REPO_URL:-https://github.com/Jeyker-Dev/funwork.git}"
skip_checks=false

for arg in "$@"; do
    case "$arg" in
        --skip-checks) skip_checks=true ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

if [[ -z "$host" ]]; then
    echo "FUNWORK_DEPLOY_HOST is empty. Set it in the environment or in .deploy.env." >&2
    exit 1
fi

git fetch --quiet origin "$branch"
if [[ "$(git rev-parse HEAD)" != "$(git rev-parse "origin/$branch")" ]]; then
    echo "Warning: local HEAD differs from origin/$branch. The VPS deploys origin/$branch, not your local checkout." >&2
fi

if [[ "$skip_checks" == false ]]; then
    echo "==> Running Pest"
    ./vendor/bin/pest
    echo "==> Running Pint"
    ./vendor/bin/pint --test
fi

echo "==> Deploying origin/$branch to $host"

# Values are quoted with printf %q so they reach the remote shell unchanged.
# shellcheck disable=SC2029  # client-side expansion is intended here
ssh "$host" "BRANCH=$(printf %q "$branch") REPO_URL=$(printf %q "$repo_url") PROJECT_ID=$(printf %q "$project_id") bash -s" <<'REMOTE'
set -euo pipefail

# bash -s reads this script from stdin as it runs, and docker compose exec
# keeps stdin attached, so it would swallow the remaining lines. Wrapping
# everything in a function makes bash parse it all before running anything.
main() {
    cd ~/funwork

    if [[ ! -f auth.json ]]; then
        echo "~/funwork/auth.json is missing (Flux Pro credentials for composer)." >&2
        exit 1
    fi

    # Dedicated checkout used only for builds; local edits there are discarded.
    if [[ ! -d src/.git ]]; then
        git clone --quiet "$REPO_URL" src
    fi
    git -C src fetch --quiet origin "$BRANCH"
    git -C src reset --quiet --hard "origin/$BRANCH"

    sha="$(git -C src rev-parse --short HEAD)"
    echo "==> Building funwork:$sha"

    cp src/compose.prod.yml compose.prod.yml

    DOCKER_BUILDKIT=1 docker build \
        --secret id=composer_auth,src="$HOME/funwork/auth.json" \
        -t "funwork:$sha" \
        -t funwork:latest \
        src

    export APP_IMAGE="funwork:$sha"
    docker compose -f compose.prod.yml up -d --remove-orphans
    docker compose -f compose.prod.yml exec -T app php artisan migrate --force </dev/null

    # docs/ is excluded from the image (.dockerignore), so it is copied in from the checkout.
    # docker compose cp keeps the host uid, which appuser cannot delete in /tmp, so cleanup runs as root.
    if [[ -n "$PROJECT_ID" ]]; then
        echo "==> Syncing docs into project $PROJECT_ID"
        docker compose -f compose.prod.yml exec -T -u root app rm -rf /tmp/funwork-docs </dev/null
        docker compose -f compose.prod.yml cp src/docs/. app:/tmp/funwork-docs
        docker compose -f compose.prod.yml exec -T app php artisan funwork:sync-docs "$PROJECT_ID" --path=/tmp/funwork-docs </dev/null
        docker compose -f compose.prod.yml exec -T -u root app rm -rf /tmp/funwork-docs </dev/null
    else
        echo "==> FUNWORK_DOCS_PROJECT_ID is empty, skipping docs sync"
    fi

    # Keep only the running build and latest; images still in use are skipped.
    docker images funwork --format '{{.Tag}}' \
        | grep -vxE "latest|$sha" \
        | xargs -r -I{} docker rmi "funwork:{}" >/dev/null 2>&1 || true
    docker image prune -f >/dev/null

    echo "==> Deployed funwork:$sha"
}

main
REMOTE
