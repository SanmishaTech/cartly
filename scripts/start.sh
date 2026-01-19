#!/bin/bash

# Cartly Development Server Starter
# Adds host entries to /etc/hosts and starts the PHP development server

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Read APP_DOMAIN from .env
if [ -f .env ]; then
    APP_DOMAIN=$(grep "^APP_DOMAIN=" .env | cut -d '=' -f 2)
else
    APP_DOMAIN="cartly.test"
fi

APP_DOMAIN=${APP_DOMAIN:-cartly.test}
HOST="127.0.0.1"
SHOPS=("demo1" "demo2")
HOSTS_FILE="/etc/hosts"

echo -e "${GREEN}=== Cartly Development Server ===${NC}"
echo "Domain: $APP_DOMAIN"
echo ""

# Function to check if an entry exists in /etc/hosts
entry_exists() {
    grep -q "^$HOST\s.*$1\.$APP_DOMAIN" "$HOSTS_FILE" 2>/dev/null
    return $?
}

# Function to add entry to /etc/hosts
add_host_entry() {
    local domain="$1"
    if ! entry_exists "$domain"; then
        echo -e "${YELLOW}Adding $domain.$APP_DOMAIN to /etc/hosts...${NC}"
        echo "$HOST $domain.$APP_DOMAIN" | sudo tee -a "$HOSTS_FILE" > /dev/null
        echo -e "${GREEN}✓ Added $domain.$APP_DOMAIN${NC}"
    else
        echo -e "${GREEN}✓ $domain.$APP_DOMAIN already in /etc/hosts${NC}"
    fi
}

# Add all shop domains
echo -e "${YELLOW}Checking /etc/hosts entries...${NC}"
for shop in "${SHOPS[@]}"; do
    add_host_entry "$shop"
done

echo ""
echo -e "${GREEN}All host entries verified.${NC}"
echo ""

# Start the server
echo -e "${YELLOW}Starting PHP development server on http://127.0.0.1:8000${NC}"
echo ""
echo "Accessible at:"
for shop in "${SHOPS[@]}"; do
    echo "  • http://$shop.$APP_DOMAIN:8000"
done
echo ""
echo "Press Ctrl+C to stop the server."
echo ""

cd "$(dirname "$0")/.."
php -S 127.0.0.1:8000 -t public
