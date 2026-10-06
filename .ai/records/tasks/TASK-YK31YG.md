---
id: TASK-YK31YG
type: task
title: 'Footer Newsletter — fix lệch layout khi validate (SLP-306)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-306
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-10-05
updated: 2026-10-05
ticket_ref: SLP-306
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions:
  - 'Fix = lg:items-start + lg:mt-0 trên field div; KHÔNG dùng absolute-position cho error và KHÔNG sửa global CSS'
decision_assessment: none-material
components:
  - app/code/Launchpad/CmsContent
source_areas:
  - hyva-form-validation
  - tailwind-v4-css-first
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-10-05
supersedes: []
---

# [SLP][TASK-YK31YG] Footer Newsletter — fix lệch layout khi validate (SLP-306)

## Summary

Khi validate email fail trên footer newsletter, nút **Đăng ký** bị tụt xuống ~11–13px so với input (screenshot ticket): error `<ul class="messages">` do `hyva.formValidation` chèn **bên trong** `.field.field-reserved` làm div field cao thêm, trong khi form đang `lg:items-center` nên button h-11 bị re-center theo field cao hơn.

**Root cause (2 yếu tố cộng lại):**
1. `lg:items-center` trên `<form>` ([subscribe.phtml](../../../app/code/Launchpad/CmsContent/view/frontend/templates/newsletter/subscribe.phtml)) — button re-center khi field cao lên.
2. `hyva.formValidation` (vendor `magento2-theme-module` `advanced-form-validation.phtml`) chèn error `ul.messages` vào trong `.field.field-reserved` (wrapper pre-wrapped trong template chính để tránh component tự wrap).

**Fix:** form → `lg:items-start`; field div thêm `lg:mt-0` để triệt tiêu `.field { @apply mt-1 }` (4px) từ `web/tailwind/components/forms.css` — nếu không, `items-start` khiến button cao hơn input 4px (đã đo được trong probe). Cả hai utility đã có sẵn trong CSS compiled → **không cần rebuild Tailwind, không cần SCD** (checksum published styles.css = source).

## Mini Spec

### Goal
Nút Đăng ký giữ nguyên vị trí thẳng hàng với input khi validation error hiển thị; error nằm dưới input, đẩy content dưới form xuống theo flow.

### Out of scope
- Subscribe flow (controller/action/form_key) — không đổi.
- JS translation EN store hiển thị message VI (quan sát tách riêng, xem "Findings ngoài scope").
- Các form khác dùng `.field` — không sửa global CSS/JS nên không ảnh hưởng.

## Changes

- [app/code/Launchpad/CmsContent/view/frontend/templates/newsletter/subscribe.phtml](../../../app/code/Launchpad/CmsContent/view/frontend/templates/newsletter/subscribe.phtml)
  - `lg:items-center` → `lg:items-start` trên `<form>` (SLP-306).
  - Field div: thêm `lg:mt-0` (counter `.field` mt-1 của forms.css ở desktop).
  - Comment header ghi quyết định + lý do đo được.

## Verification (2026-10-05)

Probe Playwright: `.ai/evidence/TASK-YK31YG/` (scripts + `probe-output.txt` + screenshots). 12/12 PASS:

| Case | Kết quả |
|---|---|
| vi desktop 1440 — error visible | delta button↔input = **0px**; error dưới input; message đúng CSV line 58 |
| vi desktop 1440 — sim `items-center` (before) | delta **+13px** → tái hiện đúng bug screenshot |
| vi mobile 375 | stack đúng, không overflow ngang (scrollWidth 368 < 375) |
| valid email | `field-success`, không error state |
| EN desktop (cookie `store=launchpad_en`) | geometry PASS — lưu ý cookie không switch store phiên này (button vẫn "Đăng ký"), xem findings |

Cache: `cache:flush full_page` sau khi sửa template (as secomm).

## Findings ngoài scope (chuyển ticket nếu cần)

1. **EN store JS/PHP translation**: error message hiển thị TIẾNG VI trên storefront; probe cookie `store=launchpad_en` không switch được store local phiên này (button label "Đăng ký" dù `en_US.csv` map đúng "Subscribe","Subscribe"; `pub/static/frontend/Secomm/launchpad/` chỉ có `vi_VN`). Khả năng liên quan js-translation.json / SCD theo locale (mục trap đã ghi trong memory). Cần ticket i18n riêng nếu EN store phải chạy thật.
2. Template này dùng chung cho footer + homepage newsletter (patch `CreateHomepageNewsletterBlock`); hiện homepage chỉ render 1 instance (footer) — fix áp dụng cho mọi placement sau này của template.

## Effort thực tế

~1.5h (analyze + fix 2 class + verify probe 3 viewport/store + evidence + record). Estimate ban đầu 2–4h.
