#!/bin/bash

# Generate /etc/hosts entries for Cartly shops
# Note: /etc/hosts does not support wildcards. Use this script to add common shop domains.
# Usage: sudo bash scripts/generate_hosts.sh

APP_DOMAIN="${APP_DOMAIN:-cartly.test}"
HOST="127.0.0.1"

# Common shop subdomains for testing
SHOPS=("demo1" "demo2" "admin" "www")

echo "# Cartly $APP_DOMAIN - Add these lines to /etc/hosts:"
echo ""

for shop in "${SHOPS[@]}"; do
    echo "$HOST $shop.$APP_DOMAIN"
done

echo ""
echo "# To add automatically (requires sudo):"
echo "# sudo bash scripts/generate_hosts.sh | grep '^127' | sudo tee -a /etc/hosts"
