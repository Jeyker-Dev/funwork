#!/usr/bin/env bash
# Deploys FunWork to the VPS without GitHub Actions or GHCR.
#
# Runs Pest and Pint locally, then over SSH:
#   1. Updates the git checkout on the VPS (~/funwork/src) and builds funwork:<sha>.
#   2. Preflight: ensures MySQL is healthy before touching running containers.
#   3. Backs up MySQL to ~/funwork/backups (retaining the 10 newest backups).
#   4. Runs database migrations in a one-off container with the new image.
#   5. Restarts the stack with the new image (--remove-orphans).
#   6. Mirrors docs/ into the FunWork "Docs" notebook.
#   7. Prunes old Docker images.
#
# Usage: scripts/deploy.sh [--skip-checks]
#
# Settings (environment variables, or a gitignored .deploy.env at the repo root):
#   FUNWORK_DEPLOY_HOST      SSH destination, e.g. "deploy@1.2.3.4" or a ~/.ssh/config alias (required)
#   FUNWORK_DOCS_PROJECT_ID  FunWork project that receives docs/ (optional; skips the sync if empty)
#   FUNWORK_DEPLOY_BRANCH    Branch to deploy (default: master)
#   FUNWORK_REPO_URL         Repository to clone on the VPS (default: public GitHub HTTPS URL)
#
# One-time VPS setup: git and Docker installed, ~/funwork/.env present,
# ~/funwork/auth.json with the Flux Pro credentials for composer, and
# ~/funwork/backups for database dumps.

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

    echo "==> Ensuring MySQL is healthy"
    docker compose -f compose.prod.yml up -d mysql

    mysql_ready=false
    for _ in {1..30}; do
        if docker compose -f compose.prod.yml exec -T mysql sh -c 'mysqladmin ping -h 127.0.0.1 -u root -p"$MYSQL_ROOT_PASSWORD" --silent' </dev/null >/dev/null 2>&1; then
            mysql_ready=true
            break
        fi
        sleep 2
    done

    if [[ "$mysql_ready" != true ]]; then
        echo "Error: MySQL did not become healthy within 60 seconds. Aborting deploy." >&2
        exit 1
    fi

    echo "==> Backing up database"
    backup_dir="$HOME/funwork/backups"
    mkdir -p "$backup_dir"
    backup_file="$backup_dir/backup_$(date -u +%Y%m%d_%H%M%S)_$sha.sql.gz"
    backup_tmp="$backup_file.tmp"

    if ! docker compose -f compose.prod.yml exec -T mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --routines --triggers --no-tablespaces "$MYSQL_DATABASE"' </dev/null | gzip > "$backup_tmp"; then
        rm -f "$backup_tmp"
        echo "Database backup failed. Aborting deploy." >&2
        exit 1
    fi
    mv "$backup_tmp" "$backup_file"

    # shellcheck disable=SC2012
    (ls -1t "$backup_dir"/backup_*.sql.gz 2>/dev/null || true) | tail -n +11 | xargs -r -d '\n' rm -f

    echo "==> Running migrations with funwork:$sha"
    docker compose -f compose.prod.yml run --rm --no-deps -T app php artisan migrate --force </dev/null

    previous_image="$(docker compose -f compose.prod.yml ps -q app | xargs -r docker inspect --format '{{.Config.Image}}' 2>/dev/null || true)"
    previous_tag="${previous_image#funwork:}"

    echo "==> Starting production stack"
    docker compose -f compose.prod.yml up -d --remove-orphans

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

    # Keep the running build, latest, and the previous image so rollback works (one prior version only).
    keep_pattern="latest|$sha"
    if [[ -n "$previous_tag" && "$previous_tag" != "$sha" && "$previous_tag" != "latest" ]]; then
        keep_pattern="latest|$sha|$previous_tag"
        echo "==> Previous image kept for rollback: funwork:$previous_tag"
    fi

    docker images funwork --format '{{.Tag}}' \
        | grep -vxE "$keep_pattern" \
        | xargs -r -I{} docker rmi "funwork:{}" >/dev/null 2>&1 || true
    docker image prune -f >/dev/null

    echo "==> Deployed funwork:$sha"
}

main
REMOTE
