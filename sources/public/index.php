<?php
/**
 * btcwatch web entry point.
 * Single page — list, add, delete, check, plus runtime Telegram settings.
 * Auth is handled by YunoHost SSO upstream of nginx.
 */

declare(strict_types=1);

$ctx = require __DIR__ . '/../lib/boot.php';
/** @var \BtcWatch\Storage $storage */
$storage = $ctx['storage'];
/** @var \BtcWatch\Mempool $mempool */
$mempool = $ctx['mempool'];
/** @var \BtcWatch\Monitor $monitor */
$monitor = $ctx['monitor'];
/** @var \BtcWatch\Telegram $telegram */
$telegram = $ctx['telegram'];
/** @var \BtcWatch\Settings $settings */
$settings = $ctx['settings'];

session_start();

function flash(string $kind, string $msg): void {
    $_SESSION['flash'] = ['kind' => $kind, 'message' => $msg];
}
function take_flash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}
function self_url(): string {
    return strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
}
function redirect_self(): never {
    header('Location: ' . self_url());
    exit;
}
function h(string|int|null $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function format_sats(?int $sats): string {
    if ($sats === null) return '—';
    return number_format($sats / 1e8, 8) . ' BTC';
}
function format_time(?string $iso): string {
    if (!$iso) return 'never';
    $t = strtotime($iso);
    return $t ? gmdate('Y-m-d H:i', $t) . ' UTC' : $iso;
}
/** Mask all but the last 4 chars of a token, for display. */
function mask_token(?string $token): string {
    if (!$token) return '';
    $len = strlen($token);
    if ($len <= 4) return str_repeat('•', $len);
    return str_repeat('•', max(8, $len - 4)) . substr($token, -4);
}
/** Render an interval (in seconds) as e.g. "every 5 minutes". */
function format_interval(int $secs): string {
    if ($secs < 3600)  { $m = intdiv($secs, 60);   return "every $m minute"  . ($m === 1 ? '' : 's'); }
    if ($secs < 86400) { $h = intdiv($secs, 3600); return "every $h hour"    . ($h === 1 ? '' : 's'); }
    $d = intdiv($secs, 86400);
    return "every $d day" . ($d === 1 ? '' : 's');
}

// --- Action dispatch -------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

if ($method === 'POST') {
    // ----- Settings updates -----
    if ($action === 'save_telegram') {
        $token  = trim((string)($_POST['telegram_bot_token'] ?? ''));
        $chatId = trim((string)($_POST['telegram_chat_id']   ?? ''));

        // Empty token field means "keep the existing one" — so the UI can
        // submit the form without retyping the secret. To explicitly clear,
        // tick the "clear" checkbox.
        $clear = !empty($_POST['clear_token']);
        $update = ['telegram_chat_id' => $chatId];
        if ($clear) {
            $update['telegram_bot_token'] = '';
        } elseif ($token !== '') {
            $update['telegram_bot_token'] = $token;
        }
        $settings->update($update);
        flash('success', 'Telegram settings saved.');
        redirect_self();
    }

    if ($action === 'save_polling') {
        $secs = (int)($_POST['poll_interval_seconds'] ?? 300);
        // 5 min is the floor — matches cron tick rate, lower is meaningless.
        $secs = max(300, min(86400, $secs));
        $settings->update(['poll_interval_seconds' => $secs]);
        flash('success', 'Polling cadence saved (' . format_interval($secs) . ').');
        redirect_self();
    }

    if ($action === 'test_telegram') {
        // Reload telegram with whatever's now in settings.
        $fresh = new \BtcWatch\Telegram(
            $settings->get('telegram_bot_token', ''),
            $settings->get('telegram_chat_id', '')
        );
        if (!$fresh->isConfigured()) {
            flash('error', 'Set the bot token and chat id first.');
            redirect_self();
        }
        try {
            $fresh->send("<b>btcwatch</b>: this is a test message. Telegram is wired up correctly.");
            flash('success', 'Test message sent — check your Telegram.');
        } catch (\BtcWatch\TelegramError $e) {
            flash('error', 'Telegram refused the message: ' . $e->getMessage());
        }
        redirect_self();
    }

    // ----- Address ops -----
    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id !== '' && $storage->delete($id)) {
            flash('success', 'Address removed.');
        } else {
            flash('error', 'Address not found.');
        }
        redirect_self();
    }

    if ($action === 'check') {
        $id = $_POST['id'] ?? '';
        [$kind, $msg] = $monitor->checkOneById($id);
        flash($kind === 'ok' ? 'success' : 'error', "Check: $msg");
        redirect_self();
    }

    if ($action === 'check_all') {
        $monitor->checkAll();
        flash('success', 'Checked all addresses.');
        redirect_self();
    }

    // Default POST = add address
    $address = trim((string)($_POST['address'] ?? ''));
    $label   = trim((string)($_POST['label']   ?? ''));

    if ($address === '') {
        flash('error', 'Address is required.');
        redirect_self();
    }
    foreach ($storage->all() as $r) {
        if (strcasecmp($r['address'], $address) === 0) {
            flash('error', 'That address is already being monitored.');
            redirect_self();
        }
    }
    if (!$mempool->isValidAddress($address)) {
        flash('error', 'mempool.space did not recognise that address — double-check it.');
        redirect_self();
    }
    $record = $storage->add($address, $label !== '' ? $label : null);
    try { $monitor->checkOneById($record['id']); } catch (\Throwable $e) {}
    flash('success', "Now monitoring $address.");
    redirect_self();
}

// --- Render ----------------------------------------------------------------

$addresses        = $storage->all();
$flash            = take_flash();
$settingsAll      = $settings->all();
$telegramOk       = $telegram->isConfigured();
$pollIntervalSec  = max(300, (int)$settingsAll['poll_interval_seconds']);
$pollIntervalText = format_interval($pollIntervalSec);
$lastPollAt       = $settingsAll['last_poll_at'] ?? null;

require __DIR__ . '/../views/index.php';
