---
id: DEC-TASKBRKHN4-001
legacy_ids: [DEC-021]
title: Separate DestinationAddressResolver and PickupAddressResolver — pickup config validity gates carrier active; pickup mismatch never sends ambiguous request
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-07-30
created: 2026-07-30
last_verified: 2026-07-30
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-BRKHN4, TASK-KV328X, FEAT-AE761Z]
---

# Decision Record: Destination vs Pickup resolver split + pickup validity

<!-- CANONICAL DECISION STORE (Phase 1a / RM-01). ACCEPTED 2026-07-30 — approved by user acting as SA/TL. -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- New responsibility split for TASK-BRKHN4 (rate) + TASK-KV328X (order). -->

## Context

FEAT-AE761Z gộp resolution địa chỉ vào một `Address\Resolver` duy nhất xử lý cả destination (địa chỉ giao hàng của customer) và pickup (kho/người gửi của merchant). Hai responsibility có lifecycle, nguồn dữ liệu, và failure semantic khác nhau:

- **Destination** đến từ quote/order address (country+region+ward thu thập ở FEAT-JSZQV3), có thể miss mapping → fallback vi_VN best-effort.
- **Pickup** đến từ **admin config** cố định của merchant (kho GHTK). Pickup sai = không bao giờ gửi được; không có "fallback best-effort" hợp lý cho kho của chính merchant.

Gộp hai responsibility → logic pickup bị fallback che, khó test, và có thể gửi request mơ hồ (pickup thiếu province/ward) khi config không hợp lệ.

Tier-2 (shipping carrier + external API contract — §12) → Level-2 architecture decision, SA/TL.

## Decision (accepted 2026-07-30 — user as SA/TL)

1. **Tách hai resolver:**
   - `DestinationAddressResolver` — resolve `country_id + region_id + ward_id → GHTK mapping → best-effort vi_VN fallback` (giống DEC-TASKYJENM2-001). Mapping miss = không fatal, fallback tiếp tục build request.
   - `PickupAddressResolver` — resolve pickup từ admin config, **không** fallback mơ hồ.

2. **Pickup policy:**
   - Nếu config có `pick_address_id` → ưu tiên gửi `pick_address_id`; **không yêu cầu** pickup mapping province/ward cho request đó.
   - Nếu KHÔNG có `pick_address_id` → **bắt buộc** resolve `pick_province` + `pick_ward` (`pick_district` optional).
   - Config thiếu hoặc resolve pickup thất bại → **carrier invalid/inactive theo scope** (không gửi request mơ hồ; không trả rate).

3. **Admin validation:** có action/health check kiểm tra kết nối GHTK + pickup configuration (test fee request với pickup hiện tại) → báo pickup hợp lệ/không trước khi go-live.

## Alternatives

- **Một `Address\Resolver` gộp** — rejected: che failure semantic khác nhau; pickup bị fallback; khó test + diagnose.
- **Pickup cũng fallback vi_VN** — rejected: kho merchant cố định, không có "best-effort" hợp lý; chỉ che config lỗi.

## Consequences

- (+) Boundary rõ ràng: destination best-effort, pickup strict. Carrier inactive rõ ràng khi pickup sai → không có rate ảo.
- (+) Testable độc lập; admin health check phát hiện pickup misconfig sớm.
- (−) Thêm 2 class + 1 admin test-connectivity action. TASK-BRKHN4 phải hiện thực cả pickup resolver + health check trước ready.
- Follow-up: pickup config field set trong `system.xml` (TASK-BRKHN4); health-check ACL resource.

## Affected components

- `CMP-GHTK` — `Secomm_Ghtk` (`DestinationAddressResolver`, `PickupAddressResolver`, connectivity action).
- Sub-tickets: TASK-BRKHN4 (carrier+rate) · TASK-KV328X (order sync reuse pickup resolver).

## Related records

- Features: [FEAT-AE761Z](../features/FEAT-AE761Z.md)
- Decisions: [DEC-TASKYJENM2-001](DEC-TASKYJENM2-001.md) (canonical key — destination resolver dùng) · [DEC-FEATJSZQV3-002](DEC-FEATJSZQV3-002.md) · [DEC-FEATJSZQV3-003](DEC-FEATJSZQV3-003.md)
- DECISIONS.md index: DEC-TASKBRKHN4-001
