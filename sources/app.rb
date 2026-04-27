require "sinatra/base"
require "time"

begin
  require "dotenv/load"
rescue LoadError
  # dotenv is optional — env can also come from the shell.
end

require_relative "lib/storage"
require_relative "lib/mempool_client"
require_relative "lib/telegram_notifier"
require_relative "lib/monitor"

module BitcoinWatch
  class App < Sinatra::Base
    set :root, File.expand_path(__dir__)
    set :views, File.expand_path("views", __dir__)
    set :public_folder, File.expand_path("public", __dir__)
    set :bind, "0.0.0.0"
    set :port, (ENV["PORT"] || 4567).to_i
    set :show_exceptions, :after_handler

    helpers do
      def format_sats(sats)
        return "—" if sats.nil?
        btc = sats.to_f / 100_000_000
        format("%.8f BTC", btc)
      end

      def format_time(iso_string)
        return "never" if iso_string.nil? || iso_string.to_s.empty?
        t = Time.parse(iso_string)
        # Show in local time, with relative hint
        t.strftime("%Y-%m-%d %H:%M UTC")
      rescue ArgumentError
        iso_string
      end

      def h(text)
        Rack::Utils.escape_html(text.to_s)
      end
    end

    get "/" do
      @addresses        = Storage.instance.all
      @flash            = session_flash
      @poll_interval    = Monitor.instance.interval
      @telegram_ok      = TelegramNotifier.new.configured?
      erb :index
    end

    post "/addresses" do
      address = params[:address].to_s.strip
      label   = params[:label].to_s.strip

      if address.empty?
        flash!("error", "Address is required.")
        redirect "/"
      end

      if Storage.instance.all.any? { |a| a["address"].casecmp?(address) }
        flash!("error", "That address is already being monitored.")
        redirect "/"
      end

      unless MempoolClient.new.valid_address?(address)
        flash!("error", "mempool.space did not recognise that address — double-check it.")
        redirect "/"
      end

      record = Storage.instance.add(address: address, label: label)
      # Kick a baseline check immediately so the row shows a balance right away.
      Thread.new { Monitor.instance.check_one_by_id(record["id"]) }
      flash!("success", "Now monitoring #{address}.")
      redirect "/"
    end

    post "/addresses/:id/delete" do
      if Storage.instance.delete(params[:id])
        flash!("success", "Address removed.")
      else
        flash!("error", "Address not found.")
      end
      redirect "/"
    end

    post "/addresses/:id/check" do
      record = Monitor.instance.check_one_by_id(params[:id])
      if record
        flash!("success", "Checked #{record['address']} — balance #{format_sats(record['last_balance_sats'])}.")
      else
        flash!("error", "Address not found.")
      end
      redirect "/"
    end

    post "/check_all" do
      Thread.new { Monitor.instance.check_now! }
      flash!("success", "Started a check of all addresses — refresh in a few seconds.")
      redirect "/"
    end

    error MempoolClient::Error do
      flash!("error", "mempool.space error: #{env['sinatra.error'].message}")
      redirect "/"
    end

    # --- tiny session-less flash, stored in a single cookie ---------------
    private

    def flash!(kind, message)
      response.set_cookie("flash",
        value:    "#{kind}|#{message}",
        path:     "/",
        max_age:  10,
        same_site: :lax,
        http_only: true
      )
    end

    def session_flash
      raw = request.cookies["flash"]
      return nil unless raw
      response.delete_cookie("flash", path: "/")
      kind, _, msg = raw.partition("|")
      { kind: kind, message: msg }
    end
  end
end
