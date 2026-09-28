---
id: TASK-DFGFZ9
type: task
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
legacy_ids: []
title: 'COD identification + amount decision ownership → Secomm_Cod (module mới, config path move + idempotent migration, ShippingCore decouple, P1 collection policy + carrier anchor freeze)'
mode: A
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec — architecture amendment DEC-TASKDFGFZ9-001 (user acting SA/TL, plan approved 2026-09-23)
specification_ref:
risk: medium                  # shared-contract move (public interface xoá) + config migration; consumer duy nhất trong scope
status: in_review           # dev-complete 2026-09-23 — Cod+Ghtk 257 tests 0F/0E; compile/upgrade/validator gates xanh (0 finding task này); evidence .ai/evidence/TASK-DFGFZ9/
priority: high
decision_assessment: material
decisions: [DEC-TASKDFGFZ9-001, DEC-TASKDFGFZ9-002, DEC-TASKDFGFZ9-003, DEC-TASKDFGFZ9-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Cod/
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/Ghtk/
  - app/code/Secomm/Ghn/
changes_project_state: true
changes_architecture: true
created: 2026-09-23
updated: 2026-09-23
owner: [dev]
related_tickets: [TASK-STC3NB, TASK-6YG3HP]
---

# [SLP][FEAT-YA2C0W][TASK-DFGFZ9] COD identification + amount decision ownership → Secomm_Cod (module mới, config path move + idempotent migration, ShippingCore decouple, P1 collection policy + carrier anchor freeze)

## Embedded Mini-Spec

### Goal

Một owner duy nhất cho COD payment identification: module mới `Secomm_Cod` sở hữu contract
`isCod` + config; `Secomm_ShippingCore` decouple hoàn toàn khỏi COD; config path move sang
`secomm_cod/payment_identification/payment_methods` với DataPatch idempotent (copy-only,
dest-wins, preserve scope); consumer Ghtk đổi typehint + khai báo dependency tường minh.

### Expected Behavior

1. `Secomm_Cod` expose `Secomm\Cod\Api\CodPaymentMethodResolverInterface::isCod(string): bool`
   + `ConfiguredCodPaymentMethodResolver` — comma-separated config, trim + exact strict
   case-sensitive match, empty/malformed → safe false, không default COD method, đọc default scope.
2. Admin: Stores → Configuration → Sales → **COD Settings → Payment Identification** (section
   `secomm_cod`, sortOrder 75, showInDefault-only, ACL `Secomm_Cod::config` nest dưới
   `Magento_Config::config`, field `payment_methods` canRestore=1).
3. DataPatch `MigrateLegacyCodPaymentMethodConfig`: copy row path cũ
   `secomm_shippingcore/cod/payment_methods` sang path mới per `(scope, scope_id)`; skip source
   trim-rỗng; dest đã có → KHÔNG ghi đè; không xoá legacy rows; cache clean chỉ khi có copy.
4. Ghtk inject contract mới; ShippingCore KHÔNG tham chiếu COD nào (compile xanh chứng minh).
5. `Secomm_Cod => 1` trong config.php qua `setup:upgrade`; patch vào `patch_list`.

### Constraints / Rules

- KHÔNG ObjectManager / dependency ngầm; consumer cần nhận diện COD phải khai báo dependency
  `Secomm_Cod`; forbidden: `Secomm_ShippingCore -X-> Secomm_Cod`, `Secomm_Cod -X-> carriers`.
- KHÔNG adapter cho interface cũ (clean removal — đã chốt DEC-TASKDFGFZ9-001 §4).
- Carrier KHÔNG quyết COD policy; amount conversion giữ carrier-owned (DEC-SL016-001 §4-5);
  KHÔNG đụng `Secomm_GiaoHangNhanh` (legacy disabled) và GHN (deliberately no COD).
- Migration KHÔNG ghi đè dest đã cấu hình (kể cả khi source rỗng), KHÔNG xoá legacy rows,
  patch non-revertable có chủ đích; config global scope (showInWebsite=0/showInStore=0).
- Identification only — contract không diễn đạt eligibility/amount/surcharge/policy.
- Ghtk + ShippingCore changes phải cùng commit series (compile vỡ nếu tách rời).

### Out of Scope

`Secomm_GiaoHangNhanh` legacy COD helper · GHN cod_amount (deliberate) · CODRisk module (chưa
tồn tại — khi tạo phải dùng resolver chung) · COD framework items (eligibility/surcharge/
reconciliation…) · capability per-operation refactor · snapshot persistence.

### Acceptance Criteria

- AC-1: `bin/magento setup:di:compile` GREEN sau khi xoá interface cũ (0 stale reference).
- AC-2: 8 resolver tests pass trên `Secomm_Cod`; Ghtk `DefaultCodAmountResolverTest` (7 tests)
  pass với typehint mới; AclConsistencyTest pass (system.xml ↔ acl.xml ↔ config.xml ↔ const path).
- AC-3: Patch 7 unit cases pass (copy default scope / preserve website+store scope / source rỗng
  → no write / dest có → no write / dest có + source rỗng → no write / re-apply → 0 saveConfig /
  cache clean chỉ khi có copy).
- AC-4: `setup:upgrade` đăng ký `Secomm_Cod => 1` + patch vào `patch_list`; local DB (0 row cũ)
  patch no-op, KHÔNG ghi row rỗng.
- AC-5: Admin section hiển thị Stores → Configuration → Sales → COD Settings → Payment
  Identification (showInDefault-only); section ShippingCore giữ `physical`, không còn group `cod`.
- AC-6: Arch doc v11 amendment + DEC + DECISIONS.md + CURRENT_STATE + evidence before/after +
  README/CHANGELOG (ShippingCore, Ghtk, Cod) cập nhật.

## Phase 2 (r2) — COD amount decision ownership (DEC-TASKDFGFZ9-002, 2026-09-23)

### Goal

`Secomm_Cod` trở thành owner của QUYẾT ĐỊNH COD amount dùng khi carrier tạo đơn provider
(GHTK `pick_money` / GHN `cod_amount`): một contract trả quyết định thống nhất
(isCod + amount + currency + rejection), P1 policy = thu một lần/order bằng `grand_total`
theo order currency, VND-only, reject partial + second-COD-shipment; carrier chỉ map kết quả.

### Expected Behavior (r2)

1. `CodCollectionResolverInterface::resolve(Order, Shipment, ?CodCollectionPrior)` → decision
   VO 3 trạng thái; prior dùng opaque `getProviderShipmentReference()` (caller owns identity,
   resolver owns policy — contract không hardcode single-shipment).
2. GHTK: anchor table `secomm_ghtk_shipment` (partner_code UNIQUE, insertPending trước POST,
   frozen cod_amount replay khi retry, markSubmitted/RECOVERED/FAILED/UNKNOWN); xoá
   `CodAmountResolverInterface` + `DefaultCodAmountResolver`; rejection → LocalizedException
   (native label flow abort).
3. GHN: sequence += Secomm_Cod; resolve trước anchor; COD_REJECTED outcome (không anchor,
   không POST; observer log + comment visible); builder luôn emit `cod_amount` (0 = non-COD);
   cap 50M fail-closed; frozen-wins retry; `updateCodAmount` guard `<> SUBMITTED`.
4. Retry cùng yêu cầu giữ amount ban đầu (frozen); shipment COD thứ hai bị từ chối rõ ràng;
   non-COD không bao giờ bị chặn.

### Constraints / Rules (r2)

- KHÔNG base_total_due/base_grand_total; KHÔNG conversion âm thầm (≠ VND → reject);
  KHÔNG allocation framework; deposit KHÔNG còn implicit support (thu cả grand_total).
- Carrier không đọc order totals cho COD; dependency tường minh carrier → Secomm_Cod;
  vẫn KHÔNG có `ShippingCore → Secomm_Cod`.

### Acceptance Criteria (r2)

- AC-7: scoped Cod|Ghtk|Ghn 672 tests 0F/0E (decision VO + P1 resolver + anchor lifecycle +
  payload/anchor carry amount + rejection surface + frozen replay + prior blocking + cap 50M);
  flip 2 test cũ (Ghtk double-collect; GHN cod_amount-forbidden).
- AC-8: Migration proof trên DB thật: copy case + dest-wins case (legacy row thật) — evidence
  `phase2.md` §4; prior-query proof cả 2 bảng (§5); DDL anchor (§3).
- AC-9: Arch Rev v12 + DEC-002 + DECISIONS.md + USER_GUIDE Case 10 + README/CHANGELOG
  (Cod 1.1.0 / Ghn 0.10.0 / Ghtk 2.4.0) + CURRENT_STATE.

## Phase 3 (r3) — COD product final state (DEC-TASKDFGFZ9-003, 2026-09-23)

### Goal

Trạng thái Launchpad pre-release: `isCod()` mặc định hardcode `cashondelivery` (Magento core
`Magento_OfflinePayments` — KHÔNG admin field/config/DataPatch); collection ledger
`secomm_cod_collection` chống bypass + cross-carrier (audit CONFIRMED 2 holes của
caller-supplied prior); fresh-install không migration; staging cleanup SQL manual riêng.

### Expected Behavior (r3)

1. `resolve(Order, Shipment, ?CodCollectionAttemptInterface $attempt)` — attempt nullable
   nhưng REQUIRED; frozen replay + prior check do resolver tự đọc ledger (caller không thể
   bypass; null attempt an toàn — prior check không exclusion).
2. Ledger do Secomm_Cod sở hữu; carriers REPORT (`recordPending` amount>0 trước anchor+POST;
   mirror marks mọi outcome); anchor tables = provider facts only.
3. `DefaultCodPaymentMethodResolver` hardcode `['cashondelivery']`; xoá system.xml/acl.xml/
   config.xml/DataPatch + tests; module.xml sequence += `Magento_OfflinePayments`.

### Constraints / Rules (r3)

- Staging cleanup: 1 file SQL manual (`staging-cleanup.sql`) — SELECT trước, KHÔNG chạy tự
  động, KHÔNG nhúng vào patch/runtime; không xoá anchors đang phục vụ flow active.
- Provider nhận đơn COD ≠ Magento nhận tiền — không mark order paid.
- GHN sandbox `cod_amount > 0`: KHÔNG ghi E2E pass khi chưa có response thật (gate doc).

### Acceptance Criteria (r3)

- AC-10: scoped Cod|Ghtk|Ghn 676 tests 0F/0E; compile/validator GREEN (0 finding task).
- AC-11: Real-DB ledger proof (cross-carrier prior + frozen + null-attempt) — phase3.md §3.
- AC-12: Structural fresh-install proof (declarative-only, 0 config dependency) — phase3.md §4;
  empty-DB full-flow blocker ghi nhận N-Defect môi trường (ngoài scope).

## Phase 3 closure (r4) — atomic per-order claim (DEC-TASKDFGFZ9-004, 2026-09-23)

### Goal

Chứng minh invariant một-thu-một-lần ở mức cạnh tranh: atomic claim theo order
(engine-enforced), recovery timeline đầy đủ, currency gate về đúng carrier, zero-amount COD
giữ classification.

### Expected Behavior (r4)

1. `active_order_claim` UNIQUE engine-enforced: INSERT-first `recordPending`; attempt thua
   cuộc nhận `CodClaimConflictException`; FAILED releases / UNKNOWN keeps; same-reference
   re-arm restore PENDING.
2. Currency gate ở carrier (GHTK LocalizedException / GHN COD_REJECTED — trước recordPending/
   POST); decision trả ORDER currency.
3. Zero-amount COD → COLLECTIBLE 0.0; âm → REJECTED `invalid_order_amount`.
4. Claim-release fixes: GHTK arm-after-map; GHN 3 pre-POST fail sites → FAILED.

### Constraints / Rules (r4)

- Integration proof = 2 PDO connections cạnh tranh thật (gồm transaction-blocked); KHÔNG ghi
  "một lần/order" chỉ dựa test tuần tự.
- Fresh-install gate OPEN (blocker Session\Config riêng); structural proof KHÔNG ghi pass.
- GHN sandbox gate OPEN; không ghi E2E pass.

### Acceptance Criteria (r4)

- AC-13: 4 concurrency scenarios PASS trên DB thật (contest / release / re-arm / transaction-
  blocked) — phase3-closure.md §2.
- AC-14: scoped Cod|Ghtk|Ghn 705 tests 0F/0E (currency gate, claim conflict, zero-amount,
  claim-release); compile/validator GREEN (0 finding task).
- AC-15: DEC-004 + arch v13 closure amendment + CHANGELOGs (Cod 1.3.0 / Ghtk 2.6.0 / Ghn
  0.13.0) + OPEN gates ghi rõ.

## Plan

Plan file: `/home/secomm/.claude/plans/h-y-audit-v-x-polished-pixel.md` (phases 1–3 + closure
approved 2026-09-23).
