# TecnoFit - Digital Account Platform

A digital account platform that allows users to withdraw funds via PIX from their available balance.

Built with **Hyperf PHP 3.1**, **MySQL 8**, and **Mailhog** for email testing.

## Requirements

- Docker
- Docker Compose

## Project Structure

```
TecnoFit/
├── app/
│   ├── Controller/
│   │   ├── AbstractController.php
│   │   ├── AccountController.php          # POST /account/{id}/balance/withdraw
│   │   └── IndexController.php
│   ├── DTO/
│   │   ├── PixDataDTO.php                  # PIX key data transfer object
│   │   ├── WithdrawRequestDTO.php          # Withdraw request data
│   │   └── WithdrawResponseDTO.php         # Withdraw response data
│   ├── Exception/
│   │   ├── AccountNotFoundException.php    # 404 - Account not found
│   │   ├── BusinessException.php           # Base business exception
│   │   ├── InsufficientBalanceException.php # 422 - Insufficient balance
│   │   ├── InvalidScheduleException.php    # 422 - Schedule in the past
│   │   └── Handler/
│   │       ├── AppExceptionHandler.php
│   │       ├── BusinessExceptionHandler.php
│   │       └── ValidationExceptionHandler.php
│   ├── Listener/
│   ├── Model/
│   │   ├── Account.php                     # Digital account model
│   │   ├── AccountWithdraw.php             # Withdrawal record model
│   │   ├── AccountWithdrawPix.php          # PIX details model
│   │   └── Model.php
│   ├── Request/
│   │   └── WithdrawRequest.php             # Request validation
│   ├── Service/
│   │   ├── Notification/
│   │   │   ├── MailerFactory.php           # Symfony Mailer factory
│   │   │   └── NotificationService.php     # Email notifications
│   │   └── Withdraw/
│   │       ├── Method/
│   │       │   ├── AbstractWithdrawMethod.php
│   │       │   ├── PixWithdrawMethod.php   # PIX implementation
│   │       │   ├── WithdrawMethodFactory.php
│   │       │   └── WithdrawMethodInterface.php
│   │       └── WithdrawService.php         # Main business logic
│   └── Task/
│       └── ProcessScheduledWithdrawsTask.php # Cron job (every minute)
├── config/
│   ├── autoload/
│   │   ├── crontab.php                     # Crontab configuration
│   │   ├── databases.php
│   │   ├── dependencies.php
│   │   ├── exceptions.php
│   │   ├── mail.php                        # Mail configuration
│   │   └── processes.php                   # Cron process
│   └── routes.php
├── docker/
│   ├── hyperf/
│   │   └── Dockerfile
│   ├── mailhog/
│   │   └── Dockerfile
│   └── mysql/
│       ├── Dockerfile
│       └── init/
│           ├── 01-schema.sql               # Database tables
│           └── 02-seed.sql                 # Test data
├── docker-compose.yml
├── build.bat                               # Windows build script
├── build.sh                                # Linux/Mac build script
└── README.md
```

## Services

| Service | Port(s) | Description |
|---------|---------|-------------|
| hyperf | 9501 | PHP Hyperf 3.1 API Server |
| mysql | 3306 | MySQL 8.0 Database |
| mailhog | 1025, 8025 | SMTP server and Web UI |

## Getting Started

### 1. Clone the repository

```bash
git clone <repository-url>
cd TecnoFit
```

### 2. Build and start containers

**Option A: Using build script (Recommended)**

On Windows:
```cmd
build.bat
```

On Linux/Mac:
```bash
chmod +x build.sh
sudo ./build.sh
```

**Option B: Manual build**

```bash
docker-compose build mysql
docker-compose build mailhog
docker-compose build hyperf
docker-compose up -d
```

### 3. Access Points

| Service | URL |
|---------|-----|
| Hyperf API | http://localhost:9501 |
| Mailhog UI | http://localhost:8025 |
| MySQL | localhost:3306 |

## Database

### Tables

**account**
| Column | Type | Description |
|--------|------|-------------|
| id | CHAR(36) | UUID primary key |
| name | VARCHAR(255) | Account holder name |
| balance | DECIMAL(15,2) | Available balance |
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
| account_withdraw_id | CHAR(36) | FK to account_withdraw |
| type | VARCHAR(50) | PIX key type (email) |
| key | VARCHAR(255) | PIX key value |

### Test Accounts

| ID | Name | Balance |
|----|------|---------|
| 550e8400-e29b-41d4-a716-446655440001 | João Silva | R$ 1.500,00 |
| 550e8400-e29b-41d4-a716-446655440002 | Maria Santos | R$ 3.200,50 |
| 550e8400-e29b-41d4-a716-446655440003 | Pedro Oliveira | R$ 500,00 |

## API

### Withdraw Endpoint

**POST** `/account/{accountId}/balance/withdraw`

#### Request Body

```json
{
  "method": "PIX",
  "pix": {
    "type": "email",
    "key": "fulano@email.com"
  },
  "amount": 150.75,
  "schedule": null
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| method | string | Yes | Withdrawal method (only "PIX" supported) |
| pix.type | string | Yes | PIX key type (only "email" supported) |
| pix.key | string | Yes | Valid email address |
| amount | decimal | Yes | Amount to withdraw (> 0) |
| schedule | datetime/null | No | Schedule for future (null = immediate) |

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
      "key": "fulano@email.com"
    },
    "created_at": "2026-01-30 03:38:22"
  }
}
```

#### Error Responses

**Validation Error (422)**
```json
{
  "success": false,
  "message": "Erro de validação",
  "errors": {
    "amount": ["O valor do saque deve ser maior que zero."]
  }
}
```

**Insufficient Balance (422)**
```json
{
  "success": false,
  "message": "Saldo insuficiente. Valor solicitado: R$ 1000.00, Saldo disponível: R$ 500.00",
  "code": 422
}
```

**Account Not Found (404)**
```json
{
  "success": false,
  "message": "Conta não encontrada: invalid-id",
  "code": 404
}
```

### Examples

**Immediate Withdrawal**
```bash
curl -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440001/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{
    "method": "PIX",
    "pix": {"type": "email", "key": "user@email.com"},
    "amount": 100.00,
    "schedule": null
  }'
```

**Scheduled Withdrawal**
```bash
curl -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440001/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{
    "method": "PIX",
    "pix": {"type": "email", "key": "user@email.com"},
    "amount": 100.00,
    "schedule": "2026-02-15 15:00:00"
  }'
```

## Business Rules

1. Withdrawal operation is recorded in `account_withdraw` and `account_withdraw_pix` tables
2. Immediate withdrawals are processed right away
3. Scheduled withdrawals are processed by cron (runs every minute)
4. Balance is deducted from the account
5. Only PIX with email key type is currently supported
6. Cannot withdraw more than available balance
7. Account balance cannot go negative
8. Scheduled withdrawals cannot be in the past
9. Email notification is sent after successful withdrawal

## Scheduled Withdrawals (Cron)

The cron job runs every minute and processes pending scheduled withdrawals:

- Queries withdrawals where `scheduled=true`, `done=false`, `error=false`, `scheduled_for <= NOW()`
- Re-validates balance at execution time
- Deducts balance and marks as done
- Sends email notification
- Records errors in `error_reason` if failed

## Email Notifications

Emails are sent via Mailhog (SMTP on port 1025). View sent emails at http://localhost:8025

Email contains:
- Date and time of withdrawal
- Amount withdrawn
- PIX key type and value
- Transaction ID

## Useful Commands

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# Stop and remove volumes (reset database)
docker-compose down -v

# View logs
docker-compose logs -f hyperf

# Access Hyperf container
docker-compose exec hyperf sh

# Access MySQL CLI
docker-compose exec mysql mysql -uhyperf -phyperf hyperf

# Clear Hyperf cache
docker-compose exec hyperf rm -rf runtime/container/*
docker-compose restart hyperf
```

## Architecture

### Strategy Pattern for Withdrawal Methods

The project uses the Strategy Pattern to allow easy addition of new withdrawal methods:

```php
// To add a new method (e.g., TED):
// 1. Create TedWithdrawMethod implementing WithdrawMethodInterface
// 2. Register in WithdrawMethodFactory
```

### Service Layer

- **WithdrawService**: Main business logic (validation, transactions, balance management)
- **NotificationService**: Email notifications via Symfony Mailer
- **WithdrawMethodFactory**: Creates appropriate withdrawal method strategy

## Environment Variables

Located in `.env`:

```env
APP_NAME=TecnoFit
APP_ENV=dev

# Database
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=hyperf
DB_USERNAME=hyperf
DB_PASSWORD=hyperf

# Mail (Mailhog)
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_FROM_ADDRESS=noreply@tecnofit.com
MAIL_FROM_NAME=TecnoFit
```

## License

MIT
