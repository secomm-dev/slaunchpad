# Feature Spec — Magento Open Source baseline: apply security release APSB26-92 (2.4.8-p5 2026-08-001 CE)

Specification ID: SPEC-TASK-ZYGJ5P
Feature ID: NONE
Specification Level: FULL

<!-- Generated for Secomm Launchpad · Stack: Magento 2.4.8-p5 + Hyvä 3.x -->
<!-- Spec cho work item TASK-ZYGJ5P (commit ref: [LC-01] "Magento Open Source baseline - update security patch" — b844db3c). Standalone task · Mode A · Tier-2 (security-sensitive, AGENTS.md §12). -->
<!-- Spec viết hồi tố (retrospective) 2026-08-26 sau khi thay đổi đã commit 2026-08-25; nội dung được verify trực tiếp từ commit b844db3c. -->

> **Project**: Secomm Launchpad · **Stack**: Magento 2.4.8-p5 + Hyvä 3.x (Tailwind v4 + Magewire 1.13)
> **Risk tier**: Tier-2 — security-sensitive change (AGENTS.md §12) → Mode A.

## Feature Overview

**Feature name**: Áp dụng security release **APSB26-92 — "Security release 2.4.8-p5 2026-08-001 CE"** (Adobe, tháng 8/2026) lên baseline Magento Open Source 2.4.8-p5 bằng composer patches.

**Work item reference**: `TASK-ZYGJ5P` (commit ref `[LC-01]` — "Magento Open Source baseline - update security patch").

**Feature type**: Platform baseline maintenance (vendor security patch)

**Priority**: P1 / High (security)

**Mode / Risk**: A · Tier-2 (security-sensitive) · risk: medium (patch do Adobe cung cấp, không thay đổi logic tùy chỉnh)

**Status**: ✅ Completed — commit `b844db3c` (2026-08-25) trên nhánh `dev/development/baole`.

## 1. Bối cảnh & Mục tiêu

- Baseline của dự án là Magento Open Source **2.4.8-p5** cài qua composer, vendor dir không commit. Mô hình áp security release của dự án: đặt patch của Adobe vào `patches/composer/` và đăng ký qua `cweagans/composer-patches` (tiền lệ: bộ `APSB26-73` đã áp dụng trước đó, cùng cơ chế).
- **Mục tiêu**: đưa các fix bảo mật của bulletin APSB26-92 (bản security-only cho 2.4.8-p5, đợt 2026-08-001 CE) vào baseline một cách kiểm soát được (patch file được review trong repo, `patches.lock.json` pin sha256), không nâng version platform và không chạm code tùy chỉnh.

## 2. Nội dung thay đổi (verified từ commit b844db3c)

### 2.1. Patch security APSB26-92 (4 package)

Thư mục: `patches/composer/APSB26-92-248p5-2026-08-001-CE/` — description đăng ký trong `composer.json`: `"APSB26-92: Security release 248p5-2026-08-001 CE"`.

| # | Package | Patch file | Nội dung chính | Size |
|---|---------|-----------|----------------|------|
| 1 | `magento/magento2-base` | `magento2-base.patch` | Nâng bundled **Underscore.js 1.13.6 → 1.13.8** (file root `lib/web/underscore.js`) — cập nhật thư viện JS phân phối kèm base package, khắc phục vấn đề bảo mật phía client của thư viện | 371 dòng |
| 2 | `magento/module-cms` | `module-cms.patch` | Hardening 5 controller WYSIWYG Images admin: `Upload.php`, `NewFolder.php`, `DeleteFiles.php`, `DeleteFolder.php`, `OnInsert.php` (validation đường dẫn/typed input khi quản lý media qua CMS editor) + bổ sung module dependency trong `etc/module.xml` | 98 dòng |
| 3 | `magento/module-customer` | `module-customer.patch` | `Controller/Account/Edit.php` — thêm validation qua `FormFactory` / `CustomerMetadataInterface` cho form chỉnh sửa tài khoản khách hàng storefront | 73 dòng |
| 4 | `magento/module-review` | `module-review.patch` | `Controller/Adminhtml/Product/Save.php` — hardening luồng lưu review từ admin product save | 12 dòng |

### 2.2. Companion patch (đi kèm bulletin, ngoài 4 patch trên)

| Package | Patch file | Nội dung |
|---------|-----------|----------|
| `magento/framework` | `patches/magento-framework-acl-title-optional.patch` | `Acl/etc/acl_merged.xsd` — attribute `title` của ACL resource đổi từ `use="required"` → `use="optional"`, cho phép `acl.xml` khai báo resource không có `title` vẫn validate đúng khi merge ACL (cần cho bộ patch set này vận hành trên baseline hiện tại) |

### 2.3. Lock files

- `patches.lock.json`: `_hash` mới + 5 entry mới (4 patch APSB26-92 + 1 framework companion), mỗi entry pin `sha256`, `depth: 1`, `provenance: root`.
- `composer.lock`: regenerate sau khi đăng ký patch (bao gồm xoay URL dist của Hyvä private Packagist repo — URL dist không chứa secret; token vẫn chỉ nằm trong `auth.json`, không commit).

### 2.4. Phạm vi KHÔNG chạm

- Không có file nào trong `app/code/` (Secomm/Mageplaza/Vnpayment), `app/design/` hay config store bị thay đổi — toàn bộ diff nằm ở `composer.json`, 2 lock file và 5 patch file mới.

## 3. Acceptance Criteria (DoD)

- [x] **AC-001**: `composer.json` đăng ký patch APSB26-92 cho đúng 4 package (`magento/module-cms`, `magento/module-customer`, `magento/module-review`, `magento/magento2-base`) + companion patch `magento/framework` (ACL title optional); mọi đường dẫn patch trỏ tới file tồn tại trong repo.
- [x] **AC-002**: `patches.lock.json` chứa 5 entry mới với `sha256` tương ứng và `_hash` được cập nhật — cài đặt lại (`composer install`) trên môi trường sạch sẽ áp đủ patch không lỗi.
- [x] **AC-003**: Underscore.js phân phối kèm baseline được nâng **1.13.6 → 1.13.8** (áp qua `magento2-base.patch` vào `lib/web/underscore.js`).
- [x] **AC-004**: Không thay đổi code tùy chỉnh — diff của commit chỉ gồm `composer.json`, `composer.lock`, `patches.lock.json` và 5 patch file mới.
- [x] **AC-005**: Thay đổi được commit lên nhánh development với tham chiếu ticket (`[LC-01]` — commit `b844db3c`).
- [ ] **AC-006 (follow-up)**: Smoke check runtime sau khi áp patch — admin WYSIWYG Images (upload/create folder/delete), storefront customer account edit page, các trang dùng `underscore.js` — chưa có evidence artifact (xem §7).

## 4. Luồng kỹ thuật (Technical Notes)

```
composer.json (extra.patches: +5 entries)
        │
        ▼
cweagans/composer-patches (vendor/composer install)
        │  áp patch theo đúng thứ tự đã pin trong
        ▼
patches.lock.json (_hash + sha256/patch — reproducible build)
        │
        ├─► magento/magento2-base  → lib/web/underscore.js (root) 1.13.6 → 1.13.8
        ├─► magento/module-cms     → 5 WYSIWYG Images controller + module.xml
        ├─► magento/module-customer→ Controller/Account/Edit.php
        ├─► magento/module-review  → Controller/Adminhtml/Product/Save.php
        └─► magento/framework      → Acl/etc/acl_merged.xsd (title optional)
```

- Patch của `magento/magento2-base` áp lên file thuộc root project (`lib/web/underscore.js`, `pub/` samples…) vì base package map về project root — cùng cơ chế với các patch APSB26-73 trước đó (nginx sample config).
- Companion patch `magento-framework-acl-title-optional.patch` là **local compat patch** (không phải patch Adobe) — phải được đánh giá lại khi upgrade framework hoặc khi Adobe phát hành fix chính thức (đã ghi Known Limitation ở record + gợi ý update `12_UPGRADE_NOTES.md`).

## 5. Dependencies

| Dependency | Loại | Ghi chú |
|------------|------|---------|
| `cweagans/composer-patches` | tooling (đã có) | Cơ chế áp patch hiện tại của dự án |
| Adobe APSB26-92 patch bundle | vendor | Nguồn patch: security release 2.4.8-p5 2026-08-001 CE |
| Hyvä private Packagist (`auth.json`) | repo | `composer.lock` regenerate chạm URL dist; token không được commit |

## 6. Risks & Unknowns

| Rủi ro | Mức độ | Giảm thiểu |
|--------|--------|------------|
| Companion patch ACL lệch chuẩn vendor, có thể conflict khi upgrade `magento/framework` | medium | Ghi nhận trong Known Limitations; review lại ở lần upgrade baseline tiếp theo |
| Patch fail khi apply trên môi trường khác (thứ tự/hash không khớp) | low | `patches.lock.json` pin sha256; chạy `composer install` sạch để xác nhận (AC-002) |
| Hành vi runtime thay đổi sau hardening controller (WYSIWYG media, account edit) | medium | Cần smoke check AC-006 + QC khi environment được dựng |

## 7. Out of Scope

- Nâng cấp platform lên release mới hơn (2.4.8-p6+ / 2.4.9+) — task này chỉ áp security-only patch trên baseline p5 hiện tại.
- Sửa logic tùy chỉnh trong `app/code/Secomm/*`, `app/code/Mageplaza/*`, `app/code/Vnpayment/*`.
- Evidence artifact `.ai/evidence/TASK-ZYGJ5P/` + QC testcase docs (đề xuất follow-up theo evidence-policy).
- Update `12_UPGRADE_NOTES.md` với companion-patch note (đề xuất follow-up, xem record).

## 8. References

- Commit: `b844db3c` — "[LC-01] Magento Open Source baseline - update security patch" (2026-08-25)
- Tiền lệ cùng cơ chế: `patches/composer/APSB26-73/*` (security patch trước đó của baseline)
- Record: [.ai/records/tasks/TASK-ZYGJ5P.md](../records/tasks/TASK-ZYGJ5P.md) · Ticket: [.ai/tickets/TASK-ZYGJ5P-apply-apsb26-92-security-patch.md](../tickets/TASK-ZYGJ5P-apply-apsb26-92-security-patch.md)
