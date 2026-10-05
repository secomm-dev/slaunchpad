---
id: TASK-52CKN3
type: task
title: '[My Account][UI/UX] Form địa chỉ — select Quốc gia/Tỉnh full width + mở padding-under-sticky-header cho mọi trang account (SLP-305)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-305
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-10-05
updated: 2026-10-05
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/Secomm_AddressDropdown/templates/hyva/address/edit.phtml
  - app/design/frontend/Secomm/launchpad/web/tailwind/components/wrapper.css
source_areas:
  - app/design/frontend/Secomm/launchpad/Secomm_AddressDropdown
  - app/design/frontend/Secomm/launchpad/web/tailwind
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-10-05
supersedes: []
---

# [SLP][TASK-52CKN3] [My Account][UI/UX] Form địa chỉ — select Quốc gia/Tỉnh full width + mở padding-under-sticky-header cho mọi trang account (SLP-305)

<!-- External ticket: SLP-305 (session audit + QC feedback kèm screenshot). Task kế tiếp TASK-2BASRX (SLP-300) — record đó ghi rõ trong Out of Scope: "Các trang account khác... chỉ cần mở selector". -->
<!-- Mode C — embedded Mini-Spec + Approach trong record này. -->

## Summary

QC feedback trên My Account (2 screenshot, 2026-10-05):

1. **Select chưa full width** — form "Chỉnh sửa địa chỉ" (`customer/address/edit`): select "Quốc gia" và "Tỉnh/Thành phố" co theo độ dài option (~55%/35% cột) trong khi mọi input khác (`form-input w-full`) dãn full cột. Trang render `Secomm_AddressDropdown::hyva/address/edit.phtml` qua `setTemplate` trong `hyva_customer_address_form.xml` (KHÔNG phải vendor Hyva edit.phtml — theme override vendor sẽ không có tác dụng). Trong template module, country (L174), region (L205), city (L248) là `<select class="form-select">` thiếu `w-full` — `.form-select` base (utility `form-input-field`) không set width nên select co theo nội dung.
2. **Thiếu padding dưới sticky header** — page "Thông tin tài khoản" (`customer/account/edit`): content dính sát header 64px. Fix TASK-2BASRX (SLP-300) scoped `body.customer-account-index` (dashboard) — record đó liệt kê trang còn lại là out-of-scope "chỉ cần mở selector".

## Mini Spec

### Goal
Form địa chỉ My Account hiển thị đúng contract form của style guide; mọi trang có sidebar account có khoảng thở với sticky header.

### Expected Behavior
1. Country/region/city select trên form địa chỉ dãn full cột (`form-select w-full`) khớp mọi input cùng form.
2. Mọi trang render sidebar account (`.account-nav`): `main` + `.columns` có padding-top 24px — dashboard, account edit, address book/new/edit, order history/view, reviews, wishlist, newsletter, RMA extension.
3. Không đổi: markup/logic cascade GraphQL, validation, các field khác, i18n, theme `launchpad_fashion`.

### Constraints / Rules
- KHÔNG sửa module `Secomm_AddressDropdown` in place — override template vào theme (`Secomm_AddressDropdown/templates/hyva/address/edit.phtml`), pattern như 7 override còn lại của theme. §12 liệt kê AddressDropdown là Tier-2 (address capture): change chỉ là CSS class trong theme override, không đụng cascade/GraphQL/persistence; vẫn cờ cho TL review.
- Padding scoped theo `main:has(.account-nav)` thay vì enumerate body class — RMA extension page ("My RMA Requests") có sidebar nhưng body class không lường trước; `:has()` hỗ trợ Chrome 105+/Safari 15.4+/Firefox 121+.
- `w-full` đã có trong bundle (dùng nhiều nơi); không đụng `@source`.
- Không chạm payment/checkout/OSC — template này chỉ dùng cho block `customer_address_edit` (handles `customer_address_form`/`customer_address_edit`).

### Out of Scope
- Audit style guide SLP-305 tổng thể (gap Review pagination, Vault, bare CTA...) — báo cáo đã trình, chờ TL chốt scope thành work item riêng.
- Các trang không có sidebar account (forgot/reset password, login, confirmation) — spacing hiện tại chấp nhận được, không thuộc feedback QC.
- Theme `launchpad_fashion` (scaffolded).

### Acceptance Criteria
- AC-001: Theme override `Secomm_AddressDropdown/templates/hyva/address/edit.phtml` tồn tại; country/region/city select có `w-full`; module source không bị sửa.
- AC-002: `wrapper.css` rule padding scoped `main:has(.account-nav)`; compiled CSS + asset serve chứa rule; build Tailwind pass.
- AC-003: Visual (QC/TL): form địa chỉ — 3 select full cột khớp input; mọi trang account (dashboard, edit, address, orders...) content cách header ~24px, không page nào bị lệch layout.

## Approach

1. Copy `Secomm_AddressDropdown/view/frontend/templates/hyva/address/edit.phtml` → theme override, thêm `w-full` cho 3 select, header comment ghi task + lý do.
2. Generalize 2 rule TASK-2BASRX trong `wrapper.css`: `body.customer-account-index main ...` → `main:has(.account-nav) ...` (giữ guard `:not(.product-main-full-width, .page-main-full-width)`).
3. Build Tailwind as secomm; verify asset serve (vi/en); cache:flush; assert template resolution CLI (theme set tường minh).
4. Evidence `.ai/evidence/TASK-52CKN3/`; record → `in_review` (AC-003 visual chờ QC/TL).

## Verification

- [x] AC-001 — override tồn tại; 3 select có `w-full`; `app/code/Secomm/AddressDropdown` unchanged (resolver CLI: THEME OVERRIDE YES, resolved về theme dir)
- [x] AC-002 — `npm run build` pass; compiled CSS chứa `main:has(.account-nav)...{padding-top:calc(var(--spacing)*6)}`; serve vi_VN rule-count=1, en_US HTTP 200 (curl Host-header qua 127.0.0.1:80, HTTP — 443 không listen ở env này)
- [ ] AC-003 — visual verify trên browser (QC/TL)

## Implementation Notes

2026-10-05 — DONE (dev + self-review), chờ TL review. Chi tiết `.ai/evidence/TASK-52CKN3/RESULTS.md`:
- Template override: verbatim copy + `w-full` ×3 (country/region/city) + header comment; module untouched; IDE diagnostics trên `$addressViewModel->isEnabled()`... là false-positive Intelephense (`__call` magic methods), tồn tại sẵn ở bản gốc.
- `wrapper.css`: 2 rule `body.customer-account-index` → `main:has(.account-nav):not(...)` (dashboard vẫn covered — selector mới là superset).
- Build 880ms pass; static vi_VN là symlink (không có stale); cache:flush done.
- Verify CLI: resolver trả theme path; `:has(.account-nav)` có trong compiled CSS.
- Scope check working tree: `db_schema.xml` (Ahamove), `config.php`, `hyva-themes.json`, `vendor_path.php`, `bin/magento`, `SizeGuide/`, banner/tablerate media — task khác, không đụng tới.

2026-10-05 — pre-commit rebase (quan trọng cho TL): phát hiện task song song **TASK-Z6SK3T** đang sửa chính module template (`app/code/Secomm/AddressDropdown/.../hyva/address/edit.phtml`, +33/-2 chưa commit — GraphQL `GetListCity` → `addressLocations` + page-session cache, JS-only). Bản override đầu tiên đã vô tình copy bản working-tree chứa change đó → **đã rebuild override từ HEAD** của module template + áp lại đúng diff của task này (10 dòng comment + 3 dòng `w-full`, verified bằng diff). Hệ quả cần TL lưu ý: theme override **shadow** module template — khi TASK-Z6SK3T commit, JS rework của nó KHÔNG có tác dụng trên customer address page cho tới khi merge vào override này (hoặc gỡ override khi width fix đã vào module). Ghi chú này cũng nằm trong header comment của override. styles.css diff đã verify = chỉ đúng rule selector mới (delta 1 dòng minified).
