---
id: BUG-8G0WCT
type: bug
title: Cart extra fee validation ignores rule Is Required config (JS truthy check on string "0")
project_code: SLP
parent:
external_refs:
  SLP-244: "[Extra Free] Shopping cart: validate chon extra fee nên như admin config"
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-18
updated: 2026-09-18
ticket_ref: SLP-244
affects_version: Magento 2.4.8-p5
decisions: []
decision_assessment: none
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/Mageplaza_ExtraFee
source_areas:
  - checkout
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-18
supersedes: []
---

# [SLP][BUG-8G0WCT] Cart extra fee validation ignores rule Is Required config

<!-- CANONICAL RECORD — Mode C (small bug, theme template only). Embedded Mini-Spec. -->
<!-- Spec VALID 2026-09-18 — user approved approach ("thực thi") sau Ticket Analysis trong chat. -->
<!-- Lưu ý §11: ExtraFee nằm trong danh sách Tier 2 (checkout flow) — TL code review bắt buộc trước merge. -->

## Summary

Trang giỏ hàng chặn điều hướng checkout với message "Trường này là bắt buộc." cho rule extra fee dù admin đặt **Is Required = No**. Root cause (đã verify bằng API probe trên local 2026-09-18): Magento webapi trả mọi field dạng **string** — `"is_required": "0"` — trong khi template cart so sánh **truthy** (`rule.is_required ? ... : ...`). Trong JS, chuỗi `"0"` là truthy nên class `mp-extra-fee-required` + attr HTML5 `required` **luôn được gắn bất kể config**; handler click `#checkout-link-button` vì thế luôn chặn. Chiều Yes (`"1"` truthy) vô tình đúng nên bug chỉ lộ ở chiều No.

## Evidence (root cause)

- DB local: `mageplaza_extrafee_rule.is_required` = `int 0` (rule 1 sau khi UPDATE).
- `POST /V1/guest-carts/{id}/mpextrafee` → `"is_required": "0"` (string) — dump: `.ai/evidence/BUG-8G0WCT/rules_required0.json`.
- JS semantics: `JSON.parse('"0"')` truthy (node check).
- Vendor checkout templates dùng `rule.is_required == '1'` (đúng) — chỉ **cart templates** dùng truthy-check → upstream cũng xác nhận pattern chuẩn là `== '1'`.
- PDP templates không dùng `is_required` — không ảnh hưởng.

## Mini Spec

### Goal

Cart validation extra fee phản ánh đúng `is_required` của rule trong cả hai chiều: **No → không chặn checkout; Yes → chặn + message**.

### Expected Behavior

- Rule `is_required = 0` (No): không gắn class `mp-extra-fee-required`, không gắn attr `required`; click "Tiến hành thanh toán" điều hướng bình thường.
- Rule `is_required = 1` (Yes): gắn class + attr; click checkout khi chưa chọn option → message "This is a required field." (đã i18n sẵn trong cả 2 CSV) + chặn điều hướng.

### Affected Files

- `app/design/frontend/Secomm/launchpad/Mageplaza_ExtraFee/templates/hyva/cart/extra-fee.phtml` — 2 điều kiện:
  - `:class="rule.is_required ? 'mp-extra-fee-required' : ''"` → `rule.is_required == '1'`
  - `:required="rule.is_required ? 'required' : false"` → `rule.is_required == '1'`
- Không sửa vendor `app/code/Mageplaza/**` (third-party — override/extend only).
- Out of scope (report riêng cho TL): vendor checkout templates dòng `:required="rule.is_required ? ..."` (line ~69) cùng pattern truthy — ảnh hưởng nhẹ (attr HTML5 sai khi No), xem xét theme override sau.

### Constraints / Rules

- `== '1'` (loose compare với string) — khớp pattern vendor checkout templates, an toàn khi webapi trả string hoặc int, và resync-friendly khi upgrade module.
- Giữ nguyên mọi vendor logic khác trong override; giữ `__()` i18n + note "re-sync on upgrade" của override SLP-144.
- Không đổi i18n CSV (phrase đã có sẵn cả 2 file).
- Không chạm flow checkout OSC / payment / server-side totals.

### Test Plan

1. API probe (đã làm): response `"is_required": "0"`/`"1"` dạng string ở cả 2 cấu hình.
2. Browser (Playwright, store `default`): rule No → click checkout → điều hướng; rule Yes → click checkout chưa chọn → message + đứng lại; chọn 1 option → click → điều hướng.
3. Smoke: PDP extra fee + trang checkout OSC render bình thường (không đổi code nhưng phải chắc không regression).

## Test Results (2026-09-18 — local, Playwright headless)

| Case | `is_required` DB | API value | Class/attr required | Click checkout | Kết quả |
|---|---|---|---|---|---|
| no | 0 | `"0"` (string) | 0 / null | điều hướng → `/onestepcheckout/` | **PASS** |
| required | 1 | `"1"` (string) | 1 / `"required"` | chặn + "Trường này là bắt buộc." | **PASS** |
| required-select | 1 (đã tick 1 option) | `"1"` | 1 / `"required"` | điều hướng → `/onestepcheckout/` | **PASS** |

- Rule local restore về `is_required = 1` (trạng thái as-found) sau test.
- Ambient noise (pre-existing, không liên quan): console `Error fetching data: Unexpected token '<'` ×2–3 và banner "Khóa biểu mẫu không hợp lệ" khi đứng lại trên cart — đã ghi nhận sẵn trong TASK-K14RVZ verify notes; extra fee block + collect total hoạt động bình thường ở cả 3 case.
- Evidence: `.ai/evidence/BUG-8G0WCT/` — API dumps (`rules_required0/1.json` — `"is_required"` dạng string), 3 screenshots, verify script.

## Follow-up (out of scope — cần TL quyết)

- Vendor checkout templates (`hyva/checkout/{order-summary,shipping,payment}/extra-fee.phtml` line ~69) vẫn còn `:required="rule.is_required ? ..."` (truthy) — attr HTML5 sai khi No, nhưng class `mp-extra-fee-required` ở đó đã dùng `== '1'` nên flow chính không chặn nhầm. Nếu muốn nhất quán: theme override 3 file (bật scope riêng).
