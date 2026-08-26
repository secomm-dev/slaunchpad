---
id: TASK-ZYGJ5P
type: task
title: 'Magento Open Source baseline - update security patch (APSB26-92, 2.4.8-p5 2026-08-001 CE)'
project_code: SLP
parent: null
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-ZYGJ5P-apply-apsb26-92-security-patch.md
risk: medium
status: completed
created: 2026-08-26
updated: 2026-08-26
legacy_ids: [LC-01]
decisions: []
decision_assessment: none-material
components: []
source_areas:
  - composer.json
  - composer.lock
  - patches.lock.json
  - patches/composer/APSB26-92-248p5-2026-08-001-CE/
  - patches/magento-framework-acl-title-optional.patch
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit: b844db3c136ed45b8bb5017eee70ca2b15106ec5
last_verified: 2026-08-26
supersedes: []
---

# [SLP][TASK-ZYGJ5P] Magento Open Source baseline - update security patch (APSB26-92, 2.4.8-p5 2026-08-001 CE)

<!-- CANONICAL TASK RECORD — TASK-ZYGJ5P (commit ref [LC-01]). Áp dụng security release APSB26-92 (2.4.8-p5 2026-08-001 CE) lên baseline qua composer patches. -->
<!-- Record viết hồi tố 2026-08-26: công việc đã được thực hiện + commit (b844db3c, 2026-08-25); nội dung record/spec được verify trực tiếp từ commit đó. -->

## Bối cảnh (Context)

Baseline dự án là Magento Open Source 2.4.8-p5 (vendor không commit), security release được áp qua `cweagans/composer-patches` đặt trong `patches/composer/` — tiền lệ APSB26-73. Adobe phát hành bulletin **APSB26-92 — "Security release 2.4.8-p5 2026-08-001 CE"** (8/2026); ticket `[LC-01]` yêu cầu đưa bộ patch này vào baseline: cập nhật `composer.json`, regenerate lock files, commit patch file để build reproduccible.

## Specification & Plan

- **Spec (FULL, VALID)**: [SPEC-TASK-ZYGJ5P-apply-apsb26-92-security-patch.md](../../specs/SPEC-TASK-ZYGJ5P-apply-apsb26-92-security-patch.md) — viết hồi tố, verify từ commit `b844db3c`.
- **Plan**: không có file plan riêng — thay đổi là đăng ký patch theo cơ chế sẵn có (không giải pháp mới cần plan; spec §4 mô tả luồng áp patch).

## Mode & Approach

**Mode**: A — chạm risk category **security** (AGENTS.md §9 decision tree, §12) → Tier-2 escalation.
**Approach**: vendor patch qua composer — đặt patch file Adobe vào repo, đăng ký `extra.patches`, pin `patches.lock.json`, regenerate `composer.lock`. Không sửa code tùy chỉnh.

## Thay đổi đã triển khai (verified từ commit b844db3c)

1. **4 patch APSB26-92** trong `patches/composer/APSB26-92-248p5-2026-08-001-CE/`:
   - `magento/magento2-base` → nâng Underscore.js **1.13.6 → 1.13.8** (`lib/web/underscore.js`, root).
   - `magento/module-cms` → hardening 5 controller WYSIWYG Images admin (`Upload`, `NewFolder`, `DeleteFiles`, `DeleteFolder`, `OnInsert`) + module.xml dependency.
   - `magento/module-customer` → `Controller/Account/Edit.php` thêm validation `FormFactory`/`CustomerMetadataInterface`.
   - `magento/module-review` → `Controller/Adminhtml/Product/Save.php`.
2. **1 companion patch** (local, không phải của Adobe): `patches/magento-framework-acl-title-optional.patch` — `Acl/etc/acl_merged.xsd` đổi ACL resource `title` từ `required` → `optional`.
3. **Lock files**: `patches.lock.json` (+5 entry sha256, `_hash` mới), `composer.lock` regenerate (URL dist Hyvä private Packagist xoay theo repo — không secret trong lock; token vẫn chỉ ở `auth.json`).

## Acceptance Criteria

- [x] **AC-001**: `composer.json` đăng ký đủ 4 patch APSB26-92 + 1 companion framework patch; đường dẫn patch tồn tại trong repo.
- [x] **AC-002**: `patches.lock.json` pin 5 entry mới (sha256) + `_hash` cập nhật → cài lại sạch áp đủ patch.
- [x] **AC-003**: Underscore.js baseline nâng 1.13.6 → 1.13.8.
- [x] **AC-004**: Không chạm code tùy chỉnh — diff chỉ gồm composer/patch files (5 file patch + 3 file lock/registry).
- [x] **AC-005**: Commit `[LC-01]` trên nhánh development (`b844db3c`).
- [ ] **AC-006**: Smoke check runtime sau patch (admin WYSIWYG Images, customer account edit, trang dùng underscore.js) — **chưa có evidence**, để open làm follow-up.

## Known Limitations

1. **Companion patch ACL (`magento-framework-acl-title-optional.patch`) là local compat patch** — lệch khỏi stock `magento/framework`; phải review/gỡ khi upgrade framework hoặc khi Adobe phát hành fix chính thức. (Đề xuất: bổ sung note vào `project-context/12_UPGRADE_NOTES.md` — follow-up.)
2. **Chưa có evidence artifact** `.ai/evidence/TASK-ZYGJ5P/` (evidence-policy) và chưa có QC smoke sau patch (AC-006) — theo yêu cầu hiện tại chỉ lập ticket/record/spec.

## Evidence

Chưa tạo (ngoài scope yêu cầu). Bằng chứng tham chiếu: commit `b844db3c` (source_areas ở frontmatter). Follow-up đề xuất: tạo `.ai/evidence/TASK-ZYGJ5P/evidence.md` ghi lại `composer install` sạch + `patches.lock` hash + smoke checklist AC-006.

## Status Log

- 2026-08-25: Thực hiện + commit `b844db3c` — "[LC-01] Magento Open Source baseline - update security patch" (5 patch file, composer.json, patches.lock.json, composer.lock).
- 2026-08-26: Lập hồ sơ hồi tố — mint ID `TASK-ZYGJ5P`, viết spec FULL (VALID, verify ngược từ commit), ticket mirror, record này. AC-006 + evidence để open.

## Related records

- Spec: [SPEC-TASK-ZYGJ5P-apply-apsb26-92-security-patch.md](../../specs/SPEC-TASK-ZYGJ5P-apply-apsb26-92-security-patch.md)
- Ticket: [TASK-ZYGJ5P-apply-apsb26-92-security-patch.md](../../tickets/TASK-ZYGJ5P-apply-apsb26-92-security-patch.md)
- Tiền lệ cùng cơ chế: bộ patch `APSB26-73` trong `composer.json` / `patches/composer/APSB26-73/`
