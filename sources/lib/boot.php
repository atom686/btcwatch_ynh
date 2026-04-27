<?php
/**
 * Common bootstrap. Loads static config + runtime settings, wires up the
 * Storage / Mempool / Telegram / Monitor / Settings instances.
 *
 * Used by both web (public/index.php) and cron (poll.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/mempool.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/monitor.php';

$configPath = __DIR__ . '/../config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config.php — re-run the YunoHost installer or copy config.php.example.\n");
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        echo "btcwatch is misconfigured (no config.php).";
    }
    exit(1);
}

/** @var array{addresses_path:string, settings_path:string, poll_interval_human?:string} $config */
$config = require $configPath;

$settings = new \BtcWatch\Settings($config['settings_path']);
$storage  = new \BtcWatch\Storage($config['addresses_path']);
$mempool  = new \BtcWatch\Mempool();
$telegram = new \BtcWatch\Telegram(
    $settings->get('telegram_bot_token', ''),
    $settings->get('telegram_chat_id', '')
);
$monitor  = new \BtcWatch\Monitor($storage, $mempool, $telegram);

return [
    'config'   => $config,
    'settings' => $settings,
    'storage'  => $storage,
    'mempool'  => $mempool,
    'telegram' => $telegram,
    'monitor'  => $monitor,
];
