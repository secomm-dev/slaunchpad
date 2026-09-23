---
id: TASK-8019VC
type: task
title: 'Phase GHN-F — Cutover: config migration, webhook flip, disable Secomm_GiaoHangNhanh + Secomm_GhnAddressMapper, dọn bảng/cột legacy'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-FEAT-FQWEQ3 — canonical Full Spec (slice reference; đặc biệt §36..§41, §51)
specification_ref: ../../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md
risk: high                    # production cutover + data/schema removal (Tier-2 cao nhất)
status: proposed
priority: low
decision_assessment: material   # cutover checklist + removal scope cần TL/CTO sign-off riêng
decisions: [DEC-FEATFQWEQ3-001, DEC-FEATYA2C0W-004]
components:
  - CMP-GHN
source_areas:
  - app/code/Secomm/GiaoHangNhanh/
  - app/code/Secomm/GhnAddressMapper/
  - app/code/Secomm/Ghn/
changes_project_state: true
created: 2026-09-10
updated: 2026-09-10
owner: [dev]
related_tickets: [TASK-RR1ZFN]
---

# [SLP][FEAT-FQWEQ3][TASK-8019VC] Phase GHN-F — Cutover: config migration, webhook flip, disable Secomm_GiaoHangNhanh + Secomm_GhnAddressMapper, dọn bảng/cột legacy

**GATE**: parity gate SPEC §41 (11 flows pass trên store thật) + QC L3 + TL/CTO sign-off riêng.
KHÔNG chạy song song với phase khác của FEAT này.

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md, FULL — đặc biệt §36 Parity Matrix, §38 Remove/Deprecate, §40 Migration Strategy, §41 Parity Gate)*

### Goal

Chuyển production từ legacy GHN sang `Secomm_Ghn` một cách tường minh (không double method/double
consume — R8), rồi dọn legacy khỏi codebase theo §38/§40.

### Expected Behavior (cutover checklist)

1. Pre-check: parity gate §41 pass trên staging store thật (11 flows); coverage mapping đạt ngưỡng
   TL; runbook rollback sẵn (re-enable legacy + flip webhook về URL cũ).
2. Config migration: `giaohangnhanh_setting/general/*` → `secomm_ghn/general/*` (script một lần,
   idempotent; token re-encrypt nếu cần).
3. Webhook flip: GHN portal staging → production URL mới `/secomm_ghn/webhook/...`; legacy endpoint
   vẫn nhận cho in-flight tới khi drain.
4. In-flight: drain queue legacy `ghn.sync.order`/`ghn.cancel.order`; orders đang có
   `tracking_code` legacy theo dõi qua legacy webhook tới delivered/closed rồi mới disable.
5. Disable legacy modules theo thứ tự `Secomm_GhnAddressMapper` → `Secomm_GiaoHangNhanh`
   (giữ code reference đến khi removal được duyệt).
6. Removal (task/PR riêng sau khi disable ổn định): drop/archive `secomm_giaohangnhanh_*`,
   `secomm_ghn_address_mapping_location`, `ghn_webhook_track`, cột `sales_order.ghn_status/
   tracking_code/ghn_canceling_status` (bản grid + cột grid remove), `quote_address.district/
   shipping_service_id/shipping_service_type_id` — giữ whitelist entry khi drop (không whitelist =
   Magento bỏ qua im lặng); KHÔNG migrate historical webhook logs trừ khi yêu cầu (§40).
7. Xóa modules `Secomm_GiaoHangNhanh` + `Secomm_GhnAddressMapper` khỏi codebase + config.php.

### Constraints / Rules

- Không rename/refactor legacy in-place (§40) — disable rồi removal riêng.
- Mỗi bước cutover có rollback path; QC L3 sau từng bước trên production-like.
- Bảng/cột drop chỉ sau khi backup + prod data scan (lesson FEAT-2PZQKJ TASK-K09G8Y).

### Out of Scope

Viết lại bất kỳ phần legacy nào · migrate GHN configuration cho carrier khác · GHTK/Ahamove refactor.

### Acceptance Criteria

- AC-F1: parity 11 flows pass staging + production.
- AC-F2: không double shipping method / double consume topic ở mọi thời điểm cutover.
- AC-F3: config migrated (token/shop_id hoạt động ở module mới); webhook mới nhận event thật.
- AC-F4: legacy disabled không phá order in-flight (track delivered/closed trước disable).
- AC-F5: removal PR: schema drop + whitelist đúng, validators + phpunit full-suite không regression mới.
