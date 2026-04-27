**Bitcoin Address Watch** is a tiny PHP web app for keeping an eye on a list of
Bitcoin addresses without running a full node.

It does three things:

- A small browser UI to add, view and delete watched addresses (each with an optional label).
- A cron job runs every 5 minutes, hits [mempool.space](https://mempool.space) for each address, and stores the latest balance.
- On any balance change — confirmed transaction or new mempool entry — it sends a message to a Telegram chat via the Bot API, including the previous balance, the new balance, the delta, and a link to mempool.space.

Telegram credentials can be entered at install time or any time afterwards from the **Telegram** panel in the web UI. A "Send test message" button verifies they work.

State (the watched-address list and last-known balances) lives in a JSON file under the YunoHost data directory, so it survives upgrades.

The web UI is SSO-protected by default — only authorised YunoHost users can add or remove addresses.
