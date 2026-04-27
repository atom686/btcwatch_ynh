#!/bin/bash
# Shared helpers for btcwatch install/upgrade/remove/restore.

# Resolve the bundled PHP source folder from the package and rsync it into
# $install_dir, deleting stale upstream files but leaving runtime artefacts
# alone (config.php is regenerated each install/upgrade anyway).
btcwatch_sync_source() {
    local dest="$1"
    local src
    src="$(cd "$(dirname "${BASH_SOURCE[0]}")/../sources" && pwd)"

    if [ ! -d "$src" ]; then
        ynh_die --message="Bundled source not found at $src"
    fi

    mkdir -p "$dest"
    rsync -a --delete "$src"/ "$dest"/
}

# Seed (or refresh) settings.json in the data dir using install-time answers,
# without clobbering anything the user has already saved through the UI:
# we only fill keys that are currently empty.
btcwatch_seed_settings() {
    local data_dir="$1"
    local app_user="$2"
    local bot_token="$3"
    local chat_id="$4"

    local settings_file="$data_dir/settings.json"
    mkdir -p "$data_dir"

    python3 - "$settings_file" "$bot_token" "$chat_id" <<'PY'
import json, os, sys, datetime
path, token, chat = sys.argv[1], sys.argv[2], sys.argv[3]

data = {"telegram_bot_token": "", "telegram_chat_id": "", "updated_at": None}
if os.path.exists(path):
    try:
        with open(path) as f:
            data.update(json.load(f) or {})
    except (ValueError, OSError):
        pass

# Only fill empty fields — never overwrite UI-saved values.
if not data.get("telegram_bot_token") and token:
    data["telegram_bot_token"] = token
if not data.get("telegram_chat_id") and chat:
    data["telegram_chat_id"] = chat
if (token or chat) and not data.get("updated_at"):
    data["updated_at"] = datetime.datetime.utcnow().isoformat() + "Z"

tmp = path + ".tmp"
with open(tmp, "w") as f:
    json.dump(data, f, indent=2)
os.replace(tmp, path)
PY

    chown "$app_user:$app_user" "$settings_file"
    chmod 600 "$settings_file"
}
