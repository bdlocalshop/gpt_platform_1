# RULES.md — Coding & Architectural Constraints

## 1. Absolute Financial & Ledger Invariants
1. **NO DIRECT BALANCE MUTATIONS:** Direct modification of user balances (e.g., `User::increment('balance')`, direct SQL `UPDATE users SET points = points + 100`) is strictly forbidden. All balance updates MUST be executed via the `LedgerService` using double-entry transactions.
2. **IMMUTABLE ENTRIES:** Ledger rows (`ledger_entries`) are strictly append-only. No `UPDATE` or `DELETE` statements may ever target the ledger table. Mistakes or cancellations are corrected solely via offset entries.
3. **PESSIMISTIC ROW LOCKING:** Any ledger operation calculating or transferring balance MUST lock the respective account row using `lockForUpdate()` within an explicit database transaction:
   ```php
   DB::transaction(function () use ($userId, $amount) {
       $account = Account::where('user_id', $userId)->lockForUpdate()->first();
       // Record double-entry transaction
   });
   ```
4. **DECIMAL PRECISION:** Store all point balances and fiat amounts as `DECIMAL(16, 4)` in PostgreSQL. Never use floating-point types (`FLOAT` or `DOUBLE`).
5. **SYSTEM RECONCILIATION:** Total debits must strictly equal total credits across all account types (User Assets, Provider Liabilities, Platform Revenue, Reserve Accounts) at all times.

---

## 2. Postback & Webhook Security Standards
1. **IDEMPOTENCY FIRST:** Every postback route MUST enforce an indexed compound unique constraint: `UNIQUE(provider, external_transaction_id)`. Re-received transaction IDs must immediately return `HTTP 200 OK` (to satisfy network retries) without re-crediting the ledger.
2. **MANDATORY SIGNATURE VALIDATION:** No postback payload may be processed without verifying the provider's secret hash or signature token via constant-time string comparison (`hash_equals()`).
3. **IP WHITELISTING:** Enforce middleware that validates the incoming client IP against the approved CIDR ranges for BitLabs, CPX Research, Offerwall.gg, and Offerwall.com. Unmatched IPs must be rejected with `HTTP 403 Forbidden` and logged.
4. **REVERSAL / CHARGEBACK HANDLING:** When an offerwall sends a reversal/chargeback (e.g., status `< 0`), the system must record a deduction ledger entry. If the user's available balance is insufficient, allow their balance to go negative and place an automated hold on their account.

---

## 3. Anti-Fraud & Account Rules
1. **IP REPUTATION GATING:** Run ProxyCheck / MaxMind checks on user signup and on every request to generate an offerwall session. Reject or flag requests originating from VPNs, open proxies, or datacenter hosting providers.
2. **UNIQUE CRYPTO ADDRESSES:** A single cryptocurrency payout address cannot be associated with more than one user account. Any payout attempt with a reused address must immediately flag both accounts for manual fraud review.
3. **ONE-TIME REFERRAL MATURATION:** Flat referral bonuses must NEVER be awarded upon user registration. Referral bonuses only unlock after the referred user completes at least 1,000 Points ($1.00 USD) in verified offer conversions.
4. **WITHDRAWAL AUDIT PIPELINE:** First-time crypto withdrawals must always be routed to a pending review queue requiring admin sign-off. Automated disbursements are only permitted for users with established conversion histories and zero chargeback flags.

---

## 4. Code Architecture & Conventions
1. **LARAVEL (API):**
   * Keep Controllers thin; encapsulate business logic in dedicated Actions or Services (`app/Services/Ledger/`, `app/Services/Postbacks/`).
   * Use strictly typed Form Requests for all input validation.
   * Return standardized JSON envelopes: `{ "success": true, "data": ..., "message": ... }`.
2. **NEXT.JS (FRONTEND):**
   * Strictly TypeScript with strict mode enabled.
   * Store server secrets in `.env.local` with zero exposure of publisher private keys to the client (`NEXT_PUBLIC_` prefix forbidden for postback secrets).
   * Implement UI error boundaries and optimistic balance handling only with fallback synchronization.

<FollowUp label="Want me to generate the complete PostgreSQL ledger migration and Laravel LedgerService class?" query="Generate the complete PostgreSQL ledger migration and Laravel LedgerService class with double-entry accounting logic."/>

<ElicitationsGroup message="Next deliverables to prepare for this stack:">
  <Elicitation label="Create the S2S Postback controller and signature validators" query="Write the Laravel Postback controller and signature verification service for BitLabs, CPX Research, Offerwall.gg, and Offerwall.com."/>
  <Elicitation label="Build the crypto payout request schema and fraud review pipeline" query="Design the database schema and service logic for crypto payout requests, balance locking, and fraud screening."/>
  <Elicitation label="Draft the Next.js offerwall hub and iFrame container component" query="Create the Next.js components for the offerwall hub featuring embedded iFrames and native API offer cards."/>
</ElicitationsGroup>