# Bitcoin Address Watch — YunoHost package

[![Integration level](https://dash.yunohost.org/integration/btcwatch.svg)]()

> *This package allows you to install Bitcoin Address Watch quickly and simply on a YunoHost server.*
> *If you don't have YunoHost, please consult [the guide](https://yunohost.org/install) to learn how to install it.*

## Overview

A small Sinatra (Ruby) web app that monitors Bitcoin addresses via
[mempool.space](https://mempool.space) and sends a Telegram message whenever a
balance changes (confirmed or unconfirmed).

**Shipped version:** 0.1.0~ynh1

## Screenshots & docs

See [`doc/DESCRIPTION.md`](doc/DESCRIPTION.md) for what the app does, and
[`doc/PRE_INSTALL.md`](doc/PRE_INSTALL.md) for how to obtain the Telegram bot
token and chat id you'll be asked for during install.

## Configuration

Edit `/var/www/btcwatch/.env` and run `systemctl restart btcwatch`. Available
keys: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `POLL_INTERVAL_SECONDS`.

State (watched addresses + last-known balances) lives in
`/home/yunohost.app/btcwatch/addresses.json`.

## YunoHost specific features

- Multi-instance: yes
- Architectures: any (Ruby, native gems compile from source)
- LDAP: not relevant — the app has no concept of users; SSO is the gate
- SSO: yes (default `init_main_permission = all_users`)

## Layout of this package

```
btcwatch_ynh/
├── manifest.toml          # v2 manifest: id, install questions, resources
├── conf/
│   ├── nginx.conf         # reverse-proxy template
│   ├── systemd.service    # ExecStart=bundle exec rackup ...
│   ├── btcwatch.env       # template for /var/www/btcwatch/.env
│   └── logrotate          # placeholder (logs go to journald)
├── scripts/
│   ├── _common.sh         # shared helpers (sync source, bundle install)
│   ├── install
│   ├── remove
│   ├── upgrade
│   ├── backup
│   ├── restore
│   └── change_url
├── sources/               # bundled Sinatra app (Gemfile, app.rb, lib/, views/)
├── tests.toml             # CI install-test arguments
├── doc/
│   ├── DESCRIPTION.md
│   ├── PRE_INSTALL.md
│   └── POST_INSTALL.md
├── LICENSE
└── README.md
```

## Local install / test

On a YunoHost test server, clone the repo and point `yunohost app install` at it:

```bash
git clone https://github.com/atom686/btcwatch_ynh.git
yunohost app install ./btcwatch_ynh \
  --args "domain=example.com&path=/btcwatch&init_main_permission=all_users&telegram_bot_token=...&telegram_chat_id=...&poll_interval_seconds=3600"
```

Or via the admin web UI: Apps → Install custom app → paste this repo's URL.

## License

MIT — see [LICENSE](LICENSE).
