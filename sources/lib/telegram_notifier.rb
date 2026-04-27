require "net/http"
require "json"
require "uri"

module BitcoinWatch
  # Sends notifications to a Telegram chat via the Bot API.
  # Requires TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID env vars.
  class TelegramNotifier
    class Error < StandardError; end

    def initialize(token: ENV["TELEGRAM_BOT_TOKEN"], chat_id: ENV["TELEGRAM_CHAT_ID"])
      @token   = token
      @chat_id = chat_id
    end

    def configured?
      !@token.to_s.empty? && !@chat_id.to_s.empty?
    end

    # Sends `text` to the configured chat. Returns true on success.
    # Raises Error on transport / API failure. Silently no-ops (returns false)
    # if credentials aren't configured, so the monitor can keep running while
    # the user finishes setup.
    def send_message(text)
      unless configured?
        warn "[telegram] skipped — TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID not set"
        return false
      end

      uri = URI.parse("https://api.telegram.org/bot#{@token}/sendMessage")
      http = Net::HTTP.new(uri.host, uri.port)
      http.use_ssl = true
      http.open_timeout = 10
      http.read_timeout = 20

      req = Net::HTTP::Post.new(uri.request_uri, "Content-Type" => "application/json")
      req.body = JSON.generate(
        chat_id:    @chat_id,
        text:       text,
        parse_mode: "HTML",
        disable_web_page_preview: true
      )

      res = http.request(req)
      unless res.is_a?(Net::HTTPSuccess)
        raise Error, "Telegram API #{res.code}: #{res.body.to_s[0, 300]}"
      end
      true
    end
  end
end
