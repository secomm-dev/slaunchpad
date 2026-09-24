---
id: TASK-YPWH9B
type: task
title: COD Blacklist & Basic Risk Control (LC-26)
project_code: SLP
parent:
external_refs:
  launchpad_feature: LC-26
legacy_ids: []
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: .ai/specs/SPEC-TASK-YPWH9B-cod-risk-control.md
risk: high
status: in_progress
created: 2026-09-21
updated: 2026-09-21
ticket_ref:
affects_version: Magento 2.4.8-p5
decisions:
  - DEC-NOTE: D-01..D-14 + S-01..S-03 chốt 2026-09-21 trong LC-26-open-decisions.md (repo root, review-only)
decision_assessment: payment-availability-plugin + 4 bảng DB mới → Tier-2 đã được approve bởi chủ dự án acting as SA/TL in chat 2026-09-21
components:
  - app/code/Secomm/CodRisk
source_areas:
  - payment
  - admin
changes_project_state: true
changes_architecture: true
changes_integration: true
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-21
supersedes: []
---

# [SLP][TASK-YPWH9B] COD Blacklist & Basic Risk Control (LC-26)

<!-- Mode A — chạm payment availability + DB schema (generic risk category). Full Spec VALID 2026-09-21: toàn bộ decisions D-01…D-14 được TL confirm trong chat; S-01/S-02 spike xong. Nguồn business: lc26_cod_risk_p1_p2_launchpad_spec.md + LC-26-open-decisions.md (repo root — review-only, KHÔNG import vào .ai). Mockup UX: LC-26-admin-ux-mockup.html (repo root, review-only). -->

## Summary

Kiểm soát rủi ro bom hàng COD theo **normalized shipping phone**: Blacklist / Spam Order / Allowlist / Historical Risk → `ALLOW|WARNING|BLOCK`, chỉ ảnh hưởng COD (Magento default `CashOnDelivery`), không chạm payment khác. MỘT module mới additive: `Secomm_CodRisk` (risk domain + admin + plugin availability COD — gộp theo quyết định chủ dự án 2026-09-21, bỏ skeleton `Secomm_Cod`).

## Mini Spec

### Goal

Merchant giảm bom hàng COD: chặn/blacklist SĐT, đếm historical risk event, phát hiện spam burst — CS vận hành được trọn vẹn trong admin (tra cứu, ghi event tay, override per-order có audit).

### Expected Behavior

- Checkout: phone có Blacklist active hoặc Spam match hoặc Historical ≥ block → COD ẩn (enabled config kill-switch = No → hành vi gốc); WARNING → COD vẫn hiện; payment khác không bao giờ bị ảnh hưởng.
- Order View (mọi order COD): section COD Risk luôn hiển thị — decision live + matched source + historical count + events + nút action theo bối cảnh (Record Risk Event / Add to Blacklist / Add to Allowlist / Deactivate Blacklist / Override khi BLOCK).
- Record Risk Event (ADMIN/CUSTOMER): lưu event có snapshot include-flag; count chạm ngưỡng → checkout sau WARNING/BLOCK. Ghi tay KHÔNG set decision trực tiếp.
- Phone Inspector: nhập phone bất kỳ định dạng → decision + lý do + records + events + evaluations + audit tại 1 trang.
- Admin grid: Risk Lists (1 grid BLOCK/ALLOW), Risk Events, Evaluations, Audit Log; config thresholds có validation; ACL 1 resource full quyền.

### Constraints / Rules

- Precedence cố định ở DI: Blacklist(10) > Spam(20) > Allowlist(30) > Historical(40); Allowlist không bypass Blacklist/Spam (CR-001…CR-003).
- Spam đếm event customer-attributable trong window trên bảng event (index) — không scan sales_order (CR-011).
- Invalid/missing phone → không BLOCK vì risk (CR-008); guest = registered (CR-009).
- Evaluation chỉ log WARNING/BLOCK (CR-010); override chỉ order-level có audit (CR-007).
- Plugin payment: after-plugin thuần + fail-open (exception → COD vẫn hiện + log critical); không sửa Magento_OfflinePayments/OSC.
- PHP 8.2+ strict_types, DI constructor, không logic trong controller, i18n cả en_US + vi_VN.

### Out of Scope

Bulk import/export, carrier automation, quote-level override, OSC UI/message customer-facing (text đã định nghĩa, chưa wire), Orders grid column (P2), auto-block từ 1 event.

### Acceptance Criteria

- AC-001: 2 module install sạch (`setup:upgrade`), không đụng bảng/module có sẵn.
- AC-002: Normalizer chuẩn E.164 VN — `0901 234 567` / `84901234567` / `+84 901 234 567` → `+84901234567` (unit test).
- AC-003: Checkout — Blacklist active → COD ẩn, VietQR/VNPay/MoMo/Mollie vẫn hiện; enabled=No → COD trở lại.
- AC-004: Historical — 2 event include=Yes trong 180d → WARNING (COD vẫn hiện, evaluation log WARNING); ≥ 3 → BLOCK (log BLOCK); event include=No hoặc khác website hoặc ngoài lookback không đếm.
- AC-005: Allowlist active bypass Historical nhưng không bypass Blacklist/Spam.
- AC-006: Record Risk Event từ Order View → event + audit + snapshot đúng; count cập nhật ngay section.
- AC-007: Override per-order (BLOCK→ALLOW) bắt buộc reason, có audit base→effective; checkout phone không đổi.
- AC-008: Phone Inspector trả đúng tổng hợp cho mọi định dạng nhập.
- AC-009: ACL — role không cấp `Secomm_CodRisk::manage` không thấy menu/ không vào được controller.
- AC-010: Guest và registered cùng phone → cùng decision; invalid phone không gây BLOCK.
- AC-011: Config validation: block > warning ≥ 1, lookback > 0; spam enable/window/threshold.
- AC-012: i18n đầy đủ en_US + vi_VN; storefront strings không hardcode.

## Plan

`.ai/plans/TASK-YPWH9B-implementation-plan.md` (7 steps: skeleton→DB→rules→plugin→admin→order-view→tests/docs)

## Implementation

Xem bảng Files affected trong plan — được cập nhật tiến độ ✅ tại mỗi step hoàn thành.

| # | Step | Trạng thái | Est |
|---|------|-----------|-----|
| 1 | Skeleton + contracts + normalizer | ✅ Done (2026-09-21) | 4h |
| 2 | DB schema + persistence + audit | ✅ Done (5 bảng — spec §5.3 updated) | 4h |
| 3 | Rule engine + 4 rules + recorder | ✅ Done | 5h |
| 4 | Availability plugin (CodRisk) + config + i18n | ✅ Done (gộp 1 module 2026-09-21) | 3h |
| 5 | Admin surface (menu/ACL/grids/inspector/forms) | ✅ Done | 8h |
| 6 | Order View section + actions | ✅ Done (anchor `order_additional_info` — đúng vị trí dưới Payment & Shipping, không override template core) | 5h |
| 7 | Tests + docs + pre-review handoff | ✅ Code/docs done — runtime verify pending (docker permission) | 2h |

## Verification

- **Static (done 2026-09-21):** 23/23 file XML/JSON parse OK (python ET/json). PHP syntax lint + PHPUnit **pending** — host shell không có quyền docker socket; chạy lệnh trong "Runtime handoff" bên dưới.
- **Runtime handoff (TL/dev chạy):**
  ```bash
  ./docker-compose up -d  # hoặc bật docker stack hiện có
  ./docker-compose exec <php-container> php -l <file>   # batch lint new modules
  ./docker-compose exec <php-container> bin/magento module:enable Secomm_CodRisk
  ./docker-compose exec <php-container> bin/magento setup:upgrade && bin/magento cache:flush
  ./docker-compose exec <php-container> vendor/bin/phpunit --filter CodRisk app/code/Secomm/CodRisk/Test/Unit
  ```
- **QC bắt buộc (L3 — payment path, AC mapping):** AC-003 checkout COD ẩn/hiện + prepaid regression; AC-004 threshold; AC-006/AC-007 order view actions; AC-009 ACL; AC-010 guest=registered; AC-011 config validation.
- Spec compliance: các AC chưa demo → ⏳ QC. Fixed confirmed: ⏳ chờ runtime verify + QC.

## Pre-review notes (AI self-check — AGENTS §8.3)

- Scope: chỉ 2 module mới, 0 modify module có sẵn; config.php để CLI enable xử lý.
- Fail-open plugin (catch Throwable, log critical, không rethrow) — có chủ đích checkout safety, flag cho TL.
- ~~`AuditLogFactory`/`CodRisk*Factory` hand-written~~ → **đã xóa theo code review 22/09** (ObjectManager không đúng chuẩn): factory giờ do DI sinh vào `generated/code` khi `setup:di:compile`; module 0 ObjectManager.
- Đề xuất缺口 đã ghi Known limitations (live preview normalize + duplicate-check realtime, OSC message chưa wire, audit phone match free-text).