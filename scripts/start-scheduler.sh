#!/bin/bash

# Simple scheduler script for development
# Runs the fetch-rates command every 5 minutes

echo "Starting cryptocurrency rate fetcher (every 5 minutes)..."

while true; do
    echo "[$(date)] Fetching cryptocurrency rates..."
    php /var/www/html/bin/console app:fetch-rates >> /var/log/scheduler.log 2>&1
    
    if [ $? -eq 0 ]; then
        echo "[$(date)] ✓ Rates fetched successfully"
    else
        echo "[$(date)] ✗ Failed to fetch rates"
    fi
    
    # Wait 5 minutes (300 seconds)
    sleep 300
done
