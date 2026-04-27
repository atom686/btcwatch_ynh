require "net/http"
require "json"
require "uri"

module BitcoinWatch
  # Minimal client for the mempool.space REST API.
  # Docs: https://mempool.space/docs/api/rest
  class MempoolClient
    class Error < StandardError; end

    DEFAULT_BASE_URL = "https://mempool.space".freeze

    def initialize(base_url: ENV["MEMPOOL_BASE_URL"] || DEFAULT_BASE_URL)
      @base_url = base_url.sub(%r{/+\z}, "")
    end

    # Returns total balance in satoshis for the given address.
    # Includes both confirmed (chain_stats) and unconfirmed (mempool_stats)
    # balances so a freshly-broadcast tx shows up immediately.
    #
    # balance = (chain_funded - chain_spent) + (mempool_funded - mempool_spent)
    def balance_sats(address)
      data = address_info(address)
      chain = data["chain_stats"] || {}
      mp    = data["mempool_stats"] || {}
      confirmed   = chain["funded_txo_sum"].to_i - chain["spent_txo_sum"].to_i
      unconfirmed = mp["funded_txo_sum"].to_i    - mp["spent_txo_sum"].to_i
      confirmed + unconfirmed
    end

    def address_info(address)
      uri = URI.parse("#{@base_url}/api/address/#{URI.encode_www_form_component(address)}")
      get_json(uri)
    end

    # Lightweight check — returns true if mempool.space recognises the address
    # (i.e. valid format). Used by the web form to reject typos before we
    # store them.
    def valid_address?(address)
      address_info(address)
      true
    rescue Error
      false
    end

    private

    def get_json(uri)
      http = Net::HTTP.new(uri.host, uri.port)
      http.use_ssl = (uri.scheme == "https")
      http.open_timeout = 10
      http.read_timeout = 20

      req = Net::HTTP::Get.new(uri.request_uri)
      req["User-Agent"] = "bitcoin-watch/0.1 (+https://mempool.space)"
      req["Accept"]     = "application/json"

      res = http.request(req)
      unless res.is_a?(Net::HTTPSuccess)
        raise Error, "mempool.space #{res.code} for #{uri}: #{res.body.to_s[0, 200]}"
      end

      JSON.parse(res.body)
    rescue JSON::ParserError => e
      raise Error, "mempool.space returned non-JSON: #{e.message}"
    rescue Net::OpenTimeout, Net::ReadTimeout => e
      raise Error, "mempool.space timeout: #{e.message}"
    end
  end
end
