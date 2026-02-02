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
# Clone and configure
git clone <repository-url>
cd TecnoFit
cp .env.example .env

# Build and start (using build script)
./build.sh        # Linux/Mac
build.bat         # Windows

# Or manually
docker compose -f docker-compose-scale.yml build
docker compose -f docker-compose-scale.yml up -d
```

## Environments

| File | Description | API Port | Dashboard |
|------|-------------|----------|-----------|
| `docker-compose.yml` | Single instance | 9501 | - |
| `docker-compose-test.yml` | Single + dashboard | 9502 | 3000 |
| `docker-compose-scale.yml` | Horizontal scaling | 9501 | - |
| `docker-compose-scale-test.yml` | Scaling + dashboard | 9501 | 3000 |

```bash
# Start an environment
docker compose -f <file> build
docker compose -f <file> up -d

# Stop (always stop before switching environments)
docker compose -f <file> down

# Stop and reset database
docker compose -f <file> down -v

# View logs
docker compose -f <file> logs -f
```

## Architecture

### Single Instance
```
Client ──► Hyperf (API + Cron) ──► MySQL
```

### Horizontal Scaling
```
                    ┌─────────────────┐
     Port 9501 ────►│  Nginx (LB)     │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              │
        ┌──────────┐   ┌──────────┐         │
        │ API-1    │   │ API-2    │         │
        │ CRON=off │   │ CRON=off │         │
        └────┬─────┘   └────┬─────┘         │
             └──────┬───────┘               │
                    ▼                       │
              ┌──────────┐                  │
              │  MySQL   │◄─────────────────┤
              └──────────┘                  │
                    ▲              ┌────────┴───┐
                    └──────────────│   Cron     │
                                   └────────────┘
```

- **Nginx** distributes requests using `least_conn` algorithm
- **API containers** handle HTTP requests only (CRON_ENABLED=false)
- **Cron container** processes scheduled withdrawals only
- **X-Instance-ID** header shows which instance handled the request

---

## API Endpoints

### Health Check

| Endpoint | Description |
|----------|-------------|
| `GET /health` | Basic health check |
| `GET /health/ready` | Deep health check (includes database) |

### Withdraw

**POST** `/account/{accountId}/balance/withdraw`

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

## Database Schema

### Tables

**account**
| Column | Type | Description |
|--------|------|-------------|
| id | CHAR(36) | UUID primary key |
| name | VARCHAR(255) | Account holder name |
| balance | DECIMAL(15,2) | Available balance |
| locked | BOOLEAN | Race condition protection |
| created_at, updated_at | TIMESTAMP | Audit trail |

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
| 550e8400-e29b-41d4-a716-446655440001 | Joao Silva | R$ 1.500,00 |
| 550e8400-e29b-41d4-a716-446655440002 | Maria Santos | R$ 3.200,50 |
| 550e8400-e29b-41d4-a716-446655440003 | Pedro Oliveira | R$ 500,00 |

---

## Project Structure

```
TecnoFit/
├── app/
│   ├── Controller/          # API endpoints
│   ├── DTO/                  # Data transfer objects
│   ├── Exception/            # Custom exceptions & handlers
│   ├── Log/                  # Async logging handlers
│   ├── Middleware/           # Request tracing
│   ├── Model/                # Eloquent models
│   ├── Request/              # Validation rules
│   ├── Service/              # Business logic
│   └── Task/                 # Cron tasks
├── config/                   # Hyperf configuration
├── docker/                   # Docker files
│   ├── hyperf/               # PHP container
│   ├── mysql/                # Database + seeds
│   ├── nginx/                # Load balancer
│   └── test-dashboard/       # Test UI
├── build.sh / build.bat      # Build scripts
└── docker-compose*.yml       # Environment configs
```

---

## License

MIT
