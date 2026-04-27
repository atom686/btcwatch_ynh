# Bitcoin Address Watch — YunoHost package

[![Integration level](https://dash.yunohost.org/integration/btcwatch.svg)]()

> *This package installs Bitcoin Address Watch on a YunoHost server. If you don't have YunoHost yet, see the [install guide](https://yunohost.org/install).*

## Overview

A small PHP web app that monitors Bitcoin addresses via
[mempool.space](https://mempool.space) and sends a Telegram message whenever a
balance changes (confirmed or unconfirmed).

**Shipped version:** 0.2.0~ynh1

## Architecture

- **Web UI** — PHP 8.2 served by a per-app PHP-FPM pool behind nginx, gated by YunoHost SSO.
- **Poller** — `poll.php` runs from `/etc/cron.d/btcwatch` every 5 minutes.
- **Storage** — two JSON files in the data dir: `addresses.json` (watched list + last-known balances) and `settings.json` (Telegram credentials, editable from the web UI).
- **No daemon, no compiler, no native extensions.**

## Configuration

Most things are configurable from the **Telegram** panel in the web UI. To
change the polling cadence, edit `/etc/cron.d/btcwatch` and adjust the
`*/5 * * * *` schedule.

State paths:
- `/home/yunohost.app/btcwatch/addresses.json` — watched addresses + balances
- `/home/yunohost.app/btcwatch/settings.json` — Telegram creds (mode 0600)
- `/var/www/btcwatch/config.php` — paths only (rewritten on every install/upgrade)

Logs:
- `/var/log/btcwatch/poll.log` — output from each cron run
- `/var/log/btcwatch/error.log` — PHP errors

## YunoHost specific features

- Multi-instance: yes
- Architectures: any
- LDAP: not relevant — the app has no concept of users; SSO is the gate
- SSO: yes (default `init_main_permission = all_users`)

## Package layout

```
btcwatch_ynh/
├── manifest.toml             # v2 manifest: id, install questions, resources
├── conf/
│   ├── nginx.conf            # PHP-FPM reverse-proxy template
│   ├── php-fpm.conf          # per-app FPM pool
│   ├── cron                  # /etc/cron.d/btcwatch — runs poll.php
│   ├── config.php            # paths-only template, rendered into install_dir
│   └── logrotate             # /var/log/btcwatch/*.log rotation
├── scripts/
│   ├── _common.sh            # shared helpers (sync source, seed settings)
│   ├── install / upgrade / remove
│   ├── backup / restore
│   └── change_url
├── sources/
│   ├── public/index.php      # web entry point (UI + add/delete/check + Telegram settings)
│   ├── lib/                  # Storage, Mempool, Telegram, Monitor, Settings
│   ├── views/index.php       # the rendered HTML
│   └── poll.php              # cron entry point
├── tests.toml                # CI install-test arguments
├── doc/
├── LICENSE
└── README.md
```

## Install / test

On a YunoHost test server:

```bash
sudo yunohost app install https://github.com/atom686/btcwatch_ynh \
  --args "domain=example.com&path=/btcwatch&init_main_permission=all_users&telegram_bot_token=&telegram_chat_id="
```

The Telegram fields can be left blank — set them from the web UI's Telegram
panel after install. Or paste them via the admin web UI: **Apps → Install
custom app**.

## License

MIT — see [LICENSE](LICENSE).
