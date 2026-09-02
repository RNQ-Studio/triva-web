#!/usr/bin/env bash

# Menyalakan/mematikan sakelar maintenance TRIVA di server production dengan
# menyunting `.env` lewat ssh, lalu membangun ulang cache config.
#
# `.env` sengaja dipilih sebagai sumber kebenaran supaya sistem tetap bisa
# dimatikan saat database atau back-office justru sedang bermasalah.
#
# Pemakaian:
#   scripts/maintenance.sh status
#   scripts/maintenance.sh on  [--message "Teks untuk user"] [--until "2026-09-02T17:00:00+07:00"]
#   scripts/maintenance.sh off
#
# Environment yang bisa ditimpa:
#   TRIVA_SSH_HOST  (default root@199.180.131.115)
#   TRIVA_SSH_KEY   (default ~/Documents/Work/sshkey/manahpro)
#   TRIVA_APP_DIR   (default /var/www/triva-web)

set -Eeuo pipefail

SSH_HOST="${TRIVA_SSH_HOST:-root@199.180.131.115}"
SSH_KEY="${TRIVA_SSH_KEY:-$HOME/Documents/Work/sshkey/manahpro}"
APP_DIR="${TRIVA_APP_DIR:-/var/www/triva-web}"

action="${1:-}"
shift || true

message=''
until_at=''
have_message=0
have_until=0

while (( $# )); do
    case "$1" in
        --message)
            message="${2:?--message butuh nilai}"
            have_message=1
            shift 2
            ;;
        --until)
            until_at="${2:?--until butuh nilai}"
            have_until=1
            shift 2
            ;;
        *)
            printf 'Argumen tidak dikenal: %s\n' "$1" >&2
            exit 2
            ;;
    esac
done

case "$action" in
    on|off|status) ;;
    *)
        printf 'Pemakaian: %s {on|off|status} [--message "..."] [--until "..."]\n' "$0" >&2
        exit 2
        ;;
esac

ssh_run() {
    ssh -o BatchMode=yes -o ConnectTimeout=15 -i "$SSH_KEY" "$SSH_HOST" "$@"
}

if [[ "$action" == 'status' ]]; then
    ssh_run "cd '$APP_DIR' && grep -E '^TRIVA_MAINTENANCE_' .env || echo '(tidak ada TRIVA_MAINTENANCE_* di .env — sakelar mati)'"
    exit 0
fi

# `.env` tidak pernah masuk git, jadi backup-nya harus dibuat di server sendiri
# sebelum disunting. Penyuntingannya idempotent: kunci yang sudah ada diganti,
# yang belum ada ditambahkan.
remote_script=$(cat <<'REMOTE'
set -Eeuo pipefail
cd "$APP_DIR"

cp -p .env ".env.backup-maintenance"

set_key() {
    local key="$1" value="$2"
    if grep -qE "^${key}=" .env; then
        # Nilai lewat variabel awk, bukan interpolasi, supaya karakter khusus
        # di dalam pesan tidak diperlakukan sebagai sintaks.
        awk -v k="$key" -v v="$value" \
            'BEGIN { FS = "=" } $1 == k { print k "=" v; next } { print }' \
            .env > .env.tmp
        mv .env.tmp .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

drop_key() {
    grep -vE "^$1=" .env > .env.tmp || true
    mv .env.tmp .env
}

if [ "$ACTION" = 'on' ]; then
    set_key TRIVA_MAINTENANCE_MODE true
    # Bukan `[ ... ] && set_key ...`: di bawah `set -e`, tes yang gagal jadi
    # status akhir skrip dan mematikannya di tengah jalan.
    if [ "$HAVE_MESSAGE" = '1' ]; then
        set_key TRIVA_MAINTENANCE_MESSAGE "\"$MESSAGE\""
    fi
    if [ "$HAVE_UNTIL" = '1' ]; then
        set_key TRIVA_MAINTENANCE_UNTIL "\"$UNTIL_AT\""
    else
        drop_key TRIVA_MAINTENANCE_UNTIL
    fi
else
    set_key TRIVA_MAINTENANCE_MODE false
    drop_key TRIVA_MAINTENANCE_UNTIL
fi

chown --reference=.env.backup-maintenance .env
chmod --reference=.env.backup-maintenance .env

# Production men-cache config; tanpa ini `.env` yang baru tidak terbaca sama
# sekali dan sakelarnya tampak tidak berpengaruh.
php artisan config:cache >/dev/null

printf '\n--- TRIVA_MAINTENANCE_* di .env sekarang ---\n'
grep -E '^TRIVA_MAINTENANCE_' .env || echo '(tidak ada)'
REMOTE
)

ssh_run \
    "APP_DIR='$APP_DIR' ACTION='$action' HAVE_MESSAGE='$have_message' HAVE_UNTIL='$have_until' \
     MESSAGE=$(printf %q "$message") UNTIL_AT=$(printf %q "$until_at") bash -s" <<<"$remote_script"

printf '\nMemverifikasi lewat endpoint publik...\n'
curl -fsS 'https://triva.ramadhanrosihadi.web.id/api/v1/app/config' \
    | python3 -c 'import json,sys; d=json.load(sys.stdin)["data"]; print({k: v for k, v in d.items() if k.startswith("maintenance")})'
