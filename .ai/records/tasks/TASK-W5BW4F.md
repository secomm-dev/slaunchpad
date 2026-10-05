---
id: TASK-W5BW4F
type: task
title: GHN create failure surfacing — 2 lớp chặn pre-commit (deterministic) + loud mọi lỗi
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-30
updated: 2026-09-30
external_refs: {}
legacy_ids: []
ticket_ref:
decisions: [DEC-TASKW5BW4F-001]
decision_assessment: material
decision_refs: [DEC-TASKW5BW4F-001]
related_tickets: [TASK-9Q5ZAK, BUG-74VGQX]
components: [CMP-GHN, CMP-SHIPPING]
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-30
supersedes: []
---

# [SLP][TASK-W5BW4F] GHN create failure surfacing — 2 lớp chặn pre-commit (deterministic) + loud mọi lỗi

## Summary

Bug thực tế 2026-09-30 (shipment entity 17, order 21): admin nhập package 300×300×300cm
@ 48.6kg → GHN create fail pre-validation `INVALID_PARCEL` (0 HTTP) nhưng **shipment vẫn
được tạo và hoàn toàn im lặng** — observer create "không bao giờ throw", chỉ log; không
session message, không comment, không UI đọc `provider_status/provider_reason_code`; thêm
nữa snapshot lỗi được persist TRƯỚC khi validate nên retry CLI replay mãi snapshot 300cm.
Decision kiến trúc (user 2026-09-30, DEC-TASKW5BW4F-001): **2 lớp** — chặn pre-commit với
lỗi deterministic; mọi lỗi còn lại loud (message + comment + section status). PENDING-first
giữ nguyên cho transient (idempotency neo shipment id — không dời được GHN call lên trước
commit).

## Mini Spec

### Goal

- Dữ liệu parcel deterministic-invalid (thiếu/zero/limit) **không bao giờ** sinh ra shipment.
- Mọi failure của GHN create (429/network/UNKNOWN/provider) **không bao giờ silent**: admin
  thấy ngay lúc save + comment trên shipment + trạng thái trên shipment view + đường retry.

### Expected Behavior

- Fresh save GHN + rows vi phạm (vd 300cm > 200cm/cạnh) hoặc thiếu rows → save bị chặn,
  message đỏ (vd "package #1 length is 300 cm — above the 200 cm per-side limit"); KHÔNG
  shipment row, KHÔNG `secomm_ghn_shipment` row, KHÔNG snapshot.
- Shipment GHN created hợp lệ → create chạy như cũ; outcome non-success (UNAVAILABLE /
  TECHNICAL_FAILURE / UNKNOWN / COD_REJECTED) → admin error message (status + reason human +
  hint `secomm:ghn:shipment:retry <id>`) + shipment comment.
- Shipment view: section "GHN Shipment" read-only — status + reason human + ghn_order_code +
  fee + bảng packages đã xác nhận + hint retry cho FAILED/UNKNOWN. Non-GHN shipment: không
  hiện gì. Core modal "Show Packages" giữ hành vi BUG-74VGQX.
- Re-save shipment hiện hữu (comment/track/snapshot re-save) không bao giờ bị chặn bởi gate.

### Constraints / Rules

- **Single source of truth**: `GhnCreateParcelValidator` (row usability + limits) dùng chung
  bởi service (post-commit) và observer pre-save — hai gate không được drift.
- Eligibility mirror đúng create trigger: raw method prefix `secomm_ghn_`; gate pre-save chỉ
  áp dụng fresh save (`entity_id <= 0`).
- Layer 2 giữ observer "never throws" — surfacing qua notifier (presentation only, pattern
  `GhnActionOutcomeNotifier`), không mutate provider/order state.
- Không đụng idempotency (`client_order_code = GHNS<shipment_id>`), PENDING-first, retry CLI,
  schema. Không đụng core modal (BUG-74VGQX). ShippingCore không đổi code.

### Out of Scope

- CANONICAL_UNRESOLVED (address) chặn pre-commit — follow-up slice E-B/E-C.
- Nút Retry admin (controller + ACL + CSRF) — follow-up riêng.
- Dọn data dev db (snapshot 300cm của shipment 17) — việc tay.
- Full sync pre-commit create (order-derived key) — bị loại ở DEC (risk + schema migration).

### Acceptance Criteria

- **AC-001**: Fresh save GHN với package 300cm → save bị chặn, message đúng, 0 row mới trong
  `sales_shipment`/`secomm_ghn_shipment`/snapshot (E2E local).
- **AC-002**: Fresh save non-GHN (flatrate) với rows bất kỳ → pass-through (unit + E2E).
- **AC-003**: Outcome non-success → error message chứa status + reason human + retry CLI;
  shipment có comment "GHN shipment create failed (…)" (unit + observer tests).
- **AC-004**: Section "GHN Shipment" trên shipment view: shipment 17 (FAILED/INVALID_PARCEL)
  hiện status + reason + packages + hint retry; shipment non-GHN không hiện.
- **AC-005**: Suite Ghn (438) + ShippingCore (555) green; `setup:di:compile` OK; validator
  records pass (baseline 56 FAIL pre-existing không đổi).

## Steps to Reproduce (bug gốc)

1. Order GHN → admin tạo shipment, nhập Package Information weight 48.6kg, dims 300/300/300.
2. Save → shipment tạo thành công, không có thông báo lỗi nào.

## Root Cause Analysis

1. Observer create post-commit "không bao giờ throw" (`GhnShipmentCreateObserver.php:57-62`);
   outcome UNAVAILABLE → log-only (`:124-131`) — surfacing chỉ có trong log file.
2. Không validation pre-commit nào: form có note limits (`data-max-*`) nhưng không JS đọc;
   `shipment[physical_packages]` không được validate trước commit.
3. `resolvePhysicalData()` persist snapshot TRƯỚC `interpret()` → snapshot lỗi committed,
   retry CLI (`RetryShipmentCommand`) replay đúng snapshot lỗi vô ích.
4. Message thật của `GhnCreateValidationException` bị vứt ở `GhnShipmentCreationService.php`
   (chỉ giữ token vào `provider_reason_code`).
5. Không UI đọc `secomm_ghn_shipment` cho row FAILED + chưa có order code (block Actions
   render rỗng).

## Affected Files

- `Model/Shipment/GhnCreateParcelValidator.php` — mới: single source deterministic gate.
- `Model/Shipment/PostedPhysicalPackages.php` — mới: shared request reader.
- `Model/Shipment/GhnCreateReasonLabel.php` — mới: token → human text.
- `Model/Admin/GhnCreateOutcomeNotifier.php` — mới: layer 2 message + comment phrase.
- `Observer/GhnShipmentSaveValidationObserver.php` — mới: layer 1 gate (`save_before`).
- `Observer/GhnShipmentCreateObserver.php` — layer 2 (comment + notifier), dùng shared reader.
- `Model/Shipment/GhnShipmentCreationService.php` — delegate validator; bỏ private build.
- `etc/events.xml` — thêm `sales_order_shipment_save_before`.
- `Block/Adminhtml/Shipment/View/ProviderStatus.php` + `templates/shipment/view/provider_status.phtml`
  + layout `sales_shipment_view.xml` — section status.
- `i18n/{en_US,vi_VN}.csv` — phrase mới (kèm vi translation cho các message INVALID_PARCEL).

## Callers (blast radius)

- Fresh shipment save (admin) cho method `secomm_ghn_*` — giờ đi qua gate pre-save; save
  REST/API tạo shipment GHN thiếu packages cũng bị chặn (fail-loud đúng contract).
- Retry CLI: gọi service trực tiếp, không qua observer — hành vi không đổi (snapshot giờ
  luôn valid nhờ layer 1).
- Suite Ghn: 2 construction site cập nhật (service + observer constructor).

## Verification & Test Results

Xem `.ai/evidence/TASK-W5BW4F/evidence.md`. Tóm tắt (dev-complete 2026-09-30, chờ TL):

- Ghn 438 tests OK (mới: validator 5, save-validation observer 7, notifier 4, layer-2 observer 2);
  ShippingCore 555 OK; compile OK.
- E2E code-level: event dispatch save_before với fresh shipment + 300cm → BLOCK với message
  đúng; non-GHN (flatrate order 1) → pass-through; ProviderStatus trên shipment 17 → FAILED +
  reason human + packages + needsRetry.

## Notes for TL Review (Tier 2 — shipping/order)

> **TL/SA: (chờ review)** — DEC-TASKW5BW4F-001

- Thay đổi contract create-failure: INVALID_PARCEL-class giờ chặn save (trước đây log-only);
  PENDING-first giữ cho transient. Kiến trúc alternatives (full sync pre-commit / auto-hủy
  shipment) đã bị loại — lý do trong DEC.
- Gate pre-save chỉ áp fresh save — re-save (comment/track) không bao giờ bị chặn bởi snapshot
  stale; trade-off: shipment đã tồn tại với snapshot lỗi vẫn fail im-lặng-kém (giờ loud layer 2)
  chứ không tự sửa được (không có UI sửa snapshot post-creation).
- Edge: save REST/API shipment GHN thiếu packages giờ bị chặn tại save (trước đây tạo xong
  fail lặng) — đúng ý fail-loud, ghi nhận cho tích hợp nào tạo shipment programmatic.
