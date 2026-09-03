# TASK-ZYGJ5P — Magento Open Source baseline - update security patch (APSB26-92)

- **ID**: `TASK-ZYGJ5P`
- **External Ref**: `LC-01` (commit `[LC-01] Magento Open Source baseline - update security patch`)
- **Priority**: P1 / High (security)
- **Estimate**: ~2–4h
- **Mode**: A (security — Tier-2, AGENTS.md §12)
**Specification:** [SPEC-TASK-ZYGJ5P-apply-apsb26-92-security-patch.md](../specs/SPEC-TASK-ZYGJ5P-apply-apsb26-92-security-patch.md)
- **Risk tier**: Tier-2 (security-sensitive) · risk: medium (vendor patch, không đụng logic tùy chỉnh)
- **Status**: ✅ Completed (2026-08-25 — commit `b844db3c`; hồ sơ lập hồi tố 2026-08-26)

## Description

Áp dụng security release **APSB26-92 — "Security release 2.4.8-p5 2026-08-001 CE"** (Adobe, 8/2026) lên baseline Magento Open Source 2.4.8-p5 bằng cơ chế composer patches sẵn có của dự án (`cweagans/composer-patches`, tiền lệ APSB26-73): đặt patch file vào repo, đăng ký trong `composer.json`, pin `patches.lock.json`, regenerate `composer.lock`.

## Scope

- Thêm 4 patch APSB26-92 trong `patches/composer/APSB26-92-248p5-2026-08-001-CE/`:
  - `magento/magento2-base` — nâng Underscore.js 1.13.6 → 1.13.8 (`lib/web/underscore.js`).
  - `magento/module-cms` — hardening 5 controller WYSIWYG Images admin + module.xml.
  - `magento/module-customer` — validation `FormFactory`/`CustomerMetadataInterface` ở `Controller/Account/Edit.php`.
  - `magento/module-review` — `Controller/Adminhtml/Product/Save.php`.
- Thêm 1 companion patch local: `patches/magento-framework-acl-title-optional.patch` (`Acl/etc/acl_merged.xsd`: ACL resource `title` required → optional).
- Đăng ký 5 patch trong `composer.json`; cập nhật `patches.lock.json` (sha256 + `_hash` mới); regenerate `composer.lock`.

## Acceptance Criteria

- [x] **AC-001**: `composer.json` đăng ký đủ 4 patch APSB26-92 + 1 companion framework patch; file patch tồn tại trong repo.
- [x] **AC-002**: `patches.lock.json` pin 5 entry mới (sha256) + `_hash` cập nhật → cài lại sạch áp đủ patch.
- [x] **AC-003**: Underscore.js baseline nâng 1.13.6 → 1.13.8.
- [x] **AC-004**: Không chạm code tùy chỉnh (app/code, app/design) — diff chỉ gồm composer/patch files.
- [x] **AC-005**: Commit `[LC-01]` trên nhánh development (`b844db3c`).
- [ ] **AC-006**: Smoke check runtime sau patch (admin WYSIWYG Images, customer account edit, trang dùng underscore.js) — open, follow-up.

## Technical Approach

1. Tải bộ patch APSB26-92 cho 2.4.8-p5 CE, tách theo package vào `patches/composer/APSB26-92-248p5-2026-08-001-CE/`.
2. Viết companion patch `magento-framework-acl-title-optional.patch` để bộ patch set validate ACL đúng trên baseline hiện tại.
3. Đăng ký `extra.patches` trong `composer.json` (mô tả: `"APSB26-92: Security release 248p5-2026-08-001 CE"`).
4. `composer update --lock`/reinstall để regenerate `patches.lock.json` + `composer.lock`.
5. Commit với ref ticket `[LC-01]`.

## Files/Areas Affected

- `composer.json` — đăng ký 5 patch mới.
- `patches.lock.json` — 5 entry mới + `_hash`.
- `composer.lock` — regenerate.
- `patches/composer/APSB26-92-248p5-2026-08-001-CE/` — 4 patch Adobe (magento2-base, module-cms, module-customer, module-review).
- `patches/magento-framework-acl-title-optional.patch` — companion patch ACL XSD.

## Decisions

Không có DEC mới — dùng cơ chế patch sẵn có của dự án (tiền lệ APSB26-73), không quyết định kiến trúc.

## Risks

- Security category → Tier-2: bản thân patch là fix bảo mật của Adobe; rủi ro chính nằm ở hành vi runtime sau hardening controller (WYSIWYG media, account edit) → cần smoke AC-006.
- Companion patch ACL lệch stock `magento/framework` → phải review lại khi upgrade framework (Known Limitation ở record).

## Known Limitations

1. Companion patch ACL là local compat patch — đánh giá lại ở lần upgrade baseline tiếp theo (đề xuất note vào `12_UPGRADE_NOTES.md`).
2. Chưa có evidence artifact `.ai/evidence/TASK-ZYGJ5P/` (evidence-policy) — follow-up.

## Definition of Done

- [x] Code complete + matches spec (verify ngược từ commit `b844db3c`)
- [x] Không thay đổi ngoài scope (chỉ composer + patch files)
- [x] Commit trên nhánh development với ticket ref `[LC-01]`
- [ ] Smoke/QC verify sau patch (AC-006) — open
- [ ] Evidence `.ai/evidence/TASK-ZYGJ5P/` — open (follow-up)

## Related

- Record: [TASK-ZYGJ5P](../records/tasks/TASK-ZYGJ5P.md) · Spec: [SPEC-TASK-ZYGJ5P](../specs/SPEC-TASK-ZYGJ5P-apply-apsb26-92-security-patch.md)
- Tiền lệ: bộ patch `APSB26-73` trong `patches/composer/APSB26-73/`
