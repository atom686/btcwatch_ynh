Before installing, set up a Telegram bot:

1. Open Telegram and message [@BotFather](https://t.me/BotFather).
2. Send `/newbot`, follow the prompts, and copy the HTTP API token — that's the **Telegram bot token**.
3. Send any message to your new bot.
4. Open `https://api.telegram.org/bot<TOKEN>/getUpdates` in a browser. In the JSON response, find `"chat":{"id":...}` — that's the **Telegram chat id**.

You can leave both fields blank during install and fill them in later by editing `/var/www/btcwatch/.env`, then running `systemctl restart btcwatch`.
