<?php
/**
 * Minimal mempool.space client.
 * Docs: https://mempool.space/docs/api/rest
 */

namespace BtcWatch;

class MempoolError extends \RuntimeException {}

class Mempool
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? 'https://mempool.space', '/');
    }

    /**
     * Total balance in satoshis = confirmed (chain) + unconfirmed (mempool).
     * Picks up an in-flight transaction immediately.
     */
    public function balanceSats(string $address): int
    {
        $info  = $this->addressInfo($address);
        $chain = $info['chain_stats']   ?? [];
        $mp    = $info['mempool_stats'] ?? [];
        $confirmed   = ((int)($chain['funded_txo_sum'] ?? 0)) - ((int)($chain['spent_txo_sum'] ?? 0));
        $unconfirmed = ((int)($mp['funded_txo_sum']    ?? 0)) - ((int)($mp['spent_txo_sum']    ?? 0));
        return $confirmed + $unconfirmed;
    }

    public function addressInfo(string $address): array
    {
        $url = $this->baseUrl . '/api/address/' . rawurlencode($address);
        return $this->getJson($url);
    }

    /** Lightweight check: true if mempool.space recognises the address. */
    public function isValidAddress(string $address): bool
    {
        try {
            $this->addressInfo($address);
            return true;
        } catch (MempoolError $e) {
            return false;
        }
    }

    private function getJson(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: btcwatch/0.2 (+https://mempool.space)',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new MempoolError("curl error: $err");
        }
        if ($code < 200 || $code >= 300) {
            throw new MempoolError("mempool.space $code for $url: " . substr((string)$body, 0, 200));
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new MempoolError("mempool.space returned non-JSON for $url");
        }
        return $data;
    }
}
