<?php
/** @var array $addresses */
/** @var ?array $flash */
/** @var bool $telegramOk */
/** @var string $pollInterval */
/** @var array $settingsAll */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bitcoin Address Watch</title>
  <style>
    :root {
      --bg:#0e1116; --panel:#161b22; --border:#30363d;
      --text:#e6edf3; --muted:#8b949e;
      --accent:#f7931a; --accent-text:#1a1a1a;
      --success:#2ea043; --danger:#f85149;
    }
    *{box-sizing:border-box}
    html,body{margin:0;padding:0}
    body{font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
         background:var(--bg);color:var(--text);min-height:100vh}
    .wrap{max-width:960px;margin:0 auto;padding:24px 20px 80px}
    h1{margin:0 0 4px;font-size:22px;letter-spacing:-0.01em}
    h1 .dot{color:var(--accent)}
    .sub{color:var(--muted);margin:0 0 24px}
    .panel{background:var(--panel);border:1px solid var(--border);border-radius:8px;padding:16px 18px;margin-bottom:20px}
    .panel h2{margin:0 0 12px;font-size:15px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
    form.add{display:grid;grid-template-columns:2fr 1fr auto;gap:8px}
    form.tg{display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:end}
    form.tg .full{grid-column:1 / -1}
    form.tg label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px;font-weight:500}
    form.tg .row-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    form.tg .checkbox{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px}
    @media(max-width:640px){form.add,form.tg{grid-template-columns:1fr}}
    input[type=text],input[type=password]{background:#0d1117;color:var(--text);border:1px solid var(--border);
        border-radius:6px;padding:8px 10px;font:inherit;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;width:100%}
    input[type=text]:focus,input[type=password]:focus{outline:2px solid var(--accent);outline-offset:-1px}
    button{cursor:pointer;background:var(--accent);color:var(--accent-text);border:0;border-radius:6px;
        padding:8px 14px;font:inherit;font-weight:600}
    button.secondary{background:transparent;color:var(--text);border:1px solid var(--border);font-weight:500}
    button.danger{background:transparent;color:var(--danger);border:1px solid var(--border);font-weight:500}
    button:hover{filter:brightness(1.05)}
    table{width:100%;border-collapse:collapse}
    th,td{text-align:left;padding:10px 8px;border-bottom:1px solid var(--border);vertical-align:middle}
    th{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:600}
    td.address{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;word-break:break-all}
    td.balance{font-variant-numeric:tabular-nums;white-space:nowrap}
    td.actions{text-align:right;white-space:nowrap}
    td.actions form{display:inline}
    td.actions button{padding:4px 10px;font-size:12px;margin-left:4px}
    .empty{color:var(--muted);padding:24px 0;text-align:center}
    .flash{padding:10px 14px;border-radius:6px;margin-bottom:16px;border:1px solid var(--border)}
    .flash.success{border-color:var(--success);background:rgba(46,160,67,.12)}
    .flash.error{border-color:var(--danger);background:rgba(248,81,73,.12)}
    .meta{color:var(--muted);font-size:12px}
    .pill{display:inline-block;font-size:11px;padding:2px 8px;border-radius:999px;border:1px solid var(--border);color:var(--muted)}
    .pill.warn{color:#f0b429;border-color:#5a4514;background:rgba(240,180,41,.08)}
    .pill.ok{color:var(--success);border-color:#1a4d24;background:rgba(46,160,67,.10)}
    a{color:var(--accent);text-decoration:none}
    a:hover{text-decoration:underline}
    .toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:#0d1117;padding:1px 5px;border-radius:4px;font-size:12px}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Bitcoin Address Watch <span class="dot">●</span></h1>
    <p class="sub">Polling mempool.space <?= h($pollInterval) ?>. Telegram message fires on every balance change.</p>

    <?php if ($flash): ?>
      <div class="flash <?= h($flash['kind']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="panel">
      <h2>Add address</h2>
      <form class="add" method="post" action="">
        <input type="text" name="address" placeholder="bc1q… / 3… / 1…" autocomplete="off" required>
        <input type="text" name="label"   placeholder="Label (optional)" autocomplete="off">
        <button type="submit">Add</button>
      </form>
    </div>

    <div class="panel">
      <div class="toolbar" style="margin-bottom:12px">
        <h2 style="margin:0">Watched addresses</h2>
        <div>
          <?php if ($telegramOk): ?>
            <span class="pill ok">Telegram configured</span>
          <?php else: ?>
            <span class="pill warn">Telegram not configured</span>
          <?php endif; ?>
          <form method="post" action="?action=check_all" style="display:inline;margin-left:8px">
            <button type="submit" class="secondary">Check all now</button>
          </form>
        </div>
      </div>

      <?php if (!$addresses): ?>
        <div class="empty">No addresses yet. Add one above to start monitoring.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>Address</th><th>Label</th><th>Balance</th><th>Last checked</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($addresses as $a): ?>
              <tr>
                <td class="address">
                  <a href="https://mempool.space/address/<?= h($a['address']) ?>" target="_blank" rel="noopener">
                    <?= h($a['address']) ?>
                  </a>
                </td>
                <td><?= h($a['label'] ?? '—') ?></td>
                <td class="balance"><?= h(format_sats($a['last_balance_sats'])) ?></td>
                <td class="meta"><?= h(format_time($a['last_checked_at'])) ?></td>
                <td class="actions">
                  <form method="post" action="?action=check">
                    <input type="hidden" name="id" value="<?= h($a['id']) ?>">
                    <button type="submit" class="secondary">Check</button>
                  </form>
                  <form method="post" action="?action=delete" onsubmit="return confirm('Stop monitoring this address?');">
                    <input type="hidden" name="id" value="<?= h($a['id']) ?>">
                    <button type="submit" class="danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Telegram</h2>
      <p class="meta" style="margin:0 0 12px">
        Get a bot token from <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a>
        (run <code>/newbot</code>). Then DM your bot once and visit
        <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code> to find your chat id.
      </p>

      <form class="tg" method="post" action="?action=save_telegram">
        <div>
          <label for="tg_token">Bot token</label>
          <input type="password" id="tg_token" name="telegram_bot_token"
                 autocomplete="off" spellcheck="false"
                 placeholder="<?= $settingsAll['telegram_bot_token'] !== '' ? h(mask_token($settingsAll['telegram_bot_token'])) : '123456:AAH…' ?>">
        </div>
        <div>
          <label for="tg_chat">Chat id</label>
          <input type="text" id="tg_chat" name="telegram_chat_id"
                 autocomplete="off" spellcheck="false"
                 value="<?= h((string)$settingsAll['telegram_chat_id']) ?>"
                 placeholder="987654321">
        </div>
        <div class="full row-actions">
          <button type="submit">Save</button>
          <?php if ($settingsAll['telegram_bot_token'] !== ''): ?>
            <label class="checkbox">
              <input type="checkbox" name="clear_token" value="1">
              Clear stored bot token
            </label>
          <?php endif; ?>
          <span style="flex:1"></span>
          <span class="meta">
            <?php if ($settingsAll['updated_at']): ?>
              last saved <?= h(format_time($settingsAll['updated_at'])) ?>
            <?php else: ?>
              not yet saved
            <?php endif; ?>
          </span>
        </div>
      </form>

      <form method="post" action="?action=test_telegram" style="margin-top:10px">
        <button type="submit" class="secondary">Send test message</button>
      </form>

      <p class="meta" style="margin:12px 0 0">
        Leave the bot token field blank when saving to keep the previously-stored value.
        Tick "Clear stored bot token" to wipe it.
      </p>
    </div>

    <p class="meta">
      Source: <a href="https://mempool.space" target="_blank" rel="noopener">mempool.space</a>.
    </p>
  </div>
</body>
</html>
