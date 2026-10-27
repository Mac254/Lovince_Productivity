#!/usr/bin/env bash
# Run Focus as an app on this computer. It runs only when you start it: nothing starts at login.
#
#   scripts/local-app.sh install    build, add the services and the "Focus" launcher, then start it
#   scripts/local-app.sh update     rebuild after code changes (restarts it only if it was running)
#   scripts/local-app.sh open       start Focus if needed and open its window (what the launcher runs)
#   scripts/local-app.sh start      start the server, scheduler and queue worker without a window
#   scripts/local-app.sh stop       stop all of them
#   scripts/local-app.sh status     show service state
#   scripts/local-app.sh logs       follow the app's logs
#   scripts/local-app.sh autostart on|off   also open Focus when you log in (off by default)
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
QUEUE_UNIT="focus-queue.service"

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
# Starting the app starts its helpers; they stop with it (PartOf below).
Wants=$SCHEDULER_UNIT $QUEUE_UNIT

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
PartOf=$APP_UNIT

[Service]
WorkingDirectory=$API
Environment=APP_ENV=production
Environment=APP_DEBUG=false
Environment=APP_URL=$URL
Environment=LOG_LEVEL=info
# The 23:55 run is missed while Focus is closed: queue any missing reports from the last week.
ExecStartPre=$php artisan focus:daily-report --missing=7
ExecStart=$php artisan schedule:work
Restart=on-failure
RestartSec=10

[Install]
WantedBy=default.target
UNIT

    cat >"$UNIT_DIR/$QUEUE_UNIT" <<UNIT
[Unit]
Description=Focus queue worker (runs background jobs such as the nightly reports)
After=$APP_UNIT
PartOf=$APP_UNIT

[Service]
WorkingDirectory=$API
Environment=APP_ENV=production
Environment=APP_DEBUG=false
Environment=APP_URL=$URL
Environment=LOG_LEVEL=info
ExecStart=$php artisan queue:work --tries=3 --sleep=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=default.target
UNIT
}

# An app-style window in a Chromium browser if there is one, else the default browser.
browser_cmd() {
    for browser in brave-browser google-chrome chromium chromium-browser microsoft-edge; do
        if command -v "$browser" >/dev/null; then
            echo "$browser --app=$URL --class=Focus"
            return
        fi
    done
    echo "xdg-open $URL"
}

write_launcher() {
    local exec="$ROOT/scripts/local-app.sh open"
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
Exec=$ROOT/scripts/local-app.sh open
Icon=$API/public/icons/icon-512.png
Terminal=false
X-GNOME-Autostart-enabled=true
X-GNOME-Autostart-Delay=3
DESKTOP
}

running() { systemctl --user is-active --quiet "$APP_UNIT"; }

start_app() {
    running || systemctl --user start "$APP_UNIT"
    wait_until_up
}

# Services are started on demand, never at login.
register_units() {
    write_units
    systemctl --user daemon-reload
    systemctl --user disable "$APP_UNIT" "$SCHEDULER_UNIT" "$QUEUE_UNIT" >/dev/null 2>&1 || true
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
        register_units
        write_launcher
        if [ "${FOCUS_AUTOSTART:-0}" = "1" ]; then
            write_autostart
        else
            rm -f "$AUTOSTART_FILE"
        fi
        start_app
        say "Focus is running at $URL"
        say "From now on, open it from your app menu (\"Focus\") — that starts it. Stop it with: pnpm app:stop"
        ;;
    update)
        require
        build
        was_running=0
        running && was_running=1
        register_units
        write_launcher
        if [ "$was_running" = 1 ]; then
            systemctl --user restart "$APP_UNIT"
            wait_until_up
            say "Updated and restarted — $URL"
        else
            say "Updated. Open Focus from your app menu to start it."
        fi
        ;;
    start)
        start_app
        say "Focus is running at $URL (stop: pnpm app:stop)"
        ;;
    stop)
        systemctl --user stop "$APP_UNIT" "$SCHEDULER_UNIT" "$QUEUE_UNIT"
        say "Focus is stopped. Open it from your app menu to start it again."
        ;;
    status)
        systemctl --user --no-pager status "$APP_UNIT" "$SCHEDULER_UNIT" "$QUEUE_UNIT" || true
        ;;
    logs)
        journalctl --user -u "$APP_UNIT" -u "$SCHEDULER_UNIT" -u "$QUEUE_UNIT" -f &
        tail -n 50 -F "$API"/storage/logs/laravel-*.log
        ;;
    open)
        start_app
        nohup $(browser_cmd) >/dev/null 2>&1 &
        ;;
    autostart)
        case "${2:-}" in
            on) write_autostart && say "Focus will start and open when you log in." ;;
            off) rm -f "$AUTOSTART_FILE" && say "Focus won't start at login; open it from your app menu." ;;
            *) fail "Use: scripts/local-app.sh autostart on|off" ;;
        esac
        ;;
    uninstall)
        systemctl --user disable --now "$APP_UNIT" "$SCHEDULER_UNIT" "$QUEUE_UNIT" 2>/dev/null || true
        rm -f "$UNIT_DIR/$APP_UNIT" "$UNIT_DIR/$SCHEDULER_UNIT" "$UNIT_DIR/$QUEUE_UNIT" "$DESKTOP_FILE" "$AUTOSTART_FILE"
        systemctl --user daemon-reload
        say "Removed the services and launcher. Your data in apps/api/database is untouched."
        ;;
    *)
        sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'
        exit 1
        ;;
esac
