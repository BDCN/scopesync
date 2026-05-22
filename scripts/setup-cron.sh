#!/bin/bash
# =============================================================================
# ScopeSync — Extraction Worker Cron Setup
# Run as root on the server: sudo bash /var/www/scopesync/scripts/setup-cron.sh
# =============================================================================

set -e

APP_DIR="/var/www/scopesync"
PHP_BIN="/usr/bin/php"
APACHE_USER="apache"
APACHE_GROUP="apache"
CRON_FILE="/etc/cron.d/scopesync-worker"
LOG_FILE="/var/log/scopesync-worker.log"

# Check running as root
if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Run this script as root (sudo bash $0)"
    exit 1
fi

# Check PHP exists at expected path
if [ ! -x "$PHP_BIN" ]; then
    echo "ERROR: PHP not found at $PHP_BIN"
    echo "Find it with: which php"
    exit 1
fi

echo "--- Setting up ScopeSync extraction worker cron ---"
echo ""

# 1. Create log file with correct ownership
echo "[1/5] Creating log file $LOG_FILE ..."
touch "$LOG_FILE"
chown "$APACHE_USER:$APACHE_GROUP" "$LOG_FILE"
chmod 640 "$LOG_FILE"
echo "      OK"

# 2. Ensure storage/ tree is writable by apache
echo "[2/5] Fixing storage/ ownership ..."
mkdir -p "$APP_DIR/storage/tenants"
chown -R "$APACHE_USER:$APACHE_GROUP" "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/storage"
echo "      OK"

# 3. Fix ownership of all app files (needed after git pull)
echo "[3/5] Fixing app file ownership ..."
chown -R "$APACHE_USER:$APACHE_GROUP" "$APP_DIR"
echo "      OK"

# 4. Write cron.d file
echo "[4/5] Installing cron job to $CRON_FILE ..."
cat > "$CRON_FILE" << EOF
# ScopeSync extraction worker — runs every minute
# Processes up to 5 pending extractions per run.
# Logs: $LOG_FILE
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
* * * * * $APACHE_USER $PHP_BIN $APP_DIR/scripts/worker.php >> $LOG_FILE 2>&1
EOF
chmod 644 "$CRON_FILE"
echo "      OK — cron entry written"

# 5. Smoke-test the worker (safe: no extractions pending means it exits immediately)
echo "[5/5] Smoke-testing worker ..."
sudo -u "$APACHE_USER" "$PHP_BIN" "$APP_DIR/scripts/worker.php"
echo "      OK"

echo ""
echo "=== Setup complete ==="
echo ""
echo "The worker runs every minute. Monitor logs with:"
echo "  tail -f $LOG_FILE"
echo ""
echo "To verify the cron is registered:"
echo "  crontab -u $APACHE_USER -l"
echo "  cat $CRON_FILE"
echo ""
echo "To test manually (runs as apache user):"
echo "  sudo -u $APACHE_USER $PHP_BIN $APP_DIR/scripts/worker.php"
