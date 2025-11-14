# Transfer System (High Concurrency) — Feature Branch: feature/transfer-system

This feature adds a safe, high‑concurrency money transfer system with Laravel API, Pusher broadcasting, and Vue 3 UI using Inertia + Pinia. It includes atomic transfer logic, realtime updates, and tests (including a guarded concurrency test).

## What’s Included
- DB migrations:
  - users: `balance decimal(18,4)` and `balance_version bigint`.
  - transactions: sender_id, receiver_id, amount, commission_fee, total_debited, status, meta, timestamps + indexes.
- Models: `User`, `Transaction`.
- Service: `App\Services\TransferService`
  - 1.5% commission, HALF UP rounding to 4 decimals via BCMath.
  - Atomic transfer within a DB transaction.
  - Consistent row locking order with `lockForUpdate()` to reduce deadlocks.
  - Deadlock/lock-wait retry with exponential backoff.
- API (Sanctum auth):
  - GET `/api/transactions` — returns auth user balance and paginated tx list.
  - POST `/api/transactions` — performs a transfer.
- Broadcasting:
  - `TransferCompleted` event broadcasts to private channels `private-user.{senderId}` and `private-user.{receiverId}` with transaction + both users’ balances.
  - Channels auth uses Sanctum.
- Frontend:
  - Route: `/transfers` page (Inertia Vue). Simple form (recipient id, amount), list, balance.
  - Pinia store + Laravel Echo Pusher private channel, realtime updates on transfers.
- Tests (PHPUnit):
  - Unit tests for service: commission/updates/rollback.
  - Feature tests for API: index/store, auth/validation, response shapes.
  - Broadcasting test asserting event dispatch.
  - Concurrency test (guarded) simulating many concurrent transfers to ensure no overdraft (MySQL + pcntl required).

## Setup

1) Clone and install
```
composer install
npm install
```

2) Configure .env
- Database (MySQL 8):
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_pass
```
- Sanctum SPA (if using custom domain/port):
```
SANCTUM_STATEFUL_DOMAINS=localhost:8000,localhost:5173
SESSION_DOMAIN=localhost
```
- Broadcasting (Pusher):
```
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_id
PUSHER_APP_KEY=your_key
PUSHER_APP_SECRET=your_secret
PUSHER_APP_CLUSTER=your_cluster
# Optional self-hosted values
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
```
- Queues (optional):
```
QUEUE_CONNECTION=sync
```

3) Migrate DB
```
php artisan migrate
```

3b) Seed demo data (recommended)
```
php artisan db:seed
# Or start fresh and seed in one go
php artisan migrate:fresh --seed
```
Demo accounts (password for all: "password"):
- demo@example.com
- alice@example.com
- bob@example.com
- charlie@example.com

4) Run app
- Dev: `composer run dev` (spins up PHP server, queue, logs, and Vite)
- OR manually:
```
php artisan serve
npm run dev
```

5) Frontend access
- Visit `/transfers` (after logging in) to use the transfer UI.
- If UI changes do not appear, run `npm run dev` or `npm run build` and reload.

## API Contract

GET `/api/transactions` (auth required)
Response:
```
{
  "balance": "123.4567",
  "transactions": [ /* paginated items */ ],
  "meta": { "current_page": 1, "per_page": 20, "total": 10000 }
}
```

POST `/api/transactions`
Request:
```
{ "receiver_id": 2, "amount": "100.00" }
```
201 Response:
```
{
  "transaction": {
    "id": 101,
    "sender_id": 1,
    "receiver_id": 2,
    "amount": "100.0000",
    "commission_fee": "1.5000",
    "total_debited": "101.5000",
    "created_at": "..."
  },
  "balance": "898.5000"
}
```
Errors:
- 422 for validation, 403 for insufficient funds.

## Concurrency & Safety Details
- All balance updates occur inside a single DB transaction.
- We lock both user rows with `SELECT ... FOR UPDATE` and always lock the smaller user id first to reduce deadlock probability.
- We compute commission `1.5%` via BCMath and round HALF UP to 4 decimals.
- We implement retry with exponential backoff on deadlock/lock wait timeout.
- Transactions are written once balances are successfully updated, ensuring no partial updates.

## Realtime
- On success, `TransferCompleted` broadcasts to private channels for both users with:
  - `transaction` payload and `balances.sender` / `balances.receiver`.
- Vue subscribes to `private-user.{authId}` via Laravel Echo + Pusher.
- UI prepends the new transaction and updates balance.

## Tests
Run tests:
```
php artisan test
```

Targeted runs:
```
php artisan test tests/Unit/TransferServiceTest.php
php artisan test tests/Feature/TransactionsApiTest.php
php artisan test tests/Feature/BroadcastingTest.php
```

Concurrency test (MySQL + pcntl required):
- Create `.env.testing.mysql` (example):
```
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=testing_db
DB_USERNAME=testing_user
DB_PASSWORD=testing_pass
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=null
```
- Run with env override:
```
php -d pcntl.enable=1 \
  -r "putenv('DB_CONNECTION=mysql'); require 'vendor/autoload.php'; passthru('php artisan test --filter=ConcurrencyTransferTest');"
```
The test skips automatically unless `DB_CONNECTION=mysql` and `pcntl` is available.

## Rounding Policy
- Commission is 1.5% of amount, rounded HALF UP to 4 decimals.
- Sender is debited `amount + commission`, receiver credited `amount`.
