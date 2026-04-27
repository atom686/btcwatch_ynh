<?php
/**
 * Cron entry point. Run via:
 *   php /var/www/btcwatch/poll.php
 *
 * Walks every watched address, polls mempool.space, fires Telegram on
 * any balance change. Designed to be idempotent and harmless to run
 * more often than strictly needed.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("poll.php is not web-accessible.\n");
}

$ctx = require __DIR__ . '/lib/boot.php';
/** @var \BtcWatch\Monitor $monitor */
$monitor = $ctx['monitor'];

$started = microtime(true);
$results = $monitor->checkAll();
$elapsed = round(microtime(true) - $started, 2);

$counts = ['ok' => 0, 'error' => 0];
foreach ($results as $r) {
    $counts[$r[0]] = ($counts[$r[0]] ?? 0) + 1;
}
fwrite(STDOUT, sprintf(
    "[btcwatch] poll done in %.2fs — %d ok, %d error, %d total\n",
    $elapsed, $counts['ok'], $counts['error'], count($results)
));
