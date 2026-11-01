# Product Requirements Document (PRD) — "Get Paid To" (GPT) Platform

## 1. Executive Summary
The platform is a web-based "Get Paid To" (GPT) rewards ecosystem where users earn rewards by completing surveys, tasks, installs, and sponsored offers via third-party offerwalls. Users redeem their accumulated virtual currency exclusively for cryptocurrency payouts. The backend enforces strict anti-fraud mitigation, immutable double-entry financial accounting, and server-to-server (S2S) postback deduplication.

---

## 2. Tech Stack & Infrastructure
* **Frontend:** Next.js (App Router, Tailwind CSS, TypeScript, Server-Sent Events / WebSockets for live notifications)
* **Backend:** Laravel 11+ (REST API, Form Requests, Queues, Database Transactions)
* **Database:** PostgreSQL (Primary relational store with strict constraints and row-level locking)
* **Cache & Message Broker:** Redis (Queue processing, rate limiting, session caching, leaderboard state)
* **Authentication:** Laravel Sanctum (HttpOnly cookie/bearer tokens) with email verification

---

## 3. Virtual Economy & Currency System
* **Standard Exchange Rate:** 1,000 Points = $1.00 USD (1 Point = $0.001 USD).
* **Display Format:** Points are displayed as integers across the user UI, but stored internally as high-precision decimals (`DECIMAL(16, 4)`) to avoid fractional loss during currency conversions.
* **Accounting Model:** Immutable double-entry ledger. Points are never modified directly on the `users` table via `increment()` or `decrement()`. Every point movement is represented by paired `debit` and `credit` ledger records.

---

## 4. Offerwall & Monetization Integrations
The platform operates a hybrid integration model across 4 primary networks:
1. **BitLabs:** Surveys & rewarded app tasks (Web SDK / iFrame + Direct API for featured cards).
2. **CPX Research:** Dynamic opinion surveys (iFrame widget + Survey API cards).
3. **Offerwall.gg:** CPA offers and app testing (S2S Postback + custom API integration).
4. **Offerwall.com:** High-paying gaming and conversion offers (Direct iFrame embed + Postbacks).

### Integration Modes
* **Embed Mode:** Branded iFrame or dynamic webview modal with user ID mapping (`user_id` query param).
* **Direct Campaign API:** Scheduled backend workers ingest high-value campaigns via REST API and serve them through a native Next.js offer card grid.

---

## 5. Security & Anti-Fraud Architecture
* **IP & Proxy Filtering:** Integrated via **ProxyCheck.io** API (initial check) backed by local **MaxMind GeoLite2 / Precision** databases to identify and reject VPNs, Tor exit nodes, and datacenters.
* **Device Identification:** Browser client fingerprinting + IP subnet locking to detect multi-accounting. Single-device-per-account policy enforced.
* **Webhook / Postback Security:**
  * Strict IP Whitelisting for all incoming provider IPs.
  * Cryptographic signature / HMAC-SHA256 / MD5 validation matching each network's provider secret.
  * Idempotency check with a unique database constraint: `UNIQUE(provider, external_transaction_id)`.
  * Support for reversal/clawback payloads (status: `-1`) triggering ledger deductions.

---

## 6. Retention, Bonuses & Referrals
* **Signup Bonus:** One-time welcome credit (e.g., 100 Points = $0.10) credited to the ledger upon email verification.
* **Referral Program:** Flat one-time bonus (e.g., 500 Points) credited to the referrer once the referred user completes their first legitimate offerwall conversion and earns a minimum threshold (e.g., 1,000 Points). No lifetime rev-share in MVP.
* **Gamification:** Excluded from initial scope.

---

## 7. Payout Engine (Crypto Only)
* **Supported Assets:** USDT (TRC-20 / BEP-20), LTC (Litecoin), BTC (Bitcoin via native address).
* **Minimum Withdrawal:** 5,000 Points ($5.00 USD equivalent).
* **Cashout Pipeline:**
  1. User submits crypto address and requests withdrawal.
  2. Points move from `available_balance` to `locked_balance` via ledger entry.
  3. Fraud checks execute automatically (IP risk score, multi-account address collision, chargeback risk).
  4. First withdrawal requires manual administrator sign-off in the admin panel; subsequent withdrawals under $20 can be processed via automated cron job / wallet API.