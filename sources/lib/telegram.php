<?php
/**
 * Telegram Bot API sender. Reads bot token + chat id from config.
 * Silently no-ops (returns false) if creds aren't configured, so the
 * cron poller can still run while the user finishes Telegram setup.
 */

namespace BtcWatch;

class TelegramError extends \RuntimeException {}

class Telegram
{
    public function __construct(
        private ?string $token,
        private string|int|null $chatId
    ) {}

    public function isConfigured(): bool
    {
        return !empty($this->token) && !empty($this->chatId);
    }

    public function send(string $text): bool
    {
        if (!$this->isConfigured()) {
            error_log('[telegram] skipped — TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID not set');
            return false;
        }

        $url = 'https://api.telegram.org/bot' . $this->token . '/sendMessage';
        $payload = json_encode([
            'chat_id'                  => $this->chatId,
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new TelegramError("curl error: $err");
        }
        if ($code < 200 || $code >= 300) {
            throw new TelegramError("Telegram API $code: " . substr((string)$body, 0, 300));
        }
        return true;
    }
}
