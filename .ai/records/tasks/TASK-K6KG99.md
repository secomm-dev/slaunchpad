---
id: TASK-K6KG99
type: task
title: 'GHN rate observability — destination vào log estimate + request payload khi 4xx/5xx'
project_code: SLP
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-10-02
updated: 2026-10-02
decisions: []
decision_assessment: none-material
related_work_items: [TASK-Z6SK3T]
components:
  - CMP-GHN
source_areas:
  - app/code/Secomm/Ghn/Model/Client/GhnApiClient.php
  - app/code/Secomm/Ghn/Model/Rate/GhnRateCalculator.php
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
last_verified: 2026-10-02
supersedes: []
---

# [SLP][TASK-K6KG99] GHN rate observability — destination vào log estimate + request payload khi 4xx/5xx

## Summary

Investigation 2026-10-02 (log `secomm_ghn.log`): `calculate_fee` 400 `CONFIG_FEE_NOT_FOUND`
flapping 200↔400 cùng cart — root cause là **bảng giá sandbox shop 190759 thiếu tuyến** cho
lane Hà Nội/ward "Ba Đình" (quote 424, xác định bằng truy ngược quote DB vì log không ghi
destination). Behavior code đúng frozen design (UNAVAILABLE, no fallback heavy —
DEC-TASKFXFMJ0/DEC-TASKWNQCRW), KHÔNG phải code bug. Fix đề xuất phần code: log-only
observability để lần sau xác định lane fail tức thì. Plan đã duyệt 2026-10-02 (gate Tier-2
CMP-GHN).

## Mini Spec

### Goal
Khi calculate_fee (hoặc operation POST bất kỳ) bị provider 4xx/5xx, log phải cho biết ngay
request nào/lane nào fail — không phải truy ngược quote DB.

### Expected Behavior
1. `GhnRateCalculator::fetchFeeTotal()`: log `GHN rate estimate` thêm
   `to_district_id` + `to_ward_code` (từ `GhnLocation`).
2. `GhnApiClient::parseResponse()`: khi `http_status >= 400` và debug enabled và request là
   POST — log `GHN call payload` với `operation` + `http_status` + `request` (payload đã
   qua `sanitizeContext`; token nằm ở header, không bao giờ vào payload log).

### Constraints / Rules
- Log-only — KHÔNG đổi behavior, payload contract, hay fallback/eligibility (frozen).
- Log payload chỉ ở debug-enabled (giống response payload log hiện có), qua
  `debugPayload` (sanitize sẵn).
- Tier-2 CMP-GHN: duyệt qua plan approval 2026-10-02.

### Out of Scope
- Sửa bảng giá sandbox shop 190759 (merchant action trên GHN seller portal); probe
  type 2 vs 5 cùng lane (sau khi merchant sửa bảng giá); fallback behavior (frozen).

### Acceptance Criteria
- AC-001: `GHN rate estimate` log có `to_district_id`/`to_ward_code`.
- AC-002: POST 4xx/5xx + debug on → `GHN call payload` chứa request payload; GET hoặc
  debug off → không log thêm.
- AC-003: Suite Ghn green (log-only change).

## Implementation Notes

2026-10-02: Đã implement (2 điểm log) — Ghn suite 471 green. Verify live: lần 400 tiếp theo
trên checkout sẽ hiện lane ngay trong `secomm_ghn.log`. Merchant action (bảng giá shop
190759) đang chờ — phần này ngoài code.
