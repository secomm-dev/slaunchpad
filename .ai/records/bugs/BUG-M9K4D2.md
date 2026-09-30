---
id: BUG-M9K4D2
type: bug
title: '[COD Risk] Editing a Risk Lists record created a NEW row instead of updating (DataObject::setData wipes entity_id); duplicate records were never validated'
project_code: SLP
parent:
external_refs:
  tickets: TASK-YPWH9B
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: high
status: in_review
created: 2026-09-30
updated: 2026-09-30
ticket_ref: TASK-YPWH9B
affects_version: Magento 2.4.8-p5
decisions: []
decision_assessment: none-material
components:
  - app/code/Secomm/CodRisk/Model/Service/ListManager.php
source_areas:
  - admin
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-30
supersedes: []
related: TASK-YPWH9B
---

# [SLP][BUG-M9K4D2] [COD Risk] Editing a Risk Lists record created a NEW row instead of updating; duplicate records never validated

<!-- Found during TASK-YPWH9B self-test round 2 (2026-09-30). Source of the duplicate rows polluting the test DB. -->

## Mini Spec

### Goal

Edit record Risk Lists phải UPDATE đúng dòng; hệ thống chặn mọi biến thể trùng lặp: cùng phone+type+website (kể cả khác reason), và phone+type ghi đè chéo website (0 = All Websites overlap mọi website).

### Expected Behavior

- Edit → save → **UPDATE đúng dòng** (Audit Log ghi `updated`, không phát sinh row).
- Add trùng: cùng type → lỗi "already exists in the %1 list"; khác type khi đã có active → lỗi yêu cầu Deactivate/sửa bản ghi cũ.
- Conflict check chỉ áp dụng khi record **kết thúc active và chưa chiếm chỗ trước đó** (mới / activate bản inactive). Edit bản đang active (đổi reason/status/note — kể cả legacy duplicate) luôn cho phép.
- Deactivated record không chặn add lại.

### Constraints / Rules

- `DataObject::setData(array)` **thay toàn bộ `_data`** (`vendor/magento/framework/DataObject.php:80-81`) — trên model đã `load()` sẽ mất `entity_id` → `save()` thành INSERT. Model đã load phải dùng `addData()`.
- Conflict identity = normalized_phone + list_type + website scope (0 overlap mọi website); Reason là thuộc tính, không phải định danh (PO confirmed 30/09).
- Quick-add từ Order View không gửi `is_active` → default Active.

### Out of Scope

Bulk dedup tool — legacy duplicates trong DB test được dọn tay 1 lần (SQL) sau khi fix.

### Acceptance Criteria

- AC-01: Edit (đổi reason/status, kể cả Active→Inactive) → grid KHÔNG phát sinh row; Audit = `updated`.
- AC-02: Add trùng cùng website → lỗi; Add khi đã có record ở website 0 hoặc cụ thể overlap → lỗi.
- AC-03: Deactivate rồi Activate khi twin active → bị chặn (tránh tái tạo dup); twin inactive → cho phép.
- AC-04: Quick-add Order View vẫn tạo record Active bình thường.

## Root Cause & Fix

1. **Edit → INSERT**: `ListManager::saveRecord` dùng `$record->setData([...])` trên model đã load → setData(array) thay cả `_data` (DataObject.php:80) → mất `entity_id` → save thành INSERT. Fix: `addData([...])` (merge, giữ id). Toàn module quét lại — các `setData` khác đều trên model mới hoặc gán đơn key (an toàn).
2. **Thiếu validate duplicate** (Bug 3/4 của QC): thêm `assertNoActiveConflict` — cùng phone + website scope (0 overlap) + is_active=1, exclude self; cùng type → lỗi trùng, khác type → lỗi yêu cầu gỡ bản cũ.
3. **Conflict check chặn nhầm edit/deactivate** (legacy dup): gate check theo trạng thái kết thúc (chỉ khi new/activate).

## Verification

- AC-01…AC-04: PASS (user-verified 2026-09-30 sau deploy; legacy dup dọn bằng SQL). Regression: quick-add/record event hoạt động bình thường.
