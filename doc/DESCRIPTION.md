**Bitcoin Address Watch** is a tiny Sinatra (Ruby) web app that lets you keep
an eye on a list of Bitcoin addresses without running a full node.

It does three things:

- A small browser UI to add, view and delete watched addresses (each with an optional label).
- A background poller that hits [mempool.space](https://mempool.space) every hour (configurable) and stores the latest balance per address.
- On any balance change — confirmed transaction or new mempool entry — it sends a message to a Telegram chat via the Bot API, including the previous balance, new balance, delta, and a link to mempool.space.

State (the watched-address list and last-known balances) is persisted to a single JSON file under the YunoHost data directory, so it survives upgrades.

The web UI is SSO-protected by default — only authorised YunoHost users can add or remove addresses.
