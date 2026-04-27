<?php
/**
 * Cron entry point. Run via:
 *   php /var/www/btcwatch/poll.php
 *
 * Cron fires every 5 minutes (the system-wide floor); this script then
 * self-throttles using settings.poll_interval_seconds so the user can
 * stretch the cadence from the web UI without touching crontab.
 *
 * Two safety nets:
 *   1. flock() on a lock file in the data dir — keeps a slow poll
 *      from being clobbered by the next cron tick.
 *   2. Throttle: if poll_interval_seconds > 300 and the previous run
 *      was less than that ago, exit silently.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("poll.php is not web-accessible.\n");
}

$ctx = require __DIR__ . '/lib/boot.php';
/** @var \BtcWatch\Monitor  $monitor  */
$monitor  = $ctx['monitor'];
/** @var \BtcWatch\Settings $settings */
$settings = $ctx['settings'];

// --- 1. Single-instance lock -------------------------------------------------

$dataDir  = dirname($ctx['config']['settings_path']);
$lockFile = $dataDir . '/poll.lock';
$lock = @fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

// --- 2. Throttle (if user picked >5 min in the UI, skip cron ticks) ----------

$intervalSec = max(300, (int)$settings->get('poll_interval_seconds', 300));
$lastIso     = $settings->get('last_poll_at');
$lastTs      = $lastIso ? strtotime((string)$lastIso) : 0;

if ((time() - $lastTs) < $intervalSec) {
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

// Mark "now" before doing work — concurrent ticks see we're running.
$settings->update(['last_poll_at' => gmdate('c')]);

// --- 3. Actual polling -------------------------------------------------------

$started = microtime(true);
$results = $monitor->checkAll();
$elapsed = round(microtime(true) - $started, 2);

$counts = ['ok' => 0, 'error' => 0];
foreach ($results as $r) {
    $counts[$r[0]] = ($counts[$r[0]] ?? 0) + 1;
}
fwrite(STDOUT, sprintf(
    "[btcwatch] poll done in %.2fs — %d ok, %d error, %d total (interval %ds)\n",
    $elapsed, $counts['ok'], $counts['error'], count($results), $intervalSec
));

flock($lock, LOCK_UN);
fclose($lock);
