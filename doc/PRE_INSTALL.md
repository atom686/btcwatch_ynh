You **don't** need anything before installing — the Telegram fields can be left
blank during install and filled in later from the web UI's **Telegram** panel.

When you're ready to set up Telegram:

1. Open Telegram and message [@BotFather](https://t.me/BotFather).
2. Send `/newbot`, follow the prompts, and copy the HTTP API token — that's the **bot token**.
3. Send any message to your new bot.
4. Open `https://api.telegram.org/bot<TOKEN>/getUpdates` in a browser. In the JSON response, find `"chat":{"id":...}` — that's the **chat id**.
5. Paste both into the Telegram panel of the btcwatch UI and click **Save**, then **Send test message** to confirm.
