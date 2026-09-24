# SPEC-TASK-XYDQDF — MoMo native refund idempotency + uncertainty safety (MOMO-02)

- Issue: github:thanhle74/slaunchpad#4
- Lane: MoMo · Epic #1 · Depends on #3 (DONE, merged in base)
- Mode: A (spec → record → dev → pre-review → TL review)
- Risk: high (money-real refund path; provider uncertainty handling)
- Branch: `thanhle74/momo-momo-02-make-native-credit-memo-refunds-ide` (lease: RUN-20260918-220513)

## 1. Goal

Keep the admin `Invoice → Credit Memo → Refund` flow fully native (Magento owns accounting:
totals, invoice/order state). Secomm_MoMo owns only **provider request identity, response
classification and reconciliation evidence**: a durable `secomm_momo_refund` row per logical
refund operation, minted identity (refund `orderId` + `requestId`), classification of the
provider response (SUCCESS / FAILED / UNKNOWN), and operator CLI tooling to list rows and
resolve UNKNOWN rows by provider query (`/v2/gateway/api/refund/query`).

## 2. Verified provider contract (developers.momo.vn, fetched 2026-09-18)

- `POST /v2/gateway/api/refund` — request: partnerCode, **orderId (String 50, refund
  transaction's own orderId — MUST differ from the original purchase orderId)**, **requestId
  (String 50, idempotency key valid ≥ 31 days)**, amount (Long), transId (Long, original MoMo
  transId), lang, description, signature over
  `accessKey&amount&description&orderId&partnerCode&requestId&transId` (alphabetical — matches
  the existing RefundBuilder rawSignature order). **Minimum timeout 30s.**
- Refund **response**: partnerCode, orderId, requestId, amount, transId, resultCode, message,
  responseTime (+transGroup for BNPL). **The response carries NO signature** (same as the query
  response precedent in `QueryTransactionCommand` docs). SUCCESS therefore MUST be validated by
  echo-checks against the exact request just sent, not by HTTP status alone.
- resultCode **7002** = "Transaction is being processed by the provider of the payment
  instrument selected" → NOT a failure; money may still move → UNKNOWN.
- `POST /v2/gateway/api/refund/query` — request: partnerCode, orderId (refund's orderId),
  requestId (refund's requestId), lang, signature over
  `accessKey&orderId&partnerCode&requestId`. Response carries `refundTrans[]` entries
  (orderId, amount, resultCode, transId, createdTime) describing each refund transaction.
  Used ONLY by the operator CLI resolve tool (manual, query-only; never re-POST the refund).

## 3. Design decisions

1. **The refund row IS the logical operation.** The creditmemo entity id does not exist at
   gateway time (assigned post-save inside the CreditmemoService transaction), so the row's
   minted `refund_order_id` + `request_id` (≤ 50 chars each, `substr(orderRef,0,42) + '-RF'/'-RQ' + 4hex`)
   carry provider identity. `creditmemo_id` is backfilled post-commit by a small plugin (§5.6).
2. **Persistence outside the sales transaction.** `CreditmemoService::refund()` wraps the
   gateway command inside a transaction on the `sales` connection (resolves to the same MySQL
   connection as `default` in non-split setups). FAILED/UNKNOWN evidence must survive the
   rollback that aborts the creditmemo, so all refund-row writes go through an **independent
   DB connection** (`ConnectionFactory::create()` on `db/connection/default`, autocommit,
   manual table-prefix handling). FAILED/UNKNOWN throw `LocalizedException` so Magento natively
   rolls the creditmemo back and never finalizes accounting (AC4/AC5).
3. **Double-submit guard (AC3/AC6): NULL-trick unique index.**
   `UNIQUE (momo_order_ref, momo_trans_id, open_flag)` with `open_flag = 1` for open rows
   (pending/unknown) and `NULL` for terminal rows. MySQL exempts NULL from uniqueness → at most
   ONE open row per (order_ref, transId). A second submission while a row is open is blocked
   before any provider call, with a message carrying the blocking row id + requestId.
   - Terminal rows (success/failed) release the slot: legitimate sequential partial refunds and
     a retry after a provider-confirmed FAILED work natively.
   - UNKNOWN keeps `open_flag = 1` → blocks all new refunds for that payment until an operator
     resolves it via CLI (query-only; no automatic re-POST — safety rule).
4. **Classification (pure service, unit-testable).** Input: request echoes + raw response array.
   - malformed/missing/empty response, missing `resultCode`, bad echoes (requestId/orderId/amount
     mismatch) → **UNKNOWN** (`echo_mismatch` / `malformed_response`).
   - echoes OK + `resultCode == 0` → **SUCCESS** (contract-valid SUCCESS, AC/§10).
   - echoes OK + `resultCode == 7002` → **UNKNOWN** (`provider_processing`; money may move).
   - echoes OK + other `resultCode != 0` → **FAILED** (provider-confirmed refusal, contract intact).
   - transport errors (timeout/HTTP/JSON) → **UNKNOWN** (`transport_error`), never FAILED.
5. **Budget drift guard.** Before a new submission: if `sum(SUCCESS row amounts) > payment
   amount_refunded`, block with a realignment message (closes the "resolve UNKNOWN→SUCCESS after
   the creditmemo TX rolled back" double-refund hole; operator runbook = offline creditmemo to
   realign, documented in README).
6. **Creditmemo linkage backfill.** `RefundHandler` (success only) records
   `momo_refund_request_id` on the payment (inside the TX). A `Plugin\Sales\CreditmemoService`
   after-plugin (runs AFTER CreditmemoService::refund() returns, i.e. post-commit) reads it and
   writes `creditmemo_id` onto the refund row via the independent connection. It only READS
   sales data and writes only the MoMo-owned table (no sales mutation → in scope).
7. **HTTP timeout 45s** on the refund transfer (`TransferBuilder::setClientConfig`, optional
   ctor arg on the existing TransferFactory) — docs require ≥ 30s; default Laminas ~10s would
   misclassify slow-but-successful refunds as UNKNOWN.
8. **Legacy orders (pre-MOMO-01)**: the builder's legacy fallback (increment id + legacy
   transId key) is preserved as the identity chain, so legacy refunds keep working; rows are
   keyed on the same (order_ref, transId) pair (order_ref = fallback value). No regression.
9. **RefundBuilder change**: reads minted identity from `$buildSubject['momo_refund_row']`
   (set by RefundCommand). Direct GatewayCommand-style use without a row keeps the current
   fallback identity (time-based requestId), but the command pool now points to RefundCommand,
   so production always goes through the manager.

## 4. Schema — `secomm_momo_refund` (+ whitelist)

Mirrors the attempt table conventions (resource=default, innodb, CURRENT_TIMESTAMP defaults,
uppercase referenceIds, FK sales_order SET NULL). All new columns documented in db_schema.xml.

| Column | Type | Notes |
|---|---|---|
| entity_id | int unsigned identity PK | |
| order_id | int unsigned NULL | FK → sales_order.entity_id ON DELETE SET NULL |
| order_increment_id | varchar 32 | operator-readable linkage |
| invoice_id | int unsigned NULL | native invoice linkage (available pre-gateway) |
| creditmemo_id | int unsigned NULL | backfilled post-commit (plugin) |
| momo_order_ref | varchar 64 NOT NULL | original purchase orderId (or legacy fallback) |
| momo_trans_id | varchar 64 NOT NULL | original purchase transId |
| refund_order_id | varchar 64 NOT NULL | minted refund orderId (≠ purchase orderId, docs rule) |
| request_id | varchar 96 NOT NULL | minted refund requestId (idempotency key at provider) |
| provider_transaction_id | varchar 64 NULL | refund's own MoMo transId on SUCCESS |
| amount | int unsigned NOT NULL | VND snapshot |
| currency | varchar 3 NOT NULL default VND | |
| status | varchar 32 NOT NULL default 'pending' | pending/success/failed/unknown |
| response_code | varchar 32 NULL | provider resultCode (raw) |
| response_message | varchar 255 NULL | provider message (raw, provider-sent text) |
| classification_reason | varchar 64 NULL | machine reason (echo_mismatch, transport_error, provider_processing, provider_refused, provider_confirmed, stale_pending_sweep) |
| last_error | text NULL | operator-facing detail (sanitized) |
| open_flag | boolean NULL default true | 1=open (pending/unknown), NULL=terminal → NULL-trick unique |
| store_id | smallint unsigned NOT NULL default 0 | |
| created_at / updated_at | timestamp | CURRENT_TIMESTAMP, on_update false/true |
| resolved_at | timestamp NULL | when the row left open (success/failed) |

Constraints: PRIMARY(entity_id); UNIQUE `SECOMM_MOMO_REFUND_REQUEST_ID`(request_id);
UNIQUE `SECOMM_MOMO_REFUND_OPEN`(momo_order_ref, momo_trans_id, open_flag);
FK `SECOMM_MOMO_REFUND_ORDER_ID_SALES_ORDER_ENTITY_ID` (order_id → sales_order SET NULL).
Indexes: `SECOMM_MOMO_REFUND_ORDER_ID`, `SECOMM_MOMO_REFUND_STATUS` (btree).

## 5. Components

1. `Api/Data/RefundRequestInterface` — data contract (const field names, STATUS_*, open-flag
   accessors, markFinalized()/markUnknown()/markStaleUnknown() guarded transitions mirroring
   PaymentAttemptInterface style).
2. `Api/RefundRequestRepositoryInterface` + `Model/RefundRequestRepository` — **raw SQL on the
   independent connection** (insert, update by PK, by requestId, open-row lookup, SUCCESS-sum,
   list for CLI, stale sweep). Not an AbstractDb resource: the standard resource path would join
   the sales transaction.
3. `Model/RefundRequest` — plain DataObject-backed entity (manual `RefundRequestFactory`).
4. `Service/RefundConnectionProvider` — cached independent adapter via
   `ConnectionFactory::create(deploymentConfig['db/connection/default'])` + table prefix from
   `db/table_prefix`.
5. `Service/RefundResultClassifier` — pure classification (§3.4).
6. `Service/RefundRequestManager` — openIdentity(): sweep stale pending→unknown (TTL 600s
   constant), open-row block, budget drift guard, insert pending row (unique-catch → race-lost
   block); recordOutcome(): guarded transitions (pending→success/failed/unknown; unknown→
   success/failed only via resolve), returns the updated row.
7. `Gateway/Command/RefundCommand` (custom, mirrors QueryTransactionCommand) —
   openIdentity → build → transfer → placeRequest → classify → recordOutcome;
   SUCCESS: RefundHandler + ArrayResult; FAILED/UNKNOWN: recordOutcome then LocalizedException
   (native rollback, AC4/5). Transport errors → UNKNOWN + LocalizedException.
8. `Gateway/Request/RefundBuilder` — identity from `$buildSubject['momo_refund_row']` (minted);
   fallback path (no row) unchanged behavior, but refund orderId now also minted (`-RF`) so
   even the fallback never reuses the purchase orderId.
9. `Gateway/Response/RefundHandler` — additionally stores `momo_refund_request_id` (for the
   post-commit backfill plugin).
10. `Plugin/Sales/CreditmemoService` — afterRefund backfill of creditmemo_id (independent
    connection; only for momo payments; silent no-op on any lookup miss).
11. `Console/Command/RefundListCommand` (`momo:refund:list [--status] [--order]`) +
    `RefundResolveCommand` (`momo:refund:resolve <requestId>` — refund/query, query-only,
    guarded transitions, defensive parsing: match refundTrans by refund_order_id, single-entry
    fallback, ambiguity → row stays UNKNOWN). Registered in di.xml CommandListInterface.
12. `Model/Config` — add PATH_REFUND_QUERY constant (no config surface change).
13. di.xml — rewire `MoMoRefundCommand` to the custom command; new
    `MoMoRefundQueryTransferFactory` (PATH_REFUND_QUERY); DI for manager/repository/provider/
    classifier; console registration.
14. db_schema.xml + db_schema_whitelist.json — new table.

## 6. Flows

### 6.1 Admin online refund (native Credit Memo)
CreditmemoService::refund() [sales TX] → Payment::refund() → RefundCommand.execute():
openIdentity (sweep → guards → insert pending, open_flag=1) → RefundBuilder (minted identity)
→ transfer (45s timeout) → MoMoHttpClient → classifier:
- SUCCESS → recordOutcome(success) → RefundHandler (payment additional info) → return result →
  Magento natively finalizes accounting (invoice/order refunded totals) → post-commit plugin
  backfills creditmemo_id.
- FAILED → recordOutcome(failed, open_flag→NULL) → LocalizedException → native rollback; row
  survives; retry allowed later (slot released).
- UNKNOWN/transport → recordOutcome(unknown, open_flag stays 1) → LocalizedException → native
  rollback; row blocks any new refund for the payment until resolved (AC6).

### 6.2 Operator resolve (CLI, query-only)
`momo:refund:resolve <requestId>` → refund/query (refund_order_id + request_id, signed
alphabetically) → defensive parse → recordOutcome via the SAME guarded transitions → output.
Rows unknown > TTL may also be re-queried any number of times (query is side-effect-free at the
provider? — assumption: refund/query is read-only; if MoMo's query is NOT read-only, the
operator has still explicitly invoked it — documented assumption for TL review).

## 7. Assumption log (for TL review)

1. Refund response carries no signature — VERIFIED from official docs (2026-09-18), consistent
   with the in-module query precedent; classifier is echo+resultCode based.
2. resultCode 7002 = provider processing → UNKNOWN — VERIFIED from docs example.
3. refund/query is an operator-invoked, query-side call; treated as evidence-gathering, not
   automated reconciliation (manual CLI, per-invocation, explicit requestId argument).
4. TTL constant 600s (no admin config surface added); 600s ≫ 45s HTTP timeout.
5. Legacy-order refunds keep working via the existing fallback identity chain (§3.8).

## 8. Validation plan

- Unit: classifier branches (success/7002/refused/echo-mismatch/malformed/transport),
  RefundCommand paths (success/fail/unknown/transport/double-submit block), manager guards
  (open-row block, budget drift, stale sweep, race-loss on insert), builder identity,
  resolve parsing branches, backfill plugin.
- `php -l` on all new/changed files; PHPCS (Magento2) on the module diff.
- Schema: `setup:upgrade` from the real pre-change schema on the main-checkout DB
  (`fashion_launchpad`), then fresh-schema validity via `setup:db-declaration:generate-whitelist`
  check / schema validator.
- Focused MoMo unit suite: `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml`.
