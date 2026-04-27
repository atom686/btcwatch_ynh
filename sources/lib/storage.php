<?php
/**
 * Tiny JSON-file-backed store for watched addresses.
 *
 * Each record:
 *   id, address, label, last_balance_sats, added_at,
 *   last_checked_at, last_change_at
 *
 * All writes are guarded by flock() so the cron poller and the web request
 * cannot stomp on each other.
 */

namespace BtcWatch;

class Storage
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        if (!file_exists($this->path)) {
            $dir = dirname($this->path);
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            file_put_contents($this->path, "[]");
        }
    }

    /** Returns array of address records (sorted by added_at). */
    public function all(): array
    {
        return $this->withLock(LOCK_SH, function ($fp) {
            return $this->read($fp);
        });
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }
        return null;
    }

    public function add(string $address, ?string $label = null): array
    {
        return $this->withLock(LOCK_EX, function ($fp) use ($address, $label) {
            $records = $this->read($fp);
            $record = [
                'id'                 => self::uuid(),
                'address'            => trim($address),
                'label'              => $label === null || $label === '' ? null : trim($label),
                'last_balance_sats'  => null,
                'added_at'           => gmdate('c'),
                'last_checked_at'    => null,
                'last_change_at'     => null,
            ];
            $records[] = $record;
            $this->write($fp, $records);
            return $record;
        });
    }

    public function delete(string $id): bool
    {
        return $this->withLock(LOCK_EX, function ($fp) use ($id) {
            $records = $this->read($fp);
            $before = count($records);
            $records = array_values(array_filter($records, fn($r) => $r['id'] !== $id));
            if (count($records) === $before) {
                return false;
            }
            $this->write($fp, $records);
            return true;
        });
    }

    public function updateBalance(string $id, int $balanceSats, bool $changed): ?array
    {
        return $this->withLock(LOCK_EX, function ($fp) use ($id, $balanceSats, $changed) {
            $records = $this->read($fp);
            $found = null;
            foreach ($records as &$r) {
                if ($r['id'] === $id) {
                    $now = gmdate('c');
                    $r['last_balance_sats'] = $balanceSats;
                    $r['last_checked_at'] = $now;
                    if ($changed) {
                        $r['last_change_at'] = $now;
                    }
                    $found = $r;
                    break;
                }
            }
            unset($r);
            if ($found === null) {
                return null;
            }
            $this->write($fp, $records);
            return $found;
        });
    }

    // --- internals -------------------------------------------------------

    private function withLock(int $kind, callable $fn): mixed
    {
        $fp = fopen($this->path, $kind === LOCK_EX ? 'c+' : 'r');
        if ($fp === false) {
            throw new \RuntimeException("cannot open {$this->path}");
        }
        try {
            if (!flock($fp, $kind)) {
                throw new \RuntimeException("cannot lock {$this->path}");
            }
            return $fn($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function read($fp): array
    {
        rewind($fp);
        $raw = stream_get_contents($fp);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function write($fp, array $records): void
    {
        $json = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json);
        fflush($fp);
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
