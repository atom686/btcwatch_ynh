<?php
/**
 * Glue: walks every stored record, hits mempool.space, fires Telegram
 * on balance change. Used by both the cron poller (poll.php) and the
 * "Check now" buttons in the UI.
 *
 * The first observation of a brand-new address is recorded silently as
 * a baseline — we don't notify on adding an address that already holds
 * funds.
 */

namespace BtcWatch;

class Monitor
{
    public function __construct(
        private Storage  $storage,
        private Mempool  $mempool,
        private Telegram $telegram
    ) {}

    /** Poll every record once. Errors per-address are logged, not thrown. */
    public function checkAll(): array
    {
        $results = [];
        foreach ($this->storage->all() as $record) {
            $results[$record['id']] = $this->checkOne($record);
        }
        return $results;
    }

    /** Returns ['ok'|'error', message]. */
    public function checkOneById(string $id): array
    {
        $record = $this->storage->find($id);
        if ($record === null) {
            return ['error', 'address not found'];
        }
        return $this->checkOne($record);
    }

    private function checkOne(array $record): array
    {
        $address  = $record['address'];
        $previous = $record['last_balance_sats'];

        try {
            $current = $this->mempool->balanceSats($address);
        } catch (MempoolError $e) {
            $msg = "mempool error for " . self::shortAddr($address) . ": " . $e->getMessage();
            error_log("[monitor] $msg");
            return ['error', $msg];
        }

        if ($previous === null) {
            $this->storage->updateBalance($record['id'], $current, false);
            error_log("[monitor] baseline " . self::shortAddr($address) . " = $current sats");
            return ['ok', 'baseline recorded'];
        }

        if ((int)$current !== (int)$previous) {
            $this->storage->updateBalance($record['id'], $current, true);
            $this->notifyChange($record, (int)$previous, (int)$current);
            return ['ok', 'change notified'];
        }

        $this->storage->updateBalance($record['id'], $current, false);
        return ['ok', 'no change'];
    }

    private function notifyChange(array $record, int $previous, int $current): void
    {
        $delta = $current - $previous;
        $sign  = $delta > 0 ? '+' : '';
        $label = $record['label'] ? ' (' . htmlspecialchars($record['label'], ENT_QUOTES) . ')' : '';
        $addr  = $record['address'];

        $msg = "<b>Bitcoin balance changed</b>$label\n"
             . "<code>" . htmlspecialchars($addr, ENT_QUOTES) . "</code>\n\n"
             . "Previous: <b>" . self::formatBtc($previous) . "</b>\n"
             . "Current:  <b>" . self::formatBtc($current)  . "</b>\n"
             . "Delta:    <b>$sign" . self::formatBtc($delta) . "</b>\n\n"
             . "<a href=\"https://mempool.space/address/" . rawurlencode($addr) . "\">View on mempool.space</a>";

        try {
            $this->telegram->send($msg);
            error_log("[monitor] notified change " . self::shortAddr($addr) . " $previous -> $current ($sign$delta)");
        } catch (TelegramError $e) {
            error_log("[monitor] telegram send failed: " . $e->getMessage());
        }
    }

    private static function formatBtc(int $sats): string
    {
        $btc = $sats / 100000000;
        return sprintf('%.8f BTC (%d sats)', $btc, $sats);
    }

    private static function shortAddr(string $addr): string
    {
        if (strlen($addr) <= 16) return $addr;
        return substr($addr, 0, 8) . '…' . substr($addr, -6);
    }
}
