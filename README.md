# Cryptocurrency Exchange Rate API

A professional Symfony 7.3 application that provides real-time cryptocurrency exchange rates (EUR to BTC, ETH, LTC) with automatic data fetching from Binance API.

## Features

- **Real-time Data**: Fetches EUR/BTC, EUR/ETH, EUR/LTC rates from Binance API every 5 minutes
- **RESTful API**: Clean endpoints for last 24h and specific day rate queries
- **Production Ready**: Docker configuration, logging, monitoring, error handling
- **Type Safe**: Full PHP 8.2+ type declarations with validation
- **Performance Optimized**: Database indexing, caching, efficient queries
- **Secure**: Input validation, rate limiting, security headers

## Requirements

- Docker & Docker Compose
- Git
- Make (optional, for convenience commands)

## Project Structure

```
├── src/
│   ├── Controller/         # API controllers
│   │   └── RateController.php
│   ├── Entity/            # Database entities
│   │   └── Rate.php
│   ├── Repository/        # Data access layer
│   │   └── RateRepository.php
│   ├── Service/           # Business logic
│   │   ├── BinanceApiService.php
│   │   └── BinanceApiException.php
│   ├── Command/           # Console commands
│   │   └── FetchRatesCommand.php
│   ├── Dto/              # Data transfer objects
│   │   ├── RateQueryDto.php
│   │   └── RateResponseDto.php
│   ├── EventListener/     # Exception handling
│   │   └── ExceptionListener.php
│   └── Schedule.php       # Scheduler configuration
├── config/               # Symfony configuration
│   ├── packages/         # Bundle configurations
│   └── services.yaml     # Service definitions
├── docker/              # Docker configurations
│   ├── nginx/           # Nginx configuration
│   ├── php/             # PHP configurations
│   ├── mysql/           # MySQL configuration
│   ├── supervisor/      # Process management
│   └── cron/           # Cron jobs
├── migrations/          # Database migrations
├── public/              # Web entry point
├── compose.yaml         # Development Docker Compose
├── compose.override.yaml # Development overrides
├── compose.prod.yaml    # Production Docker Compose
├── Dockerfile           # Multi-stage Docker build
└── Makefile            # Development commands
```

## Quick Start

### Option 1: One-Command Setup

```bash
git clone <repository-url>
cd exchange
make quick-start
```

This will automatically:
- Create environment configuration
- Build Docker containers
- Start all services (MySQL, Redis, PHP, Nginx)
- Run database migrations
- Fetch initial cryptocurrency rate data

### Option 2: Step-by-Step Setup

```bash
# 1. Clone repository
git clone <repository-url>
cd exchange

# 2. Start development environment
make dev

# 3. Run database migrations
make migrate

# 4. Fetch initial data
make fetch-rates

# 5. Verify everything is working
curl "http://localhost:8080/api/rates/health"
```

### Option 3: Manual Docker Commands

```bash
# Start services
docker compose up -d

# Run migrations
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Fetch initial rates
docker compose exec app php bin/console app:fetch-rates

# Check logs
docker compose logs -f
```

## API Endpoints

**Base URL**: `http://localhost:8080`

### 1. Get Last 24 Hours Rates
```http
GET /api/rates/last-24h?pair=EUR/BTC
```

**Example Request:**
```bash
curl "http://localhost:8080/api/rates/last-24h?pair=EUR/BTC"
```

**Example Response:**
```json
{
  "pair": "EUR/BTC",
  "requested_period": "last-24h",
  "statistics": {
    "min_price": "45000.12345678",
    "max_price": "47500.87654321",
    "avg_price": "46250.50000000",
    "price_change": "1500.75309753",
    "price_change_percent": "3.24",
    "total_records": 288
  },
  "rates": [
    {
      "price": "45000.12345678",
      "recorded_at": "2024-01-15T00:00:00Z",
      "timestamp": 1705276800
    },
    {
      "price": "45123.45678901",
      "recorded_at": "2024-01-15T00:05:00Z",
      "timestamp": 1705277100
    }
  ],
  "generated_at": "2024-01-15T12:00:00Z",
  "count": 288
}
```

### 2. Get Specific Day Rates
```http
GET /api/rates/day?pair=EUR/ETH&date=2024-01-15
```

**Example Request:**
```bash
curl "http://localhost:8080/api/rates/day?pair=EUR/ETH&date=2024-01-15"
```

### 3. Health Check
```http
GET /api/rates/health
```

**Example Response:**
```json
{
  "status": "healthy",
  "timestamp": "2024-01-15T12:00:00Z",
  "database": "connected",
  "latest_rates": {
    "EUR/BTC": "2024-01-15 12:00:00",
    "EUR/ETH": "2024-01-15 12:00:00",
    "EUR/LTC": "2024-01-15 12:00:00"
  }
}
```

### Supported Cryptocurrency Pairs
- `EUR/BTC` - Euro to Bitcoin
- `EUR/ETH` - Euro to Ethereum  
- `EUR/LTC` - Euro to Litecoin

## Docker Operations

### Development Commands
```bash
make dev           # Start development environment
make dev-build     # Build and start from scratch
make logs          # View all service logs
make logs-app      # View application logs only
make logs-nginx    # View nginx logs only
make logs-db       # View database logs only
make shell         # Access app container shell
make shell-db      # Access database shell
make db-reset      # Reset database (development only)
```

### Production Commands
```bash
make prod          # Start production environment
make prod-build    # Build production from scratch
```

### Database Operations
```bash
make migrate       # Run database migrations
make migrate-dev   # Create and run new migration
make backup-db     # Backup database to file
make restore-db FILE=backup.sql  # Restore from backup
```

### Application Commands
```bash
make fetch-rates   # Manually fetch cryptocurrency rates
make cache-clear   # Clear application cache
make composer-install  # Install PHP dependencies
make test          # Run tests
```

### Monitoring Commands
```bash
make stats         # Show container resource usage
make ps           # Show running containers
```

### Cleanup Commands
```bash
make clean        # Clean up Docker resources
make clean-all    # Clean everything including images
```

## Application Startup Process

### Development Startup
1. **Docker Compose** reads `compose.yaml` and `compose.override.yaml`
2. **MySQL Container** starts with crypto_exchange database
3. **Redis Container** starts for caching
4. **PHP Application Container** builds with development dependencies
5. **Nginx Container** starts with PHP-FPM proxy configuration
6. **Database Migration** creates the `rates` table with indexes
7. **Scheduler** starts to fetch rates every 5 minutes
8. **API Endpoints** become available at `http://localhost:8080`

### Production Startup
1. **Docker Compose** reads `compose.prod.yaml`
2. **Multi-stage build** creates optimized production image
3. **Supervisor** manages PHP-FPM and background processes
4. **Traefik** handles SSL termination and load balancing
5. **Scheduler Container** runs cron jobs separately
6. **Health checks** ensure all services are operational

## Configuration

### Environment Variables

The application uses these key environment variables:

```bash
# Application
APP_ENV=dev                    # Environment (dev/prod)
APP_SECRET=your-secret-key     # Application secret

# Database
DATABASE_URL=mysql://app:password@database:3306/crypto_exchange?serverVersion=8.0&charset=utf8mb4

# Binance API (automatically configured)
BINANCE_API_URL=https://api.binance.com/api/v3
```

### Development Environment

For local development, the application automatically configures:
- **MySQL**: `localhost:3306` (app/password)
- **Redis**: `localhost:6379`
- **Application**: `localhost:8080`
- **Debug**: Xdebug enabled on port 9003

### Production Configuration

For production deployment, configure these additional variables:

```bash
# Security
APP_SECRET=strong-secret-key-here

# Database
MYSQL_ROOT_PASSWORD=secure-root-password
MYSQL_USER=app
MYSQL_PASSWORD=secure-app-password

# SSL/Domain
DOMAIN_NAME=your-domain.com
ACME_EMAIL=admin@your-domain.com
```

## How the Application Works

### 1. Data Collection (Every 5 Minutes)
```
Symfony Scheduler → FetchRatesCommand → BinanceApiService → MySQL Database
```

1. **Scheduler** triggers the rate fetch command every 5 minutes
2. **FetchRatesCommand** calls the Binance API service
3. **BinanceApiService** fetches current rates for EUR/BTC, EUR/ETH, EUR/LTC
4. **Rate entities** are created and stored in MySQL with timestamps
5. **Logs** record successful operations or errors

### 2. API Request Processing
```
Client → Nginx → PHP-FPM → RateController → RateRepository → MySQL
```

1. **Client** makes HTTP request to API endpoint
2. **Nginx** handles SSL, compression, rate limiting
3. **PHP-FPM** processes the request
4. **RateController** validates input using DTOs
5. **RateRepository** executes optimized database queries
6. **Response** is formatted and returned as JSON

### 3. Error Handling
```
Exception → ExceptionListener → Structured JSON Response
```

1. **Any exception** is caught by the global exception listener
2. **Context information** is logged for debugging
3. **Structured error response** is returned to client
4. **No sensitive data** is exposed in production

## 📈 Performance Optimizations

### Database
- **Indexes** on `(pair, recorded_at)` and `recorded_at` columns
- **MySQL 8.0** with optimized configuration for time-series data
- **Connection pooling** and query cache enabled
- **Batch operations** for rate storage

### Application
- **OPcache** enabled in production for PHP bytecode caching
- **Redis** for session storage and application cache
- **Doctrine ORM** with lazy loading and query optimization
- **Symfony** with production optimizations

### HTTP Layer
- **Nginx** with gzip compression and static file caching
- **Rate limiting** to prevent API abuse
- **Keep-alive connections** for better performance
- **Security headers** for protection

## 🔒 Security Features

### Input Validation
- **Symfony Validator** for all API parameters
- **Type declarations** throughout the codebase
- **SQL injection protection** via Doctrine ORM
- **XSS prevention** with proper data escaping

### HTTP Security
- **Rate limiting** (10 requests/second per IP)
- **Security headers** (X-Frame-Options, CSP, etc.)
- **HTTPS enforcement** in production
- **CORS configuration** for API access

### Error Handling
- **No sensitive data** in production error responses
- **Structured logging** for security monitoring
- **Exception tracking** for debugging
- **Health checks** for service monitoring

## Testing the Application

### Manual API Testing
```bash
# Test health endpoint
curl "http://localhost:8080/api/rates/health"

# Test last 24h rates
curl "http://localhost:8080/api/rates/last-24h?pair=EUR/BTC"

# Test specific day rates
curl "http://localhost:8080/api/rates/day?pair=EUR/ETH&date=2024-01-15"

# Test error handling
curl "http://localhost:8080/api/rates/last-24h?pair=INVALID"
```

### Rate Fetching
```bash
# Manual rate fetch
make fetch-rates

# Check if data was stored
make shell-db
mysql> SELECT * FROM rates ORDER BY recorded_at DESC LIMIT 10;
```

### Log Monitoring
```bash
# Watch application logs
make logs-app

# Watch specific log files
docker compose exec app tail -f var/log/api.log
docker compose exec app tail -f var/log/binance.log
docker compose exec app tail -f var/log/scheduler.log
```

## Production Deployment

### Using Make Commands
```bash
# Build and deploy production
make prod-build

# Run database migrations
make migrate

# Fetch initial data
make fetch-rates

# Monitor deployment
make stats
make logs
```

### Manual Production Deployment
```bash
# 1. Clone repository
git clone <repository-url>
cd exchange

# 2. Configure environment
cp .env.example .env.prod
nano .env.prod  # Set production values

# 3. Deploy with production compose
docker compose -f compose.prod.yaml --env-file .env.prod up -d

# 4. Run migrations
docker compose -f compose.prod.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction

# 5. Verify deployment
curl https://your-domain.com/api/rates/health
```

## 🔍 Troubleshooting

### Common Issues and Solutions

#### 1. Database Connection Failed
```bash
# Check database container
docker compose ps database
make logs-db

# Reset database
make db-reset

# Check environment variables
docker compose exec app env | grep DATABASE
```

#### 2. No Rate Data Available
```bash
# Check if rates are being fetched
make fetch-rates

# Check scheduler logs
docker compose exec app tail -f var/log/scheduler.log

# Check Binance API connectivity
docker compose exec app curl -I https://api.binance.com/api/v3/ping
```

#### 3. API Returns 500 Error
```bash
# Check application logs
make logs-app

# Check PHP-FPM processes
docker compose exec app ps aux | grep php-fpm

# Check Nginx configuration
docker compose exec nginx nginx -t
```

#### 4. Container Won't Start
```bash
# Check container status
docker compose ps

# View detailed logs
docker compose logs <service-name>

# Rebuild containers
make clean
make dev-build
```

#### 5. Performance Issues
```bash
# Monitor resource usage
make stats

# Check database slow queries
docker compose exec database tail -f /var/log/mysql/slow.log

# Check memory usage
docker compose exec app free -h
```

## 📞 Development & Contributing

### Local Development Setup
```bash
# 1. Fork and clone
git clone <your-fork>
cd exchange

# 2. Create feature branch
git checkout -b feature/your-feature

# 3. Start development environment
make dev-build

# 4. Make changes and test
make test
make fetch-rates

# 5. Check logs
make logs-app

# 6. Commit and push
git add .
git commit -m "feat: your feature description"
git push origin feature/your-feature
```

### Code Quality Standards
- **PHP 8.2+** with strict type declarations
- **PSR-12** coding standard
- **SOLID principles** for clean architecture
- **Comprehensive error handling** and logging
- **Input validation** for all external data
- **Unit and integration tests** for critical components

### Adding New Features

#### Adding New Cryptocurrency Pairs
1. Update `BinanceApiService::PAIR_MAPPING`
2. Update validation in `RateQueryDto`
3. Test with new endpoints
4. Update documentation

#### Adding New API Endpoints
1. Create method in `RateController`
2. Add route annotation
3. Create/update DTOs for validation
4. Add tests and documentation
