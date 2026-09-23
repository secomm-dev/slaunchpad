---
id: TASK-44F7V7
type: task
title: 'GHTK staging contract probe — RATE/CREATE district semantics, TEXT_NATIVE, weight unit, ORDER_ID_EXIST, ver=1.5 (SPIKE-A1DGPY NEEDS_RUNTIME_VERIFICATION items)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec — user-directed verification task 2026-09-14; chuẩn bị + chạy khi credential available
specification_ref: Embedded Mini-Spec
risk: low                     # read-mostly probes trên official staging; CREATE probes dùng deterministic ids; 0 production code change
status: in_progress           # BLOCKED_BY_CREDENTIAL (staging token chưa có trong project config 2026-09-14) — probe kit + test data SẴN SÀNG
priority: high
decision_assessment: none-material # verification-only; capability KHÔNG freeze trước probe
decisions: [DEC-TASK7AJ3K8-002]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghtk/
changes_project_state: true
created: 2026-09-14
updated: 2026-09-14
owner: [dev]
related_tickets: [SPIKE-A1DGPY, TASK-KCXKVR, TASK-7AJ3K8]
---

# [SLP][FEAT-YA2C0W][TASK-44F7V7] GHTK staging contract probe

## Embedded Mini-Spec

### Goal

Chốt bằng runtime evidence trên OFFICIAL staging (`https://services-staging.ghtklab.com` — KHÔNG
production, KHÔNG dev.giaohangtietkiem.vn) các điểm NEEDS_RUNTIME_VERIFICATION của SPIKE-A1DGPY:
RATE/CREATE district semantics, VN_ADMIN_2025 + TEXT_NAME compatibility, products weight unit (kg),
ORDER_ID_EXIST runtime shape, `?ver=1.5` semantics, pick_address_id priority. Freeze (hoặc giữ
unresolved) capability per-operation sau probe.

### Expected Behavior

1. Probe matrix theo directive: R1 (2-level no district — HN/TP.HCM/Đà Nẵng + cấp-xã + ward
   trùng tên 5 tỉnh) vs R2 (with deterministic legacy district); C1/C2 tương tự với deterministic
   ids `secomm-probe-*`; ver=1.5 comparison; weight 200g→0.2kg; ORDER_ID_EXIST repeat same id+payload;
   pick_address_id chỉ khi staging pickup id có sẵn (else NOT_VERIFIED).
2. Mỗi probe ghi: operation, request fields, HTTP status, success, error_code, message, response
   fields chính — token/phone/địa chỉ cá nhân masked.
3. Test data dùng canonical identities THẬT từ `secomm_vietnam_address_unit` (codes + name_vi,
   provenance legacy district qua incoming PRE edges) — probe kit sẵn trong evidence dir.
4. Capability per-operation chỉ freeze khi probe chạy; nếu BLOCKED_BY_CREDENTIAL → giữ
   NEEDS_RUNTIME_VERIFICATION, không đổi architecture, không fake results.

### Constraints / Rules

- KHÔNG sửa ShippingCore/VietNamAddress/production GHTK code; probe kit nằm isolated tại
  `.ai/evidence/TASK-44F7V7/` (probe.php + runbook.md).
- Token từ env (`GHTK_STAGING_TOKEN`, `GHTK_PARTNER_CODE`) — không log, không commit.
- CREATE probes: deterministic ids, same-id-same-payload cho ORDER_ID_EXIST (không conflict test);
  cleanup bằng cancel chỉ khi safe + documented.
- Không abuse API (no spam/force 5xx/401).

### Out of Scope

ShippingCore v5 alignment implementation · mọi P1 backlog items khác · module refactor.

### Acceptance Criteria

AC-1: probe kit hoàn chỉnh + test data canonical provenance ✓. AC-2: khi có token — probe matrix
chạy được 1 lệnh, results masked tự lưu. AC-3: BLOCKED_BY_CREDENTIAL reported khi token thiếu
(hiện tại). AC-4: capability matrix output theo §21 (confidence per field). AC-5: evidence đủ để
viết task `GHTK ShippingCore v5 Alignment` ngay sau khi probe chạy thành công.
