Open the app at the URL you chose during install. The first balance observation
for any newly-added address is treated as a baseline, so you won't get a
notification just for adding an address that already holds funds.

To change the polling interval or update Telegram credentials later:

```
sudo nano /var/www/btcwatch/.env
sudo systemctl restart btcwatch
```

Logs:

```
journalctl -u btcwatch -f
```
