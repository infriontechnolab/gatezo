#!/usr/bin/env bash
# Nightly backup: MySQL dump + uploaded files, kept locally for 14 days and pushed
# off-box so a dead VPS is not a dead business.
#
#   bash ~/gatezo/infra/backup.sh
#
# Off-box upload is optional but strongly recommended. Put S3-compatible credentials in
# .env (Cloudflare R2, Backblaze B2, Wasabi, any S3):
#
#   BACKUP_S3_BUCKET=gatezo-backups
#   BACKUP_S3_ENDPOINT=https://<account>.r2.cloudflarestorage.com
#   BACKUP_S3_KEY=...
#   BACKUP_S3_SECRET=...
#   BACKUP_S3_REGION=auto
#
# Cron it (2:30 every night), after `chmod +x infra/backup.sh`:
#   (crontab -l 2>/dev/null; echo "30 2 * * * bash \$HOME/gatezo/infra/backup.sh >> \$HOME/backups/backup.log 2>&1") | crontab -
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/gatezo}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"
COMPOSE="docker compose -f $APP_DIR/compose.prod.yml"
STAMP="$(date +%F-%H%M)"

cd "$APP_DIR"
set -a; [[ -f .env ]] && . ./.env; set +a
mkdir -p "$BACKUP_DIR"

echo "[$(date -Is)] backup start"

# ---- database -------------------------------------------------------------------
# --single-transaction keeps it consistent without locking the gate during an event.
db_file="$BACKUP_DIR/gatezo-db-$STAMP.sql.gz"
$COMPOSE exec -T db sh -c \
    "exec mysqldump --single-transaction --quick --no-tablespaces -u\"\$MYSQL_USER\" -p\"\$MYSQL_PASSWORD\" \"\$MYSQL_DATABASE\"" \
    | gzip > "$db_file"
[[ -s "$db_file" ]] || { echo "dump is empty, aborting" >&2; rm -f "$db_file"; exit 1; }

# ---- uploaded files (event logos, stall images, CSV imports) ---------------------
files_file="$BACKUP_DIR/gatezo-files-$STAMP.tar.gz"
volume="$(docker volume ls -q | grep -E 'app_storage$' | head -1)"
if [[ -n "$volume" ]]; then
    docker run --rm -v "$volume":/data:ro -v "$BACKUP_DIR":/out alpine \
        tar czf "/out/$(basename "$files_file")" -C /data . 2>/dev/null || true
fi

echo "  db:    $(du -h "$db_file" | cut -f1)  $(basename "$db_file")"
[[ -f "$files_file" ]] && echo "  files: $(du -h "$files_file" | cut -f1)  $(basename "$files_file")"

# ---- off-box ---------------------------------------------------------------------
if [[ -n "${BACKUP_S3_BUCKET:-}" && -n "${BACKUP_S3_KEY:-}" ]]; then
    for f in "$db_file" "$files_file"; do
        [[ -f "$f" ]] || continue
        docker run --rm -e AWS_ACCESS_KEY_ID="$BACKUP_S3_KEY" -e AWS_SECRET_ACCESS_KEY="$BACKUP_S3_SECRET" \
            -e AWS_DEFAULT_REGION="${BACKUP_S3_REGION:-auto}" -v "$BACKUP_DIR":/b:ro amazon/aws-cli:latest \
            s3 cp "/b/$(basename "$f")" "s3://$BACKUP_S3_BUCKET/$(basename "$f")" \
            --endpoint-url "$BACKUP_S3_ENDPOINT" --only-show-errors
        echo "  uploaded $(basename "$f")"
    done
else
    echo "  WARNING: no BACKUP_S3_* in .env, backups are only on this server."
fi

# ---- prune -----------------------------------------------------------------------
find "$BACKUP_DIR" -name 'gatezo-*.gz' -mtime "+$KEEP_DAYS" -delete

echo "[$(date -Is)] backup done"
