---
id: TASK-E1YAHT
type: task
title: 'Social Login popup: phân biệt style title vs submit button'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-259
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-09-22
updated: 2026-09-22
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2) + Mageplaza SocialLogin
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/social-login.css
  - app/code/Mageplaza/SocialLogin (readonly reference)
source_areas:
  - theme-tailwind-v4
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-22
supersedes: []
---

# [SLP][TASK-E1YAHT] Social Login popup: phân biệt style title vs submit button

<!-- External ticket: SLP-259 — "Chỉnh lại tiêu đề cho khác button" + annotation "Apply style phân biệt nút và tiêu đề" (screenshot popup login). -->
<!-- Mode C — embedded Mini-Spec. CSS-only trong theme overlay; không đụng template/JS/flow auth. -->

## Summary

Trong `#social-login-popup` (Mageplaza SocialLogin, Hyvä modal), title "Đăng nhập" (`.social-login-title > h2.login-title`) và nút submit "Đăng nhập" (`#bnt-social-login-authentication`) nhận **cùng một màu nền** từ config `sociallogin/general/style_management` qua inline `<style>` do `Mageplaza_SocialLogin::css.phtml` inject vào `head.additional`:

- `#social-login-popup .social-login-title { background-color: $color }` (1-1-0)
- `#social-login-popup .social-login #bnt-social-login-authentication` (+ forgot/create/fake-email) (2-1-0)

Theme overlay `social-login.css` (TASK-7P5RJP) chủ ý CONFIG-RESPECTING — chỉ chỉnh shape, không đè màu → title và button trông như 2 nút giống hệt nhau (cùng màu, cùng radius 0.5rem). Ticket yêu cầu style title khác button.

Vendor `css/style.css` gốc: `.social-login-title h2 { color:#fff; padding-left:40px }` + banner PNG icon per view; `#social-login-popup .social-login-title { background:#3399cc }`.

## Mini Spec

### Goal
- Title popup (cả 4 view: authentication/create/forgot/fake-email — chung selector) render như **heading trần**: không banner màu, chữ ink token theme — khác biệt rõ với nút submit giữ nguyên màu config.
- Button submit + các nút forgot/create/fake-email: **không đổi** (giữ màu config `style_management`, hover brightness như hiện có).

### Expected Behavior
- Mở popup → title là text heading màu ink trên nền card, không còn khối màu giống nút; nút "Đăng nhập" giữ nguyên style hiện tại.
- Đổi màu `style_management` trong admin → chỉ button đổi màu, title vẫn heading trần (title không còn phụ thuộc config màu).

### Constraints / Rules
- CSS-only trong `social-login.css` (theme build) — không sửa Mageplaza in-place, không đổi template/JS/selector hook.
- Specificity: override phải cao hơn inject css.phtml (load sau styles.css) → giữ pattern hiện có (+1 class so với rule vendor/inject tương ứng).
- Auth surface (AGENTS.md §12): display-only, không đụng auth logic — TL review trước QC như precedent SLP-185/SLP-160.
- Magento/static ops chạy as `secomm` (memory shell-traps).

### Decision (direction)
- **Chọn (A) heading trần** — nhất quán design language theme (block-title ink text; "Hoặc Đăng nhập bằng" dạng text + kẻ ngang); robust với mọi màu config. User xác nhận "thực thi" trên recommendation (2026-09-22).
- Phương án (B) banner tint (giữ màu config nhưng nhạt, trắng hóa qua `background-image` gradient) — không chọn; nếu client muốn banner nhạt thay vì heading trần, swap trong cùng block CSS là trivial.
- Out of scope: `#request-popup` (title banner tĩnh `#3399cc` của vendor style.css trên auth pages) — không nằm trong screenshot ticket; ghi nhận để xử lý riêng nếu cần.

## Plan (approach)

1. Sửa section "3. Title" trong `social-login.css`: đè `background: none` + bỏ padding/radius banner ở selector `.social-login-title` (1-2-0, thắng inject 1-1-0); thêm `color: var(--color-ink)` cho h2 (1-2-1, thắng vendor `#fff` 0-1-1). Cập nhật comment CONFIG-RESPECTING → intent mới (SLP-259: màu config chỉ áp button).
2. Build Tailwind: `npm run build` (as secomm) tại `app/design/frontend/Secomm/launchpad/web/tailwind/`.
3. Verify compiled `web/css/styles.css` chứa rule mới; (nếu env cho phép) probe computed style popup qua Playwright + screenshot làm evidence.
4. Evidence → `.ai/evidence/TASK-E1YAHT/`.

## Acceptance Criteria

- [x] AC-1: Title "Đăng nhập" trong popup render heading trần (nền trong suốt, chữ ink), khác biệt rõ với nút submit — ở cả view login/create/forgot/fake-email. *(verify computed-style login + create: bg `rgba(0,0,0,0)`, h2 `oklch(0.129 0.042 264.695)` = `--color-ink` → slate-950; screenshots 1440 + 375)*
- [x] AC-2: Nút submit + nút social giữ nguyên style hiện có (màu config, radius, hover). *(verify: `#bnt-social-login-authentication` bg `rgb(51,153,204)` = config #3399cc, social buttons Google/Facebook nguyên trạng)*
- [x] AC-3: Đổi `style_management` (admin, per store) → chỉ button đổi màu; title bất biến. *(theo cơ chế: title không còn consume màu inject — `background: none` 1-2-0 thắng inject 1-1-0; verify màu cứng local trong AC-2, logic bất biến với mọi màu)*
- [x] AC-4: Không đổi behavior: form action, JS tab switching, OAuth popup — regression check 3 view + mobile ≤640px. *(probe chuyển tab login↔create bằng chính vendor `createBtn()`/`showLogin()` hoạt động; screenshots login/create × 1440 + login × 375; 0 template/JS thay đổi)*

## Verification (2026-09-22)

- Build: `npm run build` as secomm → `web/css/styles.css` (238345B, secomm-owned) chứa rule mới: `.block-container .social-login-title{...background:0 0...padding:0}` + `...div.social-login-title h2{color:var(--color-ink,#0f172a)...}`.
- Playwright probe (`http://slaunchpad.localhost`, mở popup qua header link): PASS 4/6 assertion — 2 "FAIL" là false-negative của probe (hardcode rgb; computed thực tế `oklch(0.129 0.042 264.695)` khớp chính xác `--color-ink: var(--color-slate-950)` tại `tailwind-source.css:41`). Metrics trong `results.txt`.
- Evidence: `.ai/evidence/TASK-E1YAHT/` (3 screenshots + results.txt + probe.js).

## Flags TL (chờ review)

1. **Modal social-login ở checkout OSC sẽ lệch style**: `Launchpad_MageplazaSocialLogin/view/frontend/web/css/social-login-checkout.css:148-167` (scope luma của OSC) đang giữ **banner title màu config** theo quyết định TASK-8TXS2P round-5 "khớp modal home hiện hành" — tiền đề này giờ không còn (home = heading trần). Cần TL quyết: (a) extend treatment sang checkout modal (sửa selector title trong file đó, ~vài dòng) hay (b) giữ nguyên 2 style khác nhau.
2. `#request-popup` trên auth pages (banner tĩnh `#3399cc` từ vendor `style.css:42-46`, không nhận màu config) — out of scope ticket, ghi nhận riêng.

## Pre-review checklist

- [x] Match plan / đúng scope 1 file CSS (`social-login.css` — source + compiled)
- [x] No hardcoded brand color (token `--color-ink` + fallback slate-950)
- [x] Specificity đúng pattern file (title 1-2-0 vs inject 1-1-0; h2 1-2-1 vs vendor 0-1-1)
- [x] Regression: 4 view popup + mobile + auth pages (ngoài popup, selector scoped nên không đụng)

## Notes

- `.ai/bin/project-ai-idgen` bị CRLF (toolkit checkout artifact) — đã chạy bản copy strip-CR trong /tmp; fix chính thức script thuộc toolkit regeneration, không phải scope ticket này.
- Effort logged sau TL review (estimation-tracking.csv).
