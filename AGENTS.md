# AGENTS.md — Autonomous Workspace Role Allocations

## Workspace Mission
Build, test, and maintain a secure, high-throughput Get Paid To (GPT) platform using Next.js and Laravel, strictly enforcing double-entry accounting and robust S2S postback security.

---

## Agent Directory & Task Routing

### 1. Database & Ledger Architect (`@db-ledger-agent`)
* **Scope:** Database migrations, PostgreSQL constraints, double-entry financial ledger logic, concurrency controls, and reconciliation jobs.
* **Responsibilities:**
  * Design schema for `accounts`, `ledger_entries`, `transactions`, `postbacks`, and `payout_requests`.
  * Ensure all balance mutations execute inside serialized database transactions (`DB::transaction`) using pessimistic row locking (`lockForUpdate`).
  * Implement automated ledger reconciliation scripts to verify total system debits equal total system credits.

### 2. S2S Postback & Security Specialist (`@postback-security-agent`)
* **Scope:** Webhook routing, signature verification, IP whitelisting, anti-fraud checks, and chargeback rollbacks.
* **Responsibilities:**
  * Build dedicated controllers and middleware for BitLabs, CPX Research, Offerwall.gg, and Offerwall.com.
  * Validate HMAC/MD5 secret tokens and check incoming IP ranges against strict whitelist configs.
  * Implement postback idempotency and reversal handlers that claw back points and handle negative balance states.
  * Implement ProxyCheck.io and MaxMind IP verification middleware on registration and offer access.

### 3. Laravel API Engineer (`@backend-api-agent`)
* **Scope:** Authentication, RESTful endpoints, payout queues, referral engine, and admin operations.
* **Responsibilities:**
  * Implement Laravel Sanctum auth, email verification, and signup bonus distribution.
  * Build endpoints for `/api/v1/offers`, `/api/v1/wallet`, `/api/v1/payouts`, and `/api/v1/referrals`.
  * Create admin panel API endpoints for approving/rejecting crypto payout requests and user risk audits.

### 4. Next.js Frontend Developer (`@frontend-agent`)
* **Scope:** User interface, offerwall embeds, custom offer card grids, wallet balance display, and withdrawal forms.
* **Responsibilities:**
  * Develop clean, responsive Next.js (App Router) pages: Dashboard, Offerwall Hub, Wallet/Withdrawal, and Referral Center.
  * Integrate iFrame components with secure user identification parameters for all four offerwalls.
  * Build forms with client-side validation for crypto wallet inputs (validating address formats).
  * Handle real-time balance updates and notification toasts on successful postback credits.

### 5. QA & Integrity Tester (`@qa-integrity-agent`)
* **Scope:** Edge-case validation, financial security audits, and concurrency testing.
* **Responsibilities:**
  * Write PHPUnit/Pest tests simulating concurrent postback replay attacks to confirm idempotency.
  * Test chargeback / reversal flows when a user balance is zero (negative balance verification).
  * Audit Next.js forms for sanitization and verify API rate-limiting under high-velocity traffic.