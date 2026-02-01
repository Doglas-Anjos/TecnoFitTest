# TecnoFit Project - Session Summary

## Project Overview

A digital account platform that allows users to withdraw funds via PIX from their available balance.

**Tech Stack:**
- Hyperf PHP 3.1
- MySQL 8
- Mailhog (email testing)
- Docker & Docker Compose

---

## What Was Built

### 1. Docker Environment

Created a complete Docker setup with individual Dockerfiles:

```
docker/
├── hyperf/Dockerfile      # PHP 8.1 + Swoole
├── mysql/Dockerfile       # MySQL 8.0
│   └── init/
│       ├── 01-schema.sql  # Database tables
│       └── 02-seed.sql    # Test data (3 accounts)
└── mailhog/Dockerfile     # Email testing
```

**Build scripts:** `build.sh` (Linux/Mac) and `build.bat` (Windows)

### 2. Database Schema

**Tables created:**
- `account` - Digital accounts with balance
- `account_withdraw` - Withdrawal records
- `account_withdraw_pix` - PIX details for withdrawals

**Test accounts seeded:**
| Name | Balance |
|------|---------|
| João Silva | R$ 1.500,00 |
| Maria Santos | R$ 3.200,50 |
| Pedro Oliveira | R$ 500,00 |

### 3. API Endpoint

**POST** `/account/{accountId}/balance/withdraw`

```json
{
  "method": "PIX",
  "pix": {
    "type": "email",
    "key": "user@email.com"
  },
  "amount": 150.75,
  "schedule": null | "2026-02-15 15:00:00"
}
```

**All other endpoints return 404.**

### 4. Application Architecture

```
app/
├── Controller/
│   └── AccountController.php         # Withdraw endpoint
├── DTO/
│   ├── PixDataDTO.php
│   ├── WithdrawRequestDTO.php
│   └── WithdrawResponseDTO.php
├── Exception/
│   ├── AccountNotFoundException.php
│   ├── BusinessException.php
│   ├── InsufficientBalanceException.php
│   ├── InvalidScheduleException.php
│   └── Handler/
│       ├── BusinessExceptionHandler.php
│       ├── NotFoundExceptionHandler.php
│       └── ValidationExceptionHandler.php
├── Model/
│   ├── Account.php
│   ├── AccountWithdraw.php
│   └── AccountWithdrawPix.php
├── Request/
│   └── WithdrawRequest.php           # Validation rules
├── Service/
│   ├── Notification/
│   │   ├── MailerFactory.php
│   │   └── NotificationService.php   # Email notifications
│   └── Withdraw/
│       ├── Method/
│       │   ├── WithdrawMethodInterface.php
│       │   ├── AbstractWithdrawMethod.php
│       │   ├── PixWithdrawMethod.php
│       │   └── WithdrawMethodFactory.php
│       └── WithdrawService.php       # Main business logic
└── Task/
    └── ProcessScheduledWithdrawsTask.php  # Cron job
```

### 5. Design Patterns Used

**Strategy Pattern** - For withdrawal methods (easy to add TED, Boleto, etc.)
```php
WithdrawMethodInterface
├── PixWithdrawMethod (implemented)
├── TedWithdrawMethod (future)
└── BoletoWithdrawMethod (future)
```

---

## Business Rules Implemented

1. Withdrawal recorded in `account_withdraw` and `account_withdraw_pix`
2. Immediate withdrawals processed right away
3. Scheduled withdrawals processed by cron (every minute)
4. Balance deducted from account
5. Only PIX with email key type supported
6. Cannot withdraw more than available balance
7. Balance cannot go negative
8. Schedule cannot be in the past
9. Email notification sent after successful withdrawal

### Scheduled Withdrawal - Insufficient Balance Handling

When cron processes a scheduled withdrawal with insufficient balance:
- `done = true` (processed)
- `error = true` (with failure)
- `error_reason` = "Saldo insuficiente no momento do processamento..."
- Balance is NOT deducted

---

## Access Points

| Service | URL |
|---------|-----|
| API | http://localhost:9501 |
| Mailhog UI | http://localhost:8025 |
| MySQL | localhost:3306 |

---

## Useful Commands

```bash
# Start
docker-compose up -d

# Stop
docker-compose down

# Reset database
docker-compose down -v && docker-compose up -d

# View logs
docker-compose logs -f hyperf

# Clear cache
docker-compose exec hyperf rm -rf runtime/container/*
docker-compose restart hyperf

# Access MySQL
docker-compose exec mysql mysql -uhyperf -phyperf hyperf
```

---

## API Examples

**Immediate withdrawal:**
```bash
curl -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440001/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{"method":"PIX","pix":{"type":"email","key":"user@email.com"},"amount":100,"schedule":null}'
```

**Scheduled withdrawal:**
```bash
curl -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440001/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{"method":"PIX","pix":{"type":"email","key":"user@email.com"},"amount":100,"schedule":"2026-02-15 15:00:00"}'
```

---

## Response Examples

**Success (200):**
```json
{
  "success": true,
  "message": "Saque realizado com sucesso",
  "data": {
    "id": "uuid",
    "account_id": "uuid",
    "method": "PIX",
    "amount": 100,
    "scheduled": false,
    "done": true,
    "pix": {"type": "email", "key": "user@email.com"}
  }
}
```

**Validation Error (422):**
```json
{
  "success": false,
  "message": "Erro de validação",
  "errors": {"amount": ["O valor do saque deve ser maior que zero."]}
}
```

**Insufficient Balance (422):**
```json
{
  "success": false,
  "message": "Saldo insuficiente. Valor solicitado: R$ 1000.00, Saldo disponível: R$ 500.00",
  "code": 422
}
```

**Account Not Found (404):**
```json
{
  "success": false,
  "message": "Conta não encontrada: invalid-id",
  "code": 404
}
```

**Endpoint Not Found (404):**
```json
{
  "success": false,
  "message": "Endpoint não encontrado",
  "code": 404
}
```

---

## Files Created/Modified

### Created:
- `docker-compose.yml`
- `docker/hyperf/Dockerfile`
- `docker/mysql/Dockerfile`
- `docker/mysql/init/01-schema.sql`
- `docker/mysql/init/02-seed.sql`
- `docker/mailhog/Dockerfile`
- `build.sh`, `build.bat`
- `app/Controller/AccountController.php`
- `app/DTO/PixDataDTO.php`
- `app/DTO/WithdrawRequestDTO.php`
- `app/DTO/WithdrawResponseDTO.php`
- `app/Exception/BusinessException.php`
- `app/Exception/AccountNotFoundException.php`
- `app/Exception/InsufficientBalanceException.php`
- `app/Exception/InvalidScheduleException.php`
- `app/Exception/Handler/BusinessExceptionHandler.php`
- `app/Exception/Handler/ValidationExceptionHandler.php`
- `app/Exception/Handler/NotFoundExceptionHandler.php`
- `app/Model/Account.php`
- `app/Model/AccountWithdraw.php`
- `app/Model/AccountWithdrawPix.php`
- `app/Request/WithdrawRequest.php`
- `app/Service/Notification/MailerFactory.php`
- `app/Service/Notification/NotificationService.php`
- `app/Service/Withdraw/WithdrawService.php`
- `app/Service/Withdraw/Method/WithdrawMethodInterface.php`
- `app/Service/Withdraw/Method/AbstractWithdrawMethod.php`
- `app/Service/Withdraw/Method/PixWithdrawMethod.php`
- `app/Service/Withdraw/Method/WithdrawMethodFactory.php`
- `app/Task/ProcessScheduledWithdrawsTask.php`
- `config/autoload/mail.php`

### Modified:
- `config/routes.php` - Only withdraw endpoint
- `config/autoload/dependencies.php` - Mailer factory
- `config/autoload/exceptions.php` - Custom handlers
- `config/autoload/processes.php` - Cron process
- `.env` - Database and mail config
- `README.md` - Full documentation

---

## Test Accounts UUIDs

```
João Silva:    550e8400-e29b-41d4-a716-446655440001
Maria Santos:  550e8400-e29b-41d4-a716-446655440002
Pedro Oliveira: 550e8400-e29b-41d4-a716-446655440003
```

---

## Session Date
2026-01-30

---

## Session 2: 2026-01-31

### Summary of Work Done

#### 1. UUID v7 Fix for Test Dashboard

**Problem:** The test dashboard was failing with `uuidv7 is not a function` error during database auto-population.

**Solution:**
- Updated `docker/test-dashboard/package.json` to use uuid v10.0.0
- Added a fallback `uuidv7()` function in `server.js` that generates UUID v7-like IDs if the native function isn't available

---

#### 2. Request/Response Visualization in Test Dashboard

**Request:** User wanted to visualize the responses after clicking "Run Test" and see request/response details in stress tests.

**Changes Made:**

**Race Condition Tests:**
- Added "Request/Response Details" section below the test log
- Each test now displays: request body, response body, status code, response time
- Color-coded by status (green=success, orange=locked, red=error)

**Stress Test:**
- Added "Request/Response Log" table showing:
  - Request number and timestamp
  - Account ID (abbreviated)
  - Amount
  - Status code with color badge
  - Response time
  - Expandable "View Response" to see full JSON response
- Added auto-scroll toggle and clear button
- Shows last 100 requests (newest first)

---

#### 3. PIX Key Validation

**Request:** Verify that the PIX key sent in the withdrawal request body matches a registered PIX key for that account before proceeding.

**PHP Backend Changes:**
- Created `app/Exception/InvalidPixKeyException.php` - Returns 422 status code
- Updated `app/Service/Withdraw/WithdrawService.php`:
  - Added `validatePixKey()` method that queries `account_pix` table
  - Validates that the PIX key (type + key) belongs to the account
  - Throws `InvalidPixKeyException` if not found

**Test Dashboard Changes:**
- Added `TEST_PIX_KEYS` mapping for edge case accounts (test1@test.com through test6@test.com)
- Updated `sendWithdrawRequest()` to accept and use correct PIX key per account
- Added `/api/account/:accountId/pix` endpoint in server.js to fetch PIX keys
- Added `fetchPixKey()` function for stress tests with random accounts
- Added new test case "8. Invalid PIX Key" to verify the validation works
- Updated all curl examples in HTML to show correct PIX keys

---

### Files Modified/Created in Session 2

| File | Action |
|------|--------|
| `docker/test-dashboard/package.json` | Modified - uuid v10.0.0 |
| `docker/test-dashboard/server.js` | Modified - UUID fallback, PIX key endpoint |
| `docker/test-dashboard/public/index.html` | Modified - Response details sections, new test |
| `docker/test-dashboard/public/app.js` | Modified - Response display, PIX key handling |
| `app/Exception/InvalidPixKeyException.php` | **Created** |
| `app/Service/Withdraw/WithdrawService.php` | Modified - PIX validation |

---

### New Exception Added

**InvalidPixKeyException (422)**
```json
{
  "success": false,
  "message": "Chave PIX não pertence à conta. Tipo: email, Chave: wrong@email.com, Conta: uuid",
  "code": 422
}
```

---

### Test Dashboard - Edge Case Accounts with PIX Keys

| Account ID | PIX Key | Balance | Status |
|------------|---------|---------|--------|
| 00000000-0000-0000-0000-000000000001 | test1@test.com | R$ 1000.00 | Active |
| 00000000-0000-0000-0000-000000000002 | test2@test.com | R$ 500.00 | Active |
| 00000000-0000-0000-0000-000000000003 | test3@test.com | R$ 50.00 | Active |
| 00000000-0000-0000-0000-000000000004 | test4@test.com | R$ 0.00 | Active |
| 00000000-0000-0000-0000-000000000005 | test5@test.com | R$ -100.00 | Active |
| 00000000-0000-0000-0000-000000000006 | test6@test.com | R$ 1000.00 | Locked |

---

### Rebuild Command

```bash
docker-compose -f docker-compose-test.yml down
docker-compose -f docker-compose-test.yml build --no-cache hyperf-test test-dashboard
docker-compose -f docker-compose-test.yml up -d
```

---

### Session 3: 2026-01-31 (Continued)

#### 1. Fixed Database Connection Retry Logic

**Problem:** Test dashboard was failing to auto-populate database with `connect ECONNREFUSED` error on startup.

**Solution:**
- Enhanced `initDatabase()` with retry counter (max 12 retries = 60 seconds)
- Added pool readiness check in `autoPopulateDatabase()`
- Auto-populate only triggers after successful database connection
- Removed duplicate auto-populate call from server startup
- Added retry logic to auto-populate if it fails

**Files Modified:**
- `docker/test-dashboard/server.js`

---

#### 2. Improved Race Condition Handling

**Problem:** Double withdrawal attack test was returning 500 error on second attempt instead of user-friendly message.

**Solution:**
- Implemented database row-level locking using `SELECT FOR UPDATE`
- Moved transaction wrapper to outer layer of `withdraw()` method
- Added `getAccountWithLock()` method to acquire database lock before processing
- Now returns proper 423 status with message: "Conta {id} está temporariamente bloqueada para operações. Tente novamente em alguns segundos."

**Response Example (423 - Account Locked):**
```json
{
  "success": false,
  "message": "Conta 00000000-0000-0000-0000-000000000001 está temporariamente bloqueada para operações. Tente novamente em alguns segundos.",
  "code": 423
}
```

**Technical Details:**
- Uses MySQL `SELECT FOR UPDATE` to lock account row at database level
- Prevents concurrent transactions from processing same account simultaneously
- First request acquires lock and processes withdrawal
- Second concurrent request waits for lock, then sees account is locked and returns 423
- Account unlocked automatically after transaction completes

**Files Modified:**
- `app/Service/Withdraw/WithdrawService.php`

---

#### 3. Enabled Hot Reload for Test Environment

**Problem:** Had to rebuild containers on every code change during development.

**Solution:**
- **Hyperf (PHP)**: Changed command to use `server:watch` instead of `start`
  - Automatically reloads server when PHP files change
  - Added volume exclusions for `vendor` and `runtime` directories
  
- **Test Dashboard (Node.js)**: Added nodemon for auto-restart
  - Installed nodemon as dev dependency
  - Created `npm run dev` script
  - Mounted source directory as volume with node_modules exclusion
  - Server restarts automatically when files change

**How to Use:**
```bash
# First time setup (rebuild with new configuration)
docker-compose -f docker-compose-test.yml down
docker-compose -f docker-compose-test.yml build
docker-compose -f docker-compose-test.yml up -d

# Now you can edit files and see changes automatically:
# - PHP files: Server reloads on save
# - Node.js files: Server restarts on save
# - HTML/JS files: Refresh browser to see changes

# No need to rebuild anymore!
```

**Files Modified:**
- `docker-compose-test.yml` - Added volumes and changed commands
- `docker/test-dashboard/package.json` - Added nodemon
- `docker/test-dashboard/Dockerfile` - Changed CMD to use dev script

---

### Test Dashboard Access

| Service | URL |
|---------|-----|
| Test Dashboard | http://localhost:3000 |
| Test API | http://localhost:9502 |
| Test MySQL | localhost:3308 |
| Test Mailhog UI | http://localhost:8026 |

---

### Session 4: 2026-02-01

#### 1. Fixed Scheduled Withdrawal Balance Validation

**Problem:** Balance was being validated at scheduling time, preventing users from scheduling withdrawals if they didn't have sufficient funds yet.

**Solution:**
- Moved balance validation to only apply to immediate withdrawals
- Scheduled withdrawals no longer check balance at request time
- Balance is checked by cron job at execution time (as it was already doing)

**Business Logic:**
- **Immediate withdrawal**: Balance validated immediately → fails if insufficient
- **Scheduled withdrawal**: Balance validated at scheduled time by cron → user can schedule even without current funds

**File Modified:**
- `app/Service/Withdraw/WithdrawService.php`

---

#### 2. Fixed Timezone for Scheduled Withdrawals

**Problem:** Scheduled withdrawals were failing with "date is in the past" error when scheduling 3-4 minutes ahead, because server was using UTC while user expected São Paulo time.

**Solution:**
- Added `APP_TIMEZONE=America/Sao_Paulo` to `.env`
- Updated `config/config.php` to set PHP default timezone
- Updated `app/DTO/WithdrawRequestDTO.php` to parse schedule with São Paulo timezone
- Updated `app/Service/Withdraw/WithdrawService.php`:
  - `validateSchedule()` now compares using São Paulo time
  - `getPendingScheduledWithdrawals()` uses São Paulo time for cron queries
- Updated `docker/test-dashboard/public/app.js`:
  - Added `formatDateSaoPaulo()` helper function
  - Schedule dates now sent in correct format

**Files Modified:**
- `.env`
- `config/config.php`
- `app/DTO/WithdrawRequestDTO.php`
- `app/Service/Withdraw/WithdrawService.php`
- `docker/test-dashboard/public/app.js`

---

#### 3. Removed PIX Key Validation (account_pix table)

**Problem:** The system required PIX keys to be pre-registered in `account_pix` table before a withdrawal could be made.

**Solution:**
- Removed `account_pix` table from database schema
- Deleted `app/Model/AccountPix.php` model
- Removed PIX key validation from `WithdrawService`
- Updated `PixWithdrawMethod` to use inline PIX type validation
- Updated `WithdrawRequest` to use local constant for allowed PIX types
- Cleaned up test dashboard (removed PIX key fetching)

**Current Database Tables:**
- `account` - User accounts with balance
- `account_withdraw` - Withdrawal records
- `account_withdraw_pix` - PIX details snapshot for each withdrawal

**Files Deleted:**
- `app/Model/AccountPix.php`

**Files Modified:**
- `docker/mysql/init/01-schema.sql` - Removed `account_pix` table
- `app/Service/Withdraw/WithdrawService.php` - Removed `validatePixKey()` method
- `app/Service/Withdraw/Method/PixWithdrawMethod.php` - Removed AccountPix dependency
- `app/Request/WithdrawRequest.php` - Uses local constant for PIX types
- `docker/test-dashboard/server.js` - Removed all `account_pix` operations
- `docker/test-dashboard/public/app.js` - Simplified PIX key handling

---

#### 4. Configured Mailtrap for Real Email Testing

**Problem:** Mailhog only captures emails locally, user wanted to receive emails in a real inbox for testing.

**Solution:**
- Configured Mailtrap SMTP credentials in `.env` and `docker-compose-test.yml`
- Emails now sent to Mailtrap sandbox for viewing

**Mailtrap Configuration:**
```env
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=91ce3a2bcf0fe3
MAIL_PASSWORD=014d2184fd1aaf
```

**View Emails:** https://mailtrap.io/inboxes

**Files Modified:**
- `.env`
- `docker-compose-test.yml`

---

#### 5. Implemented Async Email Sending

**Problem:** Sending emails to Mailtrap (external SMTP) was taking ~800ms, blocking the API response.

**Solution:**
- Implemented async email sending using Swoole coroutines
- Email is sent in a separate coroutine (`\Swoole\Coroutine::create()`)
- Response returns immediately while email sends in background

**Performance Improvement:**
| Before | After |
|--------|-------|
| ~800ms | ~60-100ms |

**How it works:**
1. Prepare email data as plain array (safe for coroutine)
2. Spawn new coroutine with `\Swoole\Coroutine::create()`
3. Create fresh mailer instance inside coroutine
4. Send email asynchronously
5. Main request returns immediately

**Files Modified:**
- `app/Service/Notification/NotificationService.php`:
  - Added `sendWithdrawConfirmationAsync()` method
  - Added `prepareEmailData()` for coroutine-safe data
  - Added static `buildHtmlFromData()` and `buildTextFromData()` methods
- `app/Service/Withdraw/WithdrawService.php`:
  - Changed to use `sendWithdrawConfirmationAsync()` instead of sync method

---

### Session 4 Summary

| Change | Impact |
|--------|--------|
| Scheduled balance validation | Can schedule without current funds |
| São Paulo timezone | Correct schedule time handling |
| Removed account_pix | No PIX pre-registration needed |
| Mailtrap integration | Real email inbox testing |
| Async email sending | Response time: 800ms → 60ms |

---

### Session 5: 2026-02-01 (Performance Optimization)

#### 1. Performance Investigation

**Problem:** Withdrawal endpoint had inconsistent response times (800ms-4000ms), needed to identify the source of slowness.

**Investigation Method:**
- Added `[PERF]` timing logs throughout `WithdrawService.php`, `AccountController.php`, and `NotificationService.php`
- Used `echo sprintf("[PERF] ...")` to output timing to docker logs
- Analyzed timing breakdown for each operation

**Bottlenecks Identified:**

| Operation | Time | Issue |
|-----------|------|-------|
| `fresh(['pix'])` | **923ms** | Reloading entire model from DB |
| `sendNotification()` | **1253ms** | `prepareEmailData()` loading relationships |
| DB operations | Variable | Lock contention on same account |

---

#### 2. Performance Optimizations Applied

**Fix 1: Changed `fresh()` to `load()`**
- `fresh()` reloads entire model + relationships from database
- `load()` only loads relationships on existing model
- **Result:** 923ms → 5ms

```php
// Before
$result = $withdraw->fresh(['pix']);

// After
$withdraw->load(['pix', 'account']);
return $withdraw;
```

**Fix 2: Eager load `account` relationship**
- Added `account` to relationship loading to avoid extra DB query in `prepareEmailData()`
- `fresh(['pix', 'account'])` loads both relationships in single query

**Fix 3: Verified async email working**
- `prepareEmailData()` now runs before coroutine spawn
- Coroutine only handles SMTP connection and sending
- **Result:** 1253ms → 1ms

---

#### 3. Final Performance Results

| Metric | Before | After |
|--------|--------|-------|
| Cold start (first request) | 5-15s | 5-15s (expected) |
| Warm requests | 800-4000ms | **80-210ms** |
| `fresh()`/`load()` | 923ms | 5ms |
| `sendNotification()` | 1253ms | 1ms |

**Remaining Variance (up to 400ms):**
- Database lock contention (`SELECT FOR UPDATE` on same account)
- Connection pool warmup
- MySQL transaction flushing

This variance is expected behavior for a transactional system with concurrent access.

---

#### 4. Cleanup

- Removed all `[PERF]` logging statements after investigation complete
- Code returned to clean state

**Files Modified:**
- `app/Service/Withdraw/WithdrawService.php` - Added `account` to relationship loading, removed PERF logs
- `app/Controller/AccountController.php` - Removed PERF logs
- `app/Service/Notification/NotificationService.php` - Removed PERF logs

---

### Session 5 Summary

| Change | Impact |
|--------|--------|
| `fresh()` → `load()` | 923ms → 5ms |
| Eager load `account` | Eliminated extra DB query |
| Verified async email | 1253ms → 1ms |
| **Total improvement** | **800-4000ms → 80-210ms** |
