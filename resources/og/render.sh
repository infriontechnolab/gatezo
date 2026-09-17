#!/usr/bin/env bash
# Renders the Open Graph card to public/og.png (1200x630). Needs Chrome and network for the font.
set -e
cd "$(dirname "$0")"
google-chrome --headless=new --no-sandbox --disable-gpu --hide-scrollbars --window-size=1200,630 \
  --virtual-time-budget=6000 --screenshot=../../public/og.png "file://$PWD/index.html" 2>/dev/null
echo "wrote public/og.png ($(stat -c%s ../../public/og.png) bytes)"
