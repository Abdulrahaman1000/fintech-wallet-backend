Euno – Digital Wallet & Transfer Platform

A small fintech wallet app. Users can register, fund a wallet in NGN, USD or USDT, send money to another user, and view their transaction history and details.

Stack: Laravel (API) + React (frontend)

Live application
Live app (frontend) https://fintech-wallet-frontend-five.vercel.app
API (backend) https://fintech-wallet-backend-9r5c.onrender.com/api
Repository https://github.com/Abdulrahaman1000/fintech-wallet-backend

Note: the free hosting may "sleep" when unused. The first request can take 30–60 seconds. Please wait and refresh once.

Demo accounts

Two accounts are ready to use. Alice already has funds.

Name Email Password
Alice PASTE_ALICE_EMAIL PASTE_PASSWORD
Bob PASTE_BOB_EMAIL PASTE_PASSWORD

You can also register your own accounts from the Register page. Each new user gets NGN, USD and USDT wallets automatically.

How to test (step by step)
Register or log in as Alice. Open the dashboard and check the three balances.
Fund the wallet: click Fund wallet, choose a currency, enter an amount, and submit. The balance and history update at once.
Transfer: click Send money. Enter Bob's email, currency, amount, an optional narration, and submit.
Log in as Bob (use a private window). Check that Bob received the money and sees it in Transactions.
Transaction history and details: open Transactions, then click one row to see the details (type, status, reference, date, counterparty, balance after).
Failure cases to try:
Send more than the balance → "insufficient balance" error, no money moves.
Send to an email that does not exist → clear error.
Send to yourself → rejected.
Amount of 0, a negative number, or too many decimals → rejected.
Double-click Send money → only one transfer is created.
Reuse the same reference → the transfer is not processed twice.
Open another user's transaction URL → "Transaction not found".
Concurrency: with ₦100,000 in a wallet, send two ₦80,000 transfers at the same time (two tabs, or two API calls). Only one succeeds. The other fails with insufficient balance.
Mobile: open the live URL on a phone, or use your browser's mobile view.
Run locally

The Laravel backend is in the repo root. The React frontend is in the frontend/ folder.

Requirements
PHP 8.2+, Composer
Node 18+ and npm
MySQL or PostgreSQL
Backend (repo root)
bash
composer install
cp .env.example .env
php artisan key:generate

# Set DB\_\* values in .env, then:

php artisan migrate
php artisan serve

Optional: php artisan migrate:fresh --seed (local only, it deletes all data).

Frontend
bash
cd frontend
npm install
cp .env.example .env

# .env.example contains: VITE_API_URL=http://localhost:8000/api

# Change it if your local API runs on a different address.

npm run dev
Run the tests
bash
php artisan test

The tests cover registration and login, funding, transfers, insufficient balance, self-transfer, invalid recipient, duplicate reference and idempotency key, concurrent transfers, and access to another user's data.

Deployment
Backend: Laravel API on Render (free tier), deployed from the repo root using the included Dockerfile.
Frontend: React (Vite) app on Vercel, deployed from the frontend/ folder. The API address is set with the VITE_API_URL environment variable.
Important engineering decisions
Backend enforces all rules. The frontend only sends requests and shows results. Balances, limits and ownership are checked on the server.
Database transactions. Every transfer runs inside one database transaction. If any step fails, everything is rolled back, so no money is created or lost.
Row locking. Wallet rows are locked (lockForUpdate) during a transfer. This stops two simultaneous transfers from spending the same money.
Idempotency. Transfers use a unique reference, and funding uses an idempotency key. Both are enforced with a unique database constraint, so a repeated request cannot run twice. A repeated request returns the original result.
Money is never stored as a float. Amounts are stored as exact values to avoid rounding errors.
Double-entry style records. A transfer creates a debit record for the sender and a credit record for the recipient. Each record stores the balance after the transaction.
Authorization. Users can only read their own wallets and transactions. Another user's transaction returns 404, so its existence is not revealed.
Authentication. Token-based (Laravel Sanctum). Logging out revokes the token. Protected routes return 401 without a valid token.
Error messages. Users see short, clear messages. Stack traces and SQL errors are never sent to the client (APP_DEBUG=false in production).
Recipient lookup message. The app says when an email is not registered. This helps usability in this small project. In a real product, a more generic message may be better to avoid revealing which emails exist.
Funding is simulated. No real payment provider is used. Funding only credits the wallet and records a transaction.
Notes
Do not run migrate:fresh on the live database. It deletes all users and transactions.
Supported currencies: NGN, USD, USDT. Wallets are separate per currency, with no conversion between them.
