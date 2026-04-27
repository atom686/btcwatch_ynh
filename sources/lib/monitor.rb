require "singleton"
require "time"
require_relative "storage"
require_relative "mempool_client"
require_relative "telegram_notifier"

module BitcoinWatch
  # Background poller. Wakes every POLL_INTERVAL_SECONDS, checks every
  # tracked address against mempool.space, and fires a Telegram message
  # whenever the balance differs from the last value we stored.
  #
  # The very first observation for a brand-new address is treated as a
  # baseline (no notification), so users don't get spammed when adding
  # an address that already has funds.
  class Monitor
    include Singleton

    DEFAULT_INTERVAL = 3600 # 1 hour

    def initialize
      @interval = (ENV["POLL_INTERVAL_SECONDS"] || DEFAULT_INTERVAL).to_i
      @interval = DEFAULT_INTERVAL if @interval <= 0
      @storage  = Storage.instance
      @mempool  = MempoolClient.new
      @telegram = TelegramNotifier.new
      @thread   = nil
      @lock     = Mutex.new
    end

    attr_reader :interval

    def start!
      @lock.synchronize do
        return if @thread&.alive?
        @thread = Thread.new { run_loop }
        @thread.name = "bitcoin-watch-monitor"
      end
      log "started — polling every #{@interval}s"
      unless @telegram.configured?
        log "WARNING: Telegram credentials not configured; balance changes will be logged but not sent"
      end
    end

    def stop!
      @lock.synchronize do
        @thread&.kill
        @thread = nil
      end
    end

    # Run a single poll cycle synchronously. Useful for the "Check now"
    # button in the UI and for tests.
    def check_now!
      @storage.all.each { |record| check_one(record) }
    end

    # Check a single address by id (used by "Check now" on a row).
    # Returns the updated record or nil if not found.
    def check_one_by_id(id)
      record = @storage.find(id)
      return nil unless record
      check_one(record)
      @storage.find(id)
    end

    private

    def run_loop
      loop do
        begin
          check_now!
        rescue => e
          log "poll cycle error: #{e.class}: #{e.message}"
        end
        sleep @interval
      end
    end

    def check_one(record)
      address = record["address"]
      previous = record["last_balance_sats"]
      current  = @mempool.balance_sats(address)

      if previous.nil?
        # First observation: just baseline.
        @storage.update_balance(record["id"], current, changed: false)
        log "baseline #{format_addr(address)} = #{current} sats"
        return
      end

      if current != previous
        @storage.update_balance(record["id"], current, changed: true)
        notify_change(record, previous, current)
      else
        @storage.update_balance(record["id"], current, changed: false)
      end
    rescue MempoolClient::Error => e
      log "mempool error for #{format_addr(record['address'])}: #{e.message}"
    end

    def notify_change(record, previous, current)
      delta = current - previous
      sign  = delta.positive? ? "+" : ""
      label = record["label"] ? " (#{record['label']})" : ""
      address = record["address"]

      text = <<~MSG
        <b>Bitcoin balance changed</b>#{label}
        <code>#{address}</code>

        Previous: <b>#{format_btc(previous)}</b>
        Current:  <b>#{format_btc(current)}</b>
        Delta:    <b>#{sign}#{format_btc(delta)}</b>

        <a href="https://mempool.space/address/#{address}">View on mempool.space</a>
      MSG

      @telegram.send_message(text)
      log "notified change #{format_addr(address)} #{previous} -> #{current} (#{sign}#{delta})"
    rescue TelegramNotifier::Error => e
      log "telegram send failed: #{e.message}"
    end

    def format_btc(sats)
      btc = sats.to_f / 100_000_000
      "#{format('%.8f', btc)} BTC (#{sats} sats)"
    end

    def format_addr(address)
      address.length > 16 ? "#{address[0, 8]}…#{address[-6, 6]}" : address
    end

    def log(msg)
      warn "[monitor #{Time.now.utc.iso8601}] #{msg}"
    end
  end
end
