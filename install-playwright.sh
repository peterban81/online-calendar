#!/usr/bin/env bash
set -e
cd "$(dirname "$0")"
npm install
npx playwright install chromium
echo "Installazione completata."
