require "json"
require "time"
require "fileutils"
require "securerandom"
require "monitor"

module BitcoinWatch
  # Tiny JSON-file backed store for watched addresses.
  # Thread-safe via a re-entrant Monitor — the polling thread and Sinatra
  # request threads all share the same instance.
  class Storage
    include MonitorMixin

    DEFAULT_PATH = File.expand_path("../data/addresses.json", __dir__)

    def self.instance
      @instance ||= new(ENV["ADDRESSES_PATH"] || DEFAULT_PATH)
    end

    def initialize(path)
      super() # MonitorMixin
      @path = path
      FileUtils.mkdir_p(File.dirname(@path))
      load!
    end

    # Returns an Array of address Hashes, each with string keys:
    # "id", "address", "label", "last_balance_sats",
    # "added_at", "last_checked_at", "last_change_at".
    def all
      synchronize { @addresses.map(&:dup) }
    end

    def find(id)
      synchronize { @addresses.find { |a| a["id"] == id }&.dup }
    end

    def add(address:, label: nil)
      synchronize do
        record = {
          "id" => SecureRandom.uuid,
          "address" => address.to_s.strip,
          "label" => label.to_s.strip.empty? ? nil : label.to_s.strip,
          "last_balance_sats" => nil,
          "added_at" => Time.now.utc.iso8601,
          "last_checked_at" => nil,
          "last_change_at" => nil
        }
        @addresses << record
        persist!
        record.dup
      end
    end

    def delete(id)
      synchronize do
        before = @addresses.size
        @addresses.reject! { |a| a["id"] == id }
        persist! if @addresses.size != before
        before != @addresses.size
      end
    end

    # Updates last_balance_sats / last_checked_at / last_change_at for one
    # address, persisting to disk. No-op if the id is no longer present
    # (e.g. user deleted it mid-poll).
    def update_balance(id, new_balance_sats, changed:)
      synchronize do
        record = @addresses.find { |a| a["id"] == id }
        return nil unless record

        now = Time.now.utc.iso8601
        record["last_balance_sats"] = new_balance_sats
        record["last_checked_at"] = now
        record["last_change_at"] = now if changed
        persist!
        record.dup
      end
    end

    private

    def load!
      synchronize do
        if File.exist?(@path)
          raw = File.read(@path)
          @addresses = raw.strip.empty? ? [] : JSON.parse(raw)
        else
          @addresses = []
          persist!
        end
      end
    end

    def persist!
      tmp = "#{@path}.tmp"
      File.write(tmp, JSON.pretty_generate(@addresses))
      File.rename(tmp, @path) # atomic on POSIX
    end
  end
end
