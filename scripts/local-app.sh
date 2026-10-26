#!/usr/bin/env bash
# Run Focus as an always-available app on this computer.
#
#   scripts/local-app.sh install    build, then start background services and add a launcher
#   scripts/local-app.sh update     rebuild after code changes and restart
#   scripts/local-app.sh status     show service state
#   scripts/local-app.sh logs       follow the app's logs
#   scripts/local-app.sh open       open Focus in its own window (add --wait to wait for the server)
#   scripts/local-app.sh autostart on|off   open the Focus window automatically when you log in
#   scripts/local-app.sh uninstall  stop and remove the services and launcher (keeps your data)
#
# Everything runs as your user (systemd --user): no sudo, listening on 127.0.0.1 only.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API="$ROOT/apps/api"
PORT="${FOCUS_PORT:-8765}"
URL="http://localhost:$PORT"
UNIT_DIR="$HOME/.config/systemd/user"
DESKTOP_FILE="$HOME/.local/share/applications/focus.desktop"
AUTOSTART_FILE="${XDG_CONFIG_HOME:-$HOME/.config}/autostart/focus.desktop"
APP_UNIT="focus-app.service"
SCHEDULER_UNIT="focus-scheduler.service"

say() { printf '\033[1;32m▸\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31m✗\033[0m %s\n' "$*" >&2; exit 1; }

require() {
    for cmd in php composer node pnpm systemctl; do
        command -v "$cmd" >/dev/null || fail "$cmd is required but not installed."
    done
    php -m | grep -qi pdo_sqlite || fail "PHP's SQLite driver is missing: sudo apt install php8.4-sqlite3"
    [ -f "$API/.env" ] || fail "apps/api/.env is missing — follow the setup steps in README.md first."
}

# Production settings for the installed copy; .env stays as it is for `pnpm dev`.
prod_env() {
    APP_ENV=production APP_DEBUG=false APP_URL="$URL" LOG_LEVEL=info "$@"
}

build() {
    say "Installing dependencies"
    (cd "$ROOT" && pnpm install --frozen-lockfile)
    (cd "$API" && composer install --no-interaction --quiet)

    say "Updating the database"
    (cd "$API" && prod_env php artisan migrate --force)

    say "Building the app"
    (cd "$ROOT" && pnpm build)

    say "Checking the setup"
    (cd "$API" && prod_env php artisan focus:doctor) || fail "Fix the items above, then run this again."
}

write_units() {
    local php
    php="$(command -v php)"
    mkdir -p "$UNIT_DIR"

    cat >"$UNIT_DIR/$APP_UNIT" <<UNIT
[Unit]
Description=Focus productivity app ($URL)
After=network.target

[Service]
WorkingDirectory=$API/public
Environment=APP_ENV=production
Environment=APP_DEBUG=false
Environment=APP_URL=$URL
Environment=LOG_LEVEL=info
Environment=PHP_CLI_SERVER_WORKERS=4
ExecStart=$php -S 127.0.0.1:$PORT $API/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
Restart=on-failure
RestartSec=3

[Install]
WantedBy=default.target
UNIT

    cat >"$UNIT_DIR/$SCHEDULER_UNIT" <<UNIT
[Unit]
Description=Focus scheduler (saves the daily report at 23:55)
After=$APP_UNIT

[Service]
WorkingDirectory=$API
Environment=APP_ENV=production
Environment=APP_DEBUG=false
Environment=APP_URL=$URL
Environment=LOG_LEVEL=info
ExecStart=$php artisan schedule:work
Restart=on-failure
RestartSec=10

[Install]
WantedBy=default.target
UNIT
}

write_launcher() {
    local exec="xdg-open $URL"
    for browser in brave-browser google-chrome chromium chromium-browser microsoft-edge; do
        if command -v "$browser" >/dev/null; then
            exec="$browser --app=$URL --class=Focus"
            break
        fi
    done
    mkdir -p "$(dirname "$DESKTOP_FILE")"
    cat >"$DESKTOP_FILE" <<DESKTOP
[Desktop Entry]
Type=Application
Name=Focus
Comment=Tasks, goals, blockers, journal and daily reports
Exec=$exec
Icon=$API/public/icons/icon-512.png
Categories=Office;ProjectManagement;
StartupWMClass=Focus
Terminal=false
DESKTOP
    command -v update-desktop-database >/dev/null && update-desktop-database "$(dirname "$DESKTOP_FILE")" 2>/dev/null || true
}

write_autostart() {
    mkdir -p "$(dirname "$AUTOSTART_FILE")"
    cat >"$AUTOSTART_FILE" <<DESKTOP
[Desktop Entry]
Type=Application
Name=Focus
Comment=Open Focus when you log in
Exec=$ROOT/scripts/local-app.sh open --wait
Icon=$API/public/icons/icon-512.png
Terminal=false
X-GNOME-Autostart-enabled=true
X-GNOME-Autostart-Delay=3
DESKTOP
}

wait_until_up() {
    for _ in $(seq 1 30); do
        curl -fsS -o /dev/null "$URL/up" 2>/dev/null && return 0
        sleep 0.5
    done
    fail "Focus did not start. Check: scripts/local-app.sh logs"
}

case "${1:-}" in
    install)
        require
        build
        say "Starting background services"
        write_units
        systemctl --user daemon-reload
        systemctl --user enable --now "$APP_UNIT" "$SCHEDULER_UNIT"
        wait_until_up
        write_launcher
        if [ "${FOCUS_AUTOSTART:-1}" = "1" ]; then
            write_autostart
            say "Focus will open automatically when you log in (turn off: pnpm app:autostart off)"
        fi
        say "Focus is running at $URL"
        say "Open it from your app menu (\"Focus\"), or in Brave/Chrome use ⋮ → Install Focus."
        ;;
    update)
        require
        build
        systemctl --user restart "$APP_UNIT" "$SCHEDULER_UNIT"
        wait_until_up
        say "Updated and restarted — $URL"
        ;;
    status)
        systemctl --user --no-pager status "$APP_UNIT" "$SCHEDULER_UNIT" || true
        ;;
    logs)
        journalctl --user -u "$APP_UNIT" -u "$SCHEDULER_UNIT" -f &
        tail -n 50 -F "$API"/storage/logs/laravel-*.log
        ;;
    open)
        [ "${2:-}" = "--wait" ] && wait_until_up
        exec_line="$(grep '^Exec=' "$DESKTOP_FILE" 2>/dev/null | cut -d= -f2-)"
        [ -n "$exec_line" ] || exec_line="xdg-open $URL"
        nohup $exec_line >/dev/null 2>&1 &
        ;;
    autostart)
        case "${2:-}" in
            on) write_autostart && say "Focus will open when you log in." ;;
            off) rm -f "$AUTOSTART_FILE" && say "Focus won't open at login (the background service still starts)." ;;
            *) fail "Use: scripts/local-app.sh autostart on|off" ;;
        esac
        ;;
    uninstall)
        systemctl --user disable --now "$APP_UNIT" "$SCHEDULER_UNIT" 2>/dev/null || true
        rm -f "$UNIT_DIR/$APP_UNIT" "$UNIT_DIR/$SCHEDULER_UNIT" "$DESKTOP_FILE" "$AUTOSTART_FILE"
        systemctl --user daemon-reload
        say "Removed the services and launcher. Your data in apps/api/database is untouched."
        ;;
    *)
        sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'
        exit 1
        ;;
esac
