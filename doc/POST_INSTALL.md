Open the app at the URL you chose during install. The first balance observation
for any newly-added address is treated as a baseline, so you won't get a
notification just for adding an address that already holds funds.

To set or update Telegram credentials, scroll down to the **Telegram** panel
and use the form. Click **Send test message** to confirm everything is wired
up — you should see a message appear in your Telegram chat within a few
seconds.

To change the polling cadence (default: every 5 minutes):

```
sudo nano /etc/cron.d/btcwatch
sudo systemctl reload cron
```

Logs:

```
sudo tail -f /var/log/btcwatch/poll.log
sudo tail -f /var/log/btcwatch/error.log
```
