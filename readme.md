Develop a web application using Symfony and PHP 8.x that provides an API for cryptocurrency exchange
rates from EUR to BTC, ETH, and LTC.

Data source: Binance API (https://developers.binance.com/docs/binance-spot-api-docs/rest-
api/market-data-endpoints#symbol-price-ticker).

Functionality:
• Periodic update: Store rates (EUR/BTC, EUR/ETH, EUR/LTC) in MySQL every 5 minutes using Symfony
Scheduler or cron.
• API endpoints (JSON responses for charts):
a. /api/rates/last-24h?pair=EUR/BTC — Rates for the last 24 hours (every 5 minutes).
b. /api/rates/day?pair=EUR/BTC&date=YYYY-MM-DD — Rates for the specified day (every 5 minutes).
Storage: MySQL
Production readiness:
• Code: Clean, with type declarations, validation, error handling, and logging.
• Docker: Optional.
Upload to GitHub with a README: installation, startup, and example requests. The code must be ready
for a code review.