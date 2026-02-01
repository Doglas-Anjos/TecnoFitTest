# TecnoFit - Digital Account Platform

A digital account platform that allows users to withdraw funds via PIX from their available balance.

Built with **Hyperf PHP 3.1**, **MySQL 8**, **Swoole**, and **Nginx** for horizontal scaling.

## Features

- PIX withdrawal (immediate and scheduled)
- Horizontal scaling with Nginx load balancer
- Async email notifications
- Race condition protection (database row-level locking)
- Async logging with Swoole coroutines
- Request tracing with correlation IDs
- Test dashboard for stress testing

## Requirements

- Docker
- Docker Compose

## Quick Start

```bash
# Clone the repository
git clone <repository-url>
cd TecnoFit

# Copy environment file
cp .env.example .env

# Start with test dashboard (single instance)
docker-compose -f docker-compose-test.yml up -d --build

# Access
# API: http://localhost:9502
# Dashboard: http://localhost:3000
# Mailhog: http://localhost:8026
```

---

## Docker Compose Environments

| File | Description | Use Case |
|------|-------------|----------|
| `docker-compose.yml` | Production (single instance) | Simple deployment |
| `docker-compose-test.yml` | Test with dashboard (single instance) | Development & testing |
| `docker-compose-scale.yml` | Production (horizontal scaling) | High availability |
| `docker-compose-scale-test.yml` | Test with dashboard (horizontal scaling) | Load testing |

### Single Instance Architecture

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Client     │────►│   Hyperf     │────►│    MySQL     │
└──────────────┘     │  API + Cron  │     └──────────────┘
                     └──────────────┘
```

### Horizontal Scaling Architecture

```
                         ┌─────────────────┐
        Port 9501 ──────►│  Nginx (LB)     │
                         │  least_conn     │
                         └────────┬────────┘
                                  │
                   ┌──────────────┼──────────────┐
                   ▼              ▼              │
             ┌──────────┐   ┌──────────┐         │
             │ API-1    │   │ API-2    │         │
             │ CRON=off │   │ CRON=off │         │
             └────┬─────┘   └────┬─────┘         │
                  │              │               │
                  └──────┬───────┘               │
                         ▼                       │
                   ┌──────────┐                  │
                   │  MySQL   │◄─────────────────┤
                   └──────────┘                  │
                         ▲                       │
                         │              ┌────────┴───┐
                         └──────────────│   Cron     │
                                        │ CRON=true  │
                                        └────────────┘
```

**Key Points:**
- **Nginx** distributes requests using `least_conn` algorithm
- **API containers** handle HTTP requests only (CRON_ENABLED=false)
- **Cron container** processes scheduled withdrawals only (not exposed to load balancer)
- **Single database** ensures data consistency
- **X-Instance-ID** header shows which instance handled the request

---

## Services & Ports

### Single Instance Test (`docker-compose-test.yml`)

| Service | Port | Description |
|---------|------|-------------|
| Hyperf API | 9502 | API + Cron |
| Test Dashboard | 3000 | Web UI for testing |
| MySQL | 3308 | Database |
| Mailhog | 8026 | Email UI |

### Horizontal Scaling Test (`docker-compose-scale-test.yml`)

| Service | Port | Description |
|---------|------|-------------|
| Nginx (LB) | 9501 | Load balancer |
| API-1 | (internal) | API instance 1 |
| API-2 | (internal) | API instance 2 |
| Cron | (none) | Background jobs |
| Test Dashboard | 3000 | Web UI for testing |
| MySQL | 3309 | Database |
| Mailhog | 8025 | Email UI |

---

## Project Structure

```
TecnoFit/
├── app/
│   ├── Controller/
│   │   ├── AccountController.php         # POST /account/{id}/balance/withdraw
│   │   └── HealthController.php          # GET /health, /health/ready
│   ├── DTO/
│   │   ├── PixDataDTO.php
│   │   ├── WithdrawRequestDTO.php
│   │   └── WithdrawResponseDTO.php
│   ├── Exception/
│   │   ├── AccountNotFoundException.php   # 404
│   │   ├── AccountLockedException.php     # 423
│   │   ├── BusinessException.php          # Base exception
│   │   ├── InsufficientBalanceException.php # 422
│   │   ├── InvalidScheduleException.php   # 422
│   │   └── Handler/
│   │       ├── AppExceptionHandler.php
│   │       ├── BusinessExceptionHandler.php
│   │       ├── NotFoundExceptionHandler.php
│   │       └── ValidationExceptionHandler.php
│   ├── Log/
│   │   ├── AsyncStreamHandler.php         # Non-blocking stdout logging
│   │   └── AsyncFileHandler.php           # Non-blocking file logging
│   ├── Middleware/
│   │   └── RequestTracingMiddleware.php   # Correlation ID, request logging
│   ├── Model/
│   │   ├── Account.php
│   │   ├── AccountWithdraw.php
│   │   └── AccountWithdrawPix.php
│   ├── Request/
│   │   └── WithdrawRequest.php            # Validation rules
│   ├── Service/
│   │   ├── Notification/
│   │   │   ├── MailerFactory.php
│   │   │   └── NotificationService.php    # Async email sending
│   │   ├── Pix/
│   │   │   └── PixKeyValidator.php        # BACEN PIX key validation
│   │   └── Withdraw/
│   │       ├── Method/
│   │       │   ├── AbstractWithdrawMethod.php
│   │       │   ├── PixWithdrawMethod.php
│   │       │   ├── WithdrawMethodFactory.php
│   │       │   └── WithdrawMethodInterface.php
│   │       └── WithdrawService.php
│   └── Task/
│       └── ProcessScheduledWithdrawsTask.php
├── config/
│   └── autoload/
│       ├── logger.php                     # Async logging config
│       ├── middlewares.php                # Request tracing
│       └── ...
├── docker/
│   ├── hyperf/Dockerfile
│   ├── mysql/
│   │   ├── Dockerfile
│   │   └── init/
│   │       ├── 01-schema.sql
│   │       └── 02-seed.sql
│   ├── nginx/
│   │   ├── Dockerfile
│   │   └── nginx.conf                     # Load balancer config
│   ├── mailhog/Dockerfile
│   └── test-dashboard/                    # Node.js test UI
├── docker-compose.yml
├── docker-compose-test.yml
├── docker-compose-scale.yml
├── docker-compose-scale-test.yml
├── .env.example
└── README.md
```

---

## API Endpoints

### Health Check

| Endpoint | Description |
|----------|-------------|
| `GET /health` | Basic health check (service running) |
| `GET /health/ready` | Deep health check (includes database) |

**Response Headers:**
```
X-Correlation-ID: uuid    # Request tracing
X-Instance-ID: api-1      # Which instance handled request
```

### Withdraw

**POST** `/account/{accountId}/balance/withdraw`

#### Request Body

```json
{
  "method": "PIX",
  "pix": {
    "type": "email",
    "key": "user@email.com"
  },
  "amount": 150.75,
  "schedule": null
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| method | string | Yes | Withdrawal method (only "PIX" supported) |
| pix.type | string | Yes | PIX key type (email, cpf, cnpj, phone, random) |
| pix.key | string | Yes | PIX key value |
| amount | decimal | Yes | Amount to withdraw (> 0) |
| schedule | datetime/null | No | Schedule for future (null = immediate) |

#### PIX Key Validation (BACEN Specification)

| Type | Max Length | Format | Status |
|------|------------|--------|--------|
| email | 77 chars | Valid email | ✅ Implemented |
| cpf | 11 digits | Numbers only | ⏳ Not implemented |
| cnpj | 14 digits | Numbers only | ⏳ Not implemented |
| phone | 14 chars | +5511999999999 | ⏳ Not implemented |
| random | 36 chars | UUID v4 | ⏳ Not implemented |

#### Success Response (200)

```json
{
  "success": true,
  "message": "Saque realizado com sucesso",
  "data": {
    "id": "uuid",
    "account_id": "uuid",
    "method": "PIX",
    "amount": 150.75,
    "scheduled": false,
    "scheduled_for": null,
    "done": true,
    "pix": {
      "type": "email",
      "key": "user@email.com"
    },
    "created_at": "2026-01-30 03:38:22"
  }
}
```

#### Error Responses

| Code | Description |
|------|-------------|
| 404 | Account not found |
| 422 | Validation error / Insufficient balance / Invalid PIX key |
| 423 | Account locked (concurrent operation) |
| 500 | Internal server error |

---

## Business Rules

1. **Immediate withdrawals** - Processed right away, balance validated immediately
2. **Scheduled withdrawals** - Balance validated at execution time by cron
3. **Race condition protection** - Database row-level locking (`SELECT FOR UPDATE`)
4. **Email notification** - Sent asynchronously after successful withdrawal
5. **Balance cannot go negative**
6. **Schedule cannot be in the past**

---

## Test Dashboard

The test dashboard provides a web UI for testing the API:

- **Edge Case Tests** - Pre-defined scenarios (success, insufficient balance, etc.)
- **Stress Test** - Configurable concurrent requests
- **Request/Response Viewer** - See full API responses
- **Database Auto-Population** - Creates test accounts on startup

Access at: `http://localhost:3000`

---

## Observability

### Logging

Async logging using Swoole coroutines (non-blocking):

```env
LOG_LEVEL=INFO              # DEBUG, INFO, WARNING, ERROR
LOG_FILE_ENABLED=false      # Enable JSON file logging
```

**Log Output:**
```
[15:30:00] INFO: Request received {"cid":"abc-123","method":"POST","path":"/account/.../withdraw"}
[15:30:00] INFO: Withdraw request validated {"correlation_id":"abc-123","account_id":"..."}
[15:30:00] INFO: Withdrawal record created {"withdraw_id":"...","amount":100}
[15:30:00] INFO: Response {"cid":"abc-123","status":200,"ms":85}
```

### Request Tracing

Every request receives a correlation ID for tracing:

```bash
# Pass your own correlation ID
curl -H "X-Correlation-ID: my-trace-id" http://localhost:9501/health

# Response includes the ID
# X-Correlation-ID: my-trace-id
# X-Instance-ID: api-1
```

---

## Commands

### Start Environments

```bash
# Single instance with test dashboard
docker-compose -f docker-compose-test.yml up -d --build

# Horizontal scaling with test dashboard
docker-compose -f docker-compose-scale-test.yml up -d --build

# Production (single instance)
docker-compose up -d --build

# Production (horizontal scaling)
docker-compose -f docker-compose-scale.yml up -d --build
```

### View Logs

```bash
# All containers
docker-compose -f docker-compose-scale-test.yml logs -f

# Specific containers
docker-compose -f docker-compose-scale-test.yml logs -f hyperf-api-1 hyperf-api-2

# Cron container
docker-compose -f docker-compose-scale-test.yml logs -f hyperf-cron
```

### Stop & Cleanup

```bash
# Stop containers
docker-compose -f docker-compose-scale-test.yml down

# Stop and remove volumes (reset database)
docker-compose -f docker-compose-scale-test.yml down -v
```

### Test Load Balancing

```bash
# Run multiple times - check X-Instance-ID header
curl -i http://localhost:9501/health

# Or watch logs while using test dashboard stress test
docker-compose -f docker-compose-scale-test.yml logs -f hyperf-api-1 hyperf-api-2
```

---

## Database

### Tables

**account**
| Column | Type | Description |
|--------|------|-------------|
| id | CHAR(36) | UUID primary key |
| name | VARCHAR(255) | Account holder name |
| balance | DECIMAL(15,2) | Available balance |
| locked | BOOLEAN | Account locked for operation |
| created_at | TIMESTAMP | Creation date |
| updated_at | TIMESTAMP | Last update |

**account_withdraw**
| Column | Type | Description |
|--------|------|-------------|
| id | CHAR(36) | UUID primary key |
| account_id | CHAR(36) | FK to account |
| method | VARCHAR(50) | Withdrawal method (PIX) |
| amount | DECIMAL(15,2) | Withdrawal amount |
| scheduled | BOOLEAN | Is scheduled withdrawal |
| scheduled_for | DATETIME | Scheduled execution time |
| done | BOOLEAN | Withdrawal completed |
| error | BOOLEAN | Error occurred |
| error_reason | TEXT | Error description |

**account_withdraw_pix**
| Column | Type | Description |
|--------|------|-------------|
| id | CHAR(36) | UUID primary key |
| account_withdraw_id | CHAR(36) | FK to account_withdraw |
| type | VARCHAR(50) | PIX key type |
| key | VARCHAR(77) | PIX key value |

### Test Accounts (Seeded)

| ID | Name | Balance |
|----|------|---------|
| 550e8400-e29b-41d4-a716-446655440001 | João Silva | R$ 1.500,00 |
| 550e8400-e29b-41d4-a716-446655440002 | Maria Santos | R$ 3.200,50 |
| 550e8400-e29b-41d4-a716-446655440003 | Pedro Oliveira | R$ 500,00 |

---

## Environment Variables

See `.env.example` for all available variables:

```env
# Application
APP_NAME=TecnoFit
APP_ENV=dev
APP_TIMEZONE=America/Sao_Paulo
LOG_LEVEL=INFO

# Database
DB_HOST=mysql
DB_DATABASE=hyperf
DB_USERNAME=hyperf
DB_PASSWORD=hyperf

# Mail (Mailtrap)
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password

# Scaling
NGINX_PORT=9501
SCALE_DB_PORT=3307
```

---

## Architecture

### Design Patterns

- **Strategy Pattern** - Withdrawal methods (PIX, future TED, Boleto)
- **Factory Pattern** - WithdrawMethodFactory, MailerFactory
- **DTO Pattern** - Request/Response data transfer objects
- **Service Layer** - Business logic separation

### Key Components

| Component | Responsibility |
|-----------|----------------|
| `WithdrawService` | Main business logic, transactions |
| `PixKeyValidator` | BACEN-compliant PIX key validation |
| `NotificationService` | Async email sending via Swoole |
| `RequestTracingMiddleware` | Correlation IDs, request logging |
| `AsyncStreamHandler` | Non-blocking log output |

---

## Performance Optimizations

| Optimization | Impact |
|--------------|--------|
| Async email sending | Response time: 800ms → 60ms |
| Async logging | Non-blocking I/O |
| Database row locking | Safe concurrent access |
| Eager loading | Reduced database queries |
| Connection pooling | Swoole coroutine-aware |

---

## License

MIT
