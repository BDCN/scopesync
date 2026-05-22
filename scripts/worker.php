<?php
/**
 * ScopeSync extraction worker.
 * Invoked by cron every minute:
 *   * * * * * apache /usr/bin/php /var/www/scopesync/scripts/worker.php >> /var/log/scopesync-worker.log 2>&1
 *
 * Delegates to the CI3 Cron controller via CLI so the full framework
 * (DB, models, libraries, config) is available.
 */

$php  = PHP_BINARY ?: '/usr/bin/php';
$root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';

chdir($root);
passthru("{$php} index.php cron process 2>&1");
