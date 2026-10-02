---
id: BUG-FX2VGG
type: bug
title: 'Admin: PageBuilder stage trắng khi edit trang home — orphan pb-style rule crash stage-builder (SLP-297 follow-up)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-297
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-10-02
updated: 2026-10-02
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: CMS content data fix (xóa 1 rule CSS mồ côi mỗi trang home, backup byte-exact) — không chạm schema/payment/checkout; user duyệt trực tiếp trước khi UPDATE
components:
  - cms_page page_id=2 (home, vi)
  - cms_page page_id=13 (home, en)
source_areas:
  - cms-content
  - admin-pagebuilder
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: 50212e87 (working tree)
last_verified: 2026-10-02
supersedes: []
---

# [SLP][BUG-FX2VGG] Admin: PageBuilder stage trắng khi edit trang home — orphan pb-style rule crash stage-builder

<!-- Symptom (user, 02-10): "Bấm vào edit with pagebuilder không thấy content" — cả 2 trang Home Page. -->

## Root Cause

Content 2 trang `home` chứa **1 rule CSS mồ côi** trong `<style>` block — selector trỏ tới hash `data-pb-style` không còn element nào mang trong HTML:

- page_id=2 (vi): `#html-body [data-pb-style=GEJEEJ3]{display:flex;flex-direction:column}` (70 rules / 69 elements)
- page_id=13 (en): `#html-body [data-pb-style=CVWWU69]{display:flex;flex-direction:column}` (68 rules / 67 elements)

Magento PageBuilder admin (`stage-builder.js → convertToInlineStyles → buildFromContent → new Stage`) parse từng rule rồi `setAttribute` lên element theo hash — gặp hash không tồn tại → `TypeError: Cannot read properties of null (reading 'setAttribute')` → stage build chết ngay từ đầu → `.pagebuilder-canvas` không bao giờ render → "không thấy content". Frontend vô canh (rule thừa = CSS no-op; chỉ admin builder crash).

Nguồn gốc: content `home` sửa lần cuối **2026-10-01 07:48** — một thao tác lưu/sửa content (admin hoặc script) đã xóa element nhưng sót lại rule style của nó. Các trang PB khác (testpage1: 87/87, testpage2: 61/61) cân bằng nên edit bình thường.

## Mini Spec

### Goal
- 2 trang Home Page edit được bằng PageBuilder admin trở lại — stage render đủ content.

### Expected Behavior
- Mở Admin → Content → Pages → Home Page → expand Content → `.pagebuilder-canvas` render, 0 pageerror console.
- Frontend homepage không đổi (rule bị xóa là no-op trên storefront).

### Constraints / Rules
- Chỉ xóa **exact 1 chuỗi rule orphan** mỗi trang — không đụng ký tự khác trong content.
- Backup byte-exact + md5 trước khi UPDATE; chỉ chạy sau khi user duyệt (DB content write).
- `cache:flush` as secomm sau fix (shell root + vhost secomm — trap ownership).

### Out of Scope
- Truy vết ai/cái gì đã sửa content lúc 01-10 07:48 (không có audit log content).
- Refactor cơ chế cân bằng hash khi lưu PB (prevention = checklist diff hash, xem Cảnh báo).

### Acceptance Criteria
- **AC-001**: Re-diff hash 2 trang = rules/elements cân bằng, 0 orphan.
- **AC-002**: Playwright admin mở edit cả 2 trang → canvas render đủ content types, 0 pageerror.
- **AC-003**: Frontend homepage nguyên vẹn (flash sale gradient + countdown SLP-297 không đổi).

## Fix (đã áp dụng — user duyệt "Tôi fix trực tiếp")

1. Backup byte-exact: `/tmp/cms_home_backup_20261002/page_2.html` (md5 `128953969ba9123cd837b89adb773e5c`), `page_13.html` (md5 `fd8e8227ede3938b9d235471b277018f`). ⚠ /tmp sống theo reboot — nếu cần rollback dài hạn, copy khỏi /tmp.
2. `str_replace` **exact 1 chuỗi rule orphan** mỗi trang (không đụng ký tự khác): 43648→43578 / 41235→41165 bytes.
3. `cache:flush` as secomm.

## Verify

- **Re-diff hash**: page 2 = 69 rules/69 elements, page 13 = 67/67, orphan (none).
- **Admin (Playwright, probe12)**: mở edit cả 2 trang → expand Content → `.pagebuilder-canvas` render, height ~5.2k px, đủ content types (row×13, slider×1, slide×7, heading×20, products×3, image×5…), text vi "Mùa mới, bình yên mới…" / en "New Season, New Calm…", **0 pageerror** (trước fix: `TypeError … setAttribute` + không canvas). Screenshot `.ai/evidence/BUG-FX2VGG/`.
- **Frontend**: homepage HTTP 200, flash sale (gradient + countdown SLP-297) nguyên vẹn.

## Cảnh báo / Prevention

- Cùng họ với trap **v4.12.2** (RAW quote kills admin stage; save admin cắt `payload=`): **mọi sửa content home phải cân bằng `data-pb-style` giữa `<style>` block và element**. Check nhanh: đếm hash 2 phía phải bằng nhau.
- Khi xóa/di chuyển element trong PB stage qua admin, save lại có thể để lại rule mồ côi — sau mỗi lần edit content nên chạy diff hash 1 lần.
- Script check/fix dùng được lại: /tmp/slp297-fix-orphan.php (mẫu trong evidence).

## Update

- **2026-10-02 — FIXED, chờ TL review**: chẩn đoán qua Playwright admin (login admin → menu Content → Pages — route thật `cms/page/index`, direct URL 404 vì secret key; modal notification che menu phải đóng). Fix áp dụng + verify 3 lớp như trên. Task này là data hotfix độc lập với TASK-SFGVW0 (code frontend không liên quan crash).
