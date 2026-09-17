---
id: TASK-K14RVZ
type: task
title: '[UI] Shopping cart: translate browser-native validation messages (SLP-217)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-217
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-09-16
updated: 2026-09-16
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/Magento_Checkout/templates/cart-main.phtml
  - app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv
  - app/design/frontend/Secomm/launchpad/i18n/en_US.csv
source_areas:
  - theme-i18n
  - cart-page
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-16
supersedes: []
---

# [SLP][TASK-K14RVZ] [UI] Shopping cart: translate browser-native validation messages (SLP-217)

<!-- External ticket: SLP-217 (screenshots demo): trang /checkout/cart/ hiện 2 validation bubble English khi qty không hợp lệ — "Value must be less than or equal to 10000." và "Please fill out this field.". -->
<!-- Mode C — embedded Mini-Spec + Approach trong record này. -->

## Summary

2 message trong screenshot SLP-217 **KHÔNG phải Magento phrase** — là **browser-native HTML5 Constraint Validation** (Chrome sinh theo ngôn ngữ UI trình duyệt, không theo locale site). Nguồn: qty input trong vendor Hyvä `vendor/hyva-themes/magento2-default-theme/Magento_Checkout/templates/php-cart/item/default.phtml:144-165` mang `required`, `max="<?= $maxSalesQty ?>"` (10000 = default `max_sale_qty`), `pattern`, `min`, `step`, `data-role="cart-item-qty"`. Hệ quả: fix CSV/js-translation.json (mechanism SLP-225) không áp dụng được — CSV không bao giờ chứa được chuỗi của trình duyệt. Phải override text client-side qua Constraint Validation API với message đã `__()` server-side.

## Mini Spec

### Goal
Bubble validation trên qty input của cart page hiển thị theo locale store: vi_VN → tiếng Việt, en_US → English (independent của ngôn ngữ UI trình duyệt).

### Expected Behavior
1. Store vi_VN: qty rỗng + Update → "Vui lòng điền vào trường này."; qty > max → "Giá trị phải nhỏ hơn hoặc bằng {max}."; các invalid khác (pattern/step/min/badInput) → message tiếng Việt tương ứng.
2. Store en_US: các message identity English.
3. Message không bị "kẹt": sau khi user sửa giá trị thành hợp lệ, form submit bình thường (custom validity phải được clear).
4. Không đổi hành vi server-side (`checkout/cart/updatePost` vẫn tự validate `max_sale_qty`).

### Constraints / Rules
- KHÔNG copy vendor `item/default.phtml` vào child theme (minimal diff — precedent TASK-QX93G3). Script gắn ở child override `cart-main.phtml` (file đã override sẵn), listener capture-phase trên `document` (event `invalid` không bubble) scoped `[data-role="cart-item-qty"]`.
- Message translate qua `__()` + `$escaper->escapeJs()` trong phtml (server-side — KHÔNG cần SCD/js-translation.json); giữ `$hyvaCsp->registerInlineScript()` phủ script block hiện có.
- BR-001: key vào cả `vi_VN.csv` + `en_US.csv` (en mirror identity).
- Idempotent + sống sót qua `hyva.replaceDomElement('#maincontent')` (listener trên document, guard flag).

### Out of Scope
- Cart drawer (`cart-drawer.phtml`) — input `item_qty` không có `required`/`max`, JS `normalizeQty` đã xử lý → không sinh bubble bug này.
- PDP qty (`Magento_Catalog/templates/product/view/quantity.phtml`) có cùng hiện tượng → ticket riêng nếu QC cần.
- Default server validation logic / `max_sale_qty` config.

### Acceptance Criteria
- AC-001: vi_VN — inline script trên cart page render chứa message tiếng Việt; en_US — chứa identity English (server-side, curl được).
- AC-002: Live DOM store vi — qty rỗng submit → `validationMessage` = "Vui lòng điền vào trường này."; qty 10001 → "Giá trị phải nhỏ hơn hoặc bằng 10000."; sau khi sửa lại qty hợp lệ, submit thành công (không kẹt custom validity).
- AC-003: Live DOM store en — các message identity English (không regression).

## Approach

1. Thêm 6 key vào cả 2 CSV: `Please fill out this field.`, `Value must be less than or equal to %1.`, `Value must be greater than or equal to %1.`, `Please match the format requested.`, `Please enter a valid value.`, `Please enter a valid number.`
2. Trong `cart-main.phtml` (script block hiện có, trước `initCartForm`): attach `document.addEventListener('invalid', h, true)` + reset trên `input`/`change`; handler preventDefault → map `validity` state → `setCustomValidity(msg)` → `reportValidity()`.
3. `cache:flush` as secomm → verify AC-001 curl 2 store, AC-002/003 Playwright probe (/tmp/pw-cal pattern).

## Verification

- [x] AC-001 — PASS: framework 12/12 (`verify-dictionary.php`: vi 6/6 render VI, en 6/6 identity) + live vi curl render VI (dạng encodeJs unicode escapes — decode khớp)
- [x] AC-002 — PASS: live DOM vi (Playwright `verify-cart-validation.js`): empty → "Vui lòng điền vào trường này.", 10001 → "Giá trị phải nhỏ hơn hoặc bằng 10000.", valid submit → qty=2 persist (không kẹt custom validity); debug log: 0 POST updatePost khi invalid
- [x] AC-003 — PASS framework (en identity 6/6); live en BLOCKED bởi env regression ngoài scope: store 2 `launchpad_en` locale hiện = vi_VN (trước đây en_US) → `?___store` không switch (chi tiết RESULTS.md; cần khôi phục config store 2 — surface TL/DevOps)

## Implementation Notes

2026-09-16 — bắt đầu (Mode C). Phân tích đã xác nhận nguồn chuỗi = browser-native, không phải Magento phrase.

2026-09-16 — **Regression fix (QC user report "không còn validate")**. Handler v1
`preventDefault()` + `reportValidity()` có lỗi thiết kế: `reportValidity()` fire
tiếp cancelable `invalid` #2, chính capture listener tự cancel → report bị hủy →
**không bubble nào hiển thị**. Probe v1 lọt vì assert `validationMessage`
(property) chứ không phải bubble, và headless Chrome không render native bubble.
Fix: handler chỉ `setCustomValidity()` (MDN pattern — browser fire `invalid`
trước khi render bubble), bỏ preventDefault/reportValidity/round-flag. Verify v2
10/10 PASS (`verify-bubble-fix.js`): `defaultPrevented=false` (spec: browser
report) + 1 invalid event/round + message VI đúng + 0 POST khi invalid + valid
submit persist. Bubble thật = QC mắt thường trên headed browser. Memory
`magento-browser-native-validation-override` đã cập nhật pattern đúng.

2026-09-16 — DONE. Đã đổi 2 file:
- `cart-main.phtml` (child override, giữ minimal diff): IIFE mới sau IIFE `hyva.postCart` —
  capture-phase `invalid` listener trên `document` (event không bubble) scoped
  `[data-role="cart-item-qty"]`: map 6 validity state → message `__()` + `escapeJs`
  (server-side translate — không cần SCD/js-translation.json) → `preventDefault` +
  `setCustomValidity` + `reportValidity`; clear trên `input`/`change`; guard flag
  idempotent; report 1 bubble/round (requestAnimationFrame reset) cho khớp native.
- `i18n/vi_VN.csv` + `i18n/en_US.csv`: +6 key (valueMissing/rangeOverflow/
  rangeUnderflow/patternMismatch/stepMismatch/badInput; en mirror identity).

Verify PASS chi tiết `.ai/evidence/TASK-K14RVZ/RESULTS.md`: framework 12/12 vi/en;
live DOM vi 3/3 case (Playwright, browser UI en-US để chứng minh override độc lập
ngôn ngữ trình duyệt); baseline full-flow với change DISABLED tái hiện cùng bộ
pageerror swatch + banner form-key ExtraFee → noise pre-existing, không của change set.

**Finding env (out of scope, surface):** store 2 `launchpad_en` locale = vi_VN trên
local (regression sau 09-14 khi en còn PASS) → verify live-en không thực hiện được;
cần khôi phục `general/locale/code` en_US cho store 2. Không phải do change set
(đã `cache:flush`, nguyên nhân ở data store config).

**Finding cho QC/TL:** ảnh demo SLP-217 "Value must be less than or equal to
10000." — 10000 là `max_sale_qty` default của Magento; message browser-native
nên CHROME localize theo ngôn ngữ UI trình duyệt, không theo store — user Chrome
VI có thể thấy sẵn tiếng Việt; change set làm nó độc lập ngôn ngữ trình duyệt.

Status: chờ TL review (Mode C).

2026-09-16 — **Commit `a0865309`** (SLP-217, `dev/development/anhchong`) — 18 files/651
insertions: cart-main.phtml + 2 CSV hunks (6 key/file) + record + evidence +
estimation row (dòng riêng — row TASK-8TXS2P của session song song giữ unstaged
bằng kỹ thuật hash-object HEAD+1-line, pattern BUG-KQ5A1D). Chưa push — chờ TL.
