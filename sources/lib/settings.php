<?php
/**
 * Runtime-editable settings (Telegram credentials, etc.).
 *
 * Lives in the YunoHost data dir (settings.json) — separate from the
 * install-time config.php in the install dir, so that user edits made
 * through the web UI survive `yunohost app upgrade`.
 *
 * Schema:
 *   { "telegram_bot_token": "", "telegram_chat_id": "", "updated_at": "..." }
 */

namespace BtcWatch;

class Settings
{
    public function __construct(private string $path)
    {
        if (!file_exists($this->path)) {
            $dir = dirname($this->path);
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            file_put_contents($this->path, json_encode(self::defaults(), JSON_PRETTY_PRINT));
            chmod($this->path, 0600);
        }
    }

    public function all(): array
    {
        $raw = @file_get_contents($this->path);
        if ($raw === false || trim($raw) === '') {
            return self::defaults();
        }
        $data = json_decode($raw, true);
        return is_array($data) ? array_merge(self::defaults(), $data) : self::defaults();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** Merge `$updates` into the stored settings; returns the new full hash. */
    public function update(array $updates): array
    {
        $current = $this->all();
        foreach ($updates as $k => $v) {
            $current[$k] = $v;
        }
        $current['updated_at'] = gmdate('c');

        $tmp = $this->path . '.tmp';
        file_put_contents($tmp, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        chmod($tmp, 0600);
        rename($tmp, $this->path);
        return $current;
    }

    public static function defaults(): array
    {
        return [
            'telegram_bot_token'    => '',
            'telegram_chat_id'      => '',
            // Minimum time (in seconds) between mempool.space polls.
            // Cron triggers every 5 minutes; this is a floor — the script
            // exits silently when called sooner than this interval.
            'poll_interval_seconds' => 300,
            'last_poll_at'          => null,   // ISO-8601, set by poll.php
            'updated_at'            => null,
        ];
    }
}
