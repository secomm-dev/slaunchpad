# Implementation Plan: TASK-33J3RP — Data model maximum_discount_amount (column + extension attribute)

| Field | Value |
|---|---|
| Specification | tickets/TASK-33J3RP-maxdiscount-db-schema.md (`## Mini Spec`, embedded — ID TASK-33J3RP) · canonical parent specs/SPEC-FEAT-JKZM68-promotion-max-discount.md (FULL, VALID) §6, §14 |
| Author | AI draft |
| Reviewer (TL) | **pending — Tier-2 DB schema approval (gate trước Task 1)** |
| Workflow Mode | B (+ Tier-2 escalation bắt buộc — AGENTS §11/§12, CLAUDE.md stop-list) |
| Date | 2026-08-20 |

> **Status: Dev complete (2026-08-20).** TL Tier-2 approved trong chat → execute Task 1–10 một session. Task 0 ✓ · Task 1–9 ✓ (Task 5: full `setup:di:compile` bị chặn bởi blocker PRE-EXISTING `Secomm_AddressDropdown` × `Secomm_GiaoHangNhanh` (2026-08-11, ngoài scope — đề xuất SL-026); runtime wiring chứng minh bằng service-contract tests xanh) · Task 10 ✓ evidence. AC-1..AC-5 verified — gồm 2 spec corrections (AC-1 command, AC-4 rollback semantics thật) xem [TASK-33J3RP-evidence](../runtime/evidence/TASK-33J3RP/TASK-33J3RP-evidence.md). Chờ TL code review.
> Plan derive từ Mini-Spec — không redefine business requirements (rule spec-first §"Plan derive từ Spec").

---

## PART 1 — ANALYSIS (pre-implementation)

### 1.1 Quyết định đã pin (không quyết định mới — mọi thứ canonical)

| Câu hỏi | Kết luận | Nguồn |
|---|---|---|
| Column spec? | `maximum_discount_amount` DECIMAL(12,4) UNSIGNED NULL default NULL trên bảng core `salesrule`; semantic NULL/0 = unlimited, >0 = cap base-currency (by_percent) | DEC-FEATJKZM68-001 §3, spec §6 |
| Schema mechanism? | Declarative merge (`etc/db_schema.xml` khai báo `<table name="salesrule">` chỉ với column mới) — không InstallSchema/UpgradeSchema/data-patch, không đụng `vendor/` | Mini-Spec Constraints, AC-5 |
| Whitelist? | `bin/magento setup:db-declaration:generate-whitelist` (đã confirm command tồn tại ở install này) — review tay JSON output: chỉ chứa column của module trên `salesrule` | Mini-Spec Constraints |
| Extension attribute Phase 1? | Có (D2 resolved) — `etc/extension_attributes.xml` cho `Magento\SalesRule\Api\Data\RuleInterface`, attr `maximum_discount_amount` type `float` | DEC-FEATJKZM68-001 §3 |
| Plugin target? | **Interface** `Magento\SalesRule\Api\RuleRepositoryInterface` (qua `etc/di.xml`), KHÔNG phải concrete `Model\RuleRepository` — verified vendor có đủ `save`/`getById`/`getList` (RuleRepository.php L111–140) | spec §6, AC-2/AC-3 |
| Admin data path? | Tự nhiên — `Save::execute()` → `loadPost($data)` → save; load full-column map; `DataProvider::getData()` trả full → **không PHP glue** cho admin path | spec §6 (đã phân tích), Mini-Spec Expected Behavior |

### 1.2 Spec correction đã áp dụng trước plan (Spec > Plan)

Mini-Spec AC-1 gốc tham chiếu `bin/magento schema:show salesrule` — **command không tồn tại** ở Magento 2.4.8-p5 (verify `bin/magento list`: chỉ có `setup:db:status`, `setup:db-schema:upgrade`, `setup:db-declaration:generate-whitelist`). Đã update AC-1 + Expected Behavior trong ticket sang cặp lệnh thật: MySQL `SHOW COLUMNS` + `bin/magento setup:db:status`. Behavioral intent không đổi.

### 1.3 Chi tiết kỹ thuật pin sẵn cho implementation

- **Plugin class:** `Secomm\PromotionMaxDiscount\Plugin\Rule\RuleRepositoryPlugin` (1 class, 3 methods):
  - `beforeSave(RuleRepositoryInterface $subject, RuleInterface $rule)` — nếu `$rule->getExtensionAttributes()` non-null và `getMaximumDiscountAmount() !== null` → `$rule->setData('maximum_discount_amount', $value)`. **Field absent → skip → không reset** (AC-3 guard). *Limitation:* REST không set được explicit NULL (scalar ext-attr không phân biệt absent vs null) — clear NULL qua admin path (AC-2) hoặc truyền `0` (semantic unlimited). Ghi limitation vào README.
  - `afterGetById(..., RuleInterface $rule)` — populate ext-attr từ `$rule->getData('maximum_discount_amount')`; giữ existing extension attributes object nếu có (`getExtensionAttributes() ?: create` qua `ExtensionAttributesFactory` hoặc `setData` pattern chuẩn).
  - `afterGetList(..., SearchResultsInterface $results)` — iterate `getItems()`, populate từng item.
- **Data model note:** REST payload → `Magento\SalesRule\Model\Data\Rule`, admin → `Model\Rule` — cả hai hỗ trợ `getData`/`setData` nên một plugin phục vụ cả 2 path.
- **Đọc vendor trước khi code plugin:** confirm `RuleRepository::save()` flow (L111–121) — nếu save nội bộ load model mới từ id thì `beforeSave` phải set data vào đúng object được persist (adjust placement khi đọc; hành vi AC-3 là contract, không phải placement).
- **i18n:** slice này không thêm string UI (admin field thuộc TASK-67GGPR) — không đổi `i18n/*.csv`.
- **README:** thêm section "Schema & rollback" (AC-4): disable module không drop column; drop thủ công `ALTER TABLE salesrule DROP COLUMN maximum_discount_amount` khi cần sạch hoàn toàn; theo dõi `12_UPGRADE_NOTES` khi upgrade Magento.

### 1.4 Rủi ro môi trường (lesson TASK-3R6X8E)

MySQL chạy trong WSL2 từng down giữa chừng (SQLSTATE 2002). **Check DB connectivity trước runtime verify** (`bin/magento setup:db:status` connect được = DB up); nếu down → static phase trước, runtime verify tách phase sau, ghi blocker trung thực trong evidence.

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | AC | Status |
|---|---|---|---|---|
| 0 | **TL Tier-2 approval** (gate — không code trước gate này) | — | — | ⏳ HUMAN |
| 1 | `etc/db_schema.xml` — column declarative merge (XML theo DEC §3) | `etc/db_schema.xml` (NEW) | AC-1 | — |
| 2 | Generate + review whitelist (JSON chỉ chứa column của module) | `etc/db_schema_whitelist.json` (NEW) | AC-1 | — |
| 3 | `setup:upgrade` → verify: `SHOW COLUMNS` có `decimal(12,4) unsigned NULL`; `setup:db:status` không pending; git diff whitelist không đổi sau upgrade | — | AC-1 | — |
| 4 | `etc/extension_attributes.xml` — RuleInterface + `maximum_discount_amount` float | `etc/extension_attributes.xml` (NEW) | AC-3 | — |
| 5 | Plugin `RuleRepositoryPlugin` (beforeSave / afterGetById / afterGetList) + `etc/di.xml` đăng ký trên interface → `setup:di:compile` xanh | `Plugin/Rule/RuleRepositoryPlugin.php`, `etc/di.xml` (NEW) | AC-2, AC-3 | — |
| 6 | Verify model path: bootstrap script (app/bootstrap.php) — `loadPost(['maximum_discount_amount' => 50000])` → save → reload == 50000; set NULL → save → reload == NULL; script xoá sau verify | throwaway | AC-2 | — |
| 7 | Verify REST (admin token): `GET /V1/salesRules/{id}` trả field · `PUT` omit field → giá trị giữ nguyên · `PUT` truyền 75000 → reload == 75000 | — | AC-3 | — |
| 8 | Verify rollback: `module:disable Secomm_PromotionMaxDiscount` → `setup:db:status` vẫn clean, column còn; re-enable. Document section Schema & rollback trong README | README.md | AC-4 | — |
| 9 | AC-5 sweep: `git status vendor/` clean · grep module không có InstallSchema/UpgradeSchema/DataPatch · declarative-only | — | AC-5 | — |
| 10 | Records: evidence `.ai/runtime/evidence/TASK-33J3RP/TASK-33J3RP-evidence.md` (pattern TASK-3R6X8E) · ticket AC tick + status + AI Pre-review §8.3 · plan status update | `.ai/` | — | — |

### Sequence & gating

```
Task 0: TL Tier-2 approval  ⏳ HUMAN (CLAUDE.md: DB migration = stop-and-request)
   ↓
Task 1–3 (schema layer — AC-1)  →  Task 4–5 (API layer — compile xanh)
   ↓
Task 6–7 (verify AC-2/AC-3: model path + REST)
   ↓
Task 8–9 (AC-4 rollback doc + AC-5 declarative-only sweep)
   ↓
AI Pre-review §8.3  →  TL code review (Tier-2)  ⏳ HUMAN  →  QC trên DB snapshot (TASK-4HYX6Y/025 scope)
```

Rollback nếu sai giữa chừng: column additive, chưa có consumer (TASK-5H8WKE mới đọc) → disable module là đủ để vô hiệu hoá; drop column thủ công chỉ khi cần sạch schema (README document).

## Remaining steps (human / next tickets)

1. **TL Tier-2 approval** — duyệt plan này + schema change (nói "approve TASK-33J3RP" trong chat là đủ theo precedent DEC-FEATJKZM68-001).
2. Sau merge: TASK-5H8WKE (cap engine) consume field; TASK-67GGPR (admin UI) dựng form field trên data layer này.
3. QC trên DB snapshot trước merge (ticket Risks) — thực hiện cùng TASK-4HYX6Y integration tests.

## Verification summary (đối chiếu Mini-Spec)

| Mini-Spec clause | Task / bằng chứng |
|---|---|
| Expected Behavior — additive column, SHOW COLUMNS + db:status clean | Task 3 (AC-1) |
| Expected Behavior — persist tự nhiên model path (50000 / NULL) | Task 6 (AC-2) |
| Expected Behavior — REST GET trả field, PUT omit → không reset | Task 7 (AC-3) |
| Expected Behavior — disable không drop column | Task 8 (AC-4) + README |
| Expected Behavior — totals/pricing không đổi | Column inert: không collector/engine đọc (TASK-5H8WKE mới consume) — `generated/metadata` diff chỉ thêm plugin wiring RuleRepository, không total collector |
| Constraints — thuần declarative, không vendor change | Task 9 (AC-5) |
| Constraints — semantic NULL/0 unlimited | db_schema default NULL + README semantic note (engine guard thuộc TASK-5H8WKE) |
