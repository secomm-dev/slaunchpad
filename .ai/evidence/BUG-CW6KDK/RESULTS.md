# BUG-CW6KDK (SLP-205) — Verification Results

**Date**: 2026-09-11 · **Mode C** · Env: local WSL2, MAGE_MODE=developer, stores `default`(vi_VN) + `launchpad_en`(en_US)

## Changes

1. `app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv` + `en_US.csv`: +2 key/file
   - login error full phrase (extract byte-exact từ `LoginPost.php:213-216` — 2 literal concat)
   - LAC tooltip (extract từ `LoginAsCustomerAssistance/etc/config.xml:14`, chứa `"see what you see"`)
   - Generator: `add-keys.py` (idempotent, csv.QUOTE_ALL). VI wording draft — TL duyệt.
2. Override mới `app/design/frontend/Secomm/launchpad/Magento_Wishlist/templates/sharing.phtml`
   (cp verbatim từ Hyva default theme + 4 diff — diff output trong run log):
   header provenance comment; empty-field branch `setCustomValidity(__('This is a required field.'))`
   + `reportValidity()`; `@submit="!validateForm() && $event.preventDefault()"`; bỏ attr `required`;
   submit button + `btn btn-primary`.

## Verification

`php -l` template: PASS (no syntax errors).

`verify.php` (CLI store emulation render-level, pattern BUG-GJT6C1; 1 process/store):

```
=== vi (default) ===                          === en (launchpad_en) ===
resolution_theme_path        PASS             resolution_theme_path        PASS
phrase_login_error           PASS             phrase_login_error           PASS
phrase_lac_tooltip           PASS             phrase_lac_tooltip           PASS
phrase_required_field        PASS             phrase_required_field        PASS
render_button_style          PASS             render_button_style          PASS
render_submit_guard          PASS             render_submit_guard          PASS
render_report_validity       PASS             render_report_validity       PASS
render_no_native_required    PASS             render_no_native_required    PASS
render_translated_empty_msg  PASS             render_translated_empty_msg  PASS
ALL PASS (9 checks)                           ALL PASS (9 checks)
```

Render snapshots: `render-default.html`, `render-launchpad_en.html`.

Smoke: `GET /` → 200; `GET /wishlist/index/share/` anon → 302 → login (route intact).
Cache: `cache:clean translate` as secomm sau khi thêm key. Developer mode — template override
không cần static deploy (PHP-rendered, không JS `$t()`).

## Findings / Notes

- **Alpine không auto-preventDefault khi handler `return false`** (alpine3.min.js chỉ có 1
  `preventDefault` = `.prevent` modifier) → `@submit="validateForm()"` của vendor là dead-guard;
  override dùng guard tường minh `!validateForm() && $event.preventDefault()` (CSP-safe expression).
  Race cũ của vendor (submit không-focus → không customError → form vẫn submit) được fix theo.
- **escapeJs render `\uXXXX`**: message VI trong `setCustomValidity("Trường...")` —
  decode đúng khi bubble hiển thị, khớp pattern 2 message vendor đang live.
- Bubble "Please fill out this field." là **valueMissing bubble của browser** (browser-language)
  — không dịch được bằng CSV; cách chuẩn = không dùng `required`, set custom validity có bản dịch.
- Server-side safety net: `Magento\Wishlist\Controller\Index\Send` tự validate emails
  (empty/limit/format → `addErrorMessage`) — client-side guard là UX, không phải security.
- File override tạo root:root 644 (WSL chown bị chặn) — read-only cho web worker, không chạm
  var/generated nên không dính BUG-SDZPCD.
- 2 key CSV truncated cũ (vi_VN dòng 328+741) để nguyên — duplicate harmless, cleanup optional.

## Out of Scope

- Bubble browser-language trên các form My Account khác (follow-up nếu PM muốn).
- Wording VI (login error, tooltip) — TL/client chỉnh = 1 dòng CSV.
