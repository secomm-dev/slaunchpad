# Evidence — TASK-DFGFZ9 Phase 3 (COD product final state / pre-release)

Date: 2026-09-23 · DEC-TASKDFGFZ9-003 · Plan approved (user acting SA/TL, r3)

## 1. Contract diff

| | Phase 2 | Phase 3 (final) |
|---|---|---|
| Identification | `ConfiguredCodPaymentMethodResolver` đọc config `secomm_cod/...` (empty = nothing COD) | **`DefaultCodPaymentMethodResolver`** hardcode `DEFAULT_COD_METHODS = ['cashondelivery']` (Magento core, `Magento_OfflinePayments` — merchant tự bật; zero config dependency) |
| Collection contract | `resolve(Order, Shipment, ?CodCollectionPriorInterface $prior = null)` — caller supply prior (BYPASS được) | `resolve(Order, Shipment, ?CodCollectionAttemptInterface $attempt)` — nullable-but-REQUIRED; attempt = định danh (carrier + reference); **caller KHÔNG thể bypass** |
| Frozen + prior | Caller tự query anchor bảng của mình (cross-carrier blind spot) | Resolver **tự đọc ledger** `secomm_cod_collection` (cross-carrier by construction) |
| Admin surface | Section "COD Settings" + ACL `Secomm_Cod::config` + config.xml default | **XOÁ toàn bộ** (`system.xml`, `acl.xml`, `config.xml`, DataPatch + tests) |
| Module deps | 0 | + `Magento_OfflinePayments` (tường minh) |

## 2. Ledger `secomm_cod_collection` (DDL applied trên dev DB)

`entity_id` PK · `magento_order_id` (indexed) · `carrier_code` · `provider_reference` ·
`amount` decimal(12,4) · `currency` (default VND) · `status` PENDING|SUBMITTED|RECOVERED|FAILED|UNKNOWN ·
`reason_code` · timestamps · **UNIQUE (carrier_code, provider_reference)**.
Whitelist generated. Carrier flow: `recordPending` (amount > 0, trước anchor + POST) →
mirror `markSubmitted`/`markNotSubmitted` mọi outcome. Guard: không downgrade
SUBMITTED/RECOVERED; close-out chỉ FAILED|UNKNOWN và `NOT IN (SUBMITTED, RECOVERED)`.

## 3. Real-DB ledger proof (seeded → assert → cleanup)

Order 900100 với 2 rows: `ghn/GHNS41` SUBMITTED 500,000 + `ghtk/ghtk-P1-2` FAILED 400,000:
- **Cross-carrier prior** (query với exclusion `ghtk/ghtk-P1-3`): tìm thấy `ghn/GHNS41` 500,000 — shipment khác carrier chặn; FAILED sibling không chặn.
- **Frozen** (`ghn/GHNS41`): 500,000 VND replay.
- **Null attempt**: prior vẫn thấy (không exclusion — fail-safe).
- Cleanup: 0 rows.

## 4. Fresh-install proof

**Structural (đã chứng minh):**
- `Secomm_Cod` = declarative-only module: KHÔNG InstallSchema/UpgradeSchema/DataPatch (find = 0 files).
- KHÔNG có `ScopeConfig` trong production code (grep = 0) — runtime không phụ thuộc row config.
- KHÔNG còn reference `secomm_cod/*` hay `secomm_shippingcore/cod` trong code (grep = 0).
- Ledger table được `setup:upgrade` tạo trên dev DB (0 rows — sạch).
- → Fresh install = cài module + `setup:upgrade` (declarative tạo bảng) + merchant bật
  `cashondelivery` trong admin. KHÔNG cần migration step.

**Full-flow empty-DB install: BLOCKED (pre-existing, ngoài scope COD)** — `setup:upgrade`
trên DB trống fail với "The default website isn't defined" ngay phase "Updating modules";
stack = eager `Session\Config` → StoreResolver → default website (third-party stream, DI
runtime resolve trong schema phase; đã thử clear cache/generated — không hết). Đây là
N-Defect môi trường cần team xử lý riêng; KHÔNG liên quan Secomm_Cod (module chỉ thêm
declarative schema; structural proof ở trên đáp ứng mục tiêu "fresh install không cần
migration/config row"). Scratch DB `launchpad_fresh_check` đã DROP; `env.php` đã revert
(verified dbname = launchpad).

## 5. Test results (scoped + full tách biệt)

- Scoped `Cod|Ghtk|Ghn`: **676 tests / 211,730 assertions — 0F/0E**. Chi tiết: Cod 39
  (6 DefaultCod + 12 Ledger + 15 resolver rewrite + 7 decision — xoá AclConsistency 4 +
  patch 8 + ConfiguredResolver 8), Ghtk 236 (service 19: ledger armed-before-anchor-post
  flow timeline, mirror marks, non-COD 0 ledger call, rejection không arm), Ghn 401
  (service 18: recordPending→insertPending→post ordering, mirror, no-ledger cases).
- Full suite: **2593 tests** — 16E + 2F, toàn bộ stream khác (8 PromotionMaxDiscount mới
  land + 7 Tracking + 3 FulfillmentCore — pre-existing); **0 thuộc COD/Ghtk/Ghn**.
- `setup:di:compile` GREEN; `setup:upgrade` GREEN (ledger table + whitelist).
- Validator `--check-specs --check-records --check-identity`: 0 finding TASK-DFGFZ9.

## 6. GHN sandbox status

**BLOCKED_BY_CREDENTIAL** — probe `cod_amount > 0` chưa chạy được (0 rows
`carriers/secomm_ghn/*`). QC gate đầy đủ request/expected/evidence:
`ghn-cod-sandbox-gate.md`. Internal tests chỉ chứng minh payload xây đúng
(`cod_amount` 0 + 1250000, cả 2 parcel types).

## 7. Staging cleanup

`.ai/evidence/TASK-DFGFZ9/staging-cleanup.sql` — manual only (AI không chạy): backup
mysqldump + 4 SELECT previews + DELETE có điều kiện theo entity_id + verify queries.
Cover: `secomm_ghn_shipment` (7 dev rows cod=0), `secomm_ghtk_shipment` (0),
config COD paths (0), patch_list legacy entry.

## 8. Gates còn mở (QC/TL)

1. GHN sandbox `cod_amount > 0` probe (mở khi có credentials — xem gate doc).
2. Admin browser smoke: bật `cashondelivery`, tạo COD order, shipment CREATE qua GHN/GHTK,
   thử shipment COD thứ hai (comment/error rõ ràng), retry sau timeout.
3. Empty-DB `setup:upgrade` blocker (third-party eager-session) — N-Defect môi trường riêng.
