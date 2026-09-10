---
id: BUG-MNEZ92
type: bug
title: "[SLP][BUG-MNEZ92] [Header][Account][UI] Dropdown tài khoản bị cắt phải ở 1440x900/1024x768 — root cause CSS demo stale, không cần đổi code"
project_code: SLP
parent:
external_refs:
  ticket: SLP-186
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-09
updated: 2026-09-09
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyva 3.x (demo env static deploy 2026-09-07 10:42 +07)
decisions: []
decision_assessment: pending-tl-deploy-demo
components:
  - app/design/frontend/Secomm/launchpad/Magento_Customer/templates/header
source_areas:
  - theme-templates
  - deployment-env
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: 6586660e
last_verified: 2026-09-09
supersedes: []
---

# [SLP][BUG-MNEZ92] [Header][Account][UI] Dropdown tài khoản bị cắt phải ở 1440x900/1024x768 — root cause CSS demo stale, không cần đổi code

<!-- External ticket: SLP-186. Screenshot QC (demo, logged-in): dropdown tài khoản mở xổ ra và bị cắt mất mép phải viewport ở 1440x900 và 1024x768. -->

## Summary

Dropdown tài khoản ở header bị cắt mép phải trên demo ở 1440x900 + 1024x768. Điều tra xác minh (fetch demo + Playwright local): **không phải bug code** — fix SLP-129 (commit `082ba2d6`, 09-08) đã đúng và local PASS ở cả 2 viewport bị report. Trên demo, **static deploy cũ hơn styles.css rebuild trong commit**: markup đã có class `sm:left-auto sm:-me-4` nhưng CSS demo **thiếu 2 rule tương ứng** → `left-0` thắng over-constrained resolution ở mọi breakpoint → dropdown mở sang phải từ icon account → tràn mép phải.

## Mini Spec

### Goal
- Dropdown tài khoản hiển thị đúng trên demo ở mọi viewport (mở neo phải icon ≥sm, trọn viewport <sm).
- Xác định rõ root cause + không thay đổi code theme nếu code HEAD đã đúng.

### Constraints / Rules
- Không sửa template/CSS nếu evidence chứng minh code HEAD đúng (tránh xung đột với BUG-H929MC đang chờ TL review cùng template).
- Deploy demo là human action (AI không deploy — §8.2).
- Tailwind v4 CSS-first; class trong template phải có rule trong `styles.css` đã build + đã deploy.

### Out of Scope
- Bổ sung bước verify CSS-deployed vào deployment checklist (đề xuất process — TL quyết, xem Notes).
- Fix CRLF `.ai/bin/project-ai-idgen` (finding phụ — report TL).
- Phần menu Snowdog data của BUG-H929MC (AC-004 riêng của nó).

### Acceptance Criteria
- AC-001: Demo sau redeploy (≥ `082ba2d6` + static-content:deploy + flush): 1440x900 + 1024x768, guest + logged-in — dropdown neo phải icon (right = button right + 16px), trọn viewport, không cắt. ⏳ chờ redeploy demo (human).
- AC-002: Không có diff code theme từ ticket này — root cause là env/deploy. ✅ (record chỉ tạo evidence + doc)
- AC-003: Local PASS ở viewport bị report + regression SLP-129. ✅ Playwright: 1440/1024 fits + stock gap 0.0; 1280/375 regression PASS; console sạch.

## Root Cause

Demo `slaunchpad-demo.secomm.vn` (fetch 2026-09-09):

1. **Markup ĐÃ mới**: `<nav class="z-10 absolute left-0 sm:left-auto right-0 w-40 sm:w-48 … sm:-me-4 …">` — demo đã deploy template từ commit `082ba2d6`.
2. **CSS ĐÃ cũ**: `styles.css` demo (`static/version1788752540/…`) **thiếu `.sm\:left-auto` + `.sm\:-me-4`** (chỉ còn `.lg\:mt-3` từ build trước). Static version timestamp `1788752540` = **2026-09-07 10:42 +07** — trước ngày commit 09-08.
3. **Cơ chế cắt**: absolute nav đặt cả `left` + `right` + width cố định → over-constrained; LTR bỏ `right` khi `left: 0` còn hiệu lực → dropdown mở sang phải từ mép icon account (width 160/192px > khoảng cách icon→mép phải ~145px @1440) → cắt mép phải. Tái hiện ở cả 1024 vì icon luôn sát mép phải.

Local (HEAD `6586660e`) không lỗi vì `styles.css` đã build 09-08 có đủ 2 rule.

## Fix / Disposition

**Không đổi code.** Fix là deploy-side:

1. Redeploy demo từ `origin/development` (chứa `082ba2d6`): pull code → `npm run build` (tailwind) → `bin/magento setup:static-content:deploy vi_VN en_US` → `bin/magento cache:flush`.
2. QC verify AC-001 sau redeploy.
3. Đề xuất TL: thêm bước verify CSS-deployed vào deploy checklist (grep rule class Tailwind mới trong `styles.css` deployed) — lỗi "code đúng, deploy stale CSS" tái diễn với mọi ticket UI đổi class. Cân nhắc `.gitattributes` (`*.sh text eol=lf`) + fix CRLF `.ai/bin/project-ai-idgen`.

## Verification

- Demo A/B: markup có class / CSS thiếu rule / timestamp cũ hơn commit — `.ai/evidence/BUG-MNEZ92/demo-env-check.txt`, `demo-nav-markup.txt`, `demo-styles.css`, `RESULTS.md`.
- Local Playwright 4 viewports PASS — `customer-menu-viewport-check.js` + `dropdown-*.png` + bảng số đo trong `RESULTS.md`.
- Memory stale đã sửa: CURRENT_STATE/NEXT_TASK ghi BUG-H929MC "chưa commit" → đã commit `082ba2d6`.

## Notes cho TL review

- **Quyết định cần TL**: (1) duyệt redeploy demo (DevOps/human) để đóng AC-001; (2) duyệt/từ chối đề xuất process verify-CSS-deployed; (3) SLP-186 có nên đóng trùng/merge với SLP-129 trong PM tool (cùng gốc lỗi, record riêng để giữ vết điều tra).
- Regression risk: **none** — không có thay đổi code.
