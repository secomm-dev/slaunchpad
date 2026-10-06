---
id: TASK-6YJGRV
type: task
title: '[My Account][UI/UX] Link "action back" → btn-tertiary theo style guide (SLP-305)'
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
created: 2026-10-06
updated: 2026-10-06
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/web/tailwind/components/actions-toolbar.css
source_areas:
  - app/design/frontend/Secomm/launchpad/web/tailwind
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-10-06
supersedes: []
---

# [SLP][TASK-6YJGRV] [My Account][UI/UX] Link "action back" → btn-tertiary theo style guide (SLP-305)

<!-- External ticket: SLP-305. QC feedback 06-10 kèm screenshot: "Quay lại" render plain text link (blue underline) cạnh primary "Lưu" trên form địa chỉ. -->
<!-- Mode C — embedded Mini-Spec + Approach trong record này. Task kế tiếp TASK-52CKN3. -->

## Summary

`a.action.back` là legacy Luma pattern chưa map vào hệ `btn` của style guide — rule duy nhất trong `actions-toolbar.css` là treatment `@deprecated` (`@apply underline`). Convention markup của MỌI template dùng pattern này (3 theme override: sharing, forgotpassword, address edit override; 14 vendor account-area: form/edit, register, newsletter, confirmation, address/grid, order view/invoice/creditmemo, wishlist view/sharing/shared, downloadable list) đều là `.actions-toolbar > .secondary > a.action.back` → 1 rule CSS cover toàn bộ theo đúng cơ chế CSS-remap của style guide (như `card`, `form-input`, checkbox element-type).

## Mini Spec

### Goal
Mọi link back trong `.actions-toolbar` render theo đúng pattern secondary action của theme: **text link underline màu ink** — đồng nhất với reference "Xem tất cả" (`order/recent.phtml` `action view inline-block underline`), và không bị rule Luma legacy của third-party đổi màu trên hover/press.

### Expected Behavior
1. `a.action.back` trong `.actions-toolbar`: text link underline, `color: inherit` (ink), không border/background.
2. Hover/press/focus: giữ ink + underline (`!important` re-assert chặn `.back:hover` Luma blue `#006bb4` / `.back:active` cam `#ff5501` của Mageplaza legacy CSS).
3. Không đổi markup template nào (0 override mới); không đụng `a.action` khác (edit/delete inline links giữ nguyên).

### Constraints / Rules
- CSS-only trong `actions-toolbar.css` — không chạm template, không chạm vendor, không sửa Mageplaza module (third-party).
- Không dùng hệ `btn` — design intent cho back là text link (QC round 3 chốt theo reference "Xem tất cả"), variant button chỉ dùng cho primary CTA trong toolbar.
- Không chạm §12; display-only.

### Out of Scope
- Variant `btn-secondary` thay `btn-tertiary` nếu Figma quy định khác — đổi 1 class trong 1 rule (cờ cho TL).
- `action back` nằm ngoài `.actions-toolbar` — audit cho thấy không có case nào.
- Audit SLP-305 tổng thể (Review pagination, bare CTA, Vault...) — chờ TL chốt scope.

### Acceptance Criteria
- AC-001: Rule `.actions-toolbar a.action.back` có trong compiled CSS với base `btn` (36px, touch target, text-decoration:none) + tertiary vars + `:hover`/`:focus-visible`/`:is(:disabled)` đầy đủ.
- AC-002: Asset serve (curl vi_VN) chứa rule.
- AC-003: Visual (QC/TL): "Quay lại" = tertiary button 36px cạnh "Lưu"; back links các trang account khác (order view, wishlist, address book, register, newsletter...) cùng look.

## Approach

1. Replace rule deprecated trong `actions-toolbar.css` bằng `& a.action.back { @apply btn btn-tertiary }` + comment task.
2. Build Tailwind as secomm; verify compiled (base block + tertiary block, states); verify serve.
3. Xử lý stale pub/static (xem Implementation Notes); record + evidence → `in_review`.

## Verification

- [x] AC-001 — compiled: `.actions-toolbar a.action.back{text-decoration:underline}` + grouped `:hover/:focus-visible/:is(:active,.is-active){color:inherit!important;text-decoration:underline!important}`; không còn btn vars (round-3 final)
- [x] AC-002 — serve vi_VN `a.action.back{text-decoration:underline}` count=1
- [ ] AC-003 — visual verify (QC/TL): "Quay lại" = text link underline màu ink, hover không blue, giống "Xem tất cả"

## Implementation Notes

2026-10-06 — DONE (dev + self-review), chờ TL review. Chi tiết `.ai/evidence/TASK-6YJGRV/RESULTS.md`:
- CSS-only, 1 rule; build pass 476ms.
- **Serve stale trap (mới)**: sau build #2, curl trả nội dung build #1 — `pub/static/.../vi_VN/css/styles.css` bị thay symlink bằng **real-file copy với nội dung TRƯỚC build** (step `generate`/hyva-sources materialize trước khi tailwindcss ghi file mới; md5 pub/static ≠ web/css). Fix: `rm` real file → dev mode materialize lại symlink → serve đúng. Đã append vào memory [[magento-scd-publisher-skip-existing]].
- Scope check: không đụng file task khác.
2026-10-06 — round 3 (QC feedback: "nút quay lại nên có style giống nút Xem tất cả" — variant chốt lại): reference thực của theme cho secondary action trong toolbar là "View All" ở `order/recent.phtml:54` (`action view inline-block underline`) = **text link underline màu ink, không box** — round-1 chọn `btn-tertiary` là sai variant (suy diễn pairing primary/outline; design không định nghĩa back = button). Rule cuối: `.actions-toolbar a.action.back{text-decoration:underline}` + grouped states `color:inherit!important;text-decoration:underline!important` (giữ re-assert chặn Mageplaza Luma legacy). Bài học: khi style-guide hóa legacy pattern, tìm reference CÙNG LOẠI action đã có trong theme/vendor trước khi chọn variant từ button spec — "Quay lại" (navigation) ≠ CTA form. Rule change đã đồng bộ vào Mini Spec (Goal/Expected/Constraints) + Verification AC-001/002.


2026-10-06 — round 2 (QC feedback: "không giống style guide" — underline vẫn hiện trên hover): root cause là **Mageplaza_SocialLogin** `view/frontend/web/css/hyva/style.css:74-82` ship rule Luma-legacy bare-class **`.back:hover{color:#006bb4!important; text-decoration:underline!important}` + `.back:active{color:#ff5501!important;...}`** — aggregate vào theme bundle qua `@import "@hyva-themes/hyva-modules/css"`, !important thắng var-based btn states bất kể specificity. Fix: re-assert tertiary contract trong cùng rule `.actions-toolbar a.action.back` với `:hover`/`:focus-visible`/`:is(:active,.is-active)` đặt `color: var(--btn-*-color) !important; text-decoration:none !important` — specificity (0,2,2) + !important thắng (0,1,1) + !important của Mageplaza; var-based nên an toàn khi TL đổi variant. Module Mageplaza KHÔNG bị sửa (project rule: third-party không edit in place; theme CSS override là pattern đang dùng — social-login.css:404+ override cùng kiểu cho popup). Residual đã ghi vào audit backlog SLP-305: các bare-class legacy khác trong file đó (`.action.create:hover`, `.remind:hover`) vẫn nuke màu/underline các link register/remind ngoài popup scope — xử lý khi audit SLP-305 tổng thể được duyệt.
