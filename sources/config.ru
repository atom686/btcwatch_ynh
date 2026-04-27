require_relative "app"

# Kick off the background monitor when the web server boots.
BitcoinWatch::Monitor.instance.start!

run BitcoinWatch::App
