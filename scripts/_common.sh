#!/bin/bash
# Shared helpers for btcwatch install/upgrade/remove/restore.

# Copy bundled Sinatra source from the YunoHost package's `sources/` folder
# into $install_dir. Replaces the contents but preserves any user files we
# explicitly want to keep (the .env and the data dir live elsewhere).
btcwatch_sync_source() {
    local dest="$1"
    # Scripts run from the package's scripts/ dir, so ../sources is the bundle.
    local src
    src="$(cd "$(dirname "${BASH_SOURCE[0]}")/../sources" && pwd)"

    if [ ! -d "$src" ]; then
        ynh_die --message="Bundled source not found at $src"
    fi

    mkdir -p "$dest"
    # -a preserves perms/timestamps; --delete cleans removed upstream files,
    # but we exclude the runtime-managed bits.
    rsync -a --delete \
        --exclude=".env" \
        --exclude="data/addresses.json" \
        --exclude="vendor/" \
        --exclude=".bundle/" \
        "$src"/ "$dest"/
}

# Install gems into $install_dir/vendor/bundle as the app system user.
# We deliberately avoid --deployment (no Gemfile.lock is shipped) and rely
# on bundler config + env vars instead. The .env file written at install
# time pins BUNDLE_PATH/GEM_HOME for the systemd unit.
btcwatch_bundle_install() {
    local app_user="$1"
    local install_dir="$2"

    local -a runas=(sudo -u "$app_user" -H
        env "BUNDLE_PATH=$install_dir/vendor/bundle"
            "BUNDLE_GEMFILE=$install_dir/Gemfile"
            "GEM_HOME=$install_dir/vendor/bundle"
            "BUNDLE_WITHOUT=development:test")

    pushd "$install_dir" >/dev/null
    "${runas[@]}" bundle config set --local path        "vendor/bundle"
    "${runas[@]}" bundle config set --local without     "development test"
    "${runas[@]}" bundle install --jobs 2
    popd >/dev/null
}
