---
id: FEAT-FQWEQ3
type: feature
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
legacy_ids: []
title: 'Secomm_Ghn carrier adapter — dual-scheme GHN address model trên Secomm_ShippingCore (thay Secomm_GiaoHangNhanh + Secomm_GhnAddressMapper)'
mode: A                      # shipping + DB schema + API contract → Tier-2
specification_level: FULL
spec_status: VALID           # SPEC-FEAT-FQWEQ3 — owner draft + 4 quyết định TL/SA ratified 2026-09-10 (§52)
specification_ref: ../../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md
risk: high
status: proposed
created: 2026-09-10
updated: 2026-09-10
ticket_ref:
  - TASK-RJFTPZ                   # GHN-A — skeleton module: registration, config, GhnApiClient, exception taxonomy, capability
  - TASK-MZ2TCB                   # GHN-B — secomm_ghn_address_unit + secomm_ghn_address_mapping (Tier-2 DB) + sync/mapping/audit CLI + resolver
  - TASK-FMBBSD                   # GHN-C — rate + available-services + leadtime (BLOCKED: E-B v2 + VietMap + NO_MATCH + ShippingCore rate-provider slice)
  - TASK-9Q5ZAK                   # GHN-D — Create Order (is_new_to_address=true) + cancel/return + MQ + secomm_ghn_shipment (BLOCKED: GHN-C + staging evidence D1 + package/shipment slices)
  - TASK-RR1ZFN                   # GHN-E — webhook + GhnStatusMapper + reconciliation qua ShippingCore tracking pipeline
  - TASK-8019VC                   # GHN-F — cutover: config migration, webhook flip, disable legacy modules, dọn bảng/cột
decisions:
  - DEC-FEATYA2C0W-004            # dependency chain + ownership (accepted — vẫn hiệu lực)
  - DEC-TASK3F6QWZ-002            # tracking pipeline chung (accepted — vẫn hiệu lực)
  - DEC-FEATFQWEQ3-001            # Create Order dual-scheme: GHN_ADMIN_2025 names + is_new_to_address=true (ACCEPTED 2026-09-10 — supersede một phần SPIKE-9Z231Q v3)
decision_assessment: material
decision_refs: [DEC-FEATYA2C0W-004, DEC-TASK3F6QWZ-002, DEC-FEATFQWEQ3-001]
decision_approval_summary:
  total: 3
  pending_approval: []
  approved: [DEC-FEATYA2C0W-004, DEC-TASK3F6QWZ-002, DEC-FEATFQWEQ3-001]
  rejected: []
  superseded: []
  last_synced: 2026-09-10
verified_against_commit:
components:
  - CMP-SHIPPING            # Secomm_ShippingCore — consumer chính (KHÔNG sửa code trong FEAT này)
  - CMP-VNADDR              # Secomm_VietNamAddress — canonical scheme source (KHÔNG sửa code trong FEAT này)
  - CMP-GHN                 # Secomm_Ghn — module mới toàn bộ
source_areas:
  - app/code/Secomm/Ghn/                      # mới
  - app/code/Secomm/GiaoHangNhanh/            # legacy reference — bị thay ở GHN-F
  - app/code/Secomm/GhnAddressMapper/         # legacy reference — bị thay ở GHN-F
  - .ai/research/SPIKE-9Z231Q-secomm-ghn-canonical-architecture.md
changes_project_state: true
changes_architecture: true   # carrier adapter đầu tiên trên ShippingCore; dual-scheme GHN provider model
changes_integration: true    # GHN API + webhook + MQ + admin
changes_known_limitations: true
last_verified: 2026-09-10
supersedes: []
---

# [SLP][FEAT-YA2C0W][FEAT-FQWEQ3] Secomm_Ghn carrier adapter — dual-scheme GHN address model trên Secomm_ShippingCore (thay Secomm_GiaoHangNhanh + Secomm_GhnAddressMapper)

<!-- CANONICAL RECORD — module mới `Secomm_Ghn` thay `Secomm_GiaoHangNhanh` + `Secomm_GhnAddressMapper`
     theo SPEC-FEAT-FQWEQ3. Decomposes thành TASK-RJFTPZ…TASK-8019VC theo 6 phase GHN-A..F. -->
<!-- Gate: 4 quyết định TL/SA ratified 2026-09-10 (SPEC §52 — D1 dual-scheme create, D2 scope,
     D3 FEAT mới, D4 E-B v2 + VietMap là dependency ngoài FEAT block GHN-C). -->
<!-- Parallel coexistence: carrier code `ghn` / route / topic / config namespace đều MỚI — legacy
     giữ nguyên tới GHN-F (spike R8). -->

## Context

Legacy `Secomm_GiaoHangNhanh` (122 files) + `Secomm_GhnAddressMapper` (71 files) mang nợ kiến trúc:
`client_order_code` = order increment id; package dims `1×1×1` khi create; webhook không auth mutate
thẳng `sales_order` state; dependency vòng mapper↔carrier; mapping keyed `region_id/city_id` (không
scheme-aware); rating suppress failure. BUG-JBX3H9 (2026-09-08) đã cắt fallback cứng nhưng kiến trúc
tổng vẫn legacy.

`Secomm_ShippingCore` đã hoàn tất foundation contracts 0.4.0→0.11.0 (address resolution/handoff,
rate outcome, service level + fallback, tracking pipeline, origin — "hard stop, chỉ còn carrier
adoption"); `Secomm_VietNamAddress` có canonical scheme layer (`VN_ADMIN_2025`/`VN_ADMIN_PRE_2025` +
resolver EXACT/MAPPED/AMBIGUOUS/UNMAPPED). SPIKE-9Z231Q audit legacy + GHN API và thiết kế
`Secomm_Ghn`; owner cung cấp Full Spec draft 2026-09-10 → ratified thành SPEC-FEAT-FQWEQ3.

**Target**: `Secomm_Ghn` = carrier adapter chuẩn đầu tiên — rating qua `VN_ADMIN_PRE_2025` → legacy
IDs; create qua `GHN_ADMIN_2025` names + `is_new_to_address=true` (D1/DEC-FEATFQWEQ3-001); master
data + mapping dual-scheme tự sync/audit; lifecycle (create/cancel/return/label/webhook) qua
ShippingCore contracts; reference implementation cho GHTK/Ahamove/Spx.

**Risk:** Tier-2 toàn feature (DB schema GHN-B/D, shipping logic, API contract GHN) → Mode A.

## Architecture (theo SPEC-FEAT-FQWEQ3 §2/§4/§39 + DEC-FEATYA2C0W-004)

```text
VN_ADMIN_2025 (customer)                             VN_ADMIN_2025 (order/shipment)
      ↓ ShippingCore handoff (E-B resolve 2025→PRE_2025)   ↓ Secomm_Ghn mapping
VN_ADMIN_PRE_2025 (exact ward; E-B v2 + VietMap)     GHN_ADMIN_2025 (province_name + ward_name)
      ↓ Secomm_Ghn mapping                                 ↓ Create Order
GHN_ADMIN_PRE_2025 → district_id + ward_code         is_new_to_address = true
      ↓ Calculate Fee / Leadtime / Available Services
```

Dependency: `Secomm_Ghn → Secomm_ShippingCore → Secomm_VietNamAddress`; KHÔNG depend
`Secomm_GiaoHangNhanh`/`Secomm_GhnAddressMapper`; KHÔNG carrier nào depend `Secomm_Ghn`.
Provider exception `Provider*` trong Secomm_Ghn, translate boundary sang `CarrierRateOutcome` +
`ShippingFailureReason`. KHÔNG sửa code ShippingCore/VietNamAddress trong FEAT này — mọi gap =
slice task ShippingCore riêng (rate-provider contract, package DTO, shipment contracts — TL review riêng).

## Requirements (feature-level; AC chi tiết trong SPEC §42..§46 + sub-ticket)

- **AC-F1 (AC-ADDR-001..006):** dual scheme `GHN_ADMIN_2025`/`GHN_ADMIN_PRE_2025` sync độc lập,
  mapping keyed canonical unit code (không display name), không GHN ID vào
  `secomm_vietnam_address_unit`, audit mapped/unmapped/ambiguous/invalid/disabled không auto-approve.
- **AC-F2 (AC-RATE-001..005):** rate qua handoff PRE_2025 → legacy IDs; fail = outcome UNAVAILABLE/
  TECHNICAL_FAILURE (cấm `$shippingFee=10`); service normalize ShippingCore level.
- **AC-F3 (AC-SHIP-001..005):** create `is_new_to_address=true` với names từ mapping 2025;
  `client_order_code` shipment-derived retry-safe; package thật (cấm 1×1×1).
- **AC-F4 (AC-TRACK-001..005):** webhook idempotent + dedup, qua `CarrierTrackingProcessorInterface`,
  không mutate `sales_order`; reconciliation từ Order Info.
- **AC-F5 (AC-ARCH-001..006):** dependency direction đúng; GHN DTO/endpoint/status trong module;
  không thêm field GHN vào checkout/customer address hay `sales_order`.
- **AC-F6 (spec §41 parity gate):** 11 flows pass trước khi disable legacy modules.

## Sub-ticket breakdown + readiness

| Task | Phase | Mode | Risk | Ghi chú |
|---|---|---|---|---|
| [TASK-RJFTPZ](../tasks/TASK-RJFTPZ.md) | GHN-A | A | high | skeleton + config + client + exception + capability — proceed ngay |
| [TASK-MZ2TCB](../tasks/TASK-MZ2TCB.md) | GHN-B | A | high | 2 bảng Tier-2 + sync/mapping/audit CLI + resolver; **TL review schema** |
| [TASK-FMBBSD](../tasks/TASK-FMBBSD.md) | GHN-C | A | high | rate/services/leadtime — **BLOCKED: E-B v2 + VietMap + NO_MATCH + ShippingCore rate-provider slice** |
| [TASK-9Q5ZAK](../tasks/TASK-9Q5ZAK.md) | GHN-D | A | high | create/cancel/return + MQ + `secomm_ghn_shipment` — **BLOCKED: GHN-C + staging evidence `is_new_to_address` (DEC-FEATFQWEQ3-001) + package/shipment slices** |
| [TASK-RR1ZFN](../tasks/TASK-RR1ZFN.md) | GHN-E | A | medium | webhook + status mapper + reconciliation — cần GHN-D (order_code) |
| [TASK-8019VC](../tasks/TASK-8019VC.md) | GHN-F | A | high | cutover + legacy removal — **GATE: parity §41 + QC store thật** |

Dependency ngoài FEAT (KHÔNG implement ở đây, block GHN-C): E-B v2 (ShippingCore invoke
`ExternalAddressResolverPool`), VietMap bridge (adapter `ExternalAddressResolverInterface`), NO_MATCH
authoring 38 ward (spike §15.3).

## Risks

- **R1 D1 vs SPIKE v3**: create NAME-based từng bị reject (2026-09-08) — owner re-decided 2026-09-10
  (DEC-FEATFQWEQ3-001); **staging evidence merged-ward bắt buộc trước GHN-D**, evidence trái → quay lại TL.
- **R2 Coverage PRE_2025 5,60%**: GHN-C bị block tới khi E-B v2 + VietMap đạt ngưỡng (TL chốt %,
  đề xuất ≥95% `FULL_PRE_2025_ADDRESS_RESOLVED` trước production enable).
- **R3 ShippingCore hard-stop**: rate-provider/package/shipment contracts chưa có — slice task riêng,
  không code thay trong Secomm_Ghn.
- **R4 v3 master-data endpoints chưa verify**: verify docs lúc GHN-B, không infer field (spike rule).
- **R5 Dual-active GHN**: carrier code/route/topic/config tách biệt hoàn toàn; QC gate cutover (GHN-F).
- **R6 MySQL 8.4 quirks**: UNIQUE(scheme_code, provider_key) thiết kế tránh NULL-unique; precedent
  TASK-ND6AZ2 (FK cần UNIQUE).

## References

- Full Spec: [SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter](../../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md)
- Architecture decision: [DEC-FEATFQWEQ3-001](../decisions/DEC-FEATFQWEQ3-001.md) (accepted 2026-09-10 — create dual-scheme)
- Research nền: [SPIKE-9Z231Q](../../research/SPIKE-9Z231Q-secomm-ghn-canonical-architecture.md) (§13 phases GHN-A..F, §15 reverse-mapping audit, §16 rejected alternatives) · [SPIKE-YH439T](../../research/SPIKE-YH439T-shippingcore-carrier-runtime-handoff.md)
- Còn hiệu lực: [DEC-FEATYA2C0W-004](../decisions/DEC-FEATYA2C0W-004.md) (dependency chain) · [DEC-TASK3F6QWZ-002](../decisions/DEC-TASK3F6QWZ-002.md) (tracking pipeline)
- Feature nền: [FEAT-YA2C0W](FEAT-YA2C0W.md) — E-A/E-B/E-C0/E-C1/E-SL0..2 contracts (parent)
- Legacy bị thay: `Secomm_GiaoHangNhanh` (BUG-JBX3H9 fail-closed hiện hữu) · `Secomm_GhnAddressMapper` (DEC-TASK3F6QWZ-001 — superseded một phần bởi DEC-FEATYA2C0W-004 D8 + FEAT này)
- Risk tier: AGENTS.md §9/§12 (shipping, DB schema, third-party contract)
